const db = require('../config/db.config');

class User {
    static async createTable() {
        const sql = `
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                role ENUM('Director', 'Accountant', 'Officer', 'SalesAssistant') NOT NULL,
                branch_name VARCHAR(50)
            )`;
        await db.execute(sql);
    }

    static async findByUsername(username) {
        const [rows] = await db.execute('SELECT * FROM users WHERE username = ?', [username]);
        return rows[0];
    }

    static async create(userData) {
        const { username, password, role, branch_name } = userData;
        return db.execute(
            'INSERT INTO users (username, password, role, branch_name) VALUES (?, ?, ?, ?)',
            [username, password, role, branch_name]
        );
    }
}

module.exports = User;