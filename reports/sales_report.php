<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();
require_permission('reports', 'read');

$database = new Database();
$db = $database->getConnection();

$page_title = 'Sales Report';

$type = $_GET['type'] ?? 'daily';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$branch_id = $_GET['branch_id'] ?? '';

$report_data = [];

if ($type === 'daily') {
    $query = "SELECT si.invoice_date, b.branch_name,
              COUNT(si.invoice_id) as total_invoices,
              SUM(si.subtotal) as gross_sales,
              SUM(si.discount_amount) as total_discounts,
              SUM(si.total_amount) as net_sales,
              SUM(CASE WHEN si.payment_method = 'cash' THEN si.total_amount ELSE 0 END) as cash_sales,
              SUM(CASE WHEN si.payment_method = 'credit' THEN si.total_amount ELSE 0 END) as credit_sales
              FROM sales_invoices si
              INNER JOIN branches b ON si.branch_id = b.branch_id
              WHERE si.invoice_date BETWEEN :date_from AND :date_to
              AND si.status != 'cancelled'
              " . (!empty($branch_id) ? "AND si.branch_id = :branch_id" : "") . "
              GROUP BY si.invoice_date, b.branch_id
              ORDER BY si.invoice_date DESC, b.branch_name";

    $stmt = $db->prepare($query);
    $params = [':date_from' => $date_from, ':date_to' => $date_to];
    if (!empty($branch_id)) $params[':branch_id'] = $branch_id;
    $stmt->execute($params);
    $report_data = $stmt->fetchAll();
} elseif ($type === 'product') {
    $query = "SELECT p.product_code, p.product_name, pc.category_name,
              SUM(id.quantity) as total_quantity,
              SUM(id.line_total) as total_sales,
              COUNT(DISTINCT si.invoice_id) as invoice_count
              FROM invoice_details id
              INNER JOIN sales_invoices si ON id.invoice_id = si.invoice_id
              INNER JOIN products p ON id.product_id = p.product_id
              INNER JOIN product_categories pc ON p.category_id = pc.category_id
              WHERE si.invoice_date BETWEEN :date_from AND :date_to
              AND si.status != 'cancelled'
              GROUP BY id.product_id
              ORDER BY total_sales DESC";

    $stmt = $db->prepare($query);
    $stmt->execute([':date_from' => $date_from, ':date_to' => $date_to]);
    $report_data = $stmt->fetchAll();
}

// Get branches
$branches = $db->query("SELECT * FROM branches WHERE status = 'active' ORDER BY branch_name")->fetchAll();

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-chart-line"></i> Sales Report</h2>
        <div>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Reports
            </a>
            <button onclick="exportReport()" class="btn btn-success">
                <i class="fas fa-file-excel"></i> Export to Excel
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Report Type</label>
                    <select class="form-select" name="type" onchange="this.form.submit()">
                        <option value="daily" <?php echo ($type == 'daily') ? 'selected' : ''; ?>>Daily Sales</option>
                        <option value="monthly" <?php echo ($type == 'monthly') ? 'selected' : ''; ?>>Monthly Sales</option>
                        <option value="branch" <?php echo ($type == 'branch') ? 'selected' : ''; ?>>By Branch</option>
                        <option value="product" <?php echo ($type == 'product') ? 'selected' : ''; ?>>By Product</option>
                        <option value="customer" <?php echo ($type == 'customer') ? 'selected' : ''; ?>>By Customer</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">From Date</label>
                    <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">To Date</label>
                    <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Branch</label>
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
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-sync"></i> Generate
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Report Data -->
    <div class="card">
        <div class="card-body">
            <?php if ($type === 'daily'): ?>
                <h5 class="mb-3">Daily Sales Report</h5>
                <div class="table-responsive">
                    <table class="table table-striped" id="reportTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Branch</th>
                                <th>Invoices</th>
                                <th>Gross Sales</th>
                                <th>Discounts</th>
                                <th>Net Sales</th>
                                <th>Cash Sales</th>
                                <th>Credit Sales</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $total_gross = $total_net = $total_cash = $total_credit = 0;
                            foreach ($report_data as $row):
                                $total_gross += $row['gross_sales'];
                                $total_net += $row['net_sales'];
                                $total_cash += $row['cash_sales'];
                                $total_credit += $row['credit_sales'];
                            ?>
                                <tr>
                                    <td><?php echo format_date($row['invoice_date']); ?></td>
                                    <td><?php echo htmlspecialchars($row['branch_name']); ?></td>
                                    <td><?php echo $row['total_invoices']; ?></td>
                                    <td><?php echo format_currency($row['gross_sales']); ?></td>
                                    <td><?php echo format_currency($row['total_discounts']); ?></td>
                                    <td><strong><?php echo format_currency($row['net_sales']); ?></strong></td>
                                    <td><?php echo format_currency($row['cash_sales']); ?></td>
                                    <td><?php echo format_currency($row['credit_sales']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="fw-bold bg-light">
                                <td colspan="3">TOTAL</td>
                                <td><?php echo format_currency($total_gross); ?></td>
                                <td>-</td>
                                <td><?php echo format_currency($total_net); ?></td>
                                <td><?php echo format_currency($total_cash); ?></td>
                                <td><?php echo format_currency($total_credit); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php elseif ($type === 'product'): ?>
                <h5 class="mb-3">Sales by Product</h5>
                <div class="table-responsive">
                    <table class="table table-striped" id="reportTable">
                        <thead>
                            <tr>
                                <th>Product Code</th>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Quantity Sold</th>
                                <th>Total Sales</th>
                                <th>Invoices</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $total_sales = 0;
                            foreach ($report_data as $row):
                                $total_sales += $row['total_sales'];
                            ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($row['product_code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                                    <td><?php echo number_format($row['total_quantity'], 2); ?></td>
                                    <td><strong><?php echo format_currency($row['total_sales']); ?></strong></td>
                                    <td><?php echo $row['invoice_count']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="fw-bold bg-light">
                                <td colspan="4">TOTAL</td>
                                <td><?php echo format_currency($total_sales); ?></td>
                                <td>-</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    function exportReport() {
        const table = document.getElementById('reportTable');
        const rows = [];

        for (let row of table.rows) {
            const cols = [];
            for (let cell of row.cells) {
                cols.push(cell.textContent.trim());
            }
            rows.push(cols.join('\t'));
        }

        const csv = rows.join('\n');
        const blob = new Blob([csv], {
            type: 'application/vnd.ms-excel'
        });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'sales_report_<?php echo date('Y-m-d'); ?>.xls';
        a.click();
    }
</script>

<?php include '../includes/footer.php'; ?>