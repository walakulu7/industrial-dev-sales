const db = require('../../config/db.config');

// Get all products
exports.getAllItems = async (req, res) => {
    try {
        // We select column aliases (AS name) so the Frontend doesn't break
        const query = `
            SELECT p.product_id AS id, p.product_code, p.product_name AS name, 
                   c.category_name AS type, p.unit_of_measure AS unit, 
                   p.standard_cost AS cost_price, p.standard_price AS selling_price
            FROM products p
            JOIN product_categories c ON p.category_id = c.category_id
            WHERE p.status = 'active'
            ORDER BY p.product_name ASC
        `;
        const [items] = await db.execute(query);
        res.json(items);
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
};

// Create Product
exports.createItem = async (req, res) => {
    const { name, type, unit, cost_price, selling_price } = req.body;
    
    try {
        // 1. Find Category ID based on the string sent from Frontend
        // (Frontend sends 'Yarn' or 'Finished', we need to map to ID)
        let categoryId = 1; // Default
        const [cats] = await db.execute(
            "SELECT category_id FROM product_categories WHERE category_type LIKE ? OR category_name LIKE ? LIMIT 1", 
            [`%${type}%`, `%${type}%`]
        );
        if (cats.length > 0) categoryId = cats[0].category_id;

        // 2. Generate Product Code
        const code = `ITM-${Date.now().toString().slice(-6)}`;

        await db.execute(
            `INSERT INTO products 
            (product_code, product_name, category_id, unit_of_measure, standard_cost, standard_price) 
            VALUES (?, ?, ?, ?, ?, ?)`,
            [code, name, categoryId, unit, cost_price, selling_price]
        );
        res.status(201).json({ message: "Product created successfully" });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
};

// Delete Product
exports.deleteItem = async (req, res) => {
    const { id } = req.params;
    try {
        await db.execute('UPDATE products SET status = "discontinued" WHERE product_id = ?', [id]);
        res.json({ message: "Product deleted" });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
};

// Update Product
exports.updateItem = async (req, res) => {
    const { id } = req.params;
    const { name, unit, cost_price, selling_price } = req.body;
    try {
        await db.execute(
            'UPDATE products SET product_name=?, unit_of_measure=?, standard_cost=?, standard_price=? WHERE product_id=?',
            [name, unit, cost_price, selling_price, id]
        );
        res.json({ message: "Updated successfully" });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
};