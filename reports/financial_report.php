<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../includes/functions.php';

// Check authentication
require_login();

// Check permission
require_permission('accounting', 'read');

$database = new Database();
$db = $database->getConnection();

// Get filter parameters
$report_type = $_GET['report_type'] ?? $_GET['type'] ?? 'profit_loss';
$period_type = $_GET['period_type'] ?? 'monthly';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$branch_id = $_GET['branch_id'] ?? '';

// Get branches
$branches_query = "SELECT * FROM branches WHERE status = 'active' ORDER BY branch_name";
$branches = $db->query($branches_query)->fetchAll();

$report_data = [];
$report_title = '';
$financial_summary = [];

switch ($report_type) {
    case 'profit_loss':
        $report_title = 'Profit & Loss Statement';

        // Get Revenue
        $revenue_query = "SELECT 
            COALESCE(SUM(total_amount), 0) as total_revenue,
            COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN total_amount ELSE 0 END), 0) as cash_sales,
            COALESCE(SUM(CASE WHEN payment_method = 'credit' THEN total_amount ELSE 0 END), 0) as credit_sales
        FROM sales_invoices
        WHERE invoice_date BETWEEN :date_from AND :date_to
        AND status != 'cancelled'";

        $params = [':date_from' => $date_from, ':date_to' => $date_to];
        if ($branch_id) {
            $revenue_query .= " AND branch_id = :branch_id";
            $params[':branch_id'] = $branch_id;
        }

        $stmt = $db->prepare($revenue_query);
        $stmt->execute($params);
        $revenue = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get Cost of Goods Sold (from stock transactions)
        $cogs_query = "SELECT 
            COALESCE(SUM(quantity * COALESCE(unit_cost, 0)), 0) as cogs
        FROM stock_transactions
        WHERE transaction_type IN ('issue', 'production_input')
        AND transaction_date BETWEEN :date_from AND :date_to";

        $params = [':date_from' => $date_from, ':date_to' => $date_to];
        $stmt = $db->prepare($cogs_query);
        $stmt->execute($params);
        $cogs_data = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get Expenses (simplified - would need expense tracking system)
        // Using depreciation as example expense
        $expenses_query = "SELECT 
            COALESCE(SUM(depreciation_amount), 0) as depreciation_expense
        FROM depreciation_records
        WHERE CONCAT(period_year, '-', LPAD(period_month, 2, '0'), '-01') BETWEEN :date_from AND :date_to";

        $params = [':date_from' => $date_from, ':date_to' => $date_to];
        $stmt = $db->prepare($expenses_query);
        $stmt->execute($params);
        $expenses = $stmt->fetch(PDO::FETCH_ASSOC);

        // Calculate P&L
        $financial_summary = [
            'revenue' => [
                'cash_sales' => $revenue['cash_sales'],
                'credit_sales' => $revenue['credit_sales'],
                'total_revenue' => $revenue['total_revenue']
            ],
            'cogs' => $cogs_data['cogs'],
            'gross_profit' => $revenue['total_revenue'] - $cogs_data['cogs'],
            'expenses' => [
                'depreciation' => $expenses['depreciation_expense'],
                'total_expenses' => $expenses['depreciation_expense']
            ],
            'net_profit' => ($revenue['total_revenue'] - $cogs_data['cogs'] - $expenses['depreciation_expense'])
        ];

        $financial_summary['gross_profit_margin'] = $revenue['total_revenue'] > 0
            ? ($financial_summary['gross_profit'] / $revenue['total_revenue']) * 100
            : 0;
        $financial_summary['net_profit_margin'] = $revenue['total_revenue'] > 0
            ? ($financial_summary['net_profit'] / $revenue['total_revenue']) * 100
            : 0;
        break;

    case 'sales_summary':
        $report_title = 'Sales Summary Report';

        $query = "SELECT 
            DATE(invoice_date) as sale_date,
            COUNT(invoice_id) as invoice_count,
            SUM(CASE WHEN payment_method = 'cash' THEN total_amount ELSE 0 END) as cash_sales,
            SUM(CASE WHEN payment_method = 'credit' THEN total_amount ELSE 0 END) as credit_sales,
            SUM(total_amount) as total_sales,
            SUM(paid_amount) as total_paid,
            SUM(balance_amount) as total_outstanding
        FROM sales_invoices
        WHERE invoice_date BETWEEN :date_from AND :date_to
        AND status != 'cancelled'";

        $params = [':date_from' => $date_from, ':date_to' => $date_to];
        if ($branch_id) {
            $query .= " AND branch_id = :branch_id";
            $params[':branch_id'] = $branch_id;
        }

        $query .= " GROUP BY DATE(invoice_date) ORDER BY sale_date DESC";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate totals
        $financial_summary = [
            'total_invoices' => array_sum(array_column($report_data, 'invoice_count')),
            'total_sales' => array_sum(array_column($report_data, 'total_sales')),
            'cash_sales' => array_sum(array_column($report_data, 'cash_sales')),
            'credit_sales' => array_sum(array_column($report_data, 'credit_sales')),
            'total_paid' => array_sum(array_column($report_data, 'total_paid')),
            'total_outstanding' => array_sum(array_column($report_data, 'total_outstanding'))
        ];
        break;

    case 'credit_aging':
        $report_title = 'Credit Aging Report';

        $query = "SELECT 
            c.customer_code,
            c.customer_name,
            c.phone,
            c.credit_limit,
            cs.invoice_id,
            si.invoice_number,
            cs.invoice_date,
            cs.due_date,
            cs.total_amount,
            cs.paid_amount,
            cs.balance_amount,
            DATEDIFF(CURDATE(), cs.due_date) as overdue_days,
            CASE 
                WHEN DATEDIFF(CURDATE(), cs.due_date) <= 0 THEN 'Current'
                WHEN DATEDIFF(CURDATE(), cs.due_date) <= 30 THEN '1-30 Days'
                WHEN DATEDIFF(CURDATE(), cs.due_date) <= 60 THEN '31-60 Days'
                WHEN DATEDIFF(CURDATE(), cs.due_date) <= 90 THEN '61-90 Days'
                ELSE 'Over 90 Days'
            END as aging_category
        FROM credit_sales cs
        JOIN customers c ON cs.customer_id = c.customer_id
        JOIN sales_invoices si ON cs.invoice_id = si.invoice_id
        WHERE cs.status IN ('pending', 'partial', 'overdue')
        AND cs.balance_amount > 0
        ORDER BY overdue_days DESC, c.customer_name";

        $stmt = $db->prepare($query);
        $stmt->execute();
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate aging summary
        $aging_summary = [
            'current' => 0,
            '1-30' => 0,
            '31-60' => 0,
            '61-90' => 0,
            'over_90' => 0,
            'total' => 0
        ];

        foreach ($report_data as $row) {
            $aging_summary['total'] += $row['balance_amount'];
            switch ($row['aging_category']) {
                case 'Current':
                    $aging_summary['current'] += $row['balance_amount'];
                    break;
                case '1-30 Days':
                    $aging_summary['1-30'] += $row['balance_amount'];
                    break;
                case '31-60 Days':
                    $aging_summary['31-60'] += $row['balance_amount'];
                    break;
                case '61-90 Days':
                    $aging_summary['61-90'] += $row['balance_amount'];
                    break;
                case 'Over 90 Days':
                    $aging_summary['over_90'] += $row['balance_amount'];
                    break;
            }
        }

        $financial_summary = $aging_summary;
        break;

    case 'payment_collection':
        $report_title = 'Payment Collection Report';

        $query = "SELECT 
            cp.payment_date,
            c.customer_code,
            c.customer_name,
            si.invoice_number,
            cp.payment_method,
            cp.payment_reference,
            cp.amount,
            u.full_name as collected_by
        FROM credit_payments cp
        JOIN credit_sales cs ON cp.credit_id = cs.credit_id
        JOIN customers c ON cs.customer_id = c.customer_id
        JOIN sales_invoices si ON cs.invoice_id = si.invoice_id
        JOIN users u ON cp.created_by = u.user_id
        WHERE cp.payment_date BETWEEN :date_from AND :date_to
        ORDER BY cp.payment_date DESC, cp.payment_id DESC";

        $params = [':date_from' => $date_from, ':date_to' => $date_to];
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate totals by payment method
        $financial_summary = [
            'total_collected' => array_sum(array_column($report_data, 'amount')),
            'total_payments' => count($report_data),
            'by_method' => []
        ];

        foreach ($report_data as $row) {
            $method = $row['payment_method'];
            if (!isset($financial_summary['by_method'][$method])) {
                $financial_summary['by_method'][$method] = 0;
            }
            $financial_summary['by_method'][$method] += $row['amount'];
        }
        break;

    case 'customer_statement':
        $report_title = 'Customer Account Statement';

        $query = "SELECT 
            c.customer_code,
            c.customer_name,
            c.credit_limit,
            COUNT(DISTINCT si.invoice_id) as total_invoices,
            COALESCE(SUM(si.total_amount), 0) as total_sales,
            COALESCE(SUM(si.paid_amount), 0) as total_paid,
            COALESCE(SUM(si.balance_amount), 0) as outstanding_balance,
            MAX(si.invoice_date) as last_purchase_date
        FROM customers c
        LEFT JOIN sales_invoices si ON c.customer_id = si.customer_id 
            AND si.invoice_date BETWEEN :date_from AND :date_to
            AND si.status != 'cancelled'
        WHERE c.status = 'active'";

        $params = [':date_from' => $date_from, ':date_to' => $date_to];

        $query .= " GROUP BY c.customer_id
                   HAVING total_invoices > 0 OR outstanding_balance > 0
                   ORDER BY outstanding_balance DESC, total_sales DESC";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate totals
        $financial_summary = [
            'total_customers' => count($report_data),
            'total_sales' => array_sum(array_column($report_data, 'total_sales')),
            'total_outstanding' => array_sum(array_column($report_data, 'outstanding_balance'))
        ];
        break;

    case 'asset_register':
        $report_title = 'Fixed Assets Register';

        $query = "SELECT 
            b.branch_name,
            fa.asset_code,
            fa.asset_name,
            fa.asset_category,
            fa.purchase_date,
            fa.purchase_value,
            fa.salvage_value,
            fa.useful_life_years,
            fa.depreciation_method,
            fa.accumulated_depreciation,
            fa.book_value,
            fa.status
        FROM fixed_assets fa
        JOIN branches b ON fa.branch_id = b.branch_id
        WHERE 1=1";

        $params = [];
        if ($branch_id) {
            $query .= " AND fa.branch_id = :branch_id";
            $params[':branch_id'] = $branch_id;
        }

        $query .= " ORDER BY b.branch_name, fa.asset_category, fa.purchase_date DESC";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate totals
        $financial_summary = [
            'total_assets' => count($report_data),
            'total_purchase_value' => array_sum(array_column($report_data, 'purchase_value')),
            'total_accumulated_depreciation' => array_sum(array_column($report_data, 'accumulated_depreciation')),
            'total_book_value' => array_sum(array_column($report_data, 'book_value'))
        ];
        break;
}

$page_title = 'Financial Reports';
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fas fa-chart-line me-2"></i><?php echo $report_title; ?></h2>
            <?php if ($report_type !== 'credit_aging' && $report_type !== 'asset_register'): ?>
                <p class="text-muted">Period: <?php echo date('d M Y', strtotime($date_from)); ?> to <?php echo date('d M Y', strtotime($date_to)); ?></p>
            <?php endif; ?>
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
    <?php if (!empty($financial_summary)): ?>
        <div class="row mb-4 no-print">
            <?php if ($report_type === 'profit_loss'): ?>
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h6 class="card-title">Total Revenue</h6>
                            <h3>Rs. <?php echo number_format($financial_summary['revenue']['total_revenue'], 2); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h6 class="card-title">Gross Profit</h6>
                            <h3>Rs. <?php echo number_format($financial_summary['gross_profit'], 2); ?></h3>
                            <small><?php echo number_format($financial_summary['gross_profit_margin'], 1); ?>% margin</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <h6 class="card-title">Total Expenses</h6>
                            <h3>Rs. <?php echo number_format($financial_summary['expenses']['total_expenses'], 2); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-<?php echo $financial_summary['net_profit'] >= 0 ? 'success' : 'danger'; ?> text-white">
                        <div class="card-body">
                            <h6 class="card-title">Net Profit</h6>
                            <h3>Rs. <?php echo number_format($financial_summary['net_profit'], 2); ?></h3>
                            <small><?php echo number_format($financial_summary['net_profit_margin'], 1); ?>% margin</small>
                        </div>
                    </div>
                </div>

            <?php elseif ($report_type === 'sales_summary'): ?>
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h6 class="card-title">Total Sales</h6>
                            <h3>Rs. <?php echo number_format($financial_summary['total_sales'], 2); ?></h3>
                            <small><?php echo number_format($financial_summary['total_invoices']); ?> invoices</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h6 class="card-title">Cash Sales</h6>
                            <h3>Rs. <?php echo number_format($financial_summary['cash_sales'], 2); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <h6 class="card-title">Credit Sales</h6>
                            <h3>Rs. <?php echo number_format($financial_summary['credit_sales'], 2); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-danger text-white">
                        <div class="card-body">
                            <h6 class="card-title">Outstanding</h6>
                            <h3>Rs. <?php echo number_format($financial_summary['total_outstanding'], 2); ?></h3>
                        </div>
                    </div>
                </div>

            <?php elseif ($report_type === 'credit_aging'): ?>
                <div class="col-md-2">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h6 class="card-title">Current</h6>
                            <h4>Rs. <?php echo number_format($financial_summary['current'], 2); ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h6 class="card-title">1-30 Days</h6>
                            <h4>Rs. <?php echo number_format($financial_summary['1-30'], 2); ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <h6 class="card-title">31-60 Days</h6>
                            <h4>Rs. <?php echo number_format($financial_summary['31-60'], 2); ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-orange text-white">
                        <div class="card-body">
                            <h6 class="card-title">61-90 Days</h6>
                            <h4>Rs. <?php echo number_format($financial_summary['61-90'], 2); ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-danger text-white">
                        <div class="card-body">
                            <h6 class="card-title">Over 90 Days</h6>
                            <h4>Rs. <?php echo number_format($financial_summary['over_90'], 2); ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-dark text-white">
                        <div class="card-body">
                            <h6 class="card-title">Total</h6>
                            <h4>Rs. <?php echo number_format($financial_summary['total'], 2); ?></h4>
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
                        <option value="profit_loss" <?php echo $report_type === 'profit_loss' ? 'selected' : ''; ?>>Profit & Loss</option>
                        <option value="sales_summary" <?php echo $report_type === 'sales_summary' ? 'selected' : ''; ?>>Sales Summary</option>
                        <option value="credit_aging" <?php echo $report_type === 'credit_aging' ? 'selected' : ''; ?>>Credit Aging</option>
                        <option value="payment_collection" <?php echo $report_type === 'payment_collection' ? 'selected' : ''; ?>>Payment Collection</option>
                        <option value="customer_statement" <?php echo $report_type === 'customer_statement' ? 'selected' : ''; ?>>Customer Statement</option>
                        <option value="asset_register" <?php echo $report_type === 'asset_register' ? 'selected' : ''; ?>>Asset Register</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Branch</label>
                    <select class="form-select" name="branch_id">
                        <option value="">All Branches</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?php echo $branch['branch_id']; ?>"
                                <?php echo $branch_id == $branch['branch_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($branch['branch_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($report_type !== 'credit_aging' && $report_type !== 'asset_register'): ?>
                    <div class="col-md-2">
                        <label class="form-label">Date From</label>
                        <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Date To</label>
                        <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>" required>
                    </div>
                    <div class="col-md-2">
                    <?php else: ?>
                        <div class="col-md-4">
                        <?php endif; ?>
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-1"></i>Generate Report
                        </button>
                        </div>
            </form>
        </div>
    </div>

    <!-- Report Content -->
    <div class="card">
        <div class="card-body">
            <?php if ($report_type === 'profit_loss'): ?>
                <div class="table-responsive">
                    <table class="table" id="reportTable">
                        <tbody>
                            <tr class="table-primary">
                                <td colspan="2"><strong>REVENUE</strong></td>
                            </tr>
                            <tr>
                                <td class="ps-4">Cash Sales</td>
                                <td class="text-end">Rs. <?php echo number_format($financial_summary['revenue']['cash_sales'], 2); ?></td>
                            </tr>
                            <tr>
                                <td class="ps-4">Credit Sales</td>
                                <td class="text-end">Rs. <?php echo number_format($financial_summary['revenue']['credit_sales'], 2); ?></td>
                            </tr>
                            <tr class="table-secondary">
                                <td><strong>Total Revenue</strong></td>
                                <td class="text-end"><strong>Rs. <?php echo number_format($financial_summary['revenue']['total_revenue'], 2); ?></strong></td>
                            </tr>

                            <tr class="table-warning">
                                <td colspan="2"><strong>COST OF GOODS SOLD</strong></td>
                            </tr>
                            <tr>
                                <td class="ps-4">Cost of Goods Sold</td>
                                <td class="text-end">Rs. <?php echo number_format($financial_summary['cogs'], 2); ?></td>
                            </tr>

                            <tr class="table-info">
                                <td><strong>GROSS PROFIT</strong></td>
                                <td class="text-end"><strong>Rs. <?php echo number_format($financial_summary['gross_profit'], 2); ?></strong></td>
                            </tr>
                            <tr>
                                <td class="ps-4 text-muted">Gross Profit Margin</td>
                                <td class="text-end text-muted"><?php echo number_format($financial_summary['gross_profit_margin'], 2); ?>%</td>
                            </tr>

                            <tr class="table-warning">
                                <td colspan="2"><strong>OPERATING EXPENSES</strong></td>
                            </tr>
                            <tr>
                                <td class="ps-4">Depreciation Expense</td>
                                <td class="text-end">Rs. <?php echo number_format($financial_summary['expenses']['depreciation'], 2); ?></td>
                            </tr>
                            <tr class="table-secondary">
                                <td><strong>Total Expenses</strong></td>
                                <td class="text-end"><strong>Rs. <?php echo number_format($financial_summary['expenses']['total_expenses'], 2); ?></strong></td>
                            </tr>

                            <tr class="table-<?php echo $financial_summary['net_profit'] >= 0 ? 'success' : 'danger'; ?>">
                                <td><strong>NET PROFIT / (LOSS)</strong></td>
                                <td class="text-end"><strong>Rs. <?php echo number_format($financial_summary['net_profit'], 2); ?></strong></td>
                            </tr>
                            <tr>
                                <td class="ps-4 text-muted">Net Profit Margin</td>
                                <td class="text-end text-muted"><?php echo number_format($financial_summary['net_profit_margin'], 2); ?>%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($report_type === 'sales_summary' && count($report_data) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped" id="reportTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Invoices</th>
                                <th>Cash Sales</th>
                                <th>Credit Sales</th>
                                <th>Total Sales</th>
                                <th>Paid</th>
                                <th>Outstanding</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data as $row): ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($row['sale_date'])); ?></td>
                                    <td><?php echo number_format($row['invoice_count']); ?></td>
                                    <td>Rs. <?php echo number_format($row['cash_sales'], 2); ?></td>
                                    <td>Rs. <?php echo number_format($row['credit_sales'], 2); ?></td>
                                    <td><strong>Rs. <?php echo number_format($row['total_sales'], 2); ?></strong></td>
                                    <td>Rs. <?php echo number_format($row['total_paid'], 2); ?></td>
                                    <td>Rs. <?php echo number_format($row['total_outstanding'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-dark">
                            <tr>
                                <th>Total:</th>
                                <th><?php echo number_format($financial_summary['total_invoices']); ?></th>
                                <th>Rs. <?php echo number_format($financial_summary['cash_sales'], 2); ?></th>
                                <th>Rs. <?php echo number_format($financial_summary['credit_sales'], 2); ?></th>
                                <th>Rs. <?php echo number_format($financial_summary['total_sales'], 2); ?></th>
                                <th>Rs. <?php echo number_format($financial_summary['total_paid'], 2); ?></th>
                                <th>Rs. <?php echo number_format($financial_summary['total_outstanding'], 2); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

            <?php elseif ($report_type === 'credit_aging' && count($report_data) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-sm" id="reportTable">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Contact</th>
                                <th>Invoice #</th>
                                <th>Invoice Date</th>
                                <th>Due Date</th>
                                <th>Amount</th>
                                <th>Paid</th>
                                <th>Balance</th>
                                <th>Overdue Days</th>
                                <th>Aging</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data as $row): ?>
                                <tr class="<?php
                                            echo $row['overdue_days'] > 90 ? 'table-danger' : ($row['overdue_days'] > 60 ? 'table-warning' : '');
                                            ?>">
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['customer_code']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($row['customer_name']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['phone'] ?? 'N/A'); ?></td>
                                    <td>
                                        <a href="../sales/view_invoice.php?id=<?php echo $row['invoice_id']; ?>">
                                            <?php echo htmlspecialchars($row['invoice_number']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo date('d M Y', strtotime($row['invoice_date'])); ?></td>
                                    <td><?php echo date('d M Y', strtotime($row['due_date'])); ?></td>
                                    <td>Rs. <?php echo number_format($row['total_amount'], 2); ?></td>
                                    <td>Rs. <?php echo number_format($row['paid_amount'], 2); ?></td>
                                    <td><strong>Rs. <?php echo number_format($row['balance_amount'], 2); ?></strong></td>
                                    <td>
                                        <?php if ($row['overdue_days'] > 0): ?>
                                            <span class="badge bg-danger"><?php echo $row['overdue_days']; ?> days</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Current</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php
                                                                echo $row['aging_category'] === 'Current' ? 'success' : ($row['aging_category'] === '1-30 Days' ? 'info' : ($row['aging_category'] === '31-60 Days' ? 'warning' : 'danger'));
                                                                ?>">
                                            <?php echo $row['aging_category']; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-dark">
                            <tr>
                                <th colspan="7" class="text-end">Total Outstanding:</th>
                                <th colspan="3">Rs. <?php echo number_format($financial_summary['total'], 2); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

            <?php elseif ($report_type === 'payment_collection' && count($report_data) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped" id="reportTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Invoice #</th>
                                <th>Payment Method</th>
                                <th>Reference</th>
                                <th>Amount</th>
                                <th>Collected By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data as $row): ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($row['payment_date'])); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['customer_code']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($row['customer_name']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['invoice_number']); ?></td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo ucfirst(str_replace('_', ' ', $row['payment_method'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['payment_reference'] ?? '-'); ?></td>
                                    <td><strong>Rs. <?php echo number_format($row['amount'], 2); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['collected_by']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-dark">
                            <tr>
                                <th colspan="5" class="text-end">Total Collected:</th>
                                <th colspan="2">Rs. <?php echo number_format($financial_summary['total_collected'], 2); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <?php if (!empty($financial_summary['by_method'])): ?>
                    <div class="mt-4">
                        <h5>Collection by Payment Method</h5>
                        <table class="table table-sm w-50">
                            <thead>
                                <tr>
                                    <th>Payment Method</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($financial_summary['by_method'] as $method => $amount): ?>
                                    <tr>
                                        <td><?php echo ucfirst(str_replace('_', ' ', $method)); ?></td>
                                        <td class="text-end">Rs. <?php echo number_format($amount, 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

            <?php elseif ($report_type === 'customer_statement' && count($report_data) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped" id="reportTable">
                        <thead>
                            <tr>
                                <th>Customer Code</th>
                                <th>Customer Name</th>
                                <th>Credit Limit</th>
                                <th>Invoices</th>
                                <th>Total Sales</th>
                                <th>Paid</th>
                                <th>Outstanding</th>
                                <th>Last Purchase</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data as $row):
                                $credit_utilization = $row['credit_limit'] > 0
                                    ? ($row['outstanding_balance'] / $row['credit_limit']) * 100
                                    : 0;
                            ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($row['customer_code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                    <td>Rs. <?php echo number_format($row['credit_limit'], 2); ?></td>
                                    <td><?php echo number_format($row['total_invoices']); ?></td>
                                    <td>Rs. <?php echo number_format($row['total_sales'], 2); ?></td>
                                    <td>Rs. <?php echo number_format($row['total_paid'], 2); ?></td>
                                    <td>
                                        <strong>Rs. <?php echo number_format($row['outstanding_balance'], 2); ?></strong>
                                    </td>
                                    <td><?php echo $row['last_purchase_date'] ? date('d M Y', strtotime($row['last_purchase_date'])) : 'N/A'; ?></td>
                                    <td>
                                        <?php if ($credit_utilization > 90): ?>
                                            <span class="badge bg-danger">High Risk</span>
                                        <?php elseif ($credit_utilization > 70): ?>
                                            <span class="badge bg-warning">Warning</span>
                                        <?php elseif ($row['outstanding_balance'] > 0): ?>
                                            <span class="badge bg-info">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Clear</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-dark">
                            <tr>
                                <th colspan="4" class="text-end">Total (<?php echo $financial_summary['total_customers']; ?> customers):</th>
                                <th>Rs. <?php echo number_format($financial_summary['total_sales'], 2); ?></th>
                                <th colspan="2">Rs. <?php echo number_format($financial_summary['total_outstanding'], 2); ?></th>
                                <th colspan="2"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

            <?php elseif ($report_type === 'asset_register' && count($report_data) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-sm" id="reportTable">
                        <thead>
                            <tr>
                                <th>Branch</th>
                                <th>Asset Code</th>
                                <th>Asset Name</th>
                                <th>Category</th>
                                <th>Purchase Date</th>
                                <th>Purchase Value</th>
                                <th>Salvage Value</th>
                                <th>Useful Life</th>
                                <th>Method</th>
                                <th>Accumulated Dep.</th>
                                <th>Book Value</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['branch_name']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($row['asset_code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['asset_name']); ?></td>
                                    <td>
                                        <?php
                                        $category_icons = [
                                            'building' => 'fa-building',
                                            'machinery' => 'fa-cogs',
                                            'vehicle' => 'fa-car',
                                            'furniture' => 'fa-couch',
                                            'computer' => 'fa-laptop',
                                            'other' => 'fa-box'
                                        ];
                                        $icon = $category_icons[$row['asset_category']] ?? 'fa-box';
                                        ?>
                                        <i class="fas <?php echo $icon; ?> me-1"></i>
                                        <?php echo ucfirst($row['asset_category']); ?>
                                    </td>
                                    <td><?php echo date('d M Y', strtotime($row['purchase_date'])); ?></td>
                                    <td>Rs. <?php echo number_format($row['purchase_value'], 2); ?></td>
                                    <td>Rs. <?php echo number_format($row['salvage_value'], 2); ?></td>
                                    <td><?php echo $row['useful_life_years']; ?> years</td>
                                    <td><?php echo ucwords(str_replace('_', ' ', $row['depreciation_method'])); ?></td>
                                    <td>Rs. <?php echo number_format($row['accumulated_depreciation'], 2); ?></td>
                                    <td><strong>Rs. <?php echo number_format($row['book_value'], 2); ?></strong></td>
                                    <td>
                                        <span class="badge bg-<?php
                                                                echo $row['status'] === 'active' ? 'success' : ($row['status'] === 'disposed' ? 'warning' : 'secondary');
                                                                ?>">
                                            <?php echo ucfirst($row['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-dark">
                            <tr>
                                <th colspan="5" class="text-end">Total (<?php echo $financial_summary['total_assets']; ?> assets):</th>
                                <th>Rs. <?php echo number_format($financial_summary['total_purchase_value'], 2); ?></th>
                                <th colspan="3"></th>
                                <th>Rs. <?php echo number_format($financial_summary['total_accumulated_depreciation'], 2); ?></th>
                                <th colspan="2">Rs. <?php echo number_format($financial_summary['total_book_value'], 2); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

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
            font-size: 11px;
        }

        .card {
            border: none !important;
            box-shadow: none !important;
        }
    }
</style>

<?php include '../includes/footer.php'; ?>
