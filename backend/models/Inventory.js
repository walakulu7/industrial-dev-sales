const db = require('../config/db.config');

class Inventory {
    static async createTable() {
        const sql = `
            CREATE TABLE IF NOT EXISTS inventory (
                id INT AUTO_INCREMENT PRIMARY KEY,
                item_id INT,
                location VARCHAR(50) NOT NULL,
                quantity DECIMAL(10,2) DEFAULT 0,
                last_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (item_id) REFERENCES items(id)
            )`;
        await db.execute(sql);
    }

    static async updateQuantity(itemId, location, quantity, operation) {
        // operation: '+' or '-'
        const sql = `UPDATE inventory SET quantity = quantity ${operation} ? WHERE item_id = ? AND location = ?`;
        return db.execute(sql, [quantity, itemId, location]);
    }
}

module.exports = Inventory;