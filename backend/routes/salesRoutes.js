const express = require("express");
const router = express.Router();
const salesController = require("../controllers/salesController");
const { protect } = require("../middleware/authMiddleware");

router.post("/invoice", protect, salesController.createInvoice);
router.get("/:id", protect, salesController.getInvoiceById);
router.get("/", protect, salesController.getAllInvoices);
router.get('/credit', protect, salesController.getOutstandingCredits);
router.post("/credit/pay", protect, salesController.recordPayment);

module.exports = router;
