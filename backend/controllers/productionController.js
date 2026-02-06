const db = require("../config/db.config");

// 1. Get Production History
exports.getProductionHistory = async (req, res) => {
  try {
    const query = `
            SELECT p.production_id, p.production_date, 
                   c.center_name,
                   prod_in.product_name as input_name, p.input_qty,
                   prod_out.product_name as output_name, p.output_qty,
                   u.username
            FROM production_logs p
            JOIN production_centers c ON p.center_id = c.production_center_id
            JOIN products prod_in ON p.input_product_id = prod_in.product_id
            JOIN products prod_out ON p.output_product_id = prod_out.product_id
            JOIN users u ON p.created_by = u.user_id
            ORDER BY p.production_date DESC LIMIT 50
        `;
    const [history] = await db.execute(query);
    res.json(history);
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
};

// 2. Record Production (The Core Logic)
exports.recordProduction = async (req, res) => {
  const {
    center_id,
    date,
    input_product_id,
    input_warehouse_id,
    input_qty,
    output_product_id,
    output_warehouse_id,
    output_qty,
    user_id,
  } = req.body;

  const connection = await db.getConnection();
  try {
    await connection.beginTransaction();

    // 1. Check Raw Material Stock
    const [stock] = await connection.query(
      "SELECT quantity_on_hand FROM inventory WHERE warehouse_id = ? AND product_id = ?",
      [input_warehouse_id, input_product_id],
    );

    if (
      stock.length === 0 ||
      parseFloat(stock[0].quantity_on_hand) < parseFloat(input_qty)
    ) {
      throw new Error("Insufficient Raw Material Stock!");
    }

    // 2. Deduct Raw Material
    await connection.query(
      "UPDATE inventory SET quantity_on_hand = quantity_on_hand - ? WHERE warehouse_id = ? AND product_id = ?",
      [input_qty, input_warehouse_id, input_product_id],
    );

    // 3. Add Finished Good
    await connection.query(
      `INSERT INTO inventory (warehouse_id, product_id, quantity_on_hand) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE quantity_on_hand = quantity_on_hand + ?`,
      [output_warehouse_id, output_product_id, output_qty, output_qty],
    );

    // 4. Log the Production Event
    await connection.query(
      `INSERT INTO production_logs 
            (production_date, center_id, input_product_id, input_qty, output_product_id, output_qty, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)`,
      [
        date,
        center_id,
        input_product_id,
        input_qty,
        output_product_id,
        output_qty,
        user_id,
      ],
    );

    // 5. Add Transaction Logs (One for Issue, One for Receipt)
    await connection.query(
      `INSERT INTO stock_transactions (transaction_date, warehouse_id, product_id, transaction_type, quantity, created_at) VALUES (NOW(), ?, ?, 'production_input', ?, NOW())`,
      [input_warehouse_id, input_product_id, -input_qty],
    );
    await connection.query(
      `INSERT INTO stock_transactions (transaction_date, warehouse_id, product_id, transaction_type, quantity, created_at) VALUES (NOW(), ?, ?, 'production_output', ?, NOW())`,
      [output_warehouse_id, output_product_id, output_qty],
    );

    await connection.commit();
    res.json({ message: "Production Recorded Successfully" });
  } catch (error) {
    await connection.rollback();
    console.error(error);
    res.status(500).json({ message: error.message });
  } finally {
    connection.release();
  }
};

// 3. Get Centers
exports.getCenters = async (req, res) => {
  try {
    const [centers] = await db.execute("SELECT * FROM production_centers");
    res.json(centers);
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
};

// 4. Get Production Orders
exports.getOrders = async (req, res) => {
  try {
    const query = `
            SELECT po.order_id, po.order_number, po.order_date, po.status, 
                   po.planned_quantity, po.actual_quantity,
                   c.center_name, p.product_name, u.username
            FROM production_orders po
            JOIN production_centers c ON po.production_center_id = c.production_center_id
            JOIN products p ON po.product_id = p.product_id
            JOIN users u ON po.created_by = u.user_id
            ORDER BY po.order_id DESC
        `;
    const [orders] = await db.execute(query);
    res.json(orders);
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
};

// 5. Create Production Order
exports.createOrder = async (req, res) => {
  const { center_id, product_id, planned_quantity, date, user_id } = req.body;
  try {
    // Generate Order Number (e.g., ORD-20260205-001)
    const dateStr = new Date().toISOString().slice(0, 10).replace(/-/g, "");
    const order_number = `PO-${dateStr}-${Date.now().toString().slice(-4)}`;

    await db.execute(
      `INSERT INTO production_orders 
            (order_number, order_date, production_center_id, product_id, planned_quantity, status, created_by) 
            VALUES (?, ?, ?, ?, ?, 'pending', ?)`,
      [order_number, date, center_id, product_id, planned_quantity, user_id],
    );

    res.json({ message: "Production Order Created Successfully" });
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
};

// 6. Update Order Status
exports.updateOrderStatus = async (req, res) => {
  const { id } = req.params;
  const { status } = req.body; // 'in_progress', 'completed', 'cancelled'
  try {
    await db.execute(
      "UPDATE production_orders SET status = ? WHERE order_id = ?",
      [status, id],
    );
    res.json({ message: "Status Updated" });
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
};
