const db = require('../config/db.config');

class ProductionLog {
    static async createTable() {
        const sql = `
            CREATE TABLE IF NOT EXISTS production_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                location VARCHAR(50),
                production_line VARCHAR(50),
                raw_item_id INT,
                used_qty DECIMAL(10,2),
                finished_item_id INT,
                produced_qty DECIMAL(10,2),
                date DATETIME DEFAULT CURRENT_TIMESTAMP,
                status VARCHAR(20) DEFAULT 'Completed'
            )`;
        await db.execute(sql);
    }
}

module.exports = ProductionLog;