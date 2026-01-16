<?php

/**
 * Inventory Management Class
 * Handles stock operations and inventory management
 */

class Inventory
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get current stock levels
     * @param int $warehouse_id Warehouse ID (optional)
     * @param int $product_id Product ID (optional)
     * @return array Stock data
     */
    public function getCurrentStock($warehouse_id = null, $product_id = null)
    {
        $where = ["1=1"];
        $params = [];

        if ($warehouse_id) {
            $where[] = "i.warehouse_id = :warehouse_id";
            $params[':warehouse_id'] = $warehouse_id;
        }

        if ($product_id) {
            $where[] = "i.product_id = :product_id";
            $params[':product_id'] = $product_id;
        }

        $query = "SELECT i.*, w.warehouse_code, w.warehouse_name, b.branch_name,
                  p.product_code, p.product_name, pc.category_name,
                  p.unit_of_measure, p.reorder_level,
                  CASE 
                      WHEN i.quantity_available <= 0 THEN 'Out of Stock'
                      WHEN i.quantity_available <= p.reorder_level THEN 'Low Stock'
                      ELSE 'Adequate'
                  END as stock_status
                  FROM inventory i
                  INNER JOIN warehouses w ON i.warehouse_id = w.warehouse_id
                  INNER JOIN branches b ON w.branch_id = b.branch_id
                  INNER JOIN products p ON i.product_id = p.product_id
                  INNER JOIN product_categories pc ON p.category_id = pc.category_id
                  WHERE " . implode(" AND ", $where) . "
                  ORDER BY w.warehouse_name, p.product_name";

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Transfer stock between warehouses
     * @param int $from_warehouse From warehouse ID
     * @param int $to_warehouse To warehouse ID
     * @param int $product_id Product ID
     * @param float $quantity Quantity
     * @param string $notes Notes (optional)
     * @return array Result
     */
    public function transferStock($from_warehouse, $to_warehouse, $product_id, $quantity, $notes = '')
    {
        try {
            $this->db->beginTransaction();

            // Check availability
            $check = $this->db->prepare("SELECT quantity_available FROM inventory 
                                         WHERE warehouse_id = :warehouse_id AND product_id = :product_id");
            $check->execute([':warehouse_id' => $from_warehouse, ':product_id' => $product_id]);
            $stock = $check->fetch();

            if (!$stock || $stock['quantity_available'] < $quantity) {
                throw new Exception('Insufficient stock available');
            }

            // Transfer out
            $this->updateStock($from_warehouse, $product_id, -$quantity);

            // Transfer in
            $this->updateStock($to_warehouse, $product_id, $quantity);

            // Record transactions
            $this->recordTransaction(
                $from_warehouse,
                $product_id,
                'transfer_out',
                $quantity,
                $from_warehouse,
                $to_warehouse,
                $notes
            );
            $this->recordTransaction(
                $to_warehouse,
                $product_id,
                'transfer_in',
                $quantity,
                $from_warehouse,
                $to_warehouse,
                $notes
            );

            $this->db->commit();
            return ['success' => true, 'message' => 'Stock transferred successfully'];
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Update stock quantity
     * @param int $warehouse_id Warehouse ID
     * @param int $product_id Product ID
     * @param float $quantity Quantity (positive or negative)
     */
    private function updateStock($warehouse_id, $product_id, $quantity)
    {
        $query = "INSERT INTO inventory (warehouse_id, product_id, quantity_on_hand, last_stock_date)
                  VALUES (:warehouse_id, :product_id, :quantity, CURDATE())
                  ON DUPLICATE KEY UPDATE 
                  quantity_on_hand = quantity_on_hand + :quantity,
                  last_stock_date = CURDATE()";

        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':warehouse_id' => $warehouse_id,
            ':product_id' => $product_id,
            ':quantity' => $quantity
        ]);
    }

    /**
     * Record stock transaction
     * @param int $warehouse_id Warehouse ID
     * @param int $product_id Product ID
     * @param string $type Transaction type
     * @param float $quantity Quantity
     * @param int $from_warehouse From warehouse ID (optional)
     * @param int $to_warehouse To warehouse ID (optional)
     * @param string $notes Notes (optional)
     */
    private function recordTransaction(
        $warehouse_id,
        $product_id,
        $type,
        $quantity,
        $from_warehouse = null,
        $to_warehouse = null,
        $notes = ''
    ) {
        $query = "INSERT INTO stock_transactions 
                  (transaction_date, transaction_time, warehouse_id, product_id, transaction_type,
                   quantity, from_warehouse_id, to_warehouse_id, notes, created_by)
                  VALUES 
                  (CURDATE(), CURTIME(), :warehouse_id, :product_id, :transaction_type,
                   :quantity, :from_warehouse_id, :to_warehouse_id, :notes, :created_by)";

        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':warehouse_id' => $warehouse_id,
            ':product_id' => $product_id,
            ':transaction_type' => $type,
            ':quantity' => $quantity,
            ':from_warehouse_id' => $from_warehouse,
            ':to_warehouse_id' => $to_warehouse,
            ':notes' => $notes,
            ':created_by' => $_SESSION['user_id']
        ]);
    }

    /**
     * Get stock transactions
     * @param array $filters Filter parameters
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array Transactions
     */
    public function getTransactions($filters = [], $limit = 25, $offset = 0)
    {
        $where = ["1=1"];
        $params = [];

        if (!empty($filters['date_from'])) {
            $where[] = "st.transaction_date >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "st.transaction_date <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        if (!empty($filters['warehouse_id'])) {
            $where[] = "st.warehouse_id = :warehouse_id";
            $params[':warehouse_id'] = $filters['warehouse_id'];
        }

        if (!empty($filters['transaction_type'])) {
            $where[] = "st.transaction_type = :transaction_type";
            $params[':transaction_type'] = $filters['transaction_type'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(p.product_code LIKE :search OR p.product_name LIKE :search)";
            $params[':search'] = "%{$filters['search']}%";
        }

        $where_clause = implode(" AND ", $where);

        $query = "SELECT st.*, p.product_code, p.product_name, p.unit_of_measure,
                  w.warehouse_name, u.full_name as created_by_name,
                  fw.warehouse_name as from_warehouse_name,
                  tw.warehouse_name as to_warehouse_name
                  FROM stock_transactions st
                  INNER JOIN products p ON st.product_id = p.product_id
                  INNER JOIN warehouses w ON st.warehouse_id = w.warehouse_id
                  INNER JOIN users u ON st.created_by = u.user_id
                  LEFT JOIN warehouses fw ON st.from_warehouse_id = fw.warehouse_id
                  LEFT JOIN warehouses tw ON st.to_warehouse_id = tw.warehouse_id
                  WHERE $where_clause
                  ORDER BY st.transaction_date DESC, st.transaction_time DESC
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
     * Get low stock items
     * @return array Low stock items
     */
    public function getLowStockItems()
    {
        $query = "SELECT i.*, w.warehouse_name, p.product_code, p.product_name, 
                  p.unit_of_measure, p.reorder_level,
                  (p.reorder_level - i.quantity_available) as shortage_qty
                  FROM inventory i
                  INNER JOIN warehouses w ON i.warehouse_id = w.warehouse_id
                  INNER JOIN products p ON i.product_id = p.product_id
                  WHERE i.quantity_available <= p.reorder_level
                  ORDER BY (i.quantity_available - p.reorder_level) ASC";

        return $this->db->query($query)->fetchAll();
    }

    /**
     * Stock adjustment
     * @param int $warehouse_id Warehouse ID
     * @param int $product_id Product ID
     * @param float $quantity New quantity
     * @param string $notes Adjustment notes
     * @return array Result
     */
    public function adjustStock($warehouse_id, $product_id, $quantity, $notes = '')
    {
        try {
            $this->db->beginTransaction();

            // Get current quantity
            $check = $this->db->prepare("SELECT quantity_on_hand FROM inventory 
                                         WHERE warehouse_id = :warehouse_id AND product_id = :product_id");
            $check->execute([':warehouse_id' => $warehouse_id, ':product_id' => $product_id]);
            $current = $check->fetch();

            $current_qty = $current ? $current['quantity_on_hand'] : 0;
            $adjustment = $quantity - $current_qty;

            // Update stock
            $this->updateStock($warehouse_id, $product_id, $adjustment);

            // Record transaction
            $this->recordTransaction(
                $warehouse_id,
                $product_id,
                'adjustment',
                abs($adjustment),
                null,
                null,
                "Adjustment from $current_qty to $quantity. $notes"
            );

            $this->db->commit();
            return ['success' => true, 'message' => 'Stock adjusted successfully'];
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get stock valuation
     * @param int $warehouse_id Warehouse ID (optional)
     * @return array Stock valuation data
     */
    public function getStockValuation($warehouse_id = null)
    {
        $where = "1=1";
        $params = [];

        if ($warehouse_id) {
            $where = "i.warehouse_id = :warehouse_id";
            $params[':warehouse_id'] = $warehouse_id;
        }

        $query = "SELECT i.*, w.warehouse_name, p.product_code, p.product_name,
                  p.standard_cost, p.unit_of_measure,
                  (i.quantity_on_hand * p.standard_cost) as stock_value
                  FROM inventory i
                  INNER JOIN warehouses w ON i.warehouse_id = w.warehouse_id
                  INNER JOIN products p ON i.product_id = p.product_id
                  WHERE $where
                  ORDER BY stock_value DESC";

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }
}
