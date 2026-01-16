<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../includes/functions.php';

/**
 * Check authentication - wrapper for require_login()
 * Alias for require_login() for backwards compatibility
 */
function checkAuth()
{
    return require_login();
}

/**
 * Check user permission - wrapper for has_permission()
 * @param string $permission Permission name (can be single or simplified)
 * @return bool
 */
function hasPermission($permission)
{
    // Check if permission exists in user's permissions
    if (!isset($_SESSION['permissions'])) {
        return false;
    }

    $permissions = json_decode($_SESSION['permissions'], true);

    // Administrator has all permissions
    if (isset($permissions['modules']) && in_array('all', $permissions['modules'])) {
        return true;
    }

    // Check in modules array
    if (isset($permissions['modules']) && in_array($permission, $permissions['modules'])) {
        return true;
    }

    // Check in permissions array
    if (isset($permissions['permissions']) && in_array($permission, $permissions['permissions'])) {
        return true;
    }

    return false;
}

/**
 * Set flash message - wrapper for session-based messaging
 * @param string $message Message text
 * @param string $type Message type (success, error, info, warning)
 */
function setFlashMessage($message, $type = 'success')
{
    $_SESSION[$type] = $message;
}

/**
 * Display flash message - wrapper for display_flash_message()
 * @param string $type Message type
 * @return string HTML
 */
function displayFlashMessage($type = 'success')
{
    return display_flash_message($type);
}

/**
 * Redirect to URL
 * @param string $url URL to redirect to
 */
function redirect($url)
{
    // If URL doesn't start with http and isn't a full path, make it relative
    if (!filter_var($url, FILTER_VALIDATE_URL) && strpos($url, '/') !== 0) {
        $url = dirname($_SERVER['PHP_SELF']) . '/' . $url;
    }
    header("Location: $url");
    exit();
}

// Check authentication
checkAuth();

// Check permission
if (!hasPermission('production') && !hasPermission('all')) {
    redirect('dashboard.php');
}

$database = new Database();
$db = $database->getConnection();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        try {
            $db->beginTransaction();

            $order_id = $_POST['order_id'];
            $output_date = $_POST['output_date'];
            $quantity_produced = $_POST['quantity_produced'];
            $quantity_good = $_POST['quantity_good'];
            $quantity_defective = $quantity_produced - $quantity_good;
            $warehouse_id = $_POST['warehouse_id'];
            $shift = $_POST['shift'] ?? null;
            $notes = $_POST['notes'] ?? null;

            // Validate quantities
            if ($quantity_good > $quantity_produced) {
                throw new Exception('Good quantity cannot exceed produced quantity');
            }

            // Get production order details
            $order_query = "SELECT po.*, p.product_code, p.product_name, p.unit_of_measure
                           FROM production_orders po
                           JOIN products p ON po.product_id = p.product_id
                           WHERE po.order_id = :order_id";
            $order_stmt = $db->prepare($order_query);
            $order_stmt->execute([':order_id' => $order_id]);
            $order = $order_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                throw new Exception('Production order not found');
            }

            // Check if already completed
            if ($order['status'] === 'completed') {
                throw new Exception('Production order is already completed');
            }

            // Insert production output
            $insert_output = "INSERT INTO production_outputs (
                order_id, output_date, quantity_produced, quantity_good,
                quantity_defective, warehouse_id, shift, notes, created_by
            ) VALUES (
                :order_id, :output_date, :quantity_produced, :quantity_good,
                :quantity_defective, :warehouse_id, :shift, :notes, :created_by
            )";

            $stmt = $db->prepare($insert_output);
            $stmt->execute([
                ':order_id' => $order_id,
                ':output_date' => $output_date,
                ':quantity_produced' => $quantity_produced,
                ':quantity_good' => $quantity_good,
                ':quantity_defective' => $quantity_defective,
                ':warehouse_id' => $warehouse_id,
                ':shift' => $shift,
                ':notes' => $notes,
                ':created_by' => $_SESSION['user_id']
            ]);

            // Update production order actual quantity
            $update_order = "UPDATE production_orders 
                           SET actual_quantity = actual_quantity + :quantity_good,
                               status = CASE 
                                   WHEN (actual_quantity + :quantity_good2) >= planned_quantity THEN 'completed'
                                   WHEN actual_quantity > 0 THEN 'in_progress'
                                   ELSE status
                               END
                           WHERE order_id = :order_id";

            $stmt = $db->prepare($update_order);
            $stmt->execute([
                ':quantity_good' => $quantity_good,
                ':quantity_good2' => $quantity_good,
                ':order_id' => $order_id
            ]);

            // Update inventory - add good quantity to warehouse
            $inventory_check = "SELECT inventory_id FROM inventory 
                               WHERE warehouse_id = :warehouse_id AND product_id = :product_id";
            $check_stmt = $db->prepare($inventory_check);
            $check_stmt->execute([
                ':warehouse_id' => $warehouse_id,
                ':product_id' => $order['product_id']
            ]);

            if ($check_stmt->fetch()) {
                // Update existing inventory
                $update_inventory = "UPDATE inventory 
                                   SET quantity_on_hand = quantity_on_hand + :quantity,
                                       last_stock_date = :date
                                   WHERE warehouse_id = :warehouse_id 
                                   AND product_id = :product_id";
            } else {
                // Insert new inventory record
                $update_inventory = "INSERT INTO inventory (
                    warehouse_id, product_id, quantity_on_hand, 
                    quantity_reserved, last_stock_date
                ) VALUES (
                    :warehouse_id, :product_id, :quantity, 0, :date
                )";
            }

            $stmt = $db->prepare($update_inventory);
            $stmt->execute([
                ':warehouse_id' => $warehouse_id,
                ':product_id' => $order['product_id'],
                ':quantity' => $quantity_good,
                ':date' => $output_date
            ]);

            // Record stock transaction
            $insert_transaction = "INSERT INTO stock_transactions (
                transaction_date, transaction_time, warehouse_id, product_id,
                transaction_type, quantity, reference_type, reference_id, 
                notes, created_by
            ) VALUES (
                :date, :time, :warehouse_id, :product_id,
                'production_output', :quantity, 'production_order', :order_id,
                :notes, :created_by
            )";

            $stmt = $db->prepare($insert_transaction);
            $stmt->execute([
                ':date' => $output_date,
                ':time' => date('H:i:s'),
                ':warehouse_id' => $warehouse_id,
                ':product_id' => $order['product_id'],
                ':quantity' => $quantity_good,
                ':order_id' => $order_id,
                ':notes' => "Production output - Good: $quantity_good, Defective: $quantity_defective",
                ':created_by' => $_SESSION['user_id']
            ]);

            $db->commit();

            setFlashMessage('Production output recorded successfully', 'success');
            redirect('outputs.php');
        } catch (Exception $e) {
            $db->rollBack();
            $error = $e->getMessage();
        }
    }
}

// Get filter parameters
$filter_order = $_GET['order_id'] ?? '';
$filter_date_from = $_GET['date_from'] ?? date('Y-m-01');
$filter_date_to = $_GET['date_to'] ?? date('Y-m-d');
$filter_center = $_GET['center_id'] ?? '';

// Build query
$query = "SELECT 
    po_out.*,
    po.order_number,
    po.planned_quantity,
    po.actual_quantity as order_actual_qty,
    po.status as order_status,
    p.product_code,
    p.product_name,
    p.unit_of_measure,
    pc.center_name,
    w.warehouse_name,
    u.full_name as created_by_name
FROM production_outputs po_out
JOIN production_orders po ON po_out.order_id = po.order_id
JOIN products p ON po.product_id = p.product_id
JOIN production_centers pc ON po.production_center_id = pc.production_center_id
JOIN warehouses w ON po_out.warehouse_id = w.warehouse_id
JOIN users u ON po_out.created_by = u.user_id
WHERE 1=1";

$params = [];

if ($filter_order) {
    $query .= " AND po_out.order_id = :order_id";
    $params[':order_id'] = $filter_order;
}

if ($filter_date_from) {
    $query .= " AND po_out.output_date >= :date_from";
    $params[':date_from'] = $filter_date_from;
}

if ($filter_date_to) {
    $query .= " AND po_out.output_date <= :date_to";
    $params[':date_to'] = $filter_date_to;
}

if ($filter_center) {
    $query .= " AND po.production_center_id = :center_id";
    $params[':center_id'] = $filter_center;
}

$query .= " ORDER BY po_out.output_date DESC, po_out.output_id DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$outputs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get active production orders for dropdown
$orders_query = "SELECT 
    po.order_id,
    po.order_number,
    po.planned_quantity,
    po.actual_quantity,
    po.status,
    p.product_code,
    p.product_name,
    p.unit_of_measure,
    pc.center_name
FROM production_orders po
JOIN products p ON po.product_id = p.product_id
JOIN production_centers pc ON po.production_center_id = pc.production_center_id
WHERE po.status IN ('pending', 'in_progress')
ORDER BY po.order_date DESC, po.order_number DESC";

$orders_stmt = $db->prepare($orders_query);
$orders_stmt->execute();
$active_orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get production centers for filter
$centers_query = "SELECT * FROM production_centers WHERE status = 'active' ORDER BY center_name";
$centers_stmt = $db->prepare($centers_query);
$centers_stmt->execute();
$centers = $centers_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get warehouses for dropdown
$warehouses_query = "SELECT * FROM warehouses WHERE status = 'active' ORDER BY warehouse_name";
$warehouses_stmt = $db->prepare($warehouses_query);
$warehouses_stmt->execute();
$warehouses = $warehouses_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate summary statistics
$total_outputs = count($outputs);
$total_produced = array_sum(array_column($outputs, 'quantity_produced'));
$total_good = array_sum(array_column($outputs, 'quantity_good'));
$total_defective = array_sum(array_column($outputs, 'quantity_defective'));
$defect_rate = $total_produced > 0 ? ($total_defective / $total_produced) * 100 : 0;

$page_title = 'Production Outputs';
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2><i class="fas fa-box-open me-2"></i>Production Outputs</h2>
        </div>
        <div class="col-md-6 text-end">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#outputModal">
                <i class="fas fa-plus me-1"></i>Record Output
            </button>
            <a href="orders.php" class="btn btn-secondary">
                <i class="fas fa-list me-1"></i>View Orders
            </a>
        </div>
    </div>

    <?php displayFlashMessage(); ?>

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
                    <h6 class="card-title">Total Outputs</h6>
                    <h3><?php echo number_format($total_outputs); ?></h3>
                    <small>Records in period</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Produced</h6>
                    <h3><?php echo number_format($total_produced, 2); ?></h3>
                    <small>All units</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title">Good Quantity</h6>
                    <h3><?php echo number_format($total_good, 2); ?></h3>
                    <small><?php echo number_format(($total_produced > 0 ? ($total_good / $total_produced) * 100 : 0), 1); ?>% of total</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-<?php echo $defect_rate > 5 ? 'danger' : 'warning'; ?> text-white">
                <div class="card-body">
                    <h6 class="card-title">Defect Rate</h6>
                    <h3><?php echo number_format($defect_rate, 2); ?>%</h3>
                    <small><?php echo number_format($total_defective, 2); ?> defective units</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Production Order</label>
                    <select class="form-select" name="order_id">
                        <option value="">All Orders</option>
                        <?php foreach ($active_orders as $order): ?>
                            <option value="<?php echo $order['order_id']; ?>"
                                <?php echo $filter_order == $order['order_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($order['order_number']); ?> -
                                <?php echo htmlspecialchars($order['product_code']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Date From</label>
                    <input type="date" class="form-control" name="date_from"
                        value="<?php echo htmlspecialchars($filter_date_from); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Date To</label>
                    <input type="date" class="form-control" name="date_to"
                        value="<?php echo htmlspecialchars($filter_date_to); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Production Center</label>
                    <select class="form-select" name="center_id">
                        <option value="">All Centers</option>
                        <?php foreach ($centers as $center): ?>
                            <option value="<?php echo $center['production_center_id']; ?>"
                                <?php echo $filter_center == $center['production_center_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($center['center_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-1"></i>Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Outputs Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Production Output Records</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="outputsTable">
                    <thead>
                        <tr>
                            <th>Output Date</th>
                            <th>Order Number</th>
                            <th>Product</th>
                            <th>Production Center</th>
                            <th>Warehouse</th>
                            <th>Produced</th>
                            <th>Good</th>
                            <th>Defective</th>
                            <th>Defect %</th>
                            <th>Shift</th>
                            <th>Recorded By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($outputs as $output):
                            $defect_pct = $output['quantity_produced'] > 0
                                ? ($output['quantity_defective'] / $output['quantity_produced']) * 100
                                : 0;
                        ?>
                            <tr>
                                <td><?php echo date('d M Y', strtotime($output['output_date'])); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($output['order_number']); ?></strong>
                                    <?php if ($output['order_status'] === 'completed'): ?>
                                        <span class="badge bg-success ms-1">Completed</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($output['product_code']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($output['product_name']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($output['center_name']); ?></td>
                                <td><?php echo htmlspecialchars($output['warehouse_name']); ?></td>
                                <td><strong><?php echo number_format($output['quantity_produced'], 2); ?></strong> <?php echo htmlspecialchars($output['unit_of_measure']); ?></td>
                                <td><span class="badge bg-success"><?php echo number_format($output['quantity_good'], 2); ?></span></td>
                                <td><span class="badge bg-danger"><?php echo number_format($output['quantity_defective'], 2); ?></span></td>
                                <td>
                                    <span class="badge bg-<?php echo $defect_pct > 5 ? 'danger' : ($defect_pct > 2 ? 'warning' : 'success'); ?>">
                                        <?php echo number_format($defect_pct, 2); ?>%
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($output['shift'] ?? '-'); ?></td>
                                <td>
                                    <small><?php echo htmlspecialchars($output['created_by_name']); ?></small><br>
                                    <small class="text-muted"><?php echo date('d M Y H:i', strtotime($output['created_at'])); ?></small>
                                </td>
                                <td>
                                    <a href="view_output.php?id=<?php echo $output['output_id']; ?>"
                                        class="btn btn-sm btn-info" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($output['notes']): ?>
                                        <button class="btn btn-sm btn-secondary"
                                            title="<?php echo htmlspecialchars($output['notes']); ?>"
                                            data-bs-toggle="tooltip">
                                            <i class="fas fa-comment"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Record Output Modal -->
<div class="modal fade" id="outputModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Record Production Output</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="outputForm">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Production Order <span class="text-danger">*</span></label>
                            <select class="form-select" name="order_id" id="order_id" required onchange="updateOrderDetails()">
                                <option value="">Select Order</option>
                                <?php foreach ($active_orders as $order): ?>
                                    <option value="<?php echo $order['order_id']; ?>"
                                        data-product="<?php echo htmlspecialchars($order['product_code'] . ' - ' . $order['product_name']); ?>"
                                        data-unit="<?php echo htmlspecialchars($order['unit_of_measure']); ?>"
                                        data-planned="<?php echo $order['planned_quantity']; ?>"
                                        data-actual="<?php echo $order['actual_quantity']; ?>"
                                        data-center="<?php echo htmlspecialchars($order['center_name']); ?>">
                                        <?php echo htmlspecialchars($order['order_number']); ?> -
                                        <?php echo htmlspecialchars($order['product_code']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Output Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="output_date"
                                value="<?php echo date('Y-m-d'); ?>" required max="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <!-- Order Details -->
                    <div id="orderDetails" class="alert alert-info d-none mb-3">
                        <h6>Order Details:</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Product:</strong> <span id="detail_product"></span><br>
                                <strong>Production Center:</strong> <span id="detail_center"></span>
                            </div>
                            <div class="col-md-6">
                                <strong>Planned Qty:</strong> <span id="detail_planned"></span> <span id="detail_unit"></span><br>
                                <strong>Completed Qty:</strong> <span id="detail_actual"></span> <span id="detail_unit2"></span><br>
                                <strong>Remaining:</strong> <span id="detail_remaining" class="text-danger"></span> <span id="detail_unit3"></span>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Quantity Produced <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="quantity_produced"
                                id="quantity_produced" step="0.001" min="0" required
                                onchange="calculateDefective()">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Good Quantity <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="quantity_good"
                                id="quantity_good" step="0.001" min="0" required
                                onchange="calculateDefective()">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Defective Quantity</label>
                            <input type="number" class="form-control" id="quantity_defective"
                                step="0.001" readonly style="background-color: #f8f9fa;">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Warehouse <span class="text-danger">*</span></label>
                            <select class="form-select" name="warehouse_id" required>
                                <option value="">Select Warehouse</option>
                                <?php foreach ($warehouses as $warehouse): ?>
                                    <option value="<?php echo $warehouse['warehouse_id']; ?>">
                                        <?php echo htmlspecialchars($warehouse['warehouse_name']); ?>
                                        (<?php echo ucwords(str_replace('_', ' ', $warehouse['warehouse_type'])); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Shift</label>
                            <select class="form-select" name="shift">
                                <option value="">Select Shift</option>
                                <option value="Morning">Morning</option>
                                <option value="Afternoon">Afternoon</option>
                                <option value="Night">Night</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="3"
                            placeholder="Any additional notes about this production output..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Record Output
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        $('#outputsTable').DataTable({
            order: [
                [0, 'desc']
            ],
            pageLength: 25
        });

        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });

    function updateOrderDetails() {
        var select = document.getElementById('order_id');
        var selectedOption = select.options[select.selectedIndex];

        if (select.value) {
            var product = selectedOption.getAttribute('data-product');
            var unit = selectedOption.getAttribute('data-unit');
            var planned = parseFloat(selectedOption.getAttribute('data-planned'));
            var actual = parseFloat(selectedOption.getAttribute('data-actual'));
            var center = selectedOption.getAttribute('data-center');
            var remaining = planned - actual;

            document.getElementById('detail_product').textContent = product;
            document.getElementById('detail_center').textContent = center;
            document.getElementById('detail_planned').textContent = planned.toFixed(2);
            document.getElementById('detail_actual').textContent = actual.toFixed(2);
            document.getElementById('detail_remaining').textContent = remaining.toFixed(2);
            document.getElementById('detail_unit').textContent = unit;
            document.getElementById('detail_unit2').textContent = unit;
            document.getElementById('detail_unit3').textContent = unit;

            document.getElementById('orderDetails').classList.remove('d-none');
        } else {
            document.getElementById('orderDetails').classList.add('d-none');
        }
    }

    function calculateDefective() {
        var produced = parseFloat(document.getElementById('quantity_produced').value) || 0;
        var good = parseFloat(document.getElementById('quantity_good').value) || 0;
        var defective = produced - good;

        if (defective < 0) {
            defective = 0;
            document.getElementById('quantity_good').value = produced;
        }

        document.getElementById('quantity_defective').value = defective.toFixed(3);
    }
</script>

<?php include '../includes/footer.php'; ?>