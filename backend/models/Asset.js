const db = require('../config/db.config');

class Asset {
    static async createTable() {
        const sql = `
            CREATE TABLE IF NOT EXISTS fixed_assets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                purchase_date DATE,
                original_value DECIMAL(12,2),
                current_value DECIMAL(12,2),
                depreciation_rate DECIMAL(5,2),
                location_id INT,
                status ENUM('Active', 'Disposed') DEFAULT 'Active'
            )`;
        await db.execute(sql);
    }
}

module.exports = Asset;