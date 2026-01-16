<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();
require_permission('credit', 'read');

$database = new Database();
$db = $database->getConnection();

$page_title = 'Credit Sales';

// Filters
$status = $_GET['status'] ?? '';
$customer_id = $_GET['customer_id'] ?? '';
$aging = $_GET['aging'] ?? '';
$search = sanitize_input($_GET['search'] ?? '');

$where_conditions = ["1=1"];
$params = [];

if (!empty($status)) {
    $where_conditions[] = "cs.status = :status";
    $params[':status'] = $status;
}

if (!empty($customer_id)) {
    $where_conditions[] = "cs.customer_id = :customer_id";
    $params[':customer_id'] = $customer_id;
}

if (!empty($search)) {
    $where_conditions[] = "(si.invoice_number LIKE :search OR c.customer_name LIKE :search)";
    $params[':search'] = "%$search%";
}

$where_clause = implode(" AND ", $where_conditions);

$query = "SELECT cs.*, c.customer_name, c.customer_code, si.invoice_number, si.branch_id, b.branch_name,
          DATEDIFF(CURDATE(), cs.due_date) as overdue_days,
          CASE 
              WHEN DATEDIFF(CURDATE(), cs.due_date) > 90 THEN 'Critical'
              WHEN DATEDIFF(CURDATE(), cs.due_date) > 60 THEN 'High Risk'
              WHEN DATEDIFF(CURDATE(), cs.due_date) > 30 THEN 'Overdue'
              WHEN DATEDIFF(CURDATE(), cs.due_date) > 0 THEN 'Due Soon'
              ELSE 'Current'
          END as aging_category
          FROM credit_sales cs
          INNER JOIN customers c ON cs.customer_id = c.customer_id
          INNER JOIN sales_invoices si ON cs.invoice_id = si.invoice_id
          INNER JOIN branches b ON si.branch_id = b.branch_id
          WHERE $where_clause
          ORDER BY cs.due_date ASC, overdue_days DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$credit_sales = $stmt->fetchAll();

// Apply aging filter
if (!empty($aging)) {
    $credit_sales = array_filter($credit_sales, function ($item) use ($aging) {
        return $item['aging_category'] === $aging;
    });
}

// Get customers for filter
$customers = $db->query("SELECT customer_id, customer_code, customer_name FROM customers 
                         WHERE status = 'active' ORDER BY customer_name")->fetchAll();

// Calculate totals
$total_outstanding = array_sum(array_column($credit_sales, 'balance_amount'));
$total_overdue = array_sum(array_map(function ($item) {
    return $item['overdue_days'] > 0 ? $item['balance_amount'] : 0;
}, $credit_sales));

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-credit-card"></i> Credit Sales Management</h2>
        <?php if (has_permission('credit', 'create')): ?>
            <a href="record_payment.php" class="btn btn-success">
                <i class="fas fa-plus"></i> Record Payment
            </a>
        <?php endif; ?>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h6>Total Outstanding</h6>
                    <h3><?php echo format_currency($total_outstanding); ?></h3>
                    <small><?php echo count($credit_sales); ?> invoices</small>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h6>Total Overdue</h6>
                    <h3><?php echo format_currency($total_overdue); ?></h3>
                    <small>Requires immediate attention</small>
                </div>
            </div>
        </div>
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
                <div class="col-md-3">
                    <select class="form-select" name="customer_id">
                        <option value="">All Customers</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo $customer['customer_id']; ?>"
                                <?php echo ($customer_id == $customer['customer_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($customer['customer_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="status">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo ($status == 'pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="partial" <?php echo ($status == 'partial') ? 'selected' : ''; ?>>Partial</option>
                        <option value="overdue" <?php echo ($status == 'overdue') ? 'selected' : ''; ?>>Overdue</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="aging">
                        <option value="">All Aging</option>
                        <option value="Current" <?php echo ($aging == 'Current') ? 'selected' : ''; ?>>Current</option>
                        <option value="Due Soon" <?php echo ($aging == 'Due Soon') ? 'selected' : ''; ?>>Due Soon</option>
                        <option value="Overdue" <?php echo ($aging == 'Overdue') ? 'selected' : ''; ?>>Overdue</option>
                        <option value="High Risk" <?php echo ($aging == 'High Risk') ? 'selected' : ''; ?>>High Risk</option>
                        <option value="Critical" <?php echo ($aging == 'Critical') ? 'selected' : ''; ?>>Critical</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Credit Sales Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Customer</th>
                            <th>Branch</th>
                            <th>Invoice Date</th>
                            <th>Due Date</th>
                            <th>Total Amount</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Days</th>
                            <th>Aging</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($credit_sales)): ?>
                            <tr>
                                <td colspan="11" class="text-center text-muted">No credit sales found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($credit_sales as $sale): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($sale['invoice_number']); ?></strong></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($sale['customer_code']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($sale['customer_name']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($sale['branch_name']); ?></td>
                                    <td><?php echo format_date($sale['invoice_date']); ?></td>
                                    <td><?php echo format_date($sale['due_date']); ?></td>
                                    <td><?php echo format_currency($sale['total_amount']); ?></td>
                                    <td><?php echo format_currency($sale['paid_amount']); ?></td>
                                    <td><strong><?php echo format_currency($sale['balance_amount']); ?></strong></td>
                                    <td>
                                        <?php if ($sale['overdue_days'] > 0): ?>
                                            <span class="badge bg-danger"><?php echo $sale['overdue_days']; ?> overdue</span>
                                        <?php else: ?>
                                            <span class="badge bg-success"><?php echo abs($sale['overdue_days']); ?> left</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php
                                                                echo $sale['aging_category'] === 'Critical' ? 'danger' : ($sale['aging_category'] === 'High Risk' ? 'warning' : ($sale['aging_category'] === 'Overdue' ? 'orange' : 'success'));
                                                                ?>">
                                            <?php echo $sale['aging_category']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="view_credit.php?id=<?php echo $sale['credit_id']; ?>"
                                            class="btn btn-sm btn-info" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($sale['balance_amount'] > 0 && has_permission('credit', 'create')): ?>
                                            <a href="record_payment.php?credit_id=<?php echo $sale['credit_id']; ?>"
                                                class="btn btn-sm btn-success" title="Record Payment">
                                                <i class="fas fa-dollar-sign"></i>
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