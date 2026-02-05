const db = require("../config/db.config");
const bcrypt = require("bcryptjs");

// Get all users
exports.getAllUsers = async (req, res) => {
  try {
    const query = `
            SELECT u.user_id AS id, u.username, u.full_name, u.status,
                   r.role_name AS role, b.branch_name
            FROM users u
            JOIN user_roles r ON u.role_id = r.role_id
            LEFT JOIN branches b ON u.branch_id = b.branch_id
            ORDER BY u.user_id DESC
        `;
    const [users] = await db.execute(query);
    res.json(users);
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
};

// Create a new user
exports.createUser = async (req, res) => {
  const { username, password, full_name, role, branch_id } = req.body;

  try {
    // 1. Check if username exists
    const [exists] = await db.execute(
      "SELECT user_id FROM users WHERE username = ?",
      [username],
    );
    if (exists.length > 0)
      return res.status(400).json({ message: "Username already exists" });

    // 2. Get Role ID from Name (Frontend sends 'Sales Assistant', DB needs ID)
    const [roles] = await db.execute(
      "SELECT role_id FROM user_roles WHERE role_name = ?",
      [role],
    );
    if (roles.length === 0)
      return res.status(400).json({ message: "Invalid Role" });
    const roleId = roles[0].role_id;

    // 3. Hash Password
    const hashedPassword = await bcrypt.hash(password, 10);

    // 4. Insert
    await db.execute(
      "INSERT INTO users (username, password_hash, full_name, role_id, branch_id) VALUES (?, ?, ?, ?, ?)",
      [username, hashedPassword, full_name, roleId, branch_id || 1],
    );

    res.status(201).json({ message: "User created successfully" });
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
};

// Delete User
exports.deleteUser = async (req, res) => {
  const { id } = req.params;
  try {
    if (req.user.id == id)
      return res.status(400).json({ message: "Cannot delete yourself" });
    await db.execute("DELETE FROM users WHERE user_id = ?", [id]);
    res.json({ message: "User deleted" });
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
};

// Reset User Password (Admin Override)
exports.resetUserPassword = async (req, res) => {
  const { id } = req.params;
  const { newPassword } = req.body;

  try {
    if (!newPassword || newPassword.length < 4) {
      return res
        .status(400)
        .json({ message: "Password must be at least 4 chars" });
    }

    const hashedPassword = await bcrypt.hash(newPassword, 10);

    await db.execute("UPDATE users SET password_hash = ? WHERE user_id = ?", [
      hashedPassword,
      id,
    ]);

    res.json({ message: "Password reset successfully" });
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
};
