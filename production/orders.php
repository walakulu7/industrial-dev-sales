<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();
require_permission('production', 'read');

$database = new Database();
$db = $database->getConnection();

$page_title = 'Production Orders';

// Filters
$status = $_GET['status'] ?? '';
$center_id = $_GET['center_id'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = sanitize_input($_GET['search'] ?? '');

// Build query
$where_conditions = ["1=1"];
$params = [];

if (!empty($status)) {
    $where_conditions[] = "po.status = :status";
    $params[':status'] = $status;
}

if (!empty($center_id)) {
    $where_conditions[] = "po.production_center_id = :center_id";
    $params[':center_id'] = $center_id;
}

if (!empty($date_from)) {
    $where_conditions[] = "po.order_date >= :date_from";
    $params[':date_from'] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "po.order_date <= :date_to";
    $params[':date_to'] = $date_to;
}

if (!empty($search)) {
    $where_conditions[] = "(po.order_number LIKE :search OR p.product_name LIKE :search)";
    $params[':search'] = "%$search%";
}

$where_clause = implode(" AND ", $where_conditions);

$query = "SELECT po.*, pc.center_name, p.product_code, p.product_name, p.unit_of_measure,
          pl.line_name, u.full_name as created_by_name
          FROM production_orders po
          INNER JOIN production_centers pc ON po.production_center_id = pc.production_center_id
          INNER JOIN products p ON po.product_id = p.product_id
          LEFT JOIN production_lines pl ON po.production_line_id = pl.line_id
          INNER JOIN users u ON po.created_by = u.user_id
          WHERE $where_clause
          ORDER BY po.order_date DESC, po.order_id DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Get production centers
$centers = $db->query("SELECT * FROM production_centers WHERE status = 'active' ORDER BY center_name")->fetchAll();

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-industry"></i> Production Orders</h2>
        <?php if (has_permission('production', 'create')): ?>
            <div>
                <a href="create_order.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> New Production Order
                </a>
                <a href="outputs.php" class="btn btn-success">
                    <i class="fas fa-box-open"></i> Record Output
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <input type="text" class="form-control" name="search"
                        placeholder="Search order or product..."
                        value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
                </div>
                <div class="col-md-2">
                    <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="center_id">
                        <option value="">All Centers</option>
                        <?php foreach ($centers as $center): ?>
                            <option value="<?php echo $center['production_center_id']; ?>"
                                <?php echo ($center_id == $center['production_center_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($center['center_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="status">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo ($status == 'pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="in_progress" <?php echo ($status == 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                        <option value="completed" <?php echo ($status == 'completed') ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo ($status == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Date</th>
                            <th>Product</th>
                            <th>Center</th>
                            <th>Line</th>
                            <th>Planned Qty</th>
                            <th>Actual Qty</th>
                            <th>Progress</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="10" class="text-center text-muted">No production orders found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <?php
                                $progress = $order['planned_quantity'] > 0 ?
                                    ($order['actual_quantity'] / $order['planned_quantity'] * 100) : 0;
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                                    <td><?php echo format_date($order['order_date']); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($order['product_code']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($order['product_name']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($order['center_name']); ?></td>
                                    <td><?php echo htmlspecialchars($order['line_name'] ?? '-'); ?></td>
                                    <td><?php echo number_format($order['planned_quantity'], 2); ?> <?php echo $order['unit_of_measure']; ?></td>
                                    <td><?php echo number_format($order['actual_quantity'], 2); ?> <?php echo $order['unit_of_measure']; ?></td>
                                    <td>
                                        <div class="progress" style="height: 25px;">
                                            <div class="progress-bar <?php echo $progress >= 100 ? 'bg-success' : 'bg-primary'; ?>"
                                                style="width: <?php echo min($progress, 100); ?>%">
                                                <?php echo number_format($progress, 1); ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php
                                                                echo $order['status'] === 'completed' ? 'success' : ($order['status'] === 'in_progress' ? 'primary' : ($order['status'] === 'pending' ? 'warning' : 'danger'));
                                                                ?>">
                                            <?php echo ucwords(str_replace('_', ' ', $order['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="view_order.php?id=<?php echo $order['order_id']; ?>"
                                            class="btn btn-sm btn-info" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($order['status'] !== 'completed' && has_permission('production', 'update')): ?>
                                            <a href="record_output.php?order_id=<?php echo $order['order_id']; ?>"
                                                class="btn btn-sm btn-success" title="Record Output">
                                                <i class="fas fa-plus"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>