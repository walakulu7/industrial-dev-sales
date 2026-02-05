const db = require('../config/db.config');

class Invoice {
    static async createTable() {
        const sql = `
            CREATE TABLE IF NOT EXISTS invoices (
                id INT AUTO_INCREMENT PRIMARY KEY,
                invoice_no VARCHAR(20) UNIQUE NOT NULL,
                customer_name VARCHAR(100),
                total_amount DECIMAL(10,2),
                issued_by INT,
                location VARCHAR(50),
                payment_status ENUM('Paid', 'Credit') DEFAULT 'Paid',
                date DATETIME DEFAULT CURRENT_TIMESTAMP
            )`;
        await db.execute(sql);
    }
}

module.exports = Invoice;