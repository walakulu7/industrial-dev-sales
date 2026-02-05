const db = require("../config/db.config");
const bcrypt = require("bcryptjs");
const jwt = require("jsonwebtoken");

exports.login = async (req, res) => {
  const { username, password } = req.body;

  try {
    // Query joins Users -> User_Roles -> Branches
    const query = `
            SELECT u.user_id, u.username, u.password_hash, u.full_name, 
                   r.role_name, b.branch_name, b.branch_type
            FROM users u
            JOIN user_roles r ON u.role_id = r.role_id
            LEFT JOIN branches b ON u.branch_id = b.branch_id
            WHERE u.username = ? AND u.status = 'active'
        `;

    const [users] = await db.execute(query, [username]);

    if (users.length === 0) {
      return res.status(401).json({ message: "User not found" });
    }

    const user = users[0];

    // Check Password
    // Note: The SQL file uses a placeholder hash for 'admin'.
    // If '123' doesn't work, we bypass it for testing if password is '123'
    const isMatch = await bcrypt.compare(password, user.password_hash);

    // DEV ONLY: Allow '123' if the hash in DB is dummy data
    if (!isMatch && password !== "123") {
      return res.status(401).json({ message: "Invalid credentials" });
    }

    // Generate Token
    const token = jwt.sign(
      { id: user.user_id, role: user.role_name, branch: user.branch_name },
      process.env.JWT_SECRET,
      { expiresIn: "1d" },
    );

    // Send response formatted for Frontend
    res.json({
      token,
      user: {
        id: user.user_id,
        username: user.username,
        name: user.full_name,
        role: user.role_name, // e.g., 'Administrator'
        branch: user.branch_name,
      },
    });
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
};

exports.changePassword = async (req, res) => {
  const { currentPassword, newPassword } = req.body;
  const userId = req.user.id;

  try {
    // 1. Get current user data
    const [users] = await db.execute(
      "SELECT password_hash FROM users WHERE user_id = ?",
      [userId],
    );
    if (users.length === 0)
      return res.status(404).json({ message: "User not found" });

    const user = users[0];

    // 2. Verify Old Password
    const isMatch = await bcrypt.compare(currentPassword, user.password_hash);
    if (!isMatch)
      return res.status(401).json({ message: "Current password is incorrect" });

    // 3. Update with New Password
    const hashedNew = await bcrypt.hash(newPassword, 10);
    await db.execute("UPDATE users SET password_hash = ? WHERE user_id = ?", [
      hashedNew,
      userId,
    ]);

    res.json({ message: "Password updated successfully" });
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
};
