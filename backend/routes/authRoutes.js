const express = require("express");
const router = express.Router();
const authController = require("../controllers/authController");
const { protect } = require("../middleware/authMiddleware"); // Import protect

// Public Routes
router.post("/login", authController.login);

// We removed '/register' because only Admins can create users now.
// That logic is handled in adminRoutes.js
// New Change Password Route (Protected)
router.put("/change-password", protect, authController.changePassword);

module.exports = router;
