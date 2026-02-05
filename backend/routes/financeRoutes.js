const express = require('express');
const router = express.Router();
const financeController = require('../controllers/financeController');
const { protect } = require('../middleware/authMiddleware');
const { checkRole } = require('../middleware/roleMiddleware');

// --- Credit Management Routes (Active) ---
// These functions exist in your current financeController.js

// Get list of outstanding credits
router.get('/credits', protect, financeController.getCreditList);

// Record a payment
router.post('/payment', protect, financeController.recordPayment);

// Get payment history for a specific credit record
router.get('/payment-history/:id', protect, financeController.getPaymentHistory);


// --- Accounting Routes
router.get('/stats', protect, financeController.getFinancialStats); // P&L
router.post('/expenses', protect, financeController.addExpense); // Add Expense
router.get('/expenses', protect, financeController.getExpenses); // List Expenses

// I have commented these out because 'addTransaction' and 'getProfitLoss' 
// are NOT in your current financeController.js. 
// Uncomment these only after you add those functions to the controller.

/*
router.post(
  "/transaction",
  protect,
  checkRole(["Accountant", "Director"]),
  financeController.addTransaction
);

router.get(
  "/pnl",
  protect,
  checkRole(["Accountant", "Director", "Provincial Director"]),
  financeController.getProfitLoss
);
*/

module.exports = router;