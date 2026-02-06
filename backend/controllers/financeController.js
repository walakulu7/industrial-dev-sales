const db = require("../config/db.config");

// --- CREDIT SALES SECTION ---

// 1. Get Credit List
const getCreditList = async (req, res) => {
  try {
    const query = `
            SELECT cs.credit_id, cs.invoice_id, cs.total_amount, cs.paid_amount, 
                   (cs.total_amount - cs.paid_amount) AS balance_amount, 
                   cs.due_date, cs.status,
                   c.customer_name, c.phone,
                   si.invoice_number, si.invoice_date
            FROM credit_sales cs
            JOIN customers c ON cs.customer_id = c.customer_id
            JOIN sales_invoices si ON cs.invoice_id = si.invoice_id
            WHERE cs.status != 'paid'
            ORDER BY balance_amount DESC
        `;
    const [credits] = await db.execute(query);
    res.json(credits);
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
};

// 2. Record Payment (FIXED SQL QUERY)
const recordPayment = async (req, res) => {
  const { credit_id, amount, payment_method, reference, user_id } = req.body;

  if (!credit_id || !amount || !user_id) {
    return res.status(400).json({ message: "Missing required fields" });
  }

  const connection = await db.getConnection();
  try {
    await connection.beginTransaction();

    // Check Invoice Balance
    const [credit] = await connection.query(
      "SELECT total_amount, paid_amount, invoice_id FROM credit_sales WHERE credit_id = ?",
      [credit_id],
    );
    if (credit.length === 0) throw new Error("Credit record not found");

    const currentBalance =
      parseFloat(credit[0].total_amount) - parseFloat(credit[0].paid_amount);
    const invoiceId = credit[0].invoice_id;

    if (parseFloat(amount) > currentBalance) {
      throw new Error(`Overpayment. Max allowed: ${currentBalance}`);
    }

    // Insert Payment Record (Fixed placeholders)
    await connection.query(
      `INSERT INTO credit_payments (credit_id, payment_date, payment_method, payment_reference, amount, created_by) 
             VALUES (?, NOW(), ?, ?, ?, ?)`,
      [credit_id, payment_method, reference || "", amount, user_id],
    );

    // Update Credit Sales Table
    const newPaid = parseFloat(credit[0].paid_amount) + parseFloat(amount);
    const newBalance = parseFloat(credit[0].total_amount) - newPaid;

    // IMPORTANT: Ensure status becomes 'paid' if balance is 0 or less
    const newStatus = newBalance <= 0.01 ? "paid" : "partial";

    await connection.query(
      `UPDATE credit_sales SET paid_amount = ?, status = ? WHERE credit_id = ?`,
      [newPaid, newStatus, credit_id],
    );

    // Update Sales Invoice Table
    await connection.query(
      `UPDATE sales_invoices SET paid_amount = ?, status = ? WHERE invoice_id = ?`,
      [newPaid, newStatus, invoiceId],
    );

    await connection.commit();
    res.json({ message: "Payment recorded successfully" });
  } catch (error) {
    await connection.rollback();
    console.error(error);
    res.status(500).json({ message: error.message });
  } finally {
    connection.release();
  }
};

// 3. Get Payment History
const getPaymentHistory = async (req, res) => {
  const { id } = req.params; // This is the credit_id
  try {
    const query = `
            SELECT payment_id, payment_date, payment_method, payment_reference, amount, u.username
            FROM credit_payments cp
            JOIN users u ON cp.created_by = u.user_id
            WHERE cp.credit_id = ? 
            ORDER BY cp.payment_date DESC
        `;
    const [history] = await db.execute(query, [id]);
    res.json(history);
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
};

// --- ACCOUNTING SECTION ---

// 4. Get Financial Stats (P&L)
const getFinancialStats = async (req, res) => {
  try {
    const { startDate, endDate } = req.query;

    let revQuery = `SELECT SUM(total_amount) as total FROM sales_invoices WHERE status != 'cancelled'`;
    let expQuery = `SELECT SUM(amount) as total FROM expenses`;
    const params = [];

    if (startDate && endDate) {
      revQuery += ` AND invoice_date BETWEEN ? AND ?`;
      expQuery += ` WHERE expense_date BETWEEN ? AND ?`;
      params.push(startDate, endDate);
    }

    const [revenue] = await db.execute(revQuery, params);
    const [expenses] = await db.execute(expQuery, params);

    const totalRev = parseFloat(revenue[0].total || 0);
    const totalExp = parseFloat(expenses[0].total || 0);

    res.json({
      revenue: totalRev,
      expenses: totalExp,
      profit: totalRev - totalExp,
    });
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
};

//  Get All Payments (Global Log)
const getAllPayments = async (req, res) => {
  try {
    const query = `
            SELECT cp.payment_id, cp.payment_date, cp.payment_method, cp.payment_reference, cp.amount, 
                   u.username, si.invoice_number, c.customer_name
            FROM credit_payments cp
            JOIN users u ON cp.created_by = u.user_id
            JOIN credit_sales cs ON cp.credit_id = cs.credit_id
            JOIN sales_invoices si ON cs.invoice_id = si.invoice_id
            JOIN customers c ON cs.customer_id = c.customer_id
            ORDER BY cp.payment_date DESC LIMIT 100
        `;
    const [history] = await db.execute(query);
    res.json(history);
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
};

// 5. Add Expense
const addExpense = async (req, res) => {
  const { date, category, amount, description, user_id } = req.body;
  try {
    await db.execute(
      `INSERT INTO expenses (expense_date, category, amount, description, created_by) VALUES (?, ?, ?, ?, ?)`,
      [date, category, amount, description, user_id],
    );
    res.json({ message: "Expense recorded" });
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
};

// 6. Get Expense List
const getExpenses = async (req, res) => {
  try {
    const [rows] = await db.execute(
      `SELECT * FROM expenses ORDER BY expense_date DESC LIMIT 50`,
    );
    res.json(rows);
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
};

module.exports = {
  getCreditList,
  recordPayment,
  getPaymentHistory,
  getAllPayments,
  getFinancialStats,
  addExpense,
  getExpenses,
};
