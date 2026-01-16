<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();
require_permission('inventory', 'create');

$database = new Database();
$db = $database->getConnection();

$page_title = 'Stock Transfer';

// Get warehouses
$warehouses = $db->query("SELECT * FROM warehouses WHERE status = 'active' ORDER BY warehouse_name")->fetchAll();

// Get products with stock
$products = $db->query("SELECT p.*, pc.category_name 
                        FROM products p
                        INNER JOIN product_categories pc ON p.category_id = pc.category_id
                        WHERE p.status = 'active'
                        ORDER BY p.product_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        $from_warehouse_id = (int)$_POST['from_warehouse_id'];
        $to_warehouse_id = (int)$_POST['to_warehouse_id'];
        $product_id = (int)$_POST['product_id'];
        $quantity = (float)$_POST['quantity'];
        $notes = sanitize_input($_POST['notes'] ?? '');

        // Validate
        if ($from_warehouse_id === $to_warehouse_id) {
            throw new Exception('Source and destination warehouses cannot be the same');
        }

        // Check stock availability
        $check_stock = $db->prepare("SELECT quantity_available FROM inventory 
                                     WHERE warehouse_id = :warehouse_id AND product_id = :product_id");
        $check_stock->execute([':warehouse_id' => $from_warehouse_id, ':product_id' => $product_id]);
        $stock = $check_stock->fetch();

        if (!$stock || $stock['quantity_available'] < $quantity) {
            throw new Exception('Insufficient stock in source warehouse');
        }

        // Transfer out from source warehouse
        $update_from = $db->prepare("UPDATE inventory 
                                     SET quantity_on_hand = quantity_on_hand - :quantity,
                                         last_stock_date = CURDATE()
                                     WHERE warehouse_id = :warehouse_id AND product_id = :product_id");
        $update_from->execute([':quantity' => $quantity, ':warehouse_id' => $from_warehouse_id, ':product_id' => $product_id]);

        // Transfer in to destination warehouse
        $update_to = $db->prepare("INSERT INTO inventory (warehouse_id, product_id, quantity_on_hand, last_stock_date)
                                   VALUES (:warehouse_id, :product_id, :quantity, CURDATE())
                                   ON DUPLICATE KEY UPDATE 
                                   quantity_on_hand = quantity_on_hand + :quantity,
                                   last_stock_date = CURDATE()");
        $update_to->execute([':warehouse_id' => $to_warehouse_id, ':product_id' => $product_id, ':quantity' => $quantity]);

        // Record transfer out transaction
        $trans_out = $db->prepare("INSERT INTO stock_transactions 
                                   (transaction_date, transaction_time, warehouse_id, product_id, transaction_type,
                                    quantity, from_warehouse_id, to_warehouse_id, notes, created_by)
                                   VALUES 
                                   (CURDATE(), CURTIME(), :warehouse_id, :product_id, 'transfer_out',
                                    :quantity, :from_warehouse_id, :to_warehouse_id, :notes, :created_by)");
        $trans_out->execute([
            ':warehouse_id' => $from_warehouse_id,
            ':product_id' => $product_id,
            ':quantity' => $quantity,
            ':from_warehouse_id' => $from_warehouse_id,
            ':to_warehouse_id' => $to_warehouse_id,
            ':notes' => $notes,
            ':created_by' => $_SESSION['user_id']
        ]);

        // Record transfer in transaction
        $trans_in = $db->prepare("INSERT INTO stock_transactions 
                                  (transaction_date, transaction_time, warehouse_id, product_id, transaction_type,
                                   quantity, from_warehouse_id, to_warehouse_id, notes, created_by)
                                  VALUES 
                                  (CURDATE(), CURTIME(), :warehouse_id, :product_id, 'transfer_in',
                                   :quantity, :from_warehouse_id, :to_warehouse_id, :notes, :created_by)");
        $trans_in->execute([
            ':warehouse_id' => $to_warehouse_id,
            ':product_id' => $product_id,
            ':quantity' => $quantity,
            ':from_warehouse_id' => $from_warehouse_id,
            ':to_warehouse_id' => $to_warehouse_id,
            ':notes' => $notes,
            ':created_by' => $_SESSION['user_id']
        ]);

        $db->commit();

        $_SESSION['success'] = 'Stock transfer completed successfully';
        header("Location: transfer.php");
        exit();
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $_SESSION['error'] = $e->getMessage();
    }
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-truck"></i> Stock Transfer</h2>
        <a href="stock_levels.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Stock Levels
        </a>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['error'];
            unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['success'];
            unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-exchange-alt"></i> Transfer Stock Between Warehouses</h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="transferForm">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">From Warehouse <span class="text-danger">*</span></label>
                                <select class="form-select" name="from_warehouse_id" id="fromWarehouse" required>
                                    <option value="">Select Source Warehouse</option>
                                    <?php foreach ($warehouses as $warehouse): ?>
                                        <option value="<?php echo $warehouse['warehouse_id']; ?>">
                                            <?php echo htmlspecialchars($warehouse['warehouse_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">To Warehouse <span class="text-danger">*</span></label>
                                <select class="form-select" name="to_warehouse_id" id="toWarehouse" required>
                                    <option value="">Select Destination Warehouse</option>
                                    <?php foreach ($warehouses as $warehouse): ?>
                                        <option value="<?php echo $warehouse['warehouse_id']; ?>">
                                            <?php echo htmlspecialchars($warehouse['warehouse_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-8">
                                <label class="form-label">Product <span class="text-danger">*</span></label>
                                <select class="form-select" name="product_id" id="productSelect" required>
                                    <option value="">Select Product</option>
                                    <?php foreach ($products as $product): ?>
                                        <option value="<?php echo $product['product_id']; ?>"
                                            data-uom="<?php echo $product['unit_of_measure']; ?>">
                                            <?php echo htmlspecialchars($product['product_code'] . ' - ' . $product['product_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Quantity <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" class="form-control" name="quantity" id="quantity"
                                        step="0.001" min="0.001" required>
                                    <span class="input-group-text" id="uomDisplay">-</span>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="alert alert-info" id="stockInfo" style="display: none;">
                                    <strong>Available Stock:</strong> <span id="availableQty">-</span>
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Notes</label>
                                <textarea class="form-control" name="notes" rows="3"></textarea>
                            </div>

                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-check"></i> Process Transfer
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('productSelect').addEventListener('change', function() {
        const selectedOption = this.selectedOptions[0];
        const uom = selectedOption.dataset.uom || '-';
        document.getElementById('uomDisplay').textContent = uom;
        checkStock();
    });

    document.getElementById('fromWarehouse').addEventListener('change', checkStock);

    function checkStock() {
        const warehouseId = document.getElementById('fromWarehouse').value;
        const productId = document.getElementById('productSelect').value;

        if (warehouseId && productId) {
            fetch(`get_stock.php?warehouse_id=${warehouseId}&product_id=${productId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('availableQty').textContent =
                            data.quantity + ' ' + (document.getElementById('productSelect').selectedOptions[0].dataset.uom || '');
                        document.getElementById('stockInfo').style.display = 'block';
                        document.getElementById('quantity').max = data.quantity;
                    }
                });
        }
    }
</script>

<?php include '../includes/footer.php'; ?>