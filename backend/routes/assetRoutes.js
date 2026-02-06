const express = require('express');
const router = express.Router();
const assetController = require('../controllers/assetController');
const { protect } = require('../middleware/authMiddleware');

router.get('/', protect, assetController.getAssets);
router.post('/', protect, assetController.addAsset);
router.delete('/:id', protect, assetController.deleteAsset);

module.exports = router;
