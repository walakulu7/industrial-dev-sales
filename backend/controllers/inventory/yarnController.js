const db = require('../../config/db.config');

// Get Stock
exports.getYarnStock = async (req, res) => {
    try {
        // We use the Database View 'v_current_stock'
        // We filter for 'Yarn' based on the category name
        const query = `
            SELECT inventory_id AS id, product_name AS name, unit_of_measure AS unit,
                   quantity_available AS quantity, warehouse_name AS location, stock_status
            FROM v_current_stock
            WHERE category_name LIKE '%Yarn%'
        `;
        
        const [stock] = await db.execute(query);
        res.json(stock);
    } catch (error) {
        console.error(error);
        res.status(500).json({ error: error.message });
    }
};