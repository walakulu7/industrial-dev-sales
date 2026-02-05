const db = require('../config/db.config');

// 1. Sales Report (Date Range)
exports.getSalesReport = async (req, res) => {
    const { startDate, endDate } = req.query;
    try {
        const query = `
            SELECT si.invoice_number, si.invoice_date, c.customer_name, 
                   b.branch_name, si.payment_method, si.total_amount, si.status
            FROM sales_invoices si
            JOIN customers c ON si.customer_id = c.customer_id
            JOIN branches b ON si.branch_id = b.branch_id
            WHERE si.invoice_date BETWEEN ? AND ?
            ORDER BY si.invoice_date DESC
        `;
        const [rows] = await db.execute(query, [startDate, endDate]);
        res.json(rows);
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
};

// 2. Inventory Valuation Report (Current Snapshot)
exports.getInventoryReport = async (req, res) => {
    try {
        const query = `
            SELECT p.product_code, p.product_name, c.category_name, 
                   i.quantity_on_hand, p.unit_of_measure, 
                   p.standard_cost, 
                   (i.quantity_on_hand * p.standard_cost) AS total_value
            FROM inventory i
            JOIN products p ON i.product_id = p.product_id
            JOIN product_categories c ON p.category_id = c.category_id
            ORDER BY total_value DESC
        `;
        const [rows] = await db.execute(query);
        res.json(rows);
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
};

// 3. Production Report (Date Range)
exports.getProductionReport = async (req, res) => {
    const { startDate, endDate } = req.query;
    try {
        const query = `
            SELECT pl.production_date, c.center_name, 
                   p_in.product_name AS input_material, pl.input_qty,
                   p_out.product_name AS output_product, pl.output_qty
            FROM production_logs pl
            JOIN production_centers c ON pl.center_id = c.production_center_id
            JOIN products p_in ON pl.input_product_id = p_in.product_id
            JOIN products p_out ON pl.output_product_id = p_out.product_id
            WHERE pl.production_date BETWEEN ? AND ?
            ORDER BY pl.production_date DESC
        `;
        const [rows] = await db.execute(query, [startDate, endDate]);
        res.json(rows);
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
};