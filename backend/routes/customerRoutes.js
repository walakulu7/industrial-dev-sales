const express = require('express');
const router = express.Router();
const customerController = require('../controllers/crm/customerController');
const { protect } = require('../middleware/authMiddleware');

router.get('/', protect, customerController.getCustomers);
router.post('/', protect, customerController.createCustomer);
router.put('/:id', protect, customerController.updateCustomer);
router.get('/:id/history', protect, customerController.getCustomerHistory);

module.exports = router;