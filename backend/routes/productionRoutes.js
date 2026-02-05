const express = require('express');
const router = express.Router();
const productionController = require('../controllers/productionController');
const { protect } = require('../middleware/authMiddleware');

router.get('/centers', protect, productionController.getCenters);
router.post('/record', protect, productionController.recordProduction);
router.get('/history', protect, productionController.getProductionHistory);

module.exports = router;