const db = require('../config/db.config');

class AccountTransaction {
    static async createTable() {
        const sql = `
            CREATE TABLE IF NOT EXISTS accounting_ledger (
                id INT AUTO_INCREMENT PRIMARY KEY,
                date DATETIME DEFAULT CURRENT_TIMESTAMP,
                description VARCHAR(255),
                type ENUM('Income', 'Expense') NOT NULL,
                category VARCHAR(50),
                amount DECIMAL(10,2),
                related_invoice_id INT NULL
            )`;
        await db.execute(sql);
    }
}

module.exports = AccountTransaction;