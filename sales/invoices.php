<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();
require_permission('sales', 'read');

$database = new Database();
$db = $database->getConnection();

$page_title = 'Sales Invoices';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = RECORDS_PER_PAGE;
$offset = ($page - 1) * $records_per_page;

// Filters
$search = sanitize_input($_GET['search'] ?? '');
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$branch_id = $_GET['branch_id'] ?? '';
$status = $_GET['status'] ?? '';

// Build query
$where_conditions = ["1=1"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(si.invoice_number LIKE :search OR c.customer_name LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($date_from)) {
    $where_conditions[] = "si.invoice_date >= :date_from";
    $params[':date_from'] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "si.invoice_date <= :date_to";
    $params[':date_to'] = $date_to;
}

if (!empty($branch_id)) {
    $where_conditions[] = "si.branch_id = :branch_id";
    $params[':branch_id'] = $branch_id;
}

if (!empty($status)) {
    $where_conditions[] = "si.status = :status";
    $params[':status'] = $status;
}

$where_clause = implode(" AND ", $where_conditions);

// Get total count
$count_query = "SELECT COUNT(*) as total 
                FROM sales_invoices si 
                INNER JOIN customers c ON si.customer_id = c.customer_id 
                WHERE $where_clause";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($params);
$total_records = $count_stmt->fetch()['total'];

// Get invoices
$query = "SELECT si.*, c.customer_name, b.branch_name, u.full_name as created_by_name
          FROM sales_invoices si
          INNER JOIN customers c ON si.customer_id = c.customer_id
          INNER JOIN branches b ON si.branch_id = b.branch_id
          INNER JOIN users u ON si.created_by = u.user_id
          WHERE $where_clause
          ORDER BY si.invoice_date DESC, si.invoice_id DESC
          LIMIT :limit OFFSET :offset";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$invoices = $stmt->fetchAll();

$pagination = calculate_pagination($total_records, $page, $records_per_page);

// Get branches for filter
$branches = $db->query("SELECT * FROM branches WHERE status = 'active' ORDER BY branch_name")->fetchAll();

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-file-invoice"></i> Sales Invoices</h2>
        <?php if (has_permission('sales', 'create')): ?>
            <a href="create_invoice.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> New Invoice
            </a>
        <?php endif; ?>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <input type="text" class="form-control" name="search"
                        placeholder="Search invoice or customer..."
                        value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <input type="date" class="form-control" name="date_from"
                        value="<?php echo $date_from; ?>" placeholder="From Date">
                </div>
                <div class="col-md-2">
                    <input type="date" class="form-control" name="date_to"
                        value="<?php echo $date_to; ?>" placeholder="To Date">
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="branch_id">
                        <option value="">All Branches</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?php echo $branch['branch_id']; ?>"
                                <?php echo ($branch_id == $branch['branch_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($branch['branch_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="status">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo ($status == 'pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="partial" <?php echo ($status == 'partial') ? 'selected' : ''; ?>>Partial</option>
                        <option value="paid" <?php echo ($status == 'paid') ? 'selected' : ''; ?>>Paid</option>
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

    <!-- Invoices Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Branch</th>
                            <th>Payment</th>
                            <th>Amount</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($invoices)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted">No invoices found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($invoices as $invoice): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($invoice['invoice_number']); ?></strong></td>
                                    <td><?php echo format_date($invoice['invoice_date']); ?></td>
                                    <td><?php echo htmlspecialchars($invoice['customer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($invoice['branch_name']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php
                                                                echo $invoice['payment_method'] === 'cash' ? 'success' : ($invoice['payment_method'] === 'credit' ? 'warning' : 'info');
                                                                ?>">
                                            <?php echo ucfirst($invoice['payment_method']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo format_currency($invoice['total_amount']); ?></td>
                                    <td><?php echo format_currency($invoice['balance_amount']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php
                                                                echo $invoice['status'] === 'paid' ? 'success' : ($invoice['status'] === 'pending' ? 'warning' : ($invoice['status'] === 'partial' ? 'info' : 'danger'));
                                                                ?>">
                                            <?php echo ucfirst($invoice['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="view_invoice.php?id=<?php echo $invoice['invoice_id']; ?>"
                                            class="btn btn-sm btn-info" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="print_invoice.php?id=<?php echo $invoice['invoice_id']; ?>"
                                            class="btn btn-sm btn-secondary" target="_blank" title="Print">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php echo render_pagination($pagination, '?search=' . urlencode($search) .
                '&date_from=' . $date_from . '&date_to=' . $date_to .
                '&branch_id=' . $branch_id . '&status=' . $status); ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>