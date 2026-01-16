<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();
require_permission('production', 'create');

$database = new Database();
$db = $database->getConnection();

$page_title = 'Record Production Output';

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

// Get order details
$order = null;
if ($order_id) {
    $query = "SELECT po.*, pc.center_name, p.product_code, p.product_name, p.unit_of_measure
              FROM production_orders po
              INNER JOIN production_centers pc ON po.production_center_id = pc.production_center_id
              INNER JOIN products p ON po.product_id = p.product_id
              WHERE po.order_id = :order_id";

    $stmt = $db->prepare($query);
    $stmt->execute([':order_id' => $order_id]);
    $order = $stmt->fetch();
}

// Get warehouses
$warehouses = $db->query("SELECT * FROM warehouses WHERE status = 'active' AND warehouse_type = 'finished_product' ORDER BY warehouse_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_once '../classes/Production.php';
        $production = new Production($db);

        $result = $production->recordOutput(
            (int)$_POST['order_id'],
            (float)$_POST['quantity_produced'],
            (float)$_POST['quantity_good'],
            (int)$_POST['warehouse_id'],
            sanitize_input($_POST['notes'] ?? '')
        );

        if ($result['success']) {
            $_SESSION['success'] = 'Production output recorded successfully';
            header("Location: orders.php");
            exit();
        } else {
            $_SESSION['error'] = $result['message'];
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-box-open"></i> Record Production Output</h2>
        <a href="orders.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Orders
        </a>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo $_SESSION['error'];
            unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($order): ?>
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <!-- Order Info -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Production Order Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Order Number:</strong> <?php echo htmlspecialchars($order['order_number']); ?></p>
                                <p><strong>Product:</strong> <?php echo htmlspecialchars($order['product_name']); ?></p>
                                <p><strong>Center:</strong> <?php echo htmlspecialchars($order['center_name']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Planned Quantity:</strong> <?php echo number_format($order['planned_quantity'], 2); ?> <?php echo $order['unit_of_measure']; ?></p>
                                <p><strong>Completed:</strong> <?php echo number_format($order['actual_quantity'], 2); ?> <?php echo $order['unit_of_measure']; ?></p>
                                <p><strong>Remaining:</strong> <?php echo number_format($order['planned_quantity'] - $order['actual_quantity'], 2); ?> <?php echo $order['unit_of_measure']; ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Output Form -->
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Record Output</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Quantity Produced <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" name="quantity_produced"
                                            id="quantityProduced" step="0.001" min="0.001" required>
                                        <span class="input-group-text"><?php echo $order['unit_of_measure']; ?></span>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Good Quality Quantity <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" name="quantity_good"
                                            id="quantityGood" step="0.001" min="0" required>
                                        <span class="input-group-text"><?php echo $order['unit_of_measure']; ?></span>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Defective Quantity</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="quantityDefective" readonly>
                                        <span class="input-group-text"><?php echo $order['unit_of_measure']; ?></span>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Warehouse <span class="text-danger">*</span></label>
                                    <select class="form-select" name="warehouse_id" required>
                                        <option value="">Select Warehouse</option>
                                        <?php foreach ($warehouses as $warehouse): ?>
                                            <option value="<?php echo $warehouse['warehouse_id']; ?>">
                                                <?php echo htmlspecialchars($warehouse['warehouse_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Notes</label>
                                    <textarea class="form-control" name="notes" rows="3"></textarea>
                                </div>

                                <div class="col-12">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-check"></i> Record Output
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-warning">
            Production order not found or invalid.
        </div>
    <?php endif; ?>
</div>

<script>
    document.getElementById('quantityProduced').addEventListener('input', calculateDefective);
    document.getElementById('quantityGood').addEventListener('input', calculateDefective);

    function calculateDefective() {
        const produced = parseFloat(document.getElementById('quantityProduced').value) || 0;
        const good = parseFloat(document.getElementById('quantityGood').value) || 0;
        const defective = produced - good;
        document.getElementById('quantityDefective').value = defective >= 0 ? defective.toFixed(3) : '0.000';
    }
</script>

<?php include '../includes/footer.php'; ?>