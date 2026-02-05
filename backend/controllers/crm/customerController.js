const db = require('../../config/db.config');

// 1. Get All Customers (Simplified to fix "Not Showing" issue)
exports.getCustomers = async (req, res) => {
    try {
        const { search, type, status } = req.query;

        // Base Query
        let query = `SELECT * FROM customers WHERE 1=1`;
        const params = [];

        // Apply Filters
        if (search) {
            query += ` AND (customer_name LIKE ? OR customer_code LIKE ? OR phone LIKE ?)`;
            params.push(`%${search}%`, `%${search}%`, `%${search}%`);
        }

        if (type && type !== 'All Types') {
            query += ` AND customer_type = ?`;
            params.push(type);
        }

        if (status && status !== 'All Status') {
            // Map "Active Only" to "active"
            const statusValue = status.toLowerCase().includes('active') ? 'active' : 'inactive';
            query += ` AND status = ?`;
            params.push(statusValue);
        }

        query += ` ORDER BY customer_id DESC`;

        const [customers] = await db.execute(query, params);

        // Add placeholder stats so the frontend doesn't break
        // (We can add real stats back later once basic fetching works)
        const data = customers.map(c => ({
            ...c,
            total_sales: 0, 
            outstanding_balance: 0
        }));

        res.json(data);

    } catch (error) {
        console.error("Error fetching customers:", error);
        res.status(500).json({ error: error.message });
    }
};

// 2. Create New Customer
exports.createCustomer = async (req, res) => {
    const { name, type, phone, email, contact_person, city, address, credit_limit } = req.body;
    
    try {
        // Generate a Code (e.g., CUST-17384...)
        const code = `CUST-${Date.now().toString().slice(-4)}`;

        await db.execute(
            `INSERT INTO customers 
            (customer_code, customer_name, customer_type, phone, email, contact_person, city, address, credit_limit, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')`,
            [code, name, type, phone, email, contact_person, city, address, credit_limit || 0]
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
    const { name, type, phone, email, contact_person, city, address, credit_limit, status } = req.body;
    
    try {
        await db.execute(
            `UPDATE customers SET 
            customer_name=?, customer_type=?, phone=?, email=?, contact_person=?, city=?, address=?, credit_limit=?, status=?
            WHERE customer_id=?`,
            [name, type, phone, email, contact_person, city, address, credit_limit, status, id]
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