<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../includes/functions.php';

// Check authentication
require_login();

require_permission('inventory', 'read');

$database = new Database();
$db = $database->getConnection();

// Handle Add Stock form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_stock') {
        try {
            $db->beginTransaction();

            $warehouse_id = $_POST['warehouse_id'];
            $product_id = $_POST['product_id'];
            $quantity = $_POST['quantity'];
            $unit_cost = $_POST['unit_cost'];
            $notes = $_POST['notes'] ?? null;

            // Check if inventory record exists
            $check_query = "SELECT inventory_id FROM inventory WHERE warehouse_id = :wh_id AND product_id = :prod_id";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->execute([':wh_id' => $warehouse_id, ':prod_id' => $product_id]);

            if ($check_stmt->fetch()) {
                // Update existing inventory
                $update_query = "UPDATE inventory 
                               SET quantity_on_hand = quantity_on_hand + :quantity,
                                   last_stock_date = CURDATE()
                               WHERE warehouse_id = :wh_id AND product_id = :prod_id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->execute([
                    ':quantity' => $quantity,
                    ':wh_id' => $warehouse_id,
                    ':prod_id' => $product_id
                ]);
            } else {
                // Insert new inventory record
                $insert_query = "INSERT INTO inventory (warehouse_id, product_id, quantity_on_hand, quantity_reserved, last_stock_date)
                               VALUES (:wh_id, :prod_id, :quantity, 0, CURDATE())";
                $insert_stmt = $db->prepare($insert_query);
                $insert_stmt->execute([
                    ':wh_id' => $warehouse_id,
                    ':prod_id' => $product_id,
                    ':quantity' => $quantity
                ]);
            }

            // Record stock transaction
            $transaction_query = "INSERT INTO stock_transactions (
                transaction_date, transaction_time, warehouse_id, product_id,
                transaction_type, quantity, unit_cost, notes, created_by
            ) VALUES (
                CURDATE(), CURTIME(), :wh_id, :prod_id,
                'receipt', :quantity, :unit_cost, :notes, :user_id
            )";

            $transaction_stmt = $db->prepare($transaction_query);
            $transaction_stmt->execute([
                ':wh_id' => $warehouse_id,
                ':prod_id' => $product_id,
                ':quantity' => $quantity,
                ':unit_cost' => $unit_cost,
                ':notes' => $notes,
                ':user_id' => $_SESSION['user_id']
            ]);

            $db->commit();

            $_SESSION['success'] = 'Stock added successfully';
            header("Location: stock_levels.php");
            exit();
        } catch (Exception $e) {
            $db->rollBack();
            $error = $e->getMessage();
        }
    }
}

// Get filter parameters
$warehouse_filter = $_GET['warehouse'] ?? '';
$category_filter = $_GET['category'] ?? '';
$low_stock_only = isset($_GET['low_stock']);

// Build query
$query = "SELECT 
    i.*,
    p.product_code,
    p.product_name,
    p.unit_of_measure,
    p.reorder_level,
    p.standard_cost,
    pc.category_name,
    w.warehouse_name,
    w.warehouse_type,
    (i.quantity_on_hand * p.standard_cost) as stock_value
FROM inventory i
JOIN products p ON i.product_id = p.product_id
JOIN product_categories pc ON p.category_id = pc.category_id
JOIN warehouses w ON i.warehouse_id = w.warehouse_id
WHERE 1=1";

$params = [];

if ($warehouse_filter) {
    $query .= " AND i.warehouse_id = :warehouse_id";
    $params[':warehouse_id'] = $warehouse_filter;
}

if ($category_filter) {
    $query .= " AND p.category_id = :category_id";
    $params[':category_id'] = $category_filter;
}

if ($low_stock_only) {
    $query .= " AND i.quantity_available <= p.reorder_level";
}

$query .= " ORDER BY w.warehouse_name, pc.category_name, p.product_code";

$stmt = $db->prepare($query);
$stmt->execute($params);
$stock_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get warehouses for filter
$warehouses_query = "SELECT * FROM warehouses WHERE status = 'active' ORDER BY warehouse_name";
$warehouses = $db->query($warehouses_query)->fetchAll();

// Get categories for filter
$categories_query = "SELECT * FROM product_categories ORDER BY category_name";
$categories = $db->query($categories_query)->fetchAll();

// Get products for add stock form
$products_query = "SELECT p.*, pc.category_name 
                   FROM products p 
                   JOIN product_categories pc ON p.category_id = pc.category_id 
                   WHERE p.status = 'active'
                   ORDER BY p.product_name";
$products = $db->query($products_query)->fetchAll();

// Calculate summary
$total_items = count($stock_data);
$total_value = array_sum(array_column($stock_data, 'stock_value'));
$low_stock_count = count(array_filter($stock_data, function ($item) {
    return $item['quantity_available'] <= $item['reorder_level'];
}));

$page_title = 'Stock Levels';
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2><i class="fas fa-boxes me-2"></i>Current Stock Levels</h2>
        </div>
        <div class="col-md-6 text-end">
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addStockModal">
                <i class="fas fa-plus me-1"></i>Add Stock
            </button>
            <a href="transactions.php" class="btn btn-info">
                <i class="fas fa-exchange-alt me-1"></i>Transactions
            </a>
            <a href="transfer.php" class="btn btn-warning">
                <i class="fas fa-truck me-1"></i>Transfer
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['success'];
            unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Items</h6>
                    <h3><?php echo number_format($total_items); ?></h3>
                    <small>In inventory</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Stock Value</h6>
                    <h3>Rs. <?php echo number_format($total_value, 2); ?></h3>
                    <small>Current valuation</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-<?php echo $low_stock_count > 0 ? 'danger' : 'info'; ?> text-white">
                <div class="card-body">
                    <h6 class="card-title">Low Stock Items</h6>
                    <h3><?php echo number_format($low_stock_count); ?></h3>
                    <small>Below reorder level</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h6 class="card-title">Warehouses</h6>
                    <h3><?php echo count($warehouses); ?></h3>
                    <small>Active locations</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Warehouse</label>
                    <select class="form-select" name="warehouse">
                        <option value="">All Warehouses</option>
                        <?php foreach ($warehouses as $wh): ?>
                            <option value="<?php echo $wh['warehouse_id']; ?>"
                                <?php echo $warehouse_filter == $wh['warehouse_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($wh['warehouse_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Category</label>
                    <select class="form-select" name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['category_id']; ?>"
                                <?php echo $category_filter == $cat['category_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Filter</label>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="low_stock" id="low_stock"
                            <?php echo $low_stock_only ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="low_stock">
                            Show Low Stock Only
                        </label>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-1"></i>Apply Filters
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Stock Levels Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Stock Levels</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="stockTable">
                    <thead>
                        <tr>
                            <th>Product Code</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Warehouse</th>
                            <th>On Hand</th>
                            <th>Reserved</th>
                            <th>Available</th>
                            <th>Reorder Level</th>
                            <th>Unit Cost</th>
                            <th>Stock Value</th>
                            <th>Status</th>
                            <th>Last Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stock_data as $item):
                            $is_low = $item['quantity_available'] <= $item['reorder_level'];
                            $is_zero = $item['quantity_available'] == 0;
                        ?>
                            <tr class="<?php echo $is_zero ? 'table-danger' : ($is_low ? 'table-warning' : ''); ?>">
                                <td><strong><?php echo htmlspecialchars($item['product_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($item['warehouse_name']); ?><br>
                                    <small class="text-muted">
                                        <?php echo ucwords(str_replace('_', ' ', $item['warehouse_type'])); ?>
                                    </small>
                                </td>
                                <td><?php echo number_format($item['quantity_on_hand'], 2); ?></td>
                                <td><?php echo number_format($item['quantity_reserved'], 2); ?></td>
                                <td>
                                    <strong class="<?php echo $is_zero ? 'text-danger' : ($is_low ? 'text-warning' : 'text-success'); ?>">
                                        <?php echo number_format($item['quantity_available'], 2); ?>
                                    </strong>
                                    <?php echo htmlspecialchars($item['unit_of_measure']); ?>
                                </td>
                                <td><?php echo number_format($item['reorder_level'], 2); ?></td>
                                <td>Rs. <?php echo number_format($item['standard_cost'], 2); ?></td>
                                <td>Rs. <?php echo number_format($item['stock_value'], 2); ?></td>
                                <td>
                                    <?php if ($is_zero): ?>
                                        <span class="badge bg-danger">Out of Stock</span>
                                    <?php elseif ($is_low): ?>
                                        <span class="badge bg-warning">Low Stock</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">In Stock</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d M Y', strtotime($item['last_stock_date'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Stock Modal -->
<div class="modal fade" id="addStockModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add Stock</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_stock">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Use this form to add new stock received from suppliers or production.
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Warehouse <span class="text-danger">*</span></label>
                            <select class="form-select" name="warehouse_id" id="warehouse_id" required>
                                <option value="">Select Warehouse</option>
                                <?php foreach ($warehouses as $wh): ?>
                                    <option value="<?php echo $wh['warehouse_id']; ?>">
                                        <?php echo htmlspecialchars($wh['warehouse_name']); ?>
                                        (<?php echo ucwords(str_replace('_', ' ', $wh['warehouse_type'])); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Product <span class="text-danger">*</span></label>
                            <select class="form-select" name="product_id" id="product_id" required onchange="updateProductInfo()">
                                <option value="">Select Product</option>
                                <?php foreach ($products as $prod): ?>
                                    <option value="<?php echo $prod['product_id']; ?>"
                                        data-code="<?php echo htmlspecialchars($prod['product_code']); ?>"
                                        data-category="<?php echo htmlspecialchars($prod['category_name']); ?>"
                                        data-unit="<?php echo htmlspecialchars($prod['unit_of_measure']); ?>"
                                        data-cost="<?php echo $prod['standard_cost']; ?>">
                                        <?php echo htmlspecialchars($prod['product_code']); ?> -
                                        <?php echo htmlspecialchars($prod['product_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Product Info Display -->
                    <div id="productInfo" class="alert alert-secondary d-none mb-3">
                        <h6>Product Information:</h6>
                        <div class="row">
                            <div class="col-md-4">
                                <strong>Code:</strong> <span id="info_code"></span>
                            </div>
                            <div class="col-md-4">
                                <strong>Category:</strong> <span id="info_category"></span>
                            </div>
                            <div class="col-md-4">
                                <strong>Unit:</strong> <span id="info_unit"></span>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Quantity <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="quantity" id="quantity"
                                step="0.001" min="0.001" required onchange="calculateValue()">
                            <small class="text-muted">Quantity received</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Unit Cost (Rs.) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="unit_cost" id="unit_cost"
                                step="0.01" min="0" required onchange="calculateValue()">
                            <small class="text-muted">Cost per unit</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Total Value (Rs.)</label>
                            <input type="text" class="form-control" id="total_value" readonly
                                style="background-color: #f8f9fa; font-weight: bold;">
                            <small class="text-muted">Auto-calculated</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="3"
                            placeholder="Enter any notes about this stock receipt (supplier, PO number, etc.)"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check me-1"></i>Add Stock
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        $('#stockTable').DataTable({
            order: [
                [10, 'asc'],
                [6, 'asc']
            ], // Sort by status then available quantity
            pageLength: 25
        });
    });

    function updateProductInfo() {
        var select = document.getElementById('product_id');
        var selectedOption = select.options[select.selectedIndex];

        if (select.value) {
            document.getElementById('info_code').textContent = selectedOption.getAttribute('data-code');
            document.getElementById('info_category').textContent = selectedOption.getAttribute('data-category');
            document.getElementById('info_unit').textContent = selectedOption.getAttribute('data-unit');
            document.getElementById('productInfo').classList.remove('d-none');

            // Set default unit cost
            var standardCost = selectedOption.getAttribute('data-cost');
            if (standardCost) {
                document.getElementById('unit_cost').value = standardCost;
                calculateValue();
            }
        } else {
            document.getElementById('productInfo').classList.add('d-none');
        }
    }

    function calculateValue() {
        var quantity = parseFloat(document.getElementById('quantity').value) || 0;
        var unitCost = parseFloat(document.getElementById('unit_cost').value) || 0;
        var totalValue = quantity * unitCost;

        document.getElementById('total_value').value = 'Rs. ' + totalValue.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
</script>

<?php include '../includes/footer.php'; ?>
