const express = require('express');
const router = express.Router();
const assetController = require('../controllers/assetController');
const { protect } = require('../middleware/authMiddleware');
const { checkRole } = require('../middleware/roleMiddleware');

router.post('/add', protect, checkRole(['Accountant', 'Director']), assetController.addAsset);
router.get('/', protect, assetController.getAssets);
router.post('/depreciate', protect, checkRole(['Accountant']), assetController.calculateDepreciation);

module.exports = router;