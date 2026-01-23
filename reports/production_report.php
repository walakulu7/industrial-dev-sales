<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../includes/functions.php';

// Check authentication
require_login();
require_permission('reports', 'read');

$database = new Database();
$db = $database->getConnection();

// Get filter parameters
$report_type = $_GET['report_type'] ?? 'production_summary';
$center_id = $_GET['center_id'] ?? '';
$product_id = $_GET['product_id'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$status = $_GET['status'] ?? '';

// Get production centers
$centers_query = "SELECT * FROM production_centers WHERE status = 'active' ORDER BY center_name";
$centers = $db->query($centers_query)->fetchAll();

// Get products
$products_query = "SELECT p.*, pc.category_name 
                   FROM products p 
                   JOIN product_categories pc ON p.category_id = pc.category_id 
                   ORDER BY p.product_name";
$products = $db->query($products_query)->fetchAll();

$report_data = [];
$report_title = '';
$summary_stats = [];

switch ($report_type) {
    case 'production_summary':
        $report_title = 'Production Summary Report';
        $query = "SELECT 
            pc.center_name,
            p.product_code,
            p.product_name,
            p.unit_of_measure,
            COUNT(DISTINCT po.order_id) as total_orders,
            SUM(po.planned_quantity) as total_planned,
            SUM(po.actual_quantity) as total_actual,
            (SUM(po.actual_quantity) / NULLIF(SUM(po.planned_quantity), 0) * 100) as efficiency,
            SUM(po_out.quantity_produced) as total_produced,
            SUM(po_out.quantity_good) as total_good,
            SUM(po_out.quantity_defective) as total_defective,
            (SUM(po_out.quantity_defective) / NULLIF(SUM(po_out.quantity_produced), 0) * 100) as defect_rate
        FROM production_orders po
        JOIN production_centers pc ON po.production_center_id = pc.production_center_id
        JOIN products p ON po.product_id = p.product_id
        LEFT JOIN production_outputs po_out ON po.order_id = po_out.order_id
        WHERE po.order_date BETWEEN :date_from AND :date_to";

        $params = [
            ':date_from' => $date_from,
            ':date_to' => $date_to
        ];

        if ($center_id) {
            $query .= " AND po.production_center_id = :center_id";
            $params[':center_id'] = $center_id;
        }
        if ($product_id) {
            $query .= " AND po.product_id = :product_id";
            $params[':product_id'] = $product_id;
        }

        $query .= " GROUP BY pc.production_center_id, p.product_id
                   ORDER BY pc.center_name, p.product_code";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate summary
        $summary_stats = [
            'total_orders' => array_sum(array_column($report_data, 'total_orders')),
            'total_planned' => array_sum(array_column($report_data, 'total_planned')),
            'total_actual' => array_sum(array_column($report_data, 'total_actual')),
            'total_produced' => array_sum(array_column($report_data, 'total_produced')),
            'total_good' => array_sum(array_column($report_data, 'total_good')),
            'total_defective' => array_sum(array_column($report_data, 'total_defective'))
        ];
        $summary_stats['avg_efficiency'] = $summary_stats['total_planned'] > 0
            ? ($summary_stats['total_actual'] / $summary_stats['total_planned']) * 100
            : 0;
        $summary_stats['avg_defect_rate'] = $summary_stats['total_produced'] > 0
            ? ($summary_stats['total_defective'] / $summary_stats['total_produced']) * 100
            : 0;
        break;

    case 'order_status':
        $report_title = 'Production Order Status Report';
        $query = "SELECT 
            po.order_number,
            po.order_date,
            pc.center_name,
            p.product_code,
            p.product_name,
            p.unit_of_measure,
            po.planned_quantity,
            po.actual_quantity,
            (po.actual_quantity / NULLIF(po.planned_quantity, 0) * 100) as completion_pct,
            po.start_date,
            po.end_date,
            DATEDIFF(COALESCE(po.end_date, CURDATE()), po.start_date) as duration_days,
            po.status,
            u.full_name as created_by_name
        FROM production_orders po
        JOIN production_centers pc ON po.production_center_id = pc.production_center_id
        JOIN products p ON po.product_id = p.product_id
        JOIN users u ON po.created_by = u.user_id
        WHERE po.order_date BETWEEN :date_from AND :date_to";

        $params = [
            ':date_from' => $date_from,
            ':date_to' => $date_to
        ];

        if ($center_id) {
            $query .= " AND po.production_center_id = :center_id";
            $params[':center_id'] = $center_id;
        }
        if ($product_id) {
            $query .= " AND po.product_id = :product_id";
            $params[':product_id'] = $product_id;
        }
        if ($status) {
            $query .= " AND po.status = :status";
            $params[':status'] = $status;
        }

        $query .= " ORDER BY po.order_date DESC, po.order_number DESC";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;

    case 'daily_output':
        $report_title = 'Daily Production Output Report';
        $query = "SELECT 
            po_out.output_date,
            pc.center_name,
            pl.line_name,
            p.product_code,
            p.product_name,
            p.unit_of_measure,
            po.order_number,
            po_out.quantity_produced,
            po_out.quantity_good,
            po_out.quantity_defective,
            (po_out.quantity_defective / NULLIF(po_out.quantity_produced, 0) * 100) as defect_rate,
            po_out.shift,
            w.warehouse_name,
            u.full_name as created_by_name
        FROM production_outputs po_out
        JOIN production_orders po ON po_out.order_id = po.order_id
        JOIN production_centers pc ON po.production_center_id = pc.production_center_id
        LEFT JOIN production_lines pl ON po.production_line_id = pl.line_id
        JOIN products p ON po.product_id = p.product_id
        JOIN warehouses w ON po_out.warehouse_id = w.warehouse_id
        JOIN users u ON po_out.created_by = u.user_id
        WHERE po_out.output_date BETWEEN :date_from AND :date_to";

        $params = [
            ':date_from' => $date_from,
            ':date_to' => $date_to
        ];

        if ($center_id) {
            $query .= " AND po.production_center_id = :center_id";
            $params[':center_id'] = $center_id;
        }
        if ($product_id) {
            $query .= " AND po.product_id = :product_id";
            $params[':product_id'] = $product_id;
        }

        $query .= " ORDER BY po_out.output_date DESC, pc.center_name";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate daily totals
        $summary_stats = [
            'total_outputs' => count($report_data),
            'total_produced' => array_sum(array_column($report_data, 'quantity_produced')),
            'total_good' => array_sum(array_column($report_data, 'quantity_good')),
            'total_defective' => array_sum(array_column($report_data, 'quantity_defective'))
        ];
        $summary_stats['avg_defect_rate'] = $summary_stats['total_produced'] > 0
            ? ($summary_stats['total_defective'] / $summary_stats['total_produced']) * 100
            : 0;
        break;

    case 'efficiency_analysis':
        $report_title = 'Production Efficiency Analysis';
        $query = "SELECT 
            pc.center_name,
            pl.line_name,
            p.product_code,
            p.product_name,
            COUNT(DISTINCT po.order_id) as total_orders,
            SUM(po.planned_quantity) as total_planned,
            SUM(po.actual_quantity) as total_actual,
            (SUM(po.actual_quantity) / NULLIF(SUM(po.planned_quantity), 0) * 100) as efficiency_pct,
            AVG(DATEDIFF(po.end_date, po.start_date)) as avg_duration_days,
            SUM(po_out.quantity_defective) / NULLIF(SUM(po_out.quantity_produced), 0) * 100 as defect_rate,
            COUNT(DISTINCT po_out.output_id) as total_output_sessions,
            SUM(po_out.quantity_produced) / NULLIF(COUNT(DISTINCT po_out.output_id), 0) as avg_output_per_session
        FROM production_orders po
        JOIN production_centers pc ON po.production_center_id = pc.production_center_id
        LEFT JOIN production_lines pl ON po.production_line_id = pl.line_id
        JOIN products p ON po.product_id = p.product_id
        LEFT JOIN production_outputs po_out ON po.order_id = po_out.order_id
        WHERE po.order_date BETWEEN :date_from AND :date_to
        AND po.status IN ('completed', 'in_progress')";

        $params = [
            ':date_from' => $date_from,
            ':date_to' => $date_to
        ];

        if ($center_id) {
            $query .= " AND po.production_center_id = :center_id";
            $params[':center_id'] = $center_id;
        }
        if ($product_id) {
            $query .= " AND po.product_id = :product_id";
            $params[':product_id'] = $product_id;
        }

        $query .= " GROUP BY pc.production_center_id, pl.line_id, p.product_id
                   HAVING total_orders > 0
                   ORDER BY efficiency_pct DESC";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;

    case 'defect_analysis':
        $report_title = 'Production Defect Analysis Report';
        $query = "SELECT 
            pc.center_name,
            pl.line_name,
            p.product_code,
            p.product_name,
            p.unit_of_measure,
            po_out.output_date,
            po_out.shift,
            SUM(po_out.quantity_produced) as total_produced,
            SUM(po_out.quantity_good) as total_good,
            SUM(po_out.quantity_defective) as total_defective,
            (SUM(po_out.quantity_defective) / NULLIF(SUM(po_out.quantity_produced), 0) * 100) as defect_rate,
            COUNT(po_out.output_id) as output_sessions
        FROM production_outputs po_out
        JOIN production_orders po ON po_out.order_id = po.order_id
        JOIN production_centers pc ON po.production_center_id = pc.production_center_id
        LEFT JOIN production_lines pl ON po.production_line_id = pl.line_id
        JOIN products p ON po.product_id = p.product_id
        WHERE po_out.output_date BETWEEN :date_from AND :date_to
        AND po_out.quantity_defective > 0";

        $params = [
            ':date_from' => $date_from,
            ':date_to' => $date_to
        ];

        if ($center_id) {
            $query .= " AND po.production_center_id = :center_id";
            $params[':center_id'] = $center_id;
        }
        if ($product_id) {
            $query .= " AND po.product_id = :product_id";
            $params[':product_id'] = $product_id;
        }

        $query .= " GROUP BY pc.production_center_id, pl.line_id, p.product_id, po_out.output_date, po_out.shift
                   ORDER BY defect_rate DESC";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate totals
        $summary_stats = [
            'total_produced' => array_sum(array_column($report_data, 'total_produced')),
            'total_good' => array_sum(array_column($report_data, 'total_good')),
            'total_defective' => array_sum(array_column($report_data, 'total_defective'))
        ];
        $summary_stats['overall_defect_rate'] = $summary_stats['total_produced'] > 0
            ? ($summary_stats['total_defective'] / $summary_stats['total_produced']) * 100
            : 0;
        break;
}

$page_title = 'Production Reports';
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fas fa-industry me-2"></i><?php echo $report_title; ?></h2>
            <p class="text-muted">Period: <?php echo date('d M Y', strtotime($date_from)); ?> to <?php echo date('d M Y', strtotime($date_to)); ?></p>
        </div>
        <div class="col-md-4 text-end">
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print me-1"></i>Print
            </button>
            <button onclick="exportToExcel()" class="btn btn-success">
                <i class="fas fa-file-excel me-1"></i>Export
            </button>
        </div>
    </div>

    <!-- Summary Cards -->
    <?php if (!empty($summary_stats)): ?>
        <div class="row mb-4 no-print">
            <?php if (isset($summary_stats['total_orders'])): ?>
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h6 class="card-title">Total Orders</h6>
                            <h3><?php echo number_format($summary_stats['total_orders']); ?></h3>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($summary_stats['total_produced'])): ?>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h6 class="card-title">Total Produced</h6>
                            <h3><?php echo number_format($summary_stats['total_produced'], 2); ?></h3>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($summary_stats['avg_efficiency'])): ?>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h6 class="card-title">Avg Efficiency</h6>
                            <h3><?php echo number_format($summary_stats['avg_efficiency'], 1); ?>%</h3>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($summary_stats['avg_defect_rate']) || isset($summary_stats['overall_defect_rate'])): ?>
                <div class="col-md-3">
                    <div class="card bg-<?php
                                        $defect = isset($summary_stats['avg_defect_rate']) ? $summary_stats['avg_defect_rate'] : $summary_stats['overall_defect_rate'];
                                        echo $defect > 5 ? 'danger' : ($defect > 2 ? 'warning' : 'success');
                                        ?> text-white">
                        <div class="card-body">
                            <h6 class="card-title">Defect Rate</h6>
                            <h3>
                                <?php
                                echo number_format(
                                    isset($summary_stats['avg_defect_rate']) ? $summary_stats['avg_defect_rate'] : $summary_stats['overall_defect_rate'],
                                    2
                                );
                                ?>%
                            </h3>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card mb-4 no-print">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Report Type</label>
                    <select class="form-select" name="report_type" required>
                        <option value="production_summary" <?php echo $report_type === 'production_summary' ? 'selected' : ''; ?>>Production Summary</option>
                        <option value="order_status" <?php echo $report_type === 'order_status' ? 'selected' : ''; ?>>Order Status</option>
                        <option value="daily_output" <?php echo $report_type === 'daily_output' ? 'selected' : ''; ?>>Daily Output</option>
                        <option value="efficiency_analysis" <?php echo $report_type === 'efficiency_analysis' ? 'selected' : ''; ?>>Efficiency Analysis</option>
                        <option value="defect_analysis" <?php echo $report_type === 'defect_analysis' ? 'selected' : ''; ?>>Defect Analysis</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Production Center</label>
                    <select class="form-select" name="center_id">
                        <option value="">All Centers</option>
                        <?php foreach ($centers as $center): ?>
                            <option value="<?php echo $center['production_center_id']; ?>"
                                <?php echo $center_id == $center['production_center_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($center['center_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Product</label>
                    <select class="form-select" name="product_id">
                        <option value="">All Products</option>
                        <?php foreach ($products as $prod): ?>
                            <option value="<?php echo $prod['product_id']; ?>"
                                <?php echo $product_id == $prod['product_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($prod['product_code']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($report_type === 'order_status'): ?>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="in_progress" <?php echo $status === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                <?php endif; ?>
                <div class="col-md-<?php echo $report_type === 'order_status' ? '1' : '2'; ?>">
                    <label class="form-label">From</label>
                    <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>" required>
                </div>
                <div class="col-md-<?php echo $report_type === 'order_status' ? '1' : '2'; ?>">
                    <label class="form-label">To</label>
                    <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>" required>
                </div>
                <div class="col-md-1">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Report Content -->
    <div class="card">
        <div class="card-body">
            <?php if (count($report_data) > 0): ?>

                <?php if ($report_type === 'production_summary'): ?>
                    <div class="table-responsive">
                        <table class="table table-striped" id="reportTable">
                            <thead>
                                <tr>
                                    <th>Production Center</th>
                                    <th>Product</th>
                                    <th>Unit</th>
                                    <th>Orders</th>
                                    <th>Planned</th>
                                    <th>Actual</th>
                                    <th>Efficiency</th>
                                    <th>Produced</th>
                                    <th>Good</th>
                                    <th>Defective</th>
                                    <th>Defect Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['center_name']); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['product_code']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($row['product_name']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['unit_of_measure']); ?></td>
                                        <td><?php echo number_format($row['total_orders']); ?></td>
                                        <td><?php echo number_format($row['total_planned'], 2); ?></td>
                                        <td><?php echo number_format($row['total_actual'], 2); ?></td>
                                        <td>
                                            <span class="badge bg-<?php
                                                                    echo ($row['efficiency'] ?? 0) >= 100 ? 'success' : (($row['efficiency'] ?? 0) >= 80 ? 'warning' : 'danger');
                                                                    ?>">
                                                <?php echo number_format($row['efficiency'] ?? 0, 1); ?>%
                                            </span>
                                        </td>
                                        <td><?php echo number_format($row['total_produced'] ?? 0, 2); ?></td>
                                        <td><?php echo number_format($row['total_good'] ?? 0, 2); ?></td>
                                        <td><?php echo number_format($row['total_defective'] ?? 0, 2); ?></td>
                                        <td>
                                            <span class="badge bg-<?php
                                                                    echo ($row['defect_rate'] ?? 0) > 5 ? 'danger' : (($row['defect_rate'] ?? 0) > 2 ? 'warning' : 'success');
                                                                    ?>">
                                                <?php echo number_format($row['defect_rate'] ?? 0, 2); ?>%
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php elseif ($report_type === 'order_status'): ?>
                    <div class="table-responsive">
                        <table class="table table-striped" id="reportTable">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Date</th>
                                    <th>Center</th>
                                    <th>Product</th>
                                    <th>Planned</th>
                                    <th>Actual</th>
                                    <th>Progress</th>
                                    <th>Duration</th>
                                    <th>Status</th>
                                    <th>Created By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data as $row): ?>
                                    <tr>
                                        <td>
                                            <a href="../production/view_order.php?id=<?php echo $row['order_id'] ?? ''; ?>">
                                                <?php echo htmlspecialchars($row['order_number']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo date('d M Y', strtotime($row['order_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($row['center_name']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($row['product_code']); ?><br>
                                            <small><?php echo htmlspecialchars($row['product_name']); ?></small>
                                        </td>
                                        <td><?php echo number_format($row['planned_quantity'], 2); ?></td>
                                        <td><?php echo number_format($row['actual_quantity'], 2); ?></td>
                                        <td>
                                            <div class="progress" style="height: 20px; min-width: 100px;">
                                                <div class="progress-bar <?php echo $row['completion_pct'] >= 100 ? 'bg-success' : 'bg-warning'; ?>"
                                                    style="width: <?php echo min($row['completion_pct'], 100); ?>%">
                                                    <?php echo number_format($row['completion_pct'], 0); ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo $row['duration_days'] !== null ? $row['duration_days'] . ' days' : 'N/A'; ?></td>
                                        <td>
                                            <span class="badge bg-<?php
                                                                    echo $row['status'] === 'completed' ? 'success' : ($row['status'] === 'in_progress' ? 'warning' : ($row['status'] === 'cancelled' ? 'danger' : 'secondary'));
                                                                    ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $row['status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['created_by_name']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php elseif ($report_type === 'daily_output'): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-sm" id="reportTable">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Center</th>
                                    <th>Line</th>
                                    <th>Product</th>
                                    <th>Order</th>
                                    <th>Produced</th>
                                    <th>Good</th>
                                    <th>Defective</th>
                                    <th>Defect %</th>
                                    <th>Shift</th>
                                    <th>Warehouse</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data as $row): ?>
                                    <tr>
                                        <td><?php echo date('d M Y', strtotime($row['output_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($row['center_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['line_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($row['product_code']); ?></td>
                                        <td><?php echo htmlspecialchars($row['order_number']); ?></td>
                                        <td><?php echo number_format($row['quantity_produced'], 2); ?></td>
                                        <td><span class="badge bg-success"><?php echo number_format($row['quantity_good'], 2); ?></span></td>
                                        <td><span class="badge bg-danger"><?php echo number_format($row['quantity_defective'], 2); ?></span></td>
                                        <td>
                                            <span class="badge bg-<?php echo $row['defect_rate'] > 5 ? 'danger' : ($row['defect_rate'] > 2 ? 'warning' : 'success'); ?>">
                                                <?php echo number_format($row['defect_rate'], 2); ?>%
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['shift'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($row['warehouse_name']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php elseif ($report_type === 'efficiency_analysis'): ?>
                    <div class="table-responsive">
                        <table class="table table-striped" id="reportTable">
                            <thead>
                                <tr>
                                    <th>Production Center</th>
                                    <th>Line</th>
                                    <th>Product</th>
                                    <th>Orders</th>
                                    <th>Planned</th>
                                    <th>Actual</th>
                                    <th>Efficiency</th>
                                    <th>Avg Duration</th>
                                    <th>Defect Rate</th>
                                    <th>Output Sessions</th>
                                    <th>Avg/Session</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['center_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['line_name'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($row['product_code']); ?><br>
                                            <small><?php echo htmlspecialchars($row['product_name']); ?></small>
                                        </td>
                                        <td><?php echo number_format($row['total_orders']); ?></td>
                                        <td><?php echo number_format($row['total_planned'], 2); ?></td>
                                        <td><?php echo number_format($row['total_actual'], 2); ?></td>
                                        <td>
                                            <div class="progress" style="height: 25px; min-width: 100px;">
                                                <div class="progress-bar <?php
                                                                            echo $row['efficiency_pct'] >= 100 ? 'bg-success' : ($row['efficiency_pct'] >= 80 ? 'bg-info' : ($row['efficiency_pct'] >= 60 ? 'bg-warning' : 'bg-danger'));
                                                                            ?>"
                                                    style="width: <?php echo min($row['efficiency_pct'], 100); ?>%">
                                                    <?php echo number_format($row['efficiency_pct'], 1); ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo $row['avg_duration_days'] ? number_format($row['avg_duration_days'], 1) . ' days' : 'N/A'; ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $row['defect_rate'] > 5 ? 'danger' : ($row['defect_rate'] > 2 ? 'warning' : 'success'); ?>">
                                                <?php echo number_format($row['defect_rate'], 2); ?>%
                                            </span>
                                        </td>
                                        <td><?php echo number_format($row['total_output_sessions']); ?></td>
                                        <td><?php echo number_format($row['avg_output_per_session'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php elseif ($report_type === 'defect_analysis'): ?>
                    <div class="alert alert-warning mb-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        This report shows production outputs with defects
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped" id="reportTable">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Center</th>
                                    <th>Line</th>
                                    <th>Product</th>
                                    <th>Shift</th>
                                    <th>Produced</th>
                                    <th>Good</th>
                                    <th>Defective</th>
                                    <th>Defect Rate</th>
                                    <th>Sessions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data as $row): ?>
                                    <tr class="<?php echo $row['defect_rate'] > 10 ? 'table-danger' : ($row['defect_rate'] > 5 ? 'table-warning' : ''); ?>">
                                        <td><?php echo date('d M Y', strtotime($row['output_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($row['center_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['line_name'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($row['product_code']); ?><br>
                                            <small><?php echo htmlspecialchars($row['product_name']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['shift'] ?? 'N/A'); ?></td>
                                        <td><?php echo number_format($row['total_produced'] ?? 0, 2); ?></td>
                                        <td><?php echo number_format($row['total_good'] ?? 0, 2); ?></td>
                                        <td><strong><?php echo number_format($row['total_defective'] ?? 0, 2); ?></strong></td>
                                        <td>
                                            <span class="badge bg-<?php echo ($row['defect_rate'] ?? 0) > 10 ? 'danger' : (($row['defect_rate'] ?? 0) > 5 ? 'warning' : 'info'); ?>">
                                                <?php echo number_format($row['defect_rate'] ?? 0, 2); ?>%
                                            </span>
                                        </td>
                                        <td><?php echo number_format($row['output_sessions']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>No data found for the selected filters.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
    function exportToExcel() {
        var table = document.getElementById('reportTable');
        if (!table) {
            alert('No data to export');
            return;
        }
        var html = table.outerHTML;
        var url = 'data:application/vnd.ms-excel,' + encodeURIComponent(html);
        var link = document.createElement('a');
        link.href = url;
        link.download = '<?php echo $report_type; ?>_report_<?php echo date('Y-m-d'); ?>.xls';
        link.click();
    }
</script>
<style>
    @media print {

        .no-print,
        .btn,
        nav {
            display: none !important;
        }

        .table {
            font-size: 10px;
        }
    }
</style>
<?php include '../includes/footer.php'; ?>
</parameter>
