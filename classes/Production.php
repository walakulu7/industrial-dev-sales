<?php

/**
 * Production Management Class
 * Handles production orders and operations
 */

class Production
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Create production order
     * @param array $data Order data
     * @return array Result
     */
    public function createOrder($data)
    {
        try {
            $this->db->beginTransaction();

            // Generate order number
            $result = $this->db->query("SELECT COUNT(*) as count FROM production_orders")->fetch();
            $order_number = 'PO-' . date('Ymd') . '-' . str_pad($result['count'] + 1, 5, '0', STR_PAD_LEFT);

            // Insert order
            $query = "INSERT INTO production_orders 
                     (order_number, order_date, production_center_id, production_line_id, 
                      product_id, planned_quantity, start_date, end_date, status, notes, created_by) 
                     VALUES 
                     (:order_number, :order_date, :production_center_id, :production_line_id, 
                      :product_id, :planned_quantity, :start_date, :end_date, 'pending', :notes, :created_by)";

            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':order_number' => $order_number,
                ':order_date' => $data['order_date'],
                ':production_center_id' => $data['production_center_id'],
                ':production_line_id' => $data['production_line_id'],
                ':product_id' => $data['product_id'],
                ':planned_quantity' => $data['planned_quantity'],
                ':start_date' => $data['start_date'],
                ':end_date' => $data['end_date'],
                ':notes' => $data['notes'],
                ':created_by' => $_SESSION['user_id']
            ]);

            $order_id = $this->db->lastInsertId();

            // Insert inputs
            if (!empty($data['inputs'])) {
                foreach ($data['inputs'] as $input) {
                    $this->addProductionInput($order_id, $input);
                }
            }

            $this->db->commit();

            return ['success' => true, 'order_id' => $order_id, 'order_number' => $order_number];
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Add production input
     * @param int $order_id Order ID
     * @param array $input Input data
     */
    private function addProductionInput($order_id, $input)
    {
        $query = "INSERT INTO production_inputs 
                  (order_id, product_id, warehouse_id, quantity_required) 
                  VALUES 
                  (:order_id, :product_id, :warehouse_id, :quantity_required)";

        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':order_id' => $order_id,
            ':product_id' => $input['product_id'],
            ':warehouse_id' => $input['warehouse_id'],
            ':quantity_required' => $input['quantity']
        ]);
    }

    /**
     * Record production output
     * @param int $order_id Order ID
     * @param float $quantity_produced Quantity produced
     * @param float $quantity_good Good quantity
     * @param int $warehouse_id Warehouse ID
     * @param string $notes Notes (optional)
     * @return array Result
     */
    public function recordOutput($order_id, $quantity_produced, $quantity_good, $warehouse_id, $notes = '')
    {
        try {
            $stmt = $this->db->prepare("CALL sp_record_production_output(:order_id, :quantity_produced, :quantity_good, :warehouse_id, :user_id)");
            $stmt->execute([
                ':order_id' => $order_id,
                ':quantity_produced' => $quantity_produced,
                ':quantity_good' => $quantity_good,
                ':warehouse_id' => $warehouse_id,
                ':user_id' => $_SESSION['user_id']
            ]);

            // Check if order is complete
            $check = $this->db->prepare("SELECT planned_quantity, actual_quantity 
                                         FROM production_orders 
                                         WHERE order_id = :order_id");
            $check->execute([':order_id' => $order_id]);
            $order = $check->fetch();

            if ($order['actual_quantity'] >= $order['planned_quantity']) {
                $this->db->prepare("UPDATE production_orders SET status = 'completed' WHERE order_id = :order_id")
                    ->execute([':order_id' => $order_id]);
            } else {
                $this->db->prepare("UPDATE production_orders SET status = 'in_progress' WHERE order_id = :order_id")
                    ->execute([':order_id' => $order_id]);
            }

            return ['success' => true, 'message' => 'Production output recorded successfully'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get production orders
     * @param array $filters Filter parameters
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array Orders
     */
    public function getOrders($filters = [], $limit = 25, $offset = 0)
    {
        $where = ["1=1"];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = "po.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['center_id'])) {
            $where[] = "po.production_center_id = :center_id";
            $params[':center_id'] = $filters['center_id'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "po.order_date >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "po.order_date <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(po.order_number LIKE :search OR p.product_name LIKE :search)";
            $params[':search'] = "%{$filters['search']}%";
        }

        $where_clause = implode(" AND ", $where);

        $query = "SELECT po.*, pc.center_name, p.product_code, p.product_name, p.unit_of_measure,
                  pl.line_name, u.full_name as created_by_name,
                  (po.actual_quantity / NULLIF(po.planned_quantity, 0) * 100) as progress_percent
                  FROM production_orders po
                  INNER JOIN production_centers pc ON po.production_center_id = pc.production_center_id
                  INNER JOIN products p ON po.product_id = p.product_id
                  LEFT JOIN production_lines pl ON po.production_line_id = pl.line_id
                  INNER JOIN users u ON po.created_by = u.user_id
                  WHERE $where_clause
                  ORDER BY po.order_date DESC, po.order_id DESC
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
     * Get production order by ID
     * @param int $order_id Order ID
     * @return array|false Order data or false
     */
    public function getOrderById($order_id)
    {
        $query = "SELECT po.*, pc.center_name, pc.center_code, p.product_code, p.product_name, 
                  p.unit_of_measure, pl.line_name, u.full_name as created_by_name
                  FROM production_orders po
                  INNER JOIN production_centers pc ON po.production_center_id = pc.production_center_id
                  INNER JOIN products p ON po.product_id = p.product_id
                  LEFT JOIN production_lines pl ON po.production_line_id = pl.line_id
                  INNER JOIN users u ON po.created_by = u.user_id
                  WHERE po.order_id = :order_id";

        $stmt = $this->db->prepare($query);
        $stmt->execute([':order_id' => $order_id]);
        $order = $stmt->fetch();

        if ($order) {
            $order['inputs'] = $this->getOrderInputs($order_id);
            $order['outputs'] = $this->getOrderOutputs($order_id);
        }

        return $order;
    }

    /**
     * Get order inputs
     * @param int $order_id Order ID
     * @return array Inputs
     */
    private function getOrderInputs($order_id)
    {
        $query = "SELECT pi.*, p.product_code, p.product_name, p.unit_of_measure, w.warehouse_name
                  FROM production_inputs pi
                  INNER JOIN products p ON pi.product_id = p.product_id
                  INNER JOIN warehouses w ON pi.warehouse_id = w.warehouse_id
                  WHERE pi.order_id = :order_id";

        $stmt = $this->db->prepare($query);
        $stmt->execute([':order_id' => $order_id]);
        return $stmt->fetchAll();
    }

    /**
     * Get order outputs
     * @param int $order_id Order ID
     * @return array Outputs
     */
    private function getOrderOutputs($order_id)
    {
        $query = "SELECT po.*, w.warehouse_name, u.full_name as created_by_name
                  FROM production_outputs po
                  INNER JOIN warehouses w ON po.warehouse_id = w.warehouse_id
                  INNER JOIN users u ON po.created_by = u.user_id
                  WHERE po.order_id = :order_id
                  ORDER BY po.output_date DESC";

        $stmt = $this->db->prepare($query);
        $stmt->execute([':order_id' => $order_id]);
        return $stmt->fetchAll();
    }

    /**
     * Update order status
     * @param int $order_id Order ID
     * @param string $status New status
     * @return array Result
     */
    public function updateOrderStatus($order_id, $status)
    {
        try {
            $query = "UPDATE production_orders SET status = :status WHERE order_id = :order_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':status' => $status, ':order_id' => $order_id]);

            return ['success' => true, 'message' => 'Order status updated successfully'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get production lines by center
     * @param int $center_id Center ID
     * @return array Production lines
     */
    public function getLinesByCenter($center_id)
    {
        $query = "SELECT * FROM production_lines 
                  WHERE production_center_id = :center_id AND status = 'active'
                  ORDER BY line_name";

        $stmt = $this->db->prepare($query);
        $stmt->execute([':center_id' => $center_id]);
        return $stmt->fetchAll();
    }

    /**
     * Get production efficiency report
     * @param string $date_from Start date
     * @param string $date_to End date
     * @return array Efficiency data
     */
    public function getEfficiencyReport($date_from, $date_to)
    {
        $query = "SELECT 
                  po.order_number,
                  pc.center_name,
                  p.product_name,
                  po.order_date,
                  po.planned_quantity,
                  po.actual_quantity,
                  (po.actual_quantity / NULLIF(po.planned_quantity, 0) * 100) as efficiency_percent,
                  DATEDIFF(po.end_date, po.start_date) as production_days,
                  po.status
                  FROM production_orders po
                  INNER JOIN production_centers pc ON po.production_center_id = pc.production_center_id
                  INNER JOIN products p ON po.product_id = p.product_id
                  WHERE po.order_date BETWEEN :date_from AND :date_to
                  ORDER BY efficiency_percent DESC";

        $stmt = $this->db->prepare($query);
        $stmt->execute([':date_from' => $date_from, ':date_to' => $date_to]);
        return $stmt->fetchAll();
    }
}
