const db = require('../../config/db.config');

// 1. Get All Branches
exports.getAllBranches = async (req, res) => {
    try {
        const [branches] = await db.execute("SELECT * FROM branches ORDER BY branch_id ASC");
        res.json(branches);
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
};

// 2. Create Branch
exports.createBranch = async (req, res) => {
    const { branch_code, branch_name, branch_type, location, address, contact_phone } = req.body;
    try {
        await db.execute(
            `INSERT INTO branches (branch_code, branch_name, branch_type, location, address, contact_phone, status) 
             VALUES (?, ?, ?, ?, ?, ?, 'active')`,
            [branch_code, branch_name, branch_type, location, address, contact_phone]
        );
        res.status(201).json({ message: "Branch created successfully" });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
};

// 3. Update Branch
exports.updateBranch = async (req, res) => {
    const { id } = req.params;
    const { branch_name, branch_type, location, address, contact_phone, status } = req.body;
    try {
        await db.execute(
            `UPDATE branches SET 
             branch_name=?, branch_type=?, location=?, address=?, contact_phone=?, status=? 
             WHERE branch_id=?`,
            [branch_name, branch_type, location, address, contact_phone, status, id]
        );
        res.json({ message: "Branch updated successfully" });
    } catch (error) {
        res.status(500).json({ error: error.message });
    }
};

// 4. Delete Branch
exports.deleteBranch = async (req, res) => {
    const { id } = req.params;
    try {
        await db.execute("DELETE FROM branches WHERE branch_id=?", [id]);
        res.json({ message: "Branch deleted successfully" });
    } catch (error) {
        // Handle Foreign Key Constraint (e.g. branch has users/invoices)
        if (error.errno === 1451) {
            res.status(400).json({ message: "Cannot delete: This branch has linked data (Users/Sales)." });
        } else {
            res.status(500).json({ error: error.message });
        }
    }
};