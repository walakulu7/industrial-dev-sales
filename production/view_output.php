<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../includes/functions.php';

// Check authentication
require_login();
require_permission('production', 'read');

$database = new Database();
$db = $database->getConnection();

$output_id = $_GET['id'] ?? 0;

if (!$output_id) {
    $_SESSION['error'] = 'Invalid output ID';
    header("Location: outputs.php");
    exit();
}

// Get output details
$query = "SELECT 
    po_out.*,
    po.order_id,
    po.order_number,
    po.order_date,
    po.planned_quantity,
    po.actual_quantity as order_actual_qty,
    po.start_date,
    po.end_date,
    po.status as order_status,
    po.notes as order_notes,
    p.product_id,
    p.product_code,
    p.product_name,
    p.unit_of_measure,
    p.specifications,
    pc.production_center_id,
    pc.center_code,
    pc.center_name,
    pl.line_id,
    pl.line_code,
    pl.line_name,
    w.warehouse_id,
    w.warehouse_code,
    w.warehouse_name,
    w.warehouse_type,
    b.branch_name,
    u.full_name as created_by_name,
    u.username as created_by_username
FROM production_outputs po_out
JOIN production_orders po ON po_out.order_id = po.order_id
JOIN products p ON po.product_id = p.product_id
JOIN production_centers pc ON po.production_center_id = pc.production_center_id
LEFT JOIN production_lines pl ON po.production_line_id = pl.line_id
JOIN warehouses w ON po_out.warehouse_id = w.warehouse_id
JOIN branches b ON pc.branch_id = b.branch_id
JOIN users u ON po_out.created_by = u.user_id
WHERE po_out.output_id = :output_id";

$stmt = $db->prepare($query);
$stmt->execute([':output_id' => $output_id]);
$output = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$output) {
    $_SESSION['error'] = 'Output record not found';
    header("Location: outputs.php");
    exit();
}

// Calculate metrics
$defect_rate = $output['quantity_produced'] > 0
    ? ($output['quantity_defective'] / $output['quantity_produced']) * 100
    : 0;

$efficiency = $output['order_actual_qty'] > 0 && $output['planned_quantity'] > 0
    ? ($output['order_actual_qty'] / $output['planned_quantity']) * 100
    : 0;

// Get all outputs for this order
$order_outputs_query = "SELECT 
    po_out.*,
    u.full_name as created_by_name
FROM production_outputs po_out
JOIN users u ON po_out.created_by = u.user_id
WHERE po_out.order_id = :order_id
ORDER BY po_out.output_date DESC, po_out.output_id DESC";

$stmt = $db->prepare($order_outputs_query);
$stmt->execute([':order_id' => $output['order_id']]);
$order_outputs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate order totals
$order_total_produced = array_sum(array_column($order_outputs, 'quantity_produced'));
$order_total_good = array_sum(array_column($order_outputs, 'quantity_good'));
$order_total_defective = array_sum(array_column($order_outputs, 'quantity_defective'));
$order_defect_rate = $order_total_produced > 0
    ? ($order_total_defective / $order_total_produced) * 100
    : 0;

// Get stock transaction related to this output
$transaction_query = "SELECT 
    st.*,
    u.full_name as created_by_name
FROM stock_transactions st
JOIN users u ON st.created_by = u.user_id
WHERE st.reference_type = 'production_order'
AND st.reference_id = :order_id
AND st.transaction_type = 'production_output'
AND st.transaction_date = :output_date
AND st.product_id = :product_id
ORDER BY st.transaction_id DESC
LIMIT 1";

$stmt = $db->prepare($transaction_query);
$stmt->execute([
    ':order_id' => $output['order_id'],
    ':output_date' => $output['output_date'],
    ':product_id' => $output['product_id']
]);
$stock_transaction = $stmt->fetch(PDO::FETCH_ASSOC);

$page_title = 'Production Output Details';
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fas fa-box-open me-2"></i>Production Output Details</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="orders.php">Production Orders</a></li>
                    <li class="breadcrumb-item"><a href="outputs.php">Outputs</a></li>
                    <li class="breadcrumb-item active">Output #<?php echo $output_id; ?></li>
                </ol>
            </nav>
        </div>
        <div class="col-md-4 text-end">
            <a href="outputs.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i>Back to Outputs
            </a>
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print me-1"></i>Print
            </button>
        </div>
    </div>

    <div class="row">
        <!-- Main Output Details -->
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Output Information</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <th width="40%">Output ID:</th>
                                    <td><strong>#<?php echo $output['output_id']; ?></strong></td>
                                </tr>
                                <tr>
                                    <th>Output Date:</th>
                                    <td><strong><?php echo date('d M Y', strtotime($output['output_date'])); ?></strong></td>
                                </tr>
                                <tr>
                                    <th>Production Order:</th>
                                    <td>
                                        <a href="view_order.php?id=<?php echo $output['order_id']; ?>">
                                            <strong><?php echo htmlspecialchars($output['order_number']); ?></strong>
                                        </a>
                                        <span class="badge bg-<?php
                                                                echo $output['order_status'] === 'completed' ? 'success' : ($output['order_status'] === 'in_progress' ? 'warning' : 'secondary');
                                                                ?> ms-2">
                                            <?php echo ucfirst(str_replace('_', ' ', $output['order_status'])); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Shift:</th>
                                    <td><?php echo htmlspecialchars($output['shift'] ?? 'Not specified'); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <th width="40%">Production Center:</th>
                                    <td><?php echo htmlspecialchars($output['center_name']); ?></td>
                                </tr>
                                <tr>
                                    <th>Production Line:</th>
                                    <td><?php echo htmlspecialchars($output['line_name'] ?? 'Not assigned'); ?></td>
                                </tr>
                                <tr>
                                    <th>Warehouse:</th>
                                    <td>
                                        <strong><?php echo htmlspecialchars($output['warehouse_name']); ?></strong><br>
                                        <small class="text-muted">
                                            <?php echo ucwords(str_replace('_', ' ', $output['warehouse_type'])); ?>
                                        </small>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Branch:</th>
                                    <td><?php echo htmlspecialchars($output['branch_name']); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <hr>

                    <h6 class="mb-3"><i class="fas fa-box me-2"></i>Product Information</h6>
                    <div class="row">
                        <div class="col-md-12">
                            <table class="table table-sm">
                                <tr>
                                    <th width="20%">Product Code:</th>
                                    <td><strong><?php echo htmlspecialchars($output['product_code']); ?></strong></td>
                                </tr>
                                <tr>
                                    <th>Product Name:</th>
                                    <td><?php echo htmlspecialchars($output['product_name']); ?></td>
                                </tr>
                                <tr>
                                    <th>Unit of Measure:</th>
                                    <td><?php echo htmlspecialchars($output['unit_of_measure']); ?></td>
                                </tr>
                                <?php if ($output['specifications']): ?>
                                    <tr>
                                        <th>Specifications:</th>
                                        <td>
                                            <?php
                                            $specs = json_decode($output['specifications'], true);
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
                        </div>
                    </div>

                    <hr>

                    <h6 class="mb-3"><i class="fas fa-chart-line me-2"></i>Production Quantities</h6>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <h6>Produced</h6>
                                    <h3><?php echo number_format($output['quantity_produced'], 2); ?></h3>
                                    <small><?php echo htmlspecialchars($output['unit_of_measure']); ?></small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h6>Good Quantity</h6>
                                    <h3><?php echo number_format($output['quantity_good'], 2); ?></h3>
                                    <small><?php echo number_format(($output['quantity_produced'] > 0 ? ($output['quantity_good'] / $output['quantity_produced']) * 100 : 0), 1); ?>% of total</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-danger text-white">
                                <div class="card-body text-center">
                                    <h6>Defective</h6>
                                    <h3><?php echo number_format($output['quantity_defective'], 2); ?></h3>
                                    <small><?php echo number_format($defect_rate, 1); ?>% defect rate</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-<?php echo $defect_rate > 5 ? 'danger' : ($defect_rate > 2 ? 'warning' : 'success'); ?> text-white">
                                <div class="card-body text-center">
                                    <h6>Quality Rating</h6>
                                    <h3>
                                        <?php
                                        if ($defect_rate <= 2) echo 'Excellent';
                                        elseif ($defect_rate <= 5) echo 'Good';
                                        else echo 'Poor';
                                        ?>
                                    </h3>
                                    <small><?php echo number_format(100 - $defect_rate, 1); ?>% quality</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($output['notes']): ?>
                        <hr>
                        <h6 class="mb-2"><i class="fas fa-comment me-2"></i>Notes</h6>
                        <div class="alert alert-info">
                            <?php echo nl2br(htmlspecialchars($output['notes'])); ?>
                        </div>
                    <?php endif; ?>

                    <hr>

                    <div class="row">
                        <div class="col-md-6">
                            <small class="text-muted">
                                <i class="fas fa-user me-1"></i>
                                <strong>Recorded By:</strong> <?php echo htmlspecialchars($output['created_by_name']); ?>
                            </small>
                        </div>
                        <div class="col-md-6 text-end">
                            <small class="text-muted">
                                <i class="fas fa-clock me-1"></i>
                                <strong>Recorded On:</strong> <?php echo date('d M Y H:i', strtotime($output['created_at'])); ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Progress -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>Production Order Progress</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>Order Date:</strong><br>
                            <?php echo date('d M Y', strtotime($output['order_date'])); ?>
                        </div>
                        <div class="col-md-4">
                            <strong>Planned Quantity:</strong><br>
                            <?php echo number_format($output['planned_quantity'], 2); ?> <?php echo htmlspecialchars($output['unit_of_measure']); ?>
                        </div>
                        <div class="col-md-4">
                            <strong>Actual Quantity:</strong><br>
                            <?php echo number_format($output['order_actual_qty'], 2); ?> <?php echo htmlspecialchars($output['unit_of_measure']); ?>
                            (<?php echo number_format($efficiency, 1); ?>%)
                        </div>
                    </div>

                    <div class="progress mb-2" style="height: 30px;">
                        <div class="progress-bar <?php echo $efficiency >= 100 ? 'bg-success' : 'bg-warning'; ?>"
                            role="progressbar"
                            style="width: <?php echo min($efficiency, 100); ?>%">
                            <?php echo number_format($efficiency, 1); ?>% Complete
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-6">
                            <strong>Remaining:</strong>
                            <?php
                            $remaining = $output['planned_quantity'] - $output['order_actual_qty'];
                            echo number_format(max($remaining, 0), 2);
                            ?> <?php echo htmlspecialchars($output['unit_of_measure']); ?>
                        </div>
                        <div class="col-md-6 text-end">
                            <strong>Status:</strong>
                            <span class="badge bg-<?php
                                                    echo $output['order_status'] === 'completed' ? 'success' : ($output['order_status'] === 'in_progress' ? 'warning' : 'secondary');
                                                    ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $output['order_status'])); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- All Outputs for This Order -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>All Outputs for This Order</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Produced</th>
                                    <th>Good</th>
                                    <th>Defective</th>
                                    <th>Defect %</th>
                                    <th>Shift</th>
                                    <th>Recorded By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($order_outputs as $oo):
                                    $oo_defect_rate = $oo['quantity_produced'] > 0
                                        ? ($oo['quantity_defective'] / $oo['quantity_produced']) * 100
                                        : 0;
                                    $is_current = $oo['output_id'] == $output_id;
                                ?>
                                    <tr <?php if ($is_current) echo 'class="table-primary"'; ?>>
                                        <td>
                                            <?php echo date('d M Y', strtotime($oo['output_date'])); ?>
                                            <?php if ($is_current): ?>
                                                <span class="badge bg-primary ms-1">Current</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo number_format($oo['quantity_produced'], 2); ?></td>
                                        <td><span class="badge bg-success"><?php echo number_format($oo['quantity_good'], 2); ?></span></td>
                                        <td><span class="badge bg-danger"><?php echo number_format($oo['quantity_defective'], 2); ?></span></td>
                                        <td>
                                            <span class="badge bg-<?php echo $oo_defect_rate > 5 ? 'danger' : ($oo_defect_rate > 2 ? 'warning' : 'success'); ?>">
                                                <?php echo number_format($oo_defect_rate, 2); ?>%
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($oo['shift'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($oo['created_by_name']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-dark">
                                <tr>
                                    <th>Total:</th>
                                    <th><?php echo number_format($order_total_produced, 2); ?></th>
                                    <th><?php echo number_format($order_total_good, 2); ?></th>
                                    <th><?php echo number_format($order_total_defective, 2); ?></th>
                                    <th>
                                        <span class="badge bg-<?php echo $order_defect_rate > 5 ? 'danger' : ($order_defect_rate > 2 ? 'warning' : 'success'); ?>">
                                            <?php echo number_format($order_defect_rate, 2); ?>%
                                        </span>
                                    </th>
                                    <th colspan="2"></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
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
                    <div class="mb-3">
                        <label class="text-muted small">Output Efficiency</label>
                        <div class="progress" style="height: 20px;">
                            <div class="progress-bar bg-success"
                                style="width: <?php echo min(100 - $defect_rate, 100); ?>%">
                                <?php echo number_format(100 - $defect_rate, 1); ?>%
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small">Order Completion</label>
                        <div class="progress" style="height: 20px;">
                            <div class="progress-bar bg-primary"
                                style="width: <?php echo min($efficiency, 100); ?>%">
                                <?php echo number_format($efficiency, 1); ?>%
                            </div>
                        </div>
                    </div>
                    <hr>
                    <table class="table table-sm mb-0">
                        <tr>
                            <td>Output Sessions:</td>
                            <td class="text-end"><strong><?php echo count($order_outputs); ?></strong></td>
                        </tr>
                        <tr>
                            <td>Avg per Session:</td>
                            <td class="text-end">
                                <strong><?php echo number_format($order_total_produced / count($order_outputs), 2); ?></strong>
                            </td>
                        </tr>
                        <tr>
                            <td>Overall Defect Rate:</td>
                            <td class="text-end">
                                <span class="badge bg-<?php echo $order_defect_rate > 5 ? 'danger' : ($order_defect_rate > 2 ? 'warning' : 'success'); ?>">
                                    <?php echo number_format($order_defect_rate, 2); ?>%
                                </span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Stock Transaction -->
            <?php if ($stock_transaction): ?>
                <div class="card mb-4">
                    <div class="card-header bg-warning">
                        <h6 class="mb-0"><i class="fas fa-warehouse me-2"></i>Stock Transaction</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <tr>
                                <td>Transaction ID:</td>
                                <td class="text-end"><strong>#<?php echo $stock_transaction['transaction_id']; ?></strong></td>
                            </tr>
                            <tr>
                                <td>Type:</td>
                                <td class="text-end">
                                    <span class="badge bg-success">
                                        <?php echo ucwords(str_replace('_', ' ', $stock_transaction['transaction_type'])); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td>Quantity Added:</td>
                                <td class="text-end"><strong><?php echo number_format($stock_transaction['quantity'], 2); ?></strong></td>
                            </tr>
                            <tr>
                                <td>Recorded By:</td>
                                <td class="text-end"><?php echo htmlspecialchars($stock_transaction['created_by_name']); ?></td>
                            </tr>
                        </table>
                        <?php if ($stock_transaction['notes']): ?>
                            <hr class="my-2">
                            <small class="text-muted"><?php echo htmlspecialchars($stock_transaction['notes']); ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Actions -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-cog me-2"></i>Actions</h6>
                </div>
                <div class="card-body">
                    <a href="view_order.php?id=<?php echo $output['order_id']; ?>"
                        class="btn btn-primary w-100 mb-2">
                        <i class="fas fa-clipboard-list me-1"></i>View Production Order
                    </a>
                    <a href="../inventory/stock_levels.php?warehouse=<?php echo $output['warehouse_id']; ?>"
                        class="btn btn-info w-100 mb-2">
                        <i class="fas fa-warehouse me-1"></i>View Warehouse Stock
                    </a>
                    <a href="outputs.php?order_id=<?php echo $output['order_id']; ?>"
                        class="btn btn-secondary w-100 mb-2">
                        <i class="fas fa-list me-1"></i>All Outputs for Order
                    </a>
                    <button onclick="window.print()" class="btn btn-outline-primary w-100">
                        <i class="fas fa-print me-1"></i>Print Details
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
        .sidebar,
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
