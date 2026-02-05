const express = require('express');
const router = express.Router();
const branchController = require('../controllers/admin/branchController');
const { protect } = require('../middleware/authMiddleware');

router.get('/', protect, branchController.getAllBranches);
router.post('/', protect, branchController.createBranch);
router.put('/:id', protect, branchController.updateBranch);
router.delete('/:id', protect, branchController.deleteBranch);

module.exports = router;