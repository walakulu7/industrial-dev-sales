const db = require("../../config/db.config");

// 1. Get Current Stock (All Items)
exports.getInventory = async (req, res) => {
  try {
    // Fetch data from the View 'v_current_stock'
    // Ordered by: Low Stock items first, then Alphabetical
    const query = `
            SELECT * FROM v_current_stock 
            ORDER BY stock_status = 'Low Stock' DESC, product_name ASC
        `;
    const [stock] = await db.execute(query);
    res.json(stock);
  } catch (error) {
    console.error("Error fetching inventory:", error);
    res.status(500).json({ error: error.message });
  }
};

// 2. Adjust Stock (Add/Remove)
exports.adjustStock = async (req, res) => {
  const { product_id, warehouse_id, quantity, type, notes, user_id } = req.body;

  // Validate inputs
  if (!product_id || !warehouse_id || !quantity) {
    return res.status(400).json({ message: "Missing required fields" });
  }

  const connection = await db.getConnection();
  try {
    await connection.beginTransaction();

    // Determine math operator based on transaction type
    // 'receipt' = Stock In (+), 'adjustment' = Stock Out (-)
    const operator = type === "receipt" ? "+" : "-";

    // Update Inventory Table
    // Logic: Insert a new row. If it exists, update the quantity.
    // FIXED: Removed 'last_stock_date' column to prevent SQL Error.
    const updateQuery = `
            INSERT INTO inventory (warehouse_id, product_id, quantity_on_hand)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            quantity_on_hand = quantity_on_hand ${operator} VALUES(quantity_on_hand)
        `;

    await connection.query(updateQuery, [warehouse_id, product_id, quantity]);

    // Record Transaction Log
    await connection.query(
      `INSERT INTO stock_transactions 
            (transaction_date, warehouse_id, product_id, transaction_type, quantity, created_at)
            VALUES (NOW(), ?, ?, ?, ?, NOW())`,
      [warehouse_id, product_id, type, quantity],
    );

    await connection.commit();
    res.json({ message: "Stock updated successfully" });
  } catch (error) {
    await connection.rollback();
    console.error("Stock adjustment failed:", error);
    res.status(500).json({ error: error.message });
  } finally {
    connection.release();
  }
};

// 3. Get Warehouses (For Dropdowns)
exports.getWarehouses = async (req, res) => {
  try {
    const [warehouses] = await db.execute(
      "SELECT warehouse_id, warehouse_name FROM warehouses",
    );
    res.json(warehouses);
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
};

// 4. Transfer Stock (Move from Warehouse A to B)
exports.transferStock = async (req, res) => {
  const { product_id, from_warehouse_id, to_warehouse_id, quantity, user_id } =
    req.body;

  if (from_warehouse_id == to_warehouse_id) {
    return res
      .status(400)
      .json({ message: "Source and Destination cannot be the same" });
  }

  const connection = await db.getConnection();
  try {
    await connection.beginTransaction();

    // 1. Check if Source has enough stock
    const [sourceStock] = await connection.query(
      "SELECT quantity_on_hand FROM inventory WHERE warehouse_id = ? AND product_id = ?",
      [from_warehouse_id, product_id],
    );

    if (
      sourceStock.length === 0 ||
      parseFloat(sourceStock[0].quantity_on_hand) < parseFloat(quantity)
    ) {
      throw new Error("Insufficient stock in source warehouse");
    }

    // 2. Deduct from Source
    await connection.query(
      "UPDATE inventory SET quantity_on_hand = quantity_on_hand - ? WHERE warehouse_id = ? AND product_id = ?",
      [quantity, from_warehouse_id, product_id],
    );

    // 3. Add to Destination (Insert if not exists)
    await connection.query(
      `INSERT INTO inventory (warehouse_id, product_id, quantity_on_hand) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE quantity_on_hand = quantity_on_hand + ?`,
      [to_warehouse_id, product_id, quantity, quantity],
    );

    // 4. Log Transaction (Transfer Out)
    await connection.query(
      `INSERT INTO stock_transactions (transaction_date, warehouse_id, product_id, transaction_type, quantity, created_at)
             VALUES (NOW(), ?, ?, 'transfer', ?, NOW())`,
      [from_warehouse_id, product_id, -quantity], // Negative for source
    );

    // 5. Log Transaction (Transfer In)
    await connection.query(
      `INSERT INTO stock_transactions (transaction_date, warehouse_id, product_id, transaction_type, quantity, created_at)
             VALUES (NOW(), ?, ?, 'transfer', ?, NOW())`,
      [to_warehouse_id, product_id, quantity], // Positive for destination
    );

    await connection.commit();
    res.json({ message: "Transfer successful" });
  } catch (error) {
    await connection.rollback();
    console.error("Transfer failed:", error);
    res.status(500).json({ message: error.message });
  } finally {
    connection.release();
  }
};

// 5. Get Stock History (Transactions)
exports.getStockHistory = async (req, res) => {
  try {
    const query = `
            SELECT t.transaction_id, t.transaction_date, t.transaction_type, t.quantity, 
                   p.product_name, p.product_code, w.warehouse_name
            FROM stock_transactions t
            JOIN products p ON t.product_id = p.product_id
            JOIN warehouses w ON t.warehouse_id = w.warehouse_id
            ORDER BY t.created_at DESC LIMIT 100
        `;
    const [history] = await db.execute(query);
    res.json(history);
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
};
