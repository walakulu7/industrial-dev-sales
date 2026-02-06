const db = require("../../config/db.config");

// 1. Get All Customers (Simplified to fix "Not Showing" issue)
exports.getCustomers = async (req, res) => {
  try {
    const { search, type, status } = req.query;

    // Use Subqueries to calculate totals for each customer
    let query = `
            SELECT 
                c.*,
                -- Calculate Total Sales (Sum of all non-cancelled invoices)
                (SELECT COALESCE(SUM(total_amount), 0) 
                 FROM sales_invoices 
                 WHERE customer_id = c.customer_id AND status != 'cancelled') AS total_sales,

                -- Calculate Outstanding Balance (Sum of unpaid credit)
                (SELECT COALESCE(SUM(total_amount - paid_amount), 0) 
                 FROM credit_sales 
                 WHERE customer_id = c.customer_id AND status != 'paid') AS outstanding_balance

            FROM customers c
            WHERE 1=1
        `;

    const params = [];

    // Apply Filters
    if (search) {
      query += ` AND (c.customer_name LIKE ? OR c.customer_code LIKE ? OR c.phone LIKE ?)`;
      params.push(`%${search}%`, `%${search}%`, `%${search}%`);
    }

    if (type && type !== "All Types") {
      query += ` AND c.customer_type = ?`;
      params.push(type);
    }

    if (status && status !== "All Status") {
      const statusValue = status.toLowerCase().includes("active")
        ? "active"
        : "inactive";
      query += ` AND c.status = ?`;
      params.push(statusValue);
    }

    query += ` ORDER BY c.customer_id DESC`;

    const [customers] = await db.execute(query, params);
    res.json(customers);
  } catch (error) {
    console.error("Error fetching customers:", error);
    res.status(500).json({ error: error.message });
  }
};

// 2. Create New Customer
exports.createCustomer = async (req, res) => {
  const {
    name,
    type,
    phone,
    email,
    contact_person,
    city,
    address,
    credit_limit,
  } = req.body;

  try {
    // Generate a Code (e.g., CUST-17384...)
    const code = `CUST-${Date.now().toString().slice(-4)}`;

    await db.execute(
      `INSERT INTO customers 
            (customer_code, customer_name, customer_type, phone, email, contact_person, city, address, credit_limit, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')`,
      [
        code,
        name,
        type,
        phone,
        email,
        contact_person,
        city,
        address,
        credit_limit || 0,
      ],
    );

    res.status(201).json({ message: "Customer created successfully" });
  } catch (error) {
    console.error("Error creating customer:", error);
    res.status(500).json({ error: error.message });
  }
};

// 3. Update Existing Customer
exports.updateCustomer = async (req, res) => {
  const { id } = req.params;
  const {
    name,
    type,
    phone,
    email,
    contact_person,
    city,
    address,
    credit_limit,
    status,
  } = req.body;

  try {
    await db.execute(
      `UPDATE customers SET 
            customer_name=?, customer_type=?, phone=?, email=?, contact_person=?, city=?, address=?, credit_limit=?, status=?
            WHERE customer_id=?`,
      [
        name,
        type,
        phone,
        email,
        contact_person,
        city,
        address,
        credit_limit,
        status,
        id,
      ],
    );
    res.json({ message: "Customer updated successfully" });
  } catch (error) {
    console.error("Error updating customer:", error);
    res.status(500).json({ error: error.message });
  }
};

// 4. Get Customer Transaction History (For the "Eye" Button)
exports.getCustomerHistory = async (req, res) => {
  const { id } = req.params;
  try {
    const query = `
            SELECT invoice_id, invoice_number, invoice_date, total_amount, paid_amount, status 
            FROM sales_invoices 
            WHERE customer_id = ? 
            ORDER BY invoice_date DESC
        `;
    const [history] = await db.execute(query, [id]);
    res.json(history);
  } catch (error) {
    console.error("Error fetching history:", error);
    res.status(500).json({ error: error.message });
  }
};
