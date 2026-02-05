const express = require("express");
const router = express.Router();
const productController = require("../controllers/inventory/productController");
const inventoryController = require("../controllers/inventory/inventoryController"); // <--- Import new controller
const { protect } = require("../middleware/authMiddleware");

// --- Product Master ---
router.get("/items", protect, productController.getAllItems);
router.post("/items", protect, productController.createItem);
router.put("/items/:id", protect, productController.updateItem);
router.delete("/items/:id", protect, productController.deleteItem);

// --- Stock Management ---
router.get("/stock", protect, inventoryController.getInventory); // Get All Stock
router.post("/adjust", protect, inventoryController.adjustStock); // Add/Remove Stock
router.get("/warehouses", protect, inventoryController.getWarehouses); // For Dropdowns
router.post("/transfer", protect, inventoryController.transferStock);
router.get("/history", protect, inventoryController.getStockHistory);

module.exports = router;
