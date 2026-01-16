<?php

/**
 * Invoice Management Class
 * Handles invoice creation, retrieval, and management
 */

class Invoice
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Create new invoice
     * @param array $data Invoice data
     * @return array Result
     */
    public function create($data)
    {
        try {
            $this->db->beginTransaction();

            // Generate invoice number
            $stmt = $this->db->prepare("CALL sp_generate_invoice_number(:branch_id, @invoice_number)");
            $stmt->execute([':branch_id' => $data['branch_id']]);
            $result = $this->db->query("SELECT @invoice_number as invoice_number")->fetch();
            $invoice_number = $result['invoice_number'];

            // Insert invoice
            $query = "INSERT INTO sales_invoices 
                     (invoice_number, invoice_date, invoice_time, branch_id, customer_id, 
                      payment_method, credit_days, due_date, subtotal, discount_amount, 
                      tax_amount, total_amount, status, notes, created_by) 
                     VALUES 
                     (:invoice_number, :invoice_date, :invoice_time, :branch_id, :customer_id, 
                      :payment_method, :credit_days, :due_date, :subtotal, :discount_amount, 
                      :tax_amount, :total_amount, :status, :notes, :created_by)";

            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':invoice_number' => $invoice_number,
                ':invoice_date' => $data['invoice_date'],
                ':invoice_time' => date('H:i:s'),
                ':branch_id' => $data['branch_id'],
                ':customer_id' => $data['customer_id'],
                ':payment_method' => $data['payment_method'],
                ':credit_days' => $data['credit_days'],
                ':due_date' => $data['due_date'],
                ':subtotal' => $data['subtotal'],
                ':discount_amount' => $data['discount_amount'],
                ':tax_amount' => $data['tax_amount'],
                ':total_amount' => $data['total_amount'],
                ':status' => $data['payment_method'] === 'credit' ? 'pending' : 'paid',
                ':notes' => $data['notes'],
                ':created_by' => $_SESSION['user_id']
            ]);

            $invoice_id = $this->db->lastInsertId();

            // Insert items and update inventory
            foreach ($data['items'] as $item) {
                $this->addInvoiceItem($invoice_id, $item);
            }

            // Create credit record if needed
            if ($data['payment_method'] === 'credit') {
                $this->createCreditRecord($invoice_id, $data);
            }

            // Create journal entry
            $this->createJournalEntry($invoice_id, $data);

            $this->db->commit();

            return ['success' => true, 'invoice_id' => $invoice_id, 'invoice_number' => $invoice_number];
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Add invoice item
     * @param int $invoice_id Invoice ID
     * @param array $item Item data
     */
    private function addInvoiceItem($invoice_id, $item)
    {
        // Insert detail
        $query = "INSERT INTO invoice_details 
                  (invoice_id, product_id, warehouse_id, quantity, unit_price, 
                   discount_percent, discount_amount, line_total) 
                  VALUES 
                  (:invoice_id, :product_id, :warehouse_id, :quantity, :unit_price, 
                   :discount_percent, :discount_amount, :line_total)";

        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':invoice_id' => $invoice_id,
            ':product_id' => $item['product_id'],
            ':warehouse_id' => $item['warehouse_id'],
            ':quantity' => $item['quantity'],
            ':unit_price' => $item['unit_price'],
            ':discount_percent' => $item['discount_percent'],
            ':discount_amount' => $item['discount_amount'],
            ':line_total' => $item['line_total']
        ]);

        // Update inventory
        $stmt = $this->db->prepare("CALL sp_update_inventory_sale(:warehouse_id, :product_id, :quantity, :invoice_id, :user_id)");
        $stmt->execute([
            ':warehouse_id' => $item['warehouse_id'],
            ':product_id' => $item['product_id'],
            ':quantity' => $item['quantity'],
            ':invoice_id' => $invoice_id,
            ':user_id' => $_SESSION['user_id']
        ]);
    }

    /**
     * Create credit record
     * @param int $invoice_id Invoice ID
     * @param array $data Invoice data
     */
    private function createCreditRecord($invoice_id, $data)
    {
        $query = "INSERT INTO credit_sales 
                  (invoice_id, customer_id, invoice_date, due_date, total_amount, status) 
                  VALUES 
                  (:invoice_id, :customer_id, :invoice_date, :due_date, :total_amount, 'pending')";

        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':invoice_id' => $invoice_id,
            ':customer_id' => $data['customer_id'],
            ':invoice_date' => $data['invoice_date'],
            ':due_date' => $data['due_date'],
            ':total_amount' => $data['total_amount']
        ]);
    }

    /**
     * Create journal entry
     * @param int $invoice_id Invoice ID
     * @param array $data Invoice data
     */
    private function createJournalEntry($invoice_id, $data)
    {
        $entry_number = 'JE-' . date('Ymd') . '-' . str_pad($invoice_id, 6, '0', STR_PAD_LEFT);

        $query = "INSERT INTO journal_entries 
                 (entry_number, entry_date, entry_type, reference_type, reference_id, 
                  description, total_debit, total_credit, status, branch_id, created_by) 
                 VALUES 
                 (:entry_number, :entry_date, 'sales', 'sales_invoice', :reference_id, 
                  :description, :total_debit, :total_credit, 'posted', :branch_id, :created_by)";

        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':entry_number' => $entry_number,
            ':entry_date' => $data['invoice_date'],
            ':reference_id' => $invoice_id,
            ':description' => "Sales Invoice",
            ':total_debit' => $data['total_amount'],
            ':total_credit' => $data['total_amount'],
            ':branch_id' => $data['branch_id'],
            ':created_by' => $_SESSION['user_id']
        ]);

        $entry_id = $this->db->lastInsertId();

        // Journal details
        $detail_insert = $this->db->prepare("INSERT INTO journal_entry_details 
                                             (entry_id, account_id, debit_amount, credit_amount, description) 
                                             VALUES 
                                             (:entry_id, :account_id, :debit_amount, :credit_amount, :description)");

        // Debit
        $debit_account = $data['payment_method'] === 'credit' ? 4 : 3;
        $detail_insert->execute([
            ':entry_id' => $entry_id,
            ':account_id' => $debit_account,
            ':debit_amount' => $data['total_amount'],
            ':credit_amount' => 0,
            ':description' => $data['payment_method'] === 'credit' ? 'Accounts Receivable' : 'Cash'
        ]);

        // Credit
        $detail_insert->execute([
            ':entry_id' => $entry_id,
            ':account_id' => 21,
            ':debit_amount' => 0,
            ':credit_amount' => $data['total_amount'],
            ':description' => 'Sales Revenue'
        ]);
    }

    /**
     * Get invoice by ID
     * @param int $invoice_id Invoice ID
     * @return array|false Invoice data or false
     */
    public function getById($invoice_id)
    {
        $query = "SELECT si.*, c.customer_name, c.customer_code, c.phone as customer_phone,
                  c.address as customer_address, b.branch_name, b.address as branch_address,
                  b.contact_phone as branch_phone, u.full_name as created_by_name
                  FROM sales_invoices si
                  INNER JOIN customers c ON si.customer_id = c.customer_id
                  INNER JOIN branches b ON si.branch_id = b.branch_id
                  INNER JOIN users u ON si.created_by = u.user_id
                  WHERE si.invoice_id = :invoice_id";

        $stmt = $this->db->prepare($query);
        $stmt->execute([':invoice_id' => $invoice_id]);
        $invoice = $stmt->fetch();

        if ($invoice) {
            $invoice['items'] = $this->getInvoiceItems($invoice_id);
        }

        return $invoice;
    }

    /**
     * Get invoice items
     * @param int $invoice_id Invoice ID
     * @return array Items
     */
    private function getInvoiceItems($invoice_id)
    {
        $query = "SELECT id.*, p.product_code, p.product_name, p.unit_of_measure, w.warehouse_name
                  FROM invoice_details id
                  INNER JOIN products p ON id.product_id = p.product_id
                  INNER JOIN warehouses w ON id.warehouse_id = w.warehouse_id
                  WHERE id.invoice_id = :invoice_id";

        $stmt = $this->db->prepare($query);
        $stmt->execute([':invoice_id' => $invoice_id]);
        return $stmt->fetchAll();
    }

    /**
     * Get invoices list
     * @param array $filters Filter parameters
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array Invoices
     */
    public function getList($filters = [], $limit = 25, $offset = 0)
    {
        $where = ["1=1"];
        $params = [];

        if (!empty($filters['search'])) {
            $where[] = "(si.invoice_number LIKE :search OR c.customer_name LIKE :search)";
            $params[':search'] = "%{$filters['search']}%";
        }

        if (!empty($filters['date_from'])) {
            $where[] = "si.invoice_date >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "si.invoice_date <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        if (!empty($filters['branch_id'])) {
            $where[] = "si.branch_id = :branch_id";
            $params[':branch_id'] = $filters['branch_id'];
        }

        if (!empty($filters['status'])) {
            $where[] = "si.status = :status";
            $params[':status'] = $filters['status'];
        }

        $where_clause = implode(" AND ", $where);

        $query = "SELECT si.*, c.customer_name, c.customer_code, b.branch_name, u.full_name as created_by_name
                  FROM sales_invoices si
                  INNER JOIN customers c ON si.customer_id = c.customer_id
                  INNER JOIN branches b ON si.branch_id = b.branch_id
                  INNER JOIN users u ON si.created_by = u.user_id
                  WHERE $where_clause
                  ORDER BY si.invoice_date DESC, si.invoice_id DESC
                  LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Cancel invoice
     * @param int $invoice_id Invoice ID
     * @return array Result
     */
    public function cancel($invoice_id)
    {
        try {
            $query = "UPDATE sales_invoices SET status = 'cancelled' WHERE invoice_id = :invoice_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':invoice_id' => $invoice_id]);

            return ['success' => true, 'message' => 'Invoice cancelled successfully'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
