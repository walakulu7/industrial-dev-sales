const express = require("express");
const router = express.Router();
const reportController = require("../controllers/reportController");
const { protect } = require("../middleware/authMiddleware");

router.get("/sales", protect, reportController.getSalesReport);
router.get("/inventory", protect, reportController.getInventoryReport);
router.get("/production", protect, reportController.getProductionReport);
router.get("/dashboard", protect, reportController.getDashboardStats);

module.exports = router;
