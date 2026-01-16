<?php

/**
 * Accounting Reports Page
 * Financial reports and statements
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../classes/Report.php';

require_login();
require_permission('accounting', 'read');

$database = new Database();
$db = $database->getConnection();
$report = new Report($db);

$page_title = 'Accounting Reports';

// Get report parameters
$report_type = $_GET['type'] ?? 'profit_loss';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

$report_data = null;

// Generate report based on type
switch ($report_type) {
    case 'profit_loss':
        $report_data = $report->getProfitLoss($date_from, $date_to);
        break;

    case 'credit_aging':
        $report_data = $report->getCreditAgingReport();
        break;

    case 'customer_ledger':
        $customer_id = $_GET['customer_id'] ?? null;
        if ($customer_id) {
            $query = "SELECT si.invoice_number, si.invoice_date, si.total_amount, si.paid_amount, si.balance_amount,
                      si.payment_method, si.status
                      FROM sales_invoices si
                      WHERE si.customer_id = :customer_id
                      AND si.invoice_date BETWEEN :date_from AND :date_to
                      ORDER BY si.invoice_date DESC";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':customer_id' => $customer_id,
                ':date_from' => $date_from,
                ':date_to' => $date_to
            ]);
            $report_data = $stmt->fetchAll();
        }
        break;

    case 'assets':
        $query = "SELECT fa.*, b.branch_name,
                  (fa.purchase_value - fa.accumulated_depreciation) as current_book_value
                  FROM fixed_assets fa
                  INNER JOIN branches b ON fa.branch_id = b.branch_id
                  WHERE fa.status = 'active'
                  ORDER BY b.branch_name, fa.asset_name";
        $report_data = $db->query($query)->fetchAll();
        break;
}

// Get customers for ledger filter
$customers = $db->query("SELECT customer_id, customer_code, customer_name FROM customers 
                         WHERE status = 'active' ORDER BY customer_name")->fetchAll();

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-file-invoice-dollar"></i> Accounting Reports</h2>
        <a href="../reports/index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> All Reports
        </a>
    </div>

    <!-- Report Type Selector -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Report Type</label>
                    <select class="form-select" name="type" onchange="this.form.submit()">
                        <option value="profit_loss" <?php echo $report_type == 'profit_loss' ? 'selected' : ''; ?>>Profit & Loss</option>
                        <option value="credit_aging" <?php echo $report_type == 'credit_aging' ? 'selected' : ''; ?>>Credit Aging</option>
                        <option value="customer_ledger" <?php echo $report_type == 'customer_ledger' ? 'selected' : ''; ?>>Customer Ledger</option>
                        <option value="assets" <?php echo $report_type == 'assets' ? 'selected' : ''; ?>>Fixed Assets Register</option>
                    </select>
                </div>

                <?php if ($report_type !== 'assets'): ?>
                    <div class="col-md-2">
                        <label class="form-label">From Date</label>
                        <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">To Date</label>
                        <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
                    </div>
                <?php endif; ?>

                <?php if ($report_type == 'customer_ledger'): ?>
                    <div class="col-md-3">
                        <label class="form-label">Customer</label>
                        <select class="form-select" name="customer_id">
                            <option value="">Select Customer</option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?php echo $customer['customer_id']; ?>"
                                    <?php echo ($_GET['customer_id'] ?? '') == $customer['customer_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($customer['customer_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-sync"></i> Generate
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Report Output -->
    <div class="card">
        <div class="card-body">
            <?php if ($report_type == 'profit_loss' && $report_data): ?>
                <h5 class="mb-3">Profit & Loss Statement</h5>
                <p class="text-muted">Period: <?php echo format_date($date_from); ?> to <?php echo format_date($date_to); ?></p>

                <table class="table table-bordered">
                    <tr class="table-primary">
                        <th colspan="2">REVENUE</th>
                    </tr>
                    <tr>
                        <td>Total Sales Revenue</td>
                        <td class="text-end"><?php echo format_currency($report_data['revenue']['total_revenue'] ?? 0); ?></td>
                    </tr>
                    <tr>
                        <td class="ps-4">Cash Sales</td>
                        <td class="text-end"><?php echo format_currency($report_data['revenue']['cash_revenue'] ?? 0); ?></td>
                    </tr>
                    <tr>
                        <td class="ps-4">Credit Sales</td>
                        <td class="text-end"><?php echo format_currency($report_data['revenue']['credit_revenue'] ?? 0); ?></td>
                    </tr>
                    <tr class="fw-bold table-light">
                        <td>TOTAL REVENUE</td>
                        <td class="text-end"><?php echo format_currency($report_data['revenue']['total_revenue'] ?? 0); ?></td>
                    </tr>
                </table>

            <?php elseif ($report_type == 'credit_aging' && $report_data): ?>
                <h5 class="mb-3">Credit Aging Report</h5>
                <p class="text-muted">As of <?php echo format_date(date('Y-m-d')); ?></p>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Customer Code</th>
                                <th>Customer Name</th>
                                <th>Total Outstanding</th>
                                <th>Current</th>
                                <th>1-30 Days</th>
                                <th>31-60 Days</th>
                                <th>61-90 Days</th>
                                <th>Over 90 Days</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $total_outstanding = 0;
                            $total_current = 0;
                            $total_1_30 = 0;
                            $total_31_60 = 0;
                            $total_61_90 = 0;
                            $total_over_90 = 0;

                            foreach ($report_data as $row):
                                $total_outstanding += $row['total_outstanding'];
                                $total_current += $row['current'];
                                $total_1_30 += $row['days_1_30'];
                                $total_31_60 += $row['days_31_60'];
                                $total_61_90 += $row['days_61_90'];
                                $total_over_90 += $row['days_over_90'];
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['customer_code']); ?></td>
                                    <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                    <td class="fw-bold"><?php echo format_currency($row['total_outstanding']); ?></td>
                                    <td><?php echo format_currency($row['current']); ?></td>
                                    <td><?php echo format_currency($row['days_1_30']); ?></td>
                                    <td><?php echo format_currency($row['days_31_60']); ?></td>
                                    <td><?php echo format_currency($row['days_61_90']); ?></td>
                                    <td class="text-danger"><?php echo format_currency($row['days_over_90']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="fw-bold table-light">
                                <td colspan="2">TOTAL</td>
                                <td><?php echo format_currency($total_outstanding); ?></td>
                                <td><?php echo format_currency($total_current); ?></td>
                                <td><?php echo format_currency($total_1_30); ?></td>
                                <td><?php echo format_currency($total_31_60); ?></td>
                                <td><?php echo format_currency($total_61_90); ?></td>
                                <td class="text-danger"><?php echo format_currency($total_over_90); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($report_type == 'customer_ledger' && $report_data): ?>
                <h5 class="mb-3">Customer Ledger</h5>
                <p class="text-muted">Period: <?php echo format_date($date_from); ?> to <?php echo format_date($date_to); ?></p>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Date</th>
                                <th>Payment Method</th>
                                <th>Total Amount</th>
                                <th>Paid Amount</th>
                                <th>Balance</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $total_amount = 0;
                            $total_paid = 0;
                            $total_balance = 0;

                            foreach ($report_data as $row):
                                $total_amount += $row['total_amount'];
                                $total_paid += $row['paid_amount'];
                                $total_balance += $row['balance_amount'];
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['invoice_number']); ?></td>
                                    <td><?php echo format_date($row['invoice_date']); ?></td>
                                    <td><?php echo ucfirst(str_replace('_', ' ', $row['payment_method'])); ?></td>
                                    <td><?php echo format_currency($row['total_amount']); ?></td>
                                    <td><?php echo format_currency($row['paid_amount']); ?></td>
                                    <td><?php echo format_currency($row['balance_amount']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php
                                                                echo $row['status'] === 'paid' ? 'success' : ($row['status'] === 'pending' ? 'warning' : 'info');
                                                                ?>">
                                            <?php echo ucfirst($row['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="fw-bold table-light">
                                <td colspan="3">TOTAL</td>
                                <td><?php echo format_currency($total_amount); ?></td>
                                <td><?php echo format_currency($total_paid); ?></td>
                                <td><?php echo format_currency($total_balance); ?></td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($report_type == 'assets' && $report_data): ?>
                <h5 class="mb-3">Fixed Assets Register</h5>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Asset Code</th>
                                <th>Asset Name</th>
                                <th>Branch</th>
                                <th>Category</th>
                                <th>Purchase Date</th>
                                <th>Purchase Value</th>
                                <th>Accumulated Depreciation</th>
                                <th>Book Value</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $total_purchase = 0;
                            $total_depreciation = 0;
                            $total_book_value = 0;

                            foreach ($report_data as $asset):
                                $total_purchase += $asset['purchase_value'];
                                $total_depreciation += $asset['accumulated_depreciation'];
                                $total_book_value += $asset['current_book_value'];
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($asset['asset_code']); ?></td>
                                    <td><?php echo htmlspecialchars($asset['asset_name']); ?></td>
                                    <td><?php echo htmlspecialchars($asset['branch_name']); ?></td>
                                    <td><?php echo ucfirst($asset['asset_category']); ?></td>
                                    <td><?php echo format_date($asset['purchase_date']); ?></td>
                                    <td><?php echo format_currency($asset['purchase_value']); ?></td>
                                    <td><?php echo format_currency($asset['accumulated_depreciation']); ?></td>
                                    <td class="fw-bold"><?php echo format_currency($asset['current_book_value']); ?></td>
                                    <td>
                                        <span class="badge bg-success"><?php echo ucfirst($asset['status']); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="fw-bold table-light">
                                <td colspan="5">TOTAL</td>
                                <td><?php echo format_currency($total_purchase); ?></td>
                                <td><?php echo format_currency($total_depreciation); ?></td>
                                <td><?php echo format_currency($total_book_value); ?></td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Please select report parameters and click Generate.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>