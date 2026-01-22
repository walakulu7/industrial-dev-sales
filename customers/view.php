<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../includes/functions.php';

// Check authentication
require_login();
require_permission('customers', 'read');

$database = new Database();
$db = $database->getConnection();

$customer_id = $_GET['id'] ?? 0;

if (!$customer_id) {
    $_SESSION['error'] = 'Invalid customer ID';
    header("Location: list.php");
    exit();
}

// Get customer details
$query = "SELECT * FROM customers WHERE customer_id = :id";
$stmt = $db->prepare($query);
$stmt->execute([':id' => $customer_id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    $_SESSION['error'] = 'Customer not found';
    header("Location: list.php");
    exit();
}

// Get customer sales summary
$sales_query = "SELECT 
    COUNT(DISTINCT si.invoice_id) as total_invoices,
    COALESCE(SUM(si.total_amount), 0) as total_sales,
    COALESCE(SUM(si.paid_amount), 0) as total_paid,
    COALESCE(SUM(si.balance_amount), 0) as outstanding_balance,
    MIN(si.invoice_date) as first_purchase,
    MAX(si.invoice_date) as last_purchase
FROM sales_invoices si
WHERE si.customer_id = :customer_id";

$stmt = $db->prepare($sales_query);
$stmt->execute([':customer_id' => $customer_id]);
$sales_summary = $stmt->fetch(PDO::FETCH_ASSOC);

// Get recent invoices
$invoices_query = "SELECT 
    si.*,
    b.branch_name,
    u.full_name as created_by_name
FROM sales_invoices si
JOIN branches b ON si.branch_id = b.branch_id
JOIN users u ON si.created_by = u.user_id
WHERE si.customer_id = :customer_id
ORDER BY si.invoice_date DESC, si.invoice_id DESC
LIMIT 10";

$stmt = $db->prepare($invoices_query);
$stmt->execute([':customer_id' => $customer_id]);
$recent_invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get credit sales
$credit_query = "SELECT 
    cs.*,
    si.invoice_number,
    si.invoice_date,
    DATEDIFF(CURDATE(), cs.due_date) as overdue_days
FROM credit_sales cs
JOIN sales_invoices si ON cs.invoice_id = si.invoice_id
WHERE cs.customer_id = :customer_id
AND cs.status IN ('pending', 'partial', 'overdue')
ORDER BY cs.due_date ASC";

$stmt = $db->prepare($credit_query);
$stmt->execute([':customer_id' => $customer_id]);
$credit_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get payment history
$payments_query = "SELECT 
    cp.*,
    cs.invoice_id,
    si.invoice_number,
    u.full_name as created_by_name
FROM credit_payments cp
JOIN credit_sales cs ON cp.credit_id = cs.credit_id
JOIN sales_invoices si ON cs.invoice_id = si.invoice_id
JOIN users u ON cp.created_by = u.user_id
WHERE cs.customer_id = :customer_id
ORDER BY cp.payment_date DESC
LIMIT 10";

$stmt = $db->prepare($payments_query);
$stmt->execute([':customer_id' => $customer_id]);
$recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate credit utilization
$credit_utilization = $customer['credit_limit'] > 0
    ? ($sales_summary['outstanding_balance'] / $customer['credit_limit']) * 100
    : 0;

$page_title = 'Customer Details';
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fas fa-user me-2"></i><?php echo htmlspecialchars($customer['customer_name']); ?></h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="list.php">Customers</a></li>
                    <li class="breadcrumb-item active"><?php echo htmlspecialchars($customer['customer_name']); ?></li>
                </ol>
            </nav>
        </div>
        <div class="col-md-4 text-end">
            <a href="edit.php?id=<?php echo $customer_id; ?>" class="btn btn-primary">
                <i class="fas fa-edit me-1"></i>Edit Customer
            </a>
            <a href="list.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i>Back to List
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Customer Information -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Customer Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr>
                            <th width="40%">Customer Code:</th>
                            <td><strong><?php echo htmlspecialchars($customer['customer_code']); ?></strong></td>
                        </tr>
                        <tr>
                            <th>Customer Name:</th>
                            <td><?php echo htmlspecialchars($customer['customer_name']); ?></td>
                        </tr>
                        <tr>
                            <th>Type:</th>
                            <td>
                                <?php
                                $type_badges = [
                                    'retail' => 'success',
                                    'wholesale' => 'primary',
                                    'distributor' => 'info',
                                    'manufacturer' => 'warning'
                                ];
                                $badge_color = $type_badges[$customer['customer_type']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $badge_color; ?>">
                                    <?php echo ucfirst($customer['customer_type']); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>Contact Person:</th>
                            <td><?php echo htmlspecialchars($customer['contact_person'] ?? '-'); ?></td>
                        </tr>
                        <tr>
                            <th>Phone:</th>
                            <td>
                                <?php if ($customer['phone']): ?>
                                    <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($customer['phone']); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Email:</th>
                            <td>
                                <?php if ($customer['email']): ?>
                                    <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($customer['email']); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Address:</th>
                            <td><?php echo htmlspecialchars($customer['address'] ?? '-'); ?></td>
                        </tr>
                        <tr>
                            <th>City:</th>
                            <td><?php echo htmlspecialchars($customer['city'] ?? '-'); ?></td>
                        </tr>
                        <tr>
                            <th>Status:</th>
                            <td>
                                <?php
                                $status_badges = [
                                    'active' => 'success',
                                    'inactive' => 'secondary',
                                    'suspended' => 'danger'
                                ];
                                $status_color = $status_badges[$customer['status']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $status_color; ?>">
                                    <?php echo ucfirst($customer['status']); ?>
                                </span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-warning">
                    <h6 class="mb-0"><i class="fas fa-credit-card me-2"></i>Credit Information</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr>
                            <th>Credit Limit:</th>
                            <td>Rs. <?php echo number_format($customer['credit_limit'], 2); ?></td>
                        </tr>
                        <tr>
                            <th>Credit Days:</th>
                            <td><?php echo $customer['credit_days']; ?> days</td>
                        </tr>
                        <tr>
                            <th>Outstanding:</th>
                            <td>
                                <strong class="<?php echo $sales_summary['outstanding_balance'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                    Rs. <?php echo number_format($sales_summary['outstanding_balance'], 2); ?>
                                </strong>
                            </td>
                        </tr>
                        <tr>
                            <th>Available Credit:</th>
                            <td>
                                Rs. <?php echo number_format(max(0, $customer['credit_limit'] - $sales_summary['outstanding_balance']), 2); ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Utilization:</th>
                            <td>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar <?php echo $credit_utilization > 80 ? 'bg-danger' : ($credit_utilization > 50 ? 'bg-warning' : 'bg-success'); ?>"
                                        style="width: <?php echo min($credit_utilization, 100); ?>%">
                                        <?php echo number_format($credit_utilization, 1); ?>%
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="fas fa-clock me-2"></i>Account History</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr>
                            <th>Customer Since:</th>
                            <td><?php echo date('d M Y', strtotime($customer['created_at'])); ?></td>
                        </tr>
                        <tr>
                            <th>First Purchase:</th>
                            <td><?php echo $sales_summary['first_purchase'] ? date('d M Y', strtotime($sales_summary['first_purchase'])) : 'N/A'; ?></td>
                        </tr>
                        <tr>
                            <th>Last Purchase:</th>
                            <td><?php echo $sales_summary['last_purchase'] ? date('d M Y', strtotime($sales_summary['last_purchase'])) : 'N/A'; ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Sales & Activity -->
        <div class="col-md-8">
            <!-- Sales Summary -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h6 class="card-title">Total Invoices</h6>
                            <h3><?php echo number_format($sales_summary['total_invoices']); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h6 class="card-title">Total Sales</h6>
                            <h3>Rs. <?php echo number_format($sales_summary['total_sales'], 2); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-<?php echo $sales_summary['outstanding_balance'] > 0 ? 'danger' : 'info'; ?> text-white">
                        <div class="card-body">
                            <h6 class="card-title">Outstanding</h6>
                            <h3>Rs. <?php echo number_format($sales_summary['outstanding_balance'], 2); ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Credit Sales -->
            <?php if (count($credit_sales) > 0): ?>
                <div class="card mb-4">
                    <div class="card-header bg-warning">
                        <h5 class="mb-0"><i class="fas fa-file-invoice-dollar me-2"></i>Pending Credit Sales</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>Invoice</th>
                                        <th>Invoice Date</th>
                                        <th>Due Date</th>
                                        <th>Amount</th>
                                        <th>Paid</th>
                                        <th>Balance</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($credit_sales as $cs): ?>
                                        <tr class="<?php echo $cs['overdue_days'] > 0 ? 'table-danger' : ''; ?>">
                                            <td>
                                                <a href="../sales/view_invoice.php?id=<?php echo $cs['invoice_id']; ?>">
                                                    <?php echo htmlspecialchars($cs['invoice_number']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo date('d M Y', strtotime($cs['invoice_date'])); ?></td>
                                            <td>
                                                <?php echo date('d M Y', strtotime($cs['due_date'])); ?>
                                                <?php if ($cs['overdue_days'] > 0): ?>
                                                    <br><small class="text-danger"><?php echo $cs['overdue_days']; ?> days overdue</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>Rs. <?php echo number_format($cs['total_amount'], 2); ?></td>
                                            <td>Rs. <?php echo number_format($cs['paid_amount'], 2); ?></td>
                                            <td><strong>Rs. <?php echo number_format($cs['balance_amount'], 2); ?></strong></td>
                                            <td>
                                                <span class="badge bg-<?php
                                                                        echo $cs['status'] === 'overdue' ? 'danger' : ($cs['status'] === 'partial' ? 'warning' : 'info');
                                                                        ?>">
                                                    <?php echo ucfirst($cs['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Recent Invoices -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-file-invoice me-2"></i>Recent Invoices</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Date</th>
                                    <th>Branch</th>
                                    <th>Amount</th>
                                    <th>Payment</th>
                                    <th>Balance</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_invoices as $invoice): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($invoice['invoice_number']); ?></strong></td>
                                        <td><?php echo date('d M Y', strtotime($invoice['invoice_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($invoice['branch_name']); ?></td>
                                        <td>Rs. <?php echo number_format($invoice['total_amount'], 2); ?></td>
                                        <td>
                                            <span class="badge bg-<?php
                                                                    echo $invoice['payment_method'] === 'cash' ? 'success' : ($invoice['payment_method'] === 'credit' ? 'warning' : 'info');
                                                                    ?>">
                                                <?php echo ucfirst($invoice['payment_method']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($invoice['balance_amount'] > 0): ?>
                                                <span class="text-danger">Rs. <?php echo number_format($invoice['balance_amount'], 2); ?></span>
                                            <?php else: ?>
                                                <span class="text-success">Paid</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php
                                                                    echo $invoice['status'] === 'paid' ? 'success' : ($invoice['status'] === 'partial' ? 'warning' : ($invoice['status'] === 'cancelled' ? 'danger' : 'info'));
                                                                    ?>">
                                                <?php echo ucfirst($invoice['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="../sales/view_invoice.php?id=<?php echo $invoice['invoice_id']; ?>"
                                                class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Recent Payments -->
            <?php if (count($recent_payments) > 0): ?>
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>Recent Payments</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Invoice</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Reference</th>
                                        <th>Recorded By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_payments as $payment): ?>
                                        <tr>
                                            <td><?php echo date('d M Y', strtotime($payment['payment_date'])); ?></td>
                                            <td>
                                                <a href="../sales/view_invoice.php?id=<?php echo $payment['invoice_id']; ?>">
                                                    <?php echo htmlspecialchars($payment['invoice_number']); ?>
                                                </a>
                                            </td>
                                            <td><strong>Rs. <?php echo number_format($payment['amount'], 2); ?></strong></td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($payment['payment_reference'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($payment['created_by_name']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
