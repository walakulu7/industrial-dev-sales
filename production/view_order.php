<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../includes/functions.php';

// Check authentication
require_login();
require_permission('production', 'read');

$database = new Database();
$db = $database->getConnection();

$order_id = $_GET['id'] ?? 0;

if (!$order_id) {
    $_SESSION['error'] = 'Invalid order ID';
    header("Location: orders.php");
    exit();
}

// Get production order details
$query = "SELECT 
    po.*,
    p.product_code,
    p.product_name,
    p.unit_of_measure,
    p.specifications,
    pc.center_code,
    pc.center_name,
    pl.line_code,
    pl.line_name,
    b.branch_name,
    u.full_name as created_by_name
FROM production_orders po
JOIN products p ON po.product_id = p.product_id
JOIN production_centers pc ON po.production_center_id = pc.production_center_id
LEFT JOIN production_lines pl ON po.production_line_id = pl.line_id
JOIN branches b ON pc.branch_id = b.branch_id
JOIN users u ON po.created_by = u.user_id
WHERE po.order_id = :order_id";

$stmt = $db->prepare($query);
$stmt->execute([':order_id' => $order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    $_SESSION['error'] = 'Production order not found';
    header("Location: orders.php");
    exit();
}

// Get production inputs
$inputs_query = "SELECT 
    pi.*,
    p.product_code,
    p.product_name,
    p.unit_of_measure,
    w.warehouse_name
FROM production_inputs pi
JOIN products p ON pi.product_id = p.product_id
JOIN warehouses w ON pi.warehouse_id = w.warehouse_id
WHERE pi.order_id = :order_id";

$stmt = $db->prepare($inputs_query);
$stmt->execute([':order_id' => $order_id]);
$inputs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get production outputs
$outputs_query = "SELECT 
    po_out.*,
    w.warehouse_name,
    u.full_name as created_by_name
FROM production_outputs po_out
JOIN warehouses w ON po_out.warehouse_id = w.warehouse_id
JOIN users u ON po_out.created_by = u.user_id
WHERE po_out.order_id = :order_id
ORDER BY po_out.output_date DESC";

$stmt = $db->prepare($outputs_query);
$stmt->execute([':order_id' => $order_id]);
$outputs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$total_produced = array_sum(array_column($outputs, 'quantity_produced'));
$total_good = array_sum(array_column($outputs, 'quantity_good'));
$total_defective = array_sum(array_column($outputs, 'quantity_defective'));
$defect_rate = $total_produced > 0 ? ($total_defective / $total_produced) * 100 : 0;
$efficiency = $order['planned_quantity'] > 0 ? ($order['actual_quantity'] / $order['planned_quantity']) * 100 : 0;
$remaining = max(0, $order['planned_quantity'] - $order['actual_quantity']);

// Calculate duration
$duration_days = 0;
if ($order['start_date'] && $order['end_date']) {
    $duration_days = (strtotime($order['end_date']) - strtotime($order['start_date'])) / (60 * 60 * 24);
} elseif ($order['start_date']) {
    $duration_days = (time() - strtotime($order['start_date'])) / (60 * 60 * 24);
}

$page_title = 'Production Order Details';
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fas fa-clipboard-list me-2"></i>Production Order: <?php echo htmlspecialchars($order['order_number']); ?></h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="orders.php">Production Orders</a></li>
                    <li class="breadcrumb-item active"><?php echo htmlspecialchars($order['order_number']); ?></li>
                </ol>
            </nav>
        </div>
        <div class="col-md-4 text-end">
            <a href="outputs.php?order_id=<?php echo $order_id; ?>" class="btn btn-success">
                <i class="fas fa-plus me-1"></i>Record Output
            </a>
            <a href="orders.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i>Back
            </a>
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print"></i>
            </button>
        </div>
    </div>

    <div class="row">
        <!-- Order Information -->
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Order Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <th width="40%">Order Number:</th>
                                    <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                                </tr>
                                <tr>
                                    <th>Order Date:</th>
                                    <td><?php echo date('d M Y', strtotime($order['order_date'])); ?></td>
                                </tr>
                                <tr>
                                    <th>Status:</th>
                                    <td>
                                        <span class="badge bg-<?php
                                                                echo $order['status'] === 'completed' ? 'success' : ($order['status'] === 'in_progress' ? 'warning' : ($order['status'] === 'cancelled' ? 'danger' : 'secondary'));
                                                                ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Start Date:</th>
                                    <td><?php echo $order['start_date'] ? date('d M Y', strtotime($order['start_date'])) : 'Not started'; ?></td>
                                </tr>
                                <tr>
                                    <th>End Date:</th>
                                    <td><?php echo $order['end_date'] ? date('d M Y', strtotime($order['end_date'])) : 'In progress'; ?></td>
                                </tr>
                                <?php if ($duration_days > 0): ?>
                                    <tr>
                                        <th>Duration:</th>
                                        <td><?php echo number_format($duration_days, 0); ?> days</td>
                                    </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <th width="40%">Production Center:</th>
                                    <td><?php echo htmlspecialchars($order['center_name']); ?></td>
                                </tr>
                                <tr>
                                    <th>Production Line:</th>
                                    <td><?php echo htmlspecialchars($order['line_name'] ?? 'Not assigned'); ?></td>
                                </tr>
                                <tr>
                                    <th>Branch:</th>
                                    <td><?php echo htmlspecialchars($order['branch_name']); ?></td>
                                </tr>
                                <tr>
                                    <th>Created By:</th>
                                    <td><?php echo htmlspecialchars($order['created_by_name']); ?></td>
                                </tr>
                                <tr>
                                    <th>Created On:</th>
                                    <td><?php echo date('d M Y H:i', strtotime($order['created_at'])); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <hr>

                    <h6><i class="fas fa-box me-2"></i>Product Information</h6>
                    <table class="table table-sm">
                        <tr>
                            <th width="20%">Product Code:</th>
                            <td><strong><?php echo htmlspecialchars($order['product_code']); ?></strong></td>
                        </tr>
                        <tr>
                            <th>Product Name:</th>
                            <td><?php echo htmlspecialchars($order['product_name']); ?></td>
                        </tr>
                        <tr>
                            <th>Unit:</th>
                            <td><?php echo htmlspecialchars($order['unit_of_measure']); ?></td>
                        </tr>
                        <?php if ($order['specifications']): ?>
                            <tr>
                                <th>Specifications:</th>
                                <td>
                                    <?php
                                    $specs = json_decode($order['specifications'], true);
                                    if ($specs) {
                                        foreach ($specs as $key => $value) {
                                            echo "<span class='badge bg-secondary me-1'>" .
                                                htmlspecialchars(ucfirst($key)) . ": " .
                                                htmlspecialchars($value) . "</span>";
                                        }
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </table>

                    <?php if ($order['notes']): ?>
                        <hr>
                        <h6><i class="fas fa-comment me-2"></i>Notes</h6>
                        <div class="alert alert-info">
                            <?php echo nl2br(htmlspecialchars($order['notes'])); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Production Progress -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Production Progress</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <h6>Planned</h6>
                                    <h3><?php echo number_format($order['planned_quantity'], 2); ?></h3>
                                    <small><?php echo htmlspecialchars($order['unit_of_measure']); ?></small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h6>Actual</h6>
                                    <h3><?php echo number_format($order['actual_quantity'], 2); ?></h3>
                                    <small><?php echo number_format($efficiency, 1); ?>% complete</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-warning text-white">
                                <div class="card-body text-center">
                                    <h6>Remaining</h6>
                                    <h3><?php echo number_format($remaining, 2); ?></h3>
                                    <small><?php echo htmlspecialchars($order['unit_of_measure']); ?></small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="progress mb-3" style="height: 30px;">
                        <div class="progress-bar <?php echo $efficiency >= 100 ? 'bg-success' : 'bg-warning'; ?>"
                            role="progressbar"
                            style="width: <?php echo min($efficiency, 100); ?>%">
                            <?php echo number_format($efficiency, 1); ?>% Complete
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <strong>Total Produced:</strong> <?php echo number_format($total_produced, 2); ?> <?php echo htmlspecialchars($order['unit_of_measure']); ?>
                        </div>
                        <div class="col-md-6 text-end">
                            <strong>Defect Rate:</strong>
                            <span class="badge bg-<?php echo $defect_rate > 5 ? 'danger' : ($defect_rate > 2 ? 'warning' : 'success'); ?>">
                                <?php echo number_format($defect_rate, 2); ?>%
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Production Inputs -->
            <?php if (count($inputs) > 0): ?>
                <div class="card mb-4">
                    <div class="card-header bg-warning">
                        <h5 class="mb-0"><i class="fas fa-box-open me-2"></i>Production Inputs (Raw Materials)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Warehouse</th>
                                        <th>Required</th>
                                        <th>Issued</th>
                                        <th>Remaining</th>
                                        <th>Unit Cost</th>
                                        <th>Total Cost</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $total_cost = 0;
                                    foreach ($inputs as $input):
                                        $input_remaining = $input['quantity_required'] - $input['quantity_issued'];
                                        $line_cost = $input['quantity_issued'] * ($input['unit_cost'] ?? 0);
                                        $total_cost += $line_cost;
                                    ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($input['product_code']); ?></strong><br>
                                                <small><?php echo htmlspecialchars($input['product_name']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($input['warehouse_name']); ?></td>
                                            <td><?php echo number_format($input['quantity_required'], 2); ?> <?php echo htmlspecialchars($input['unit_of_measure']); ?></td>
                                            <td><?php echo number_format($input['quantity_issued'], 2); ?> <?php echo htmlspecialchars($input['unit_of_measure']); ?></td>
                                            <td>
                                                <span class="<?php echo $input_remaining > 0 ? 'text-warning' : 'text-success'; ?>">
                                                    <?php echo number_format($input_remaining, 2); ?> <?php echo htmlspecialchars($input['unit_of_measure']); ?>
                                                </span>
                                            </td>
                                            <td>Rs. <?php echo number_format($input['unit_cost'] ?? 0, 2); ?></td>
                                            <td>Rs. <?php echo number_format($line_cost, 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-dark">
                                        <th colspan="6" class="text-end">Total Input Cost:</th>
                                        <th>Rs. <?php echo number_format($total_cost, 2); ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Production Outputs -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-clipboard-check me-2"></i>Production Outputs</h5>
                </div>
                <div class="card-body">
                    <?php if (count($outputs) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
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
                                        $output_defect_rate = $output['quantity_produced'] > 0
                                            ? ($output['quantity_defective'] / $output['quantity_produced']) * 100
                                            : 0;
                                    ?>
                                        <tr>
                                            <td><?php echo date('d M Y', strtotime($output['output_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($output['warehouse_name']); ?></td>
                                            <td><?php echo number_format($output['quantity_produced'], 2); ?></td>
                                            <td><span class="badge bg-success"><?php echo number_format($output['quantity_good'], 2); ?></span></td>
                                            <td><span class="badge bg-danger"><?php echo number_format($output['quantity_defective'], 2); ?></span></td>
                                            <td>
                                                <span class="badge bg-<?php echo $output_defect_rate > 5 ? 'danger' : ($output_defect_rate > 2 ? 'warning' : 'success'); ?>">
                                                    <?php echo number_format($output_defect_rate, 2); ?>%
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($output['shift'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($output['created_by_name']); ?></td>
                                            <td>
                                                <a href="view_output.php?id=<?php echo $output['output_id']; ?>"
                                                    class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-dark">
                                    <tr>
                                        <th colspan="2">Total:</th>
                                        <th><?php echo number_format($total_produced, 2); ?></th>
                                        <th><?php echo number_format($total_good, 2); ?></th>
                                        <th><?php echo number_format($total_defective, 2); ?></th>
                                        <th>
                                            <span class="badge bg-<?php echo $defect_rate > 5 ? 'danger' : ($defect_rate > 2 ? 'warning' : 'success'); ?>">
                                                <?php echo number_format($defect_rate, 2); ?>%
                                            </span>
                                        </th>
                                        <th colspan="3"></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            No production outputs recorded yet.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-md-4">
            <!-- Quick Stats -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Quick Statistics</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr>
                            <td>Completion:</td>
                            <td class="text-end">
                                <strong><?php echo number_format($efficiency, 1); ?>%</strong>
                            </td>
                        </tr>
                        <tr>
                            <td>Output Sessions:</td>
                            <td class="text-end"><strong><?php echo count($outputs); ?></strong></td>
                        </tr>
                        <tr>
                            <td>Avg per Session:</td>
                            <td class="text-end">
                                <strong>
                                    <?php echo count($outputs) > 0 ? number_format($total_good / count($outputs), 2) : '0'; ?>
                                </strong>
                            </td>
                        </tr>
                        <tr>
                            <td>Defect Rate:</td>
                            <td class="text-end">
                                <span class="badge bg-<?php echo $defect_rate > 5 ? 'danger' : ($defect_rate > 2 ? 'warning' : 'success'); ?>">
                                    <?php echo number_format($defect_rate, 2); ?>%
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td>Quality Rating:</td>
                            <td class="text-end">
                                <strong>
                                    <?php
                                    if ($defect_rate <= 2) echo '<span class="text-success">Excellent</span>';
                                    elseif ($defect_rate <= 5) echo '<span class="text-warning">Good</span>';
                                    else echo '<span class="text-danger">Needs Improvement</span>';
                                    ?>
                                </strong>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Actions -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-cog me-2"></i>Actions</h6>
                </div>
                <div class="card-body">
                    <a href="outputs.php?order_id=<?php echo $order_id; ?>" class="btn btn-success w-100 mb-2">
                        <i class="fas fa-plus me-1"></i>Record Output
                    </a>
                    <a href="orders.php" class="btn btn-secondary w-100 mb-2">
                        <i class="fas fa-list me-1"></i>All Orders
                    </a>
                    <button onclick="window.print()" class="btn btn-outline-primary w-100">
                        <i class="fas fa-print me-1"></i>Print Order
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    @media print {

        .btn,
        .breadcrumb,
        nav,
        .no-print {
            display: none !important;
        }

        .card {
            border: 1px solid #ddd !important;
            page-break-inside: avoid;
        }
    }
</style>

<?php include '../includes/footer.php'; ?>
