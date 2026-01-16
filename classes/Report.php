<?php

/**
 * Report Generation Class
 * Handles various business reports
 */

class Report
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get sales summary
     * @param string $date_from Start date
     * @param string $date_to End date
     * @param int $branch_id Branch ID (optional)
     * @return array Sales summary
     */
    public function getSalesSummary($date_from, $date_to, $branch_id = null)
    {
        $where = ["si.invoice_date BETWEEN :date_from AND :date_to", "si.status != 'cancelled'"];
        $params = [':date_from' => $date_from, ':date_to' => $date_to];

        if ($branch_id) {
            $where[] = "si.branch_id = :branch_id";
            $params[':branch_id'] = $branch_id;
        }

        $query = "SELECT 
                  COUNT(si.invoice_id) as total_invoices,
                  SUM(si.subtotal) as gross_sales,
                  SUM(si.discount_amount) as total_discounts,
                  SUM(si.total_amount) as net_sales,
                  SUM(CASE WHEN si.payment_method = 'cash' THEN si.total_amount ELSE 0 END) as cash_sales,
                  SUM(CASE WHEN si.payment_method = 'credit' THEN si.total_amount ELSE 0 END) as credit_sales
                  FROM sales_invoices si
                  WHERE " . implode(" AND ", $where);

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    /**
     * Get daily sales report
     * @param string $date_from Start date
     * @param string $date_to End date
     * @param int $branch_id Branch ID (optional)
     * @return array Daily sales data
     */
    public function getDailySales($date_from, $date_to, $branch_id = null)
    {
        $where = ["si.invoice_date BETWEEN :date_from AND :date_to", "si.status != 'cancelled'"];
        $params = [':date_from' => $date_from, ':date_to' => $date_to];

        if ($branch_id) {
            $where[] = "si.branch_id = :branch_id";
            $params[':branch_id'] = $branch_id;
        }

        $query = "SELECT si.invoice_date, b.branch_name,
                  COUNT(si.invoice_id) as total_invoices,
                  SUM(si.subtotal) as gross_sales,
                  SUM(si.discount_amount) as total_discounts,
                  SUM(si.total_amount) as net_sales,
                  SUM(CASE WHEN si.payment_method = 'cash' THEN si.total_amount ELSE 0 END) as cash_sales,
                  SUM(CASE WHEN si.payment_method = 'credit' THEN si.total_amount ELSE 0 END) as credit_sales
                  FROM sales_invoices si
                  INNER JOIN branches b ON si.branch_id = b.branch_id
                  WHERE " . implode(" AND ", $where) . "
                  GROUP BY si.invoice_date, b.branch_id
                  ORDER BY si.invoice_date DESC, b.branch_name";

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get sales by product
     * @param string $date_from Start date
     * @param string $date_to End date
     * @return array Product sales data
     */
    public function getSalesByProduct($date_from, $date_to)
    {
        $query = "SELECT p.product_code, p.product_name, pc.category_name,
                  SUM(id.quantity) as total_quantity,
                  SUM(id.line_total) as total_sales,
                  COUNT(DISTINCT si.invoice_id) as invoice_count,
                  AVG(id.unit_price) as avg_price
                  FROM invoice_details id
                  INNER JOIN sales_invoices si ON id.invoice_id = si.invoice_id
                  INNER JOIN products p ON id.product_id = p.product_id
                  INNER JOIN product_categories pc ON p.category_id = pc.category_id
                  WHERE si.invoice_date BETWEEN :date_from AND :date_to
                  AND si.status != 'cancelled'
                  GROUP BY id.product_id
                  ORDER BY total_sales DESC";

        $stmt = $this->db->prepare($query);
        $stmt->execute([':date_from' => $date_from, ':date_to' => $date_to]);
        return $stmt->fetchAll();
    }

    /**
     * Get sales by customer
     * @param string $date_from Start date
     * @param string $date_to End date
     * @return array Customer sales data
     */
    public function getSalesByCustomer($date_from, $date_to)
    {
        $query = "SELECT c.customer_code, c.customer_name, c.customer_type,
                  COUNT(si.invoice_id) as total_invoices,
                  SUM(si.total_amount) as total_sales,
                  AVG(si.total_amount) as avg_invoice_value
                  FROM sales_invoices si
                  INNER JOIN customers c ON si.customer_id = c.customer_id
                  WHERE si.invoice_date BETWEEN :date_from AND :date_to
                  AND si.status != 'cancelled'
                  GROUP BY si.customer_id
                  ORDER BY total_sales DESC";

        $stmt = $this->db->prepare($query);
        $stmt->execute([':date_from' => $date_from, ':date_to' => $date_to]);
        return $stmt->fetchAll();
    }

    /**
     * Get credit aging report
     * @return array Credit aging data
     */
    public function getCreditAgingReport()
    {
        $query = "SELECT 
                  c.customer_code,
                  c.customer_name,
                  SUM(cs.balance_amount) as total_outstanding,
                  SUM(CASE WHEN DATEDIFF(CURDATE(), cs.due_date) <= 0 THEN cs.balance_amount ELSE 0 END) as current,
                  SUM(CASE WHEN DATEDIFF(CURDATE(), cs.due_date) BETWEEN 1 AND 30 THEN cs.balance_amount ELSE 0 END) as days_1_30,
                  SUM(CASE WHEN DATEDIFF(CURDATE(), cs.due_date) BETWEEN 31 AND 60 THEN cs.balance_amount ELSE 0 END) as days_31_60,
                  SUM(CASE WHEN DATEDIFF(CURDATE(), cs.due_date) BETWEEN 61 AND 90 THEN cs.balance_amount ELSE 0 END) as days_61_90,
                  SUM(CASE WHEN DATEDIFF(CURDATE(), cs.due_date) > 90 THEN cs.balance_amount ELSE 0 END) as days_over_90
                  FROM credit_sales cs
                  INNER JOIN customers c ON cs.customer_id = c.customer_id
                  WHERE cs.status IN ('pending', 'partial', 'overdue')
                  GROUP BY cs.customer_id
                  ORDER BY total_outstanding DESC";

        return $this->db->query($query)->fetchAll();
    }

    /**
     * Get production efficiency report
     * @param string $date_from Start date
     * @param string $date_to End date
     * @return array Production efficiency data
     */
    public function getProductionEfficiency($date_from, $date_to)
    {
        $query = "SELECT 
                  po.order_number,
                  pc.center_name,
                  p.product_name,
                  po.planned_quantity,
                  po.actual_quantity,
                  (po.actual_quantity / NULLIF(po.planned_quantity, 0) * 100) as efficiency_percent,
                  DATEDIFF(po.end_date, po.start_date) as production_days
                  FROM production_orders po
                  INNER JOIN production_centers pc ON po.production_center_id = pc.production_center_id
                  INNER JOIN products p ON po.product_id = p.product_id
                  WHERE po.order_date BETWEEN :date_from AND :date_to
                  ORDER BY efficiency_percent DESC";

        $stmt = $this->db->prepare($query);
        $stmt->execute([':date_from' => $date_from, ':date_to' => $date_to]);
        return $stmt->fetchAll();
    }

    /**
     * Get inventory valuation
     * @param int $warehouse_id Warehouse ID (optional)
     * @return array Inventory valuation data
     */
    public function getInventoryValuation($warehouse_id = null)
    {
        $where = "1=1";
        $params = [];

        if ($warehouse_id) {
            $where = "i.warehouse_id = :warehouse_id";
            $params[':warehouse_id'] = $warehouse_id;
        }

        $query = "SELECT w.warehouse_name, p.product_code, p.product_name, pc.category_name,
                  i.quantity_on_hand, p.standard_cost, p.unit_of_measure,
                  (i.quantity_on_hand * p.standard_cost) as stock_value
                  FROM inventory i
                  INNER JOIN warehouses w ON i.warehouse_id = w.warehouse_id
                  INNER JOIN products p ON i.product_id = p.product_id
                  INNER JOIN product_categories pc ON p.category_id = pc.category_id
                  WHERE $where
                  ORDER BY stock_value DESC";

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Get profit and loss report
     * @param string $date_from Start date
     * @param string $date_to End date
     * @return array P&L data
     */
    public function getProfitLoss($date_from, $date_to)
    {
        // Revenue
        $revenue_query = "SELECT 
                         SUM(total_amount) as total_revenue,
                         SUM(CASE WHEN payment_method = 'cash' THEN total_amount ELSE 0 END) as cash_revenue,
                         SUM(CASE WHEN payment_method = 'credit' THEN total_amount ELSE 0 END) as credit_revenue
                         FROM sales_invoices
                         WHERE invoice_date BETWEEN :date_from AND :date_to
                         AND status != 'cancelled'";

        $stmt = $this->db->prepare($revenue_query);
        $stmt->execute([':date_from' => $date_from, ':date_to' => $date_to]);
        $revenue = $stmt->fetch();

        return [
            'revenue' => $revenue,
            'date_from' => $date_from,
            'date_to' => $date_to
        ];
    }

    /**
     * Export data to array for Excel
     * @param array $data Data to export
     * @return array Formatted data
     */
    public function exportToArray($data)
    {
        if (empty($data)) {
            return [];
        }

        $result = [];

        // Headers
        $result[] = array_keys($data[0]);

        // Data rows
        foreach ($data as $row) {
            $result[] = array_values($row);
        }

        return $result;
    }
}
