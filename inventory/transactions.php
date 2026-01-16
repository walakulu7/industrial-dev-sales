<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();
require_permission('inventory', 'read');

$database = new Database();
$db = $database->getConnection();

$page_title = 'Stock Transactions';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = RECORDS_PER_PAGE;
$offset = ($page - 1) * $records_per_page;

// Filters
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$warehouse_id = $_GET['warehouse_id'] ?? '';
$transaction_type = $_GET['transaction_type'] ?? '';
$search = sanitize_input($_GET['search'] ?? '');

// Build query
$where_conditions = ["1=1"];
$params = [];

if (!empty($date_from)) {
    $where_conditions[] = "st.transaction_date >= :date_from";
    $params[':date_from'] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "st.transaction_date <= :date_to";
    $params[':date_to'] = $date_to;
}

if (!empty($warehouse_id)) {
    $where_conditions[] = "st.warehouse_id = :warehouse_id";
    $params[':warehouse_id'] = $warehouse_id;
}

if (!empty($transaction_type)) {
    $where_conditions[] = "st.transaction_type = :transaction_type";
    $params[':transaction_type'] = $transaction_type;
}

if (!empty($search)) {
    $where_conditions[] = "(p.product_code LIKE :search OR p.product_name LIKE :search)";
    $params[':search'] = "%$search%";
}

$where_clause = implode(" AND ", $where_conditions);

// Get total count
$count_query = "SELECT COUNT(*) as total 
                FROM stock_transactions st
                INNER JOIN products p ON st.product_id = p.product_id
                WHERE $where_clause";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($params);
$total_records = $count_stmt->fetch()['total'];

// Get transactions
$query = "SELECT st.*, p.product_code, p.product_name, p.unit_of_measure,
          w.warehouse_name, u.full_name as created_by_name,
          fw.warehouse_name as from_warehouse_name,
          tw.warehouse_name as to_warehouse_name
          FROM stock_transactions st
          INNER JOIN products p ON st.product_id = p.product_id
          INNER JOIN warehouses w ON st.warehouse_id = w.warehouse_id
          INNER JOIN users u ON st.created_by = u.user_id
          LEFT JOIN warehouses fw ON st.from_warehouse_id = fw.warehouse_id
          LEFT JOIN warehouses tw ON st.to_warehouse_id = tw.warehouse_id
          WHERE $where_clause
          ORDER BY st.transaction_date DESC, st.transaction_time DESC
          LIMIT :limit OFFSET :offset";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$transactions = $stmt->fetchAll();

$pagination = calculate_pagination($total_records, $page, $records_per_page);

// Get warehouses for filter
$warehouses = $db->query("SELECT * FROM warehouses WHERE status = 'active' ORDER BY warehouse_name")->fetchAll();

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-exchange-alt"></i> Stock Transactions</h2>
        <div>
            <a href="stock_levels.php" class="btn btn-secondary">
                <i class="fas fa-boxes"></i> Stock Levels
            </a>
            <?php if (has_permission('inventory', 'create')): ?>
                <a href="transfer.php" class="btn btn-primary">
                    <i class="fas fa-truck"></i> Stock Transfer
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <input type="text" class="form-control" name="search"
                        placeholder="Search product..."
                        value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <input type="date" class="form-control" name="date_from"
                        value="<?php echo $date_from; ?>">
                </div>
                <div class="col-md-2">
                    <input type="date" class="form-control" name="date_to"
                        value="<?php echo $date_to; ?>">
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="warehouse_id">
                        <option value="">All Warehouses</option>
                        <?php foreach ($warehouses as $warehouse): ?>
                            <option value="<?php echo $warehouse['warehouse_id']; ?>"
                                <?php echo ($warehouse_id == $warehouse['warehouse_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($warehouse['warehouse_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="transaction_type">
                        <option value="">All Types</option>
                        <option value="receipt" <?php echo ($transaction_type == 'receipt') ? 'selected' : ''; ?>>Receipt</option>
                        <option value="issue" <?php echo ($transaction_type == 'issue') ? 'selected' : ''; ?>>Issue</option>
                        <option value="transfer_in" <?php echo ($transaction_type == 'transfer_in') ? 'selected' : ''; ?>>Transfer In</option>
                        <option value="transfer_out" <?php echo ($transaction_type == 'transfer_out') ? 'selected' : ''; ?>>Transfer Out</option>
                        <option value="adjustment" <?php echo ($transaction_type == 'adjustment') ? 'selected' : ''; ?>>Adjustment</option>
                        <option value="production_input" <?php echo ($transaction_type == 'production_input') ? 'selected' : ''; ?>>Production Input</option>
                        <option value="production_output" <?php echo ($transaction_type == 'production_output') ? 'selected' : ''; ?>>Production Output</option>
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

    <!-- Transactions Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-sm">
                    <thead>
                        <tr>
                            <th>Date/Time</th>
                            <th>Type</th>
                            <th>Product</th>
                            <th>Warehouse</th>
                            <th>Quantity</th>
                            <th>From/To</th>
                            <th>Reference</th>
                            <th>Notes</th>
                            <th>Created By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted">No transactions found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($transactions as $trans): ?>
                                <tr>
                                    <td>
                                        <?php echo format_date($trans['transaction_date']); ?><br>
                                        <small class="text-muted"><?php echo $trans['transaction_time']; ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php
                                                                echo in_array($trans['transaction_type'], ['receipt', 'transfer_in', 'production_output']) ? 'success' : (in_array($trans['transaction_type'], ['issue', 'transfer_out', 'production_input']) ? 'danger' : 'warning');
                                                                ?>">
                                            <?php echo ucwords(str_replace('_', ' ', $trans['transaction_type'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($trans['product_code']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($trans['product_name']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($trans['warehouse_name']); ?></td>
                                    <td>
                                        <strong><?php echo number_format($trans['quantity'], 2); ?></strong>
                                        <?php echo $trans['unit_of_measure']; ?>
                                    </td>
                                    <td>
                                        <?php if ($trans['from_warehouse_name']): ?>
                                            From: <?php echo htmlspecialchars($trans['from_warehouse_name']); ?>
                                        <?php endif; ?>
                                        <?php if ($trans['to_warehouse_name']): ?>
                                            To: <?php echo htmlspecialchars($trans['to_warehouse_name']); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($trans['reference_type']): ?>
                                            <small><?php echo ucwords(str_replace('_', ' ', $trans['reference_type'])); ?> #<?php echo $trans['reference_id']; ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><small><?php echo htmlspecialchars($trans['notes'] ?? '-'); ?></small></td>
                                    <td><small><?php echo htmlspecialchars($trans['created_by_name']); ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php echo render_pagination($pagination, '?search=' . urlencode($search) .
                '&date_from=' . $date_from . '&date_to=' . $date_to .
                '&warehouse_id=' . $warehouse_id . '&transaction_type=' . $transaction_type); ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>