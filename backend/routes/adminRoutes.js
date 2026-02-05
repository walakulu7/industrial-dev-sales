const express = require('express');
const router = express.Router();
const adminController = require('../controllers/adminController');
const { protect } = require('../middleware/authMiddleware');

// Middleware to check if user is Administrator
const checkAdmin = (req, res, next) => {
    if (req.user && req.user.role === 'Administrator') {
        next();
    } else {
        res.status(403).json({ message: 'Not authorized as admin' });
    }
};

// Admin Routes
router.get('/users', protect, checkAdmin, adminController.getAllUsers);
router.post('/users', protect, checkAdmin, adminController.createUser);
router.delete('/users/:id', protect, checkAdmin, adminController.deleteUser);
router.put('/users/:id/reset-password', protect, checkAdmin, adminController.resetUserPassword);

module.exports = router;