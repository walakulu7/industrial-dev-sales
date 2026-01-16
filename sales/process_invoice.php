<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();
require_permission('sales', 'create');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_response('Invalid request method', 405);
}

// Verify CSRF token
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    error_response('Invalid CSRF token', 403);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Start transaction
    $db->beginTransaction();

    // Validate required fields
    $required_fields = [
        'branch_id',
        'customer_id',
        'invoice_date',
        'payment_method',
        'subtotal',
        'total_amount'
    ];
    $errors = validate_required($_POST, $required_fields);

    if (!empty($errors)) {
        throw new Exception(implode(', ', $errors));
    }

    // Validate items
    if (!isset($_POST['items']) || empty($_POST['items'])) {
        throw new Exception('No items in the invoice');
    }

    // Extract data
    $branch_id = (int)$_POST['branch_id'];
    $customer_id = (int)$_POST['customer_id'];
    $invoice_date = $_POST['invoice_date'];
    $payment_method = $_POST['payment_method'];
    $credit_days = (int)($_POST['credit_days'] ?? 0);
    $subtotal = (float)$_POST['subtotal'];
    $discount_amount = (float)$_POST['discount_amount'];
    $tax_amount = (float)$_POST['tax_amount'];
    $total_amount = (float)$_POST['total_amount'];
    $notes = sanitize_input($_POST['notes'] ?? '');
    $items = $_POST['items'];

    // Calculate due date for credit sales
    $due_date = null;
    if ($payment_method === 'credit' && $credit_days > 0) {
        $due_date = date('Y-m-d', strtotime($invoice_date . ' + ' . $credit_days . ' days'));
    }

    // Generate invoice number
    $stmt = $db->prepare("CALL sp_generate_invoice_number(:branch_id, @invoice_number)");
    $stmt->execute([':branch_id' => $branch_id]);
    $result = $db->query("SELECT @invoice_number as invoice_number")->fetch();
    $invoice_number = $result['invoice_number'];

    // Insert invoice header
    $invoice_query = "INSERT INTO sales_invoices 
                     (invoice_number, invoice_date, invoice_time, branch_id, customer_id, 
                      payment_method, credit_days, due_date, subtotal, discount_amount, 
                      tax_amount, total_amount, status, notes, created_by) 
                     VALUES 
                     (:invoice_number, :invoice_date, :invoice_time, :branch_id, :customer_id, 
                      :payment_method, :credit_days, :due_date, :subtotal, :discount_amount, 
                      :tax_amount, :total_amount, :status, :notes, :created_by)";

    $invoice_stmt = $db->prepare($invoice_query);
    $invoice_stmt->execute([
        ':invoice_number' => $invoice_number,
        ':invoice_date' => $invoice_date,
        ':invoice_time' => date('H:i:s'),
        ':branch_id' => $branch_id,
        ':customer_id' => $customer_id,
        ':payment_method' => $payment_method,
        ':credit_days' => $credit_days,
        ':due_date' => $due_date,
        ':subtotal' => $subtotal,
        ':discount_amount' => $discount_amount,
        ':tax_amount' => $tax_amount,
        ':total_amount' => $total_amount,
        ':status' => $payment_method === 'credit' ? 'pending' : 'paid',
        ':notes' => $notes,
        ':created_by' => $_SESSION['user_id']
    ]);

    $invoice_id = $db->lastInsertId();

    // Insert invoice details and update inventory
    $detail_query = "INSERT INTO invoice_details 
                    (invoice_id, product_id, warehouse_id, quantity, unit_price, 
                     discount_percent, discount_amount, line_total) 
                    VALUES 
                    (:invoice_id, :product_id, :warehouse_id, :quantity, :unit_price, 
                     :discount_percent, :discount_amount, :line_total)";

    $detail_stmt = $db->prepare($detail_query);

    foreach ($items as $item) {
        // Validate item data
        if (
            empty($item['product_id']) || empty($item['warehouse_id']) ||
            empty($item['quantity']) || empty($item['unit_price'])
        ) {
            throw new Exception('Invalid item data');
        }

        $product_id = (int)$item['product_id'];
        $warehouse_id = (int)$item['warehouse_id'];
        $quantity = (float)$item['quantity'];
        $unit_price = (float)$item['unit_price'];
        $discount_percent = (float)($item['discount_percent'] ?? 0);
        $line_subtotal = $quantity * $unit_price;
        $line_discount = $line_subtotal * ($discount_percent / 100);
        $line_total = (float)$item['line_total'];

        // Check stock availability
        $stock_check = $db->prepare("SELECT quantity_available FROM inventory 
                                     WHERE warehouse_id = :warehouse_id 
                                     AND product_id = :product_id");
        $stock_check->execute([
            ':warehouse_id' => $warehouse_id,
            ':product_id' => $product_id
        ]);

        $stock = $stock_check->fetch();
        if (!$stock || $stock['quantity_available'] < $quantity) {
            throw new Exception("Insufficient stock for product ID: $product_id");
        }

        // Insert detail
        $detail_stmt->execute([
            ':invoice_id' => $invoice_id,
            ':product_id' => $product_id,
            ':warehouse_id' => $warehouse_id,
            ':quantity' => $quantity,
            ':unit_price' => $unit_price,
            ':discount_percent' => $discount_percent,
            ':discount_amount' => $line_discount,
            ':line_total' => $line_total
        ]);

        // Update inventory using stored procedure
        $update_stock = $db->prepare("CALL sp_update_inventory_sale(:warehouse_id, :product_id, :quantity, :invoice_id, :user_id)");
        $update_stock->execute([
            ':warehouse_id' => $warehouse_id,
            ':product_id' => $product_id,
            ':quantity' => $quantity,
            ':invoice_id' => $invoice_id,
            ':user_id' => $_SESSION['user_id']
        ]);
    }

    // Create credit sales record if payment method is credit
    if ($payment_method === 'credit') {
        $credit_query = "INSERT INTO credit_sales 
                        (invoice_id, customer_id, invoice_date, due_date, total_amount, status) 
                        VALUES 
                        (:invoice_id, :customer_id, :invoice_date, :due_date, :total_amount, 'pending')";

        $credit_stmt = $db->prepare($credit_query);
        $credit_stmt->execute([
            ':invoice_id' => $invoice_id,
            ':customer_id' => $customer_id,
            ':invoice_date' => $invoice_date,
            ':due_date' => $due_date,
            ':total_amount' => $total_amount
        ]);
    }

    // Create journal entry
    $entry_number = 'JE-' . date('Ymd') . '-' . str_pad($invoice_id, 6, '0', STR_PAD_LEFT);

    $journal_query = "INSERT INTO journal_entries 
                     (entry_number, entry_date, entry_type, reference_type, reference_id, 
                      description, total_debit, total_credit, status, branch_id, created_by) 
                     VALUES 
                     (:entry_number, :entry_date, 'sales', 'sales_invoice', :reference_id, 
                      :description, :total_debit, :total_credit, 'posted', :branch_id, :created_by)";

    $journal_stmt = $db->prepare($journal_query);
    $journal_stmt->execute([
        ':entry_number' => $entry_number,
        ':entry_date' => $invoice_date,
        ':reference_id' => $invoice_id,
        ':description' => "Sales Invoice $invoice_number",
        ':total_debit' => $total_amount,
        ':total_credit' => $total_amount,
        ':branch_id' => $branch_id,
        ':created_by' => $_SESSION['user_id']
    ]);

    $entry_id = $db->lastInsertId();

    // Journal entry details
    // Debit: Cash/Accounts Receivable
    $debit_account = $payment_method === 'credit' ? 4 : 3; // Accounts Receivable : Cash

    $detail_insert = $db->prepare("INSERT INTO journal_entry_details 
                                   (entry_id, account_id, debit_amount, credit_amount, description) 
                                   VALUES 
                                   (:entry_id, :account_id, :debit_amount, :credit_amount, :description)");

    // Debit entry
    $detail_insert->execute([
        ':entry_id' => $entry_id,
        ':account_id' => $debit_account,
        ':debit_amount' => $total_amount,
        ':credit_amount' => 0,
        ':description' => $payment_method === 'credit' ? 'Accounts Receivable' : 'Cash'
    ]);

    // Credit: Sales Revenue
    $detail_insert->execute([
        ':entry_id' => $entry_id,
        ':account_id' => 21, // Sales Revenue
        ':debit_amount' => 0,
        ':credit_amount' => $total_amount,
        ':description' => 'Sales Revenue'
    ]);

    // Log audit
    log_audit($_SESSION['user_id'], 'create_invoice', 'sales_invoices', $invoice_id, null, [
        'invoice_number' => $invoice_number,
        'customer_id' => $customer_id,
        'total_amount' => $total_amount
    ]);

    // Commit transaction
    $db->commit();

    success_response('Invoice created successfully', [
        'invoice_id' => $invoice_id,
        'invoice_number' => $invoice_number
    ]);
} catch (Exception $e) {
    // Rollback transaction on error
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    error_log("Invoice Processing Error: " . $e->getMessage());
    error_response($e->getMessage(), 500);
}
