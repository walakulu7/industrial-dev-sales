const db = require("../config/db.config");

// 1. Get All Invoices (For Sales History List)
exports.getAllInvoices = async (req, res) => {
  try {
    const query = `
            SELECT i.invoice_id, i.invoice_number, i.invoice_date, i.total_amount, i.paid_amount, i.status, 
                   COALESCE(c.customer_name, 'Unknown') as customer_name, b.branch_name
            FROM sales_invoices i
            LEFT JOIN customers c ON i.customer_id = c.customer_id
            LEFT JOIN branches b ON i.branch_id = b.branch_id
            ORDER BY i.invoice_id DESC
        `;
    const [invoices] = await db.execute(query);
    res.json(invoices);
  } catch (error) {
    console.error("Error fetching all invoices:", error);
    res.status(500).json({ error: error.message });
  }
};

// 2. Create New Invoice (Header + Details + Stock Update + Credit Entry)
exports.createInvoice = async (req, res) => {
  const { customer_id, branch_id, payment_method, items, user_id } = req.body;
  const connection = await db.getConnection();

  try {
    await connection.beginTransaction();

    // Calculate Grand Total
    let total_amount = 0;
    items.forEach((item) => {
      total_amount += parseFloat(item.price) * parseFloat(item.quantity);
    });

    // Generate Invoice Number (Format: INV-202602-0001)
    const dateStr = new Date().toISOString().slice(0, 7).replace(/-/g, "");
    const [lastInv] = await connection.query(
      "SELECT invoice_id FROM sales_invoices ORDER BY invoice_id DESC LIMIT 1",
    );
    const nextId = (lastInv[0]?.invoice_id || 0) + 1;
    const invoice_number = `INV-${dateStr}-${String(nextId).padStart(4, "0")}`;

    // Insert Invoice Header
    const [result] = await connection.query(
      `INSERT INTO sales_invoices 
            (invoice_number, invoice_date, branch_id, customer_id, payment_method, total_amount, paid_amount, status, created_by) 
            VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?)`,
      [
        invoice_number,
        branch_id || 1,
        customer_id,
        payment_method,
        total_amount,
        payment_method === "credit" ? 0 : total_amount,
        payment_method === "credit" ? "pending" : "paid",
        user_id,
      ],
    );

    const invoiceId = result.insertId;

    // Process Items & Deduct Inventory
    for (const item of items) {
      // Record item details
      await connection.query(
        `INSERT INTO invoice_details (invoice_id, product_id, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?)`,
        [
          invoiceId,
          item.id,
          item.quantity,
          item.price,
          item.quantity * item.price,
        ],
      );

      // Deduct stock from Main Warehouse (ID: 1)
      await connection.query(
        `UPDATE inventory SET quantity_on_hand = quantity_on_hand - ? WHERE product_id = ? AND warehouse_id = 1`,
        [item.quantity, item.id],
      );
    }

    // If Credit Sale, create entry in credit_sales table
    if (payment_method === "credit") {
      const dueDate = new Date();
      dueDate.setDate(dueDate.getDate() + 30); // 30 Days default

      await connection.query(
        `INSERT INTO credit_sales (invoice_id, customer_id, due_date, total_amount, paid_amount, status) 
                VALUES (?, ?, ?, ?, 0, 'pending')`,
        [invoiceId, customer_id, dueDate, total_amount],
      );
    }

    await connection.commit();
    res
      .status(201)
      .json({ message: "Invoice created", invoiceId, invoice_number });
  } catch (error) {
    await connection.rollback();
    console.error("Invoice Creation Error:", error);
    res.status(500).json({ error: error.message });
  } finally {
    connection.release();
  }
};

// 3. Get Single Invoice Details (For Print/View Report)
exports.getInvoiceById = async (req, res) => {
  const { id } = req.params;
  try {
    const queryHeader = `
            SELECT i.*, c.customer_name, c.address, c.phone, b.branch_name, u.full_name as biller_name
            FROM sales_invoices i
            LEFT JOIN customers c ON i.customer_id = c.customer_id
            LEFT JOIN branches b ON i.branch_id = b.branch_id
            LEFT JOIN users u ON i.created_by = u.user_id
            WHERE i.invoice_id = ?
        `;
    const [header] = await db.execute(queryHeader, [id]);
    if (header.length === 0)
      return res.status(404).json({ message: "Invoice not found" });

    const [items] = await db.execute(
      `SELECT d.*, p.product_name, p.product_code FROM invoice_details d 
             JOIN products p ON d.product_id = p.product_id WHERE d.invoice_id = ?`,
      [id],
    );

    res.json({ header: header[0], items });
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
};

// 4. Get Outstanding Credit Sales (The fix for your empty list)
exports.getOutstandingCredits = async (req, res) => {
  try {
    const query = `
            SELECT 
                cs.credit_id, cs.invoice_id, cs.due_date, cs.total_amount, cs.paid_amount, cs.status,
                i.invoice_number, i.invoice_date,
                COALESCE(c.customer_name, 'Unknown Customer') as customer_name, 
                COALESCE(c.phone, 'N/A') as phone
            FROM credit_sales cs
            LEFT JOIN sales_invoices i ON cs.invoice_id = i.invoice_id
            LEFT JOIN customers c ON cs.customer_id = c.customer_id
            WHERE cs.total_amount > cs.paid_amount
            ORDER BY cs.due_date ASC
        `;
    const [credits] = await db.execute(query);
    console.log("SERVER SENDING CREDITS:", credits.length); // Check your VS Code terminal for this!
    res.json(credits);
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
};

// 5. Record Credit Payment (Partial or Full)
exports.recordPayment = async (req, res) => {
  const { credit_id, invoice_id, amount } = req.body;
  const connection = await db.getConnection();

  try {
    await connection.beginTransaction();

    // Get existing record to calculate new totals
    const [rows] = await connection.query(
      "SELECT total_amount, paid_amount FROM credit_sales WHERE credit_id = ?",
      [credit_id],
    );
    if (rows.length === 0) throw new Error("Credit record not found");

    const newPaid = parseFloat(rows[0].paid_amount) + parseFloat(amount);
    const total = parseFloat(rows[0].total_amount);
    const newStatus = newPaid >= total ? "paid" : "partial";

    // Update the Credit Tracking table
    await connection.query(
      "UPDATE credit_sales SET paid_amount = ?, status = ? WHERE credit_id = ?",
      [newPaid, newStatus, credit_id],
    );

    // Update the Main Invoice table to match
    await connection.query(
      "UPDATE sales_invoices SET paid_amount = ?, status = ? WHERE invoice_id = ?",
      [newPaid, newStatus, invoice_id],
    );

    await connection.commit();
    res.json({ message: "Payment recorded successfully" });
  } catch (error) {
    await connection.rollback();
    console.error("Payment Error:", error);
    res.status(500).json({ error: error.message });
  } finally {
    connection.release();
  }
};
// Get All Invoices (With Date Filter)
exports.getAllInvoices = async (req, res) => {
  try {
    const { startDate, endDate } = req.query;

    let query = `
            SELECT i.invoice_id, i.invoice_number, i.invoice_date, i.total_amount, i.paid_amount, i.status, 
                   c.customer_name, b.branch_name
            FROM sales_invoices i
            JOIN customers c ON i.customer_id = c.customer_id
            JOIN branches b ON i.branch_id = b.branch_id
        `;

    const params = [];

    // Apply Date Filter if provided
    if (startDate && endDate) {
      query += ` WHERE DATE(i.invoice_date) BETWEEN ? AND ?`;
      params.push(startDate, endDate);
    }

    query += ` ORDER BY i.invoice_date DESC`;

    const [invoices] = await db.execute(query, params);
    res.json(invoices);
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
};
