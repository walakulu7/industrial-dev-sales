const express = require('express');
const router = express.Router();
const authController = require('../controllers/authController');
const yarnController = require('../controllers/inventory/yarnController');
const salesController = require('../controllers/salesController');

// Auth
router.post('/login', authController.login);
router.post('/register', authController.register);

// Inventory
router.get('/inventory', yarnController.getStock);
router.post('/inventory/update', yarnController.updateStock);

// Sales
router.post('/invoice', salesController.createInvoice);

module.exports = router;