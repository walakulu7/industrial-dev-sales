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
$report_type = $_GET['report_type'] ?? 'stock_levels';
$warehouse_id = $_GET['warehouse_id'] ?? '';
$category_id = $_GET['category_id'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// Get warehouses
$warehouses_query = "SELECT * FROM warehouses WHERE status = 'active' ORDER BY warehouse_name";
$warehouses = $db->query($warehouses_query)->fetchAll();

// Get categories
$categories_query = "SELECT * FROM product_categories ORDER BY category_name";
$categories = $db->query($categories_query)->fetchAll();

$report_data = [];
$report_title = '';

switch ($report_type) {
    case 'stock_levels':
        $report_title = 'Current Stock Levels Report';
        $query = "SELECT 
            p.product_code,
            p.product_name,
            pc.category_name,
            p.unit_of_measure,
            w.warehouse_name,
            i.quantity_on_hand,
            i.quantity_reserved,
            i.quantity_available,
            i.last_stock_date,
            p.reorder_level,
            p.standard_cost,
            (i.quantity_on_hand * p.standard_cost) as stock_value
        FROM inventory i
        JOIN products p ON i.product_id = p.product_id
        JOIN product_categories pc ON p.category_id = pc.category_id
        JOIN warehouses w ON i.warehouse_id = w.warehouse_id
        WHERE 1=1";

        $params = [];
        if ($warehouse_id) {
            $query .= " AND i.warehouse_id = :warehouse_id";
            $params[':warehouse_id'] = $warehouse_id;
        }
        if ($category_id) {
            $query .= " AND p.category_id = :category_id";
            $params[':category_id'] = $category_id;
        }

        $query .= " ORDER BY w.warehouse_name, pc.category_name, p.product_code";
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;

    case 'low_stock':
        $report_title = 'Low Stock Alert Report';
        $query = "SELECT 
            p.product_code,
            p.product_name,
            pc.category_name,
            p.unit_of_measure,
            w.warehouse_name,
            i.quantity_on_hand,
            i.quantity_reserved,
            i.quantity_available,
            p.reorder_level,
            (p.reorder_level - i.quantity_available) as shortage
        FROM inventory i
        JOIN products p ON i.product_id = p.product_id
        JOIN product_categories pc ON p.category_id = pc.category_id
        JOIN warehouses w ON i.warehouse_id = w.warehouse_id
        WHERE i.quantity_available <= p.reorder_level
        AND p.status = 'active'";

        $params = [];
        if ($warehouse_id) {
            $query .= " AND i.warehouse_id = :warehouse_id";
            $params[':warehouse_id'] = $warehouse_id;
        }
        if ($category_id) {
            $query .= " AND p.category_id = :category_id";
            $params[':category_id'] = $category_id;
        }

        $query .= " ORDER BY (p.reorder_level - i.quantity_available) DESC";
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;

    case 'stock_movement':
        $report_title = 'Stock Movement Report';
        $query = "SELECT 
            st.transaction_date,
            st.transaction_time,
            st.transaction_type,
            p.product_code,
            p.product_name,
            pc.category_name,
            w.warehouse_name,
            st.quantity,
            st.unit_cost,
            (st.quantity * COALESCE(st.unit_cost, 0)) as total_value,
            st.reference_type,
            st.reference_id,
            u.full_name as created_by_name
        FROM stock_transactions st
        JOIN products p ON st.product_id = p.product_id
        JOIN product_categories pc ON p.category_id = pc.category_id
        JOIN warehouses w ON st.warehouse_id = w.warehouse_id
        JOIN users u ON st.created_by = u.user_id
        WHERE st.transaction_date BETWEEN :date_from AND :date_to";

        $params = [
            ':date_from' => $date_from,
            ':date_to' => $date_to
        ];

        if ($warehouse_id) {
            $query .= " AND st.warehouse_id = :warehouse_id";
            $params[':warehouse_id'] = $warehouse_id;
        }
        if ($category_id) {
            $query .= " AND p.category_id = :category_id";
            $params[':category_id'] = $category_id;
        }

        $query .= " ORDER BY st.transaction_date DESC, st.transaction_time DESC";
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;

    case 'stock_valuation':
        $report_title = 'Stock Valuation Report';
        $query = "SELECT 
            w.warehouse_name,
            pc.category_name,
            p.product_code,
            p.product_name,
            p.unit_of_measure,
            i.quantity_on_hand,
            p.standard_cost,
            (i.quantity_on_hand * p.standard_cost) as stock_value
        FROM inventory i
        JOIN products p ON i.product_id = p.product_id
        JOIN product_categories pc ON p.category_id = pc.category_id
        JOIN warehouses w ON i.warehouse_id = w.warehouse_id
        WHERE i.quantity_on_hand > 0";

        $params = [];
        if ($warehouse_id) {
            $query .= " AND i.warehouse_id = :warehouse_id";
            $params[':warehouse_id'] = $warehouse_id;
        }
        if ($category_id) {
            $query .= " AND p.category_id = :category_id";
            $params[':category_id'] = $category_id;
        }

        $query .= " ORDER BY w.warehouse_name, pc.category_name, p.product_code";
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
}

$page_title = 'Inventory Reports';
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fas fa-chart-bar me-2"></i><?php echo $report_title; ?></h2>
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

    <!-- Filters -->
    <div class="card mb-4 no-print">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Report Type</label>
                    <select class="form-select" name="report_type" required>
                        <option value="stock_levels" <?php echo $report_type === 'stock_levels' ? 'selected' : ''; ?>>Current Stock Levels</option>
                        <option value="low_stock" <?php echo $report_type === 'low_stock' ? 'selected' : ''; ?>>Low Stock Alert</option>
                        <option value="stock_movement" <?php echo $report_type === 'stock_movement' ? 'selected' : ''; ?>>Stock Movement</option>
                        <option value="stock_valuation" <?php echo $report_type === 'stock_valuation' ? 'selected' : ''; ?>>Stock Valuation</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Warehouse</label>
                    <select class="form-select" name="warehouse_id">
                        <option value="">All Warehouses</option>
                        <?php foreach ($warehouses as $wh): ?>
                            <option value="<?php echo $wh['warehouse_id']; ?>"
                                <?php echo $warehouse_id == $wh['warehouse_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($wh['warehouse_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Category</label>
                    <select class="form-select" name="category_id">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['category_id']; ?>"
                                <?php echo $category_id == $cat['category_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($report_type === 'stock_movement'): ?>
                    <div class="col-md-2">
                        <label class="form-label">Date From</label>
                        <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Date To</label>
                        <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
                    </div>
                <?php endif; ?>
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

                <?php if ($report_type === 'stock_levels'): ?>
                    <div class="table-responsive">
                        <table class="table table-striped" id="reportTable">
                            <thead>
                                <tr>
                                    <th>Product Code</th>
                                    <th>Product Name</th>
                                    <th>Category</th>
                                    <th>Warehouse</th>
                                    <th>On Hand</th>
                                    <th>Reserved</th>
                                    <th>Available</th>
                                    <th>Reorder Level</th>
                                    <th>Stock Value</th>
                                    <th>Last Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $total_value = 0;
                                foreach ($report_data as $row):
                                    $total_value += $row['stock_value'];
                                    $is_low = $row['quantity_available'] <= $row['reorder_level'];
                                ?>
                                    <tr class="<?php echo $is_low ? 'table-warning' : ''; ?>">
                                        <td><?php echo htmlspecialchars($row['product_code']); ?></td>
                                        <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['warehouse_name']); ?></td>
                                        <td><?php echo number_format($row['quantity_on_hand'], 2); ?></td>
                                        <td><?php echo number_format($row['quantity_reserved'], 2); ?></td>
                                        <td><strong><?php echo number_format($row['quantity_available'], 2); ?></strong></td>
                                        <td><?php echo number_format($row['reorder_level'], 2); ?></td>
                                        <td>Rs. <?php echo number_format($row['stock_value'], 2); ?></td>
                                        <td><?php echo date('d M Y', strtotime($row['last_stock_date'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-dark">
                                    <th colspan="8" class="text-end">Total Stock Value:</th>
                                    <th colspan="2">Rs. <?php echo number_format($total_value, 2); ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                <?php elseif ($report_type === 'low_stock'): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong><?php echo count($report_data); ?></strong> items are below reorder level
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped" id="reportTable">
                            <thead>
                                <tr>
                                    <th>Product Code</th>
                                    <th>Product Name</th>
                                    <th>Category</th>
                                    <th>Warehouse</th>
                                    <th>Available</th>
                                    <th>Reorder Level</th>
                                    <th>Shortage</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['product_code']); ?></td>
                                        <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['warehouse_name']); ?></td>
                                        <td><strong><?php echo number_format($row['quantity_available'], 2); ?></strong></td>
                                        <td><?php echo number_format($row['reorder_level'], 2); ?></td>
                                        <td><span class="badge bg-danger"><?php echo number_format($row['shortage'], 2); ?></span></td>
                                        <td>
                                            <?php if ($row['quantity_available'] == 0): ?>
                                                <span class="badge bg-danger">Out of Stock</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Low Stock</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php elseif ($report_type === 'stock_movement'): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-sm" id="reportTable">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Type</th>
                                    <th>Product</th>
                                    <th>Warehouse</th>
                                    <th>Quantity</th>
                                    <th>Unit Cost</th>
                                    <th>Total Value</th>
                                    <th>Reference</th>
                                    <th>User</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $total_in = 0;
                                $total_out = 0;
                                foreach ($report_data as $row):
                                    $is_in = in_array($row['transaction_type'], ['receipt', 'transfer_in', 'production_output']);
                                    if ($is_in) $total_in += $row['quantity'];
                                    else $total_out += $row['quantity'];
                                ?>
                                    <tr>
                                        <td><?php echo date('d M Y', strtotime($row['transaction_date'])); ?></td>
                                        <td><?php echo date('H:i', strtotime($row['transaction_time'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $is_in ? 'success' : 'danger'; ?>">
                                                <?php echo ucwords(str_replace('_', ' ', $row['transaction_type'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['product_code']); ?></td>
                                        <td><?php echo htmlspecialchars($row['warehouse_name']); ?></td>
                                        <td><?php echo number_format($row['quantity'], 2); ?></td>
                                        <td>Rs. <?php echo number_format($row['unit_cost'] ?? 0, 2); ?></td>
                                        <td>Rs. <?php echo number_format($row['total_value'], 2); ?></td>
                                        <td>
                                            <?php if ($row['reference_type']): ?>
                                                <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $row['reference_type']))); ?>
                                                #<?php echo $row['reference_id']; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['created_by_name']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-dark">
                                    <th colspan="5" class="text-end">Total In:</th>
                                    <th><?php echo number_format($total_in, 2); ?></th>
                                    <th colspan="4"></th>
                                </tr>
                                <tr class="table-dark">
                                    <th colspan="5" class="text-end">Total Out:</th>
                                    <th><?php echo number_format($total_out, 2); ?></th>
                                    <th colspan="4"></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                <?php elseif ($report_type === 'stock_valuation'): ?>
                    <div class="table-responsive">
                        <table class="table table-striped" id="reportTable">
                            <thead>
                                <tr>
                                    <th>Warehouse</th>
                                    <th>Category</th>
                                    <th>Product Code</th>
                                    <th>Product Name</th>
                                    <th>Quantity</th>
                                    <th>Unit</th>
                                    <th>Cost/Unit</th>
                                    <th>Stock Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $total_qty = 0;
                                $total_value = 0;
                                foreach ($report_data as $row):
                                    $total_qty += $row['quantity_on_hand'];
                                    $total_value += $row['stock_value'];
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['warehouse_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['product_code']); ?></td>
                                        <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                                        <td><?php echo number_format($row['quantity_on_hand'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($row['unit_of_measure']); ?></td>
                                        <td>Rs. <?php echo number_format($row['standard_cost'], 2); ?></td>
                                        <td><strong>Rs. <?php echo number_format($row['stock_value'], 2); ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-dark">
                                    <th colspan="7" class="text-end">Total Inventory Value:</th>
                                    <th>Rs. <?php echo number_format($total_value, 2); ?></th>
                                </tr>
                            </tfoot>
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
    }
</style>

<?php include '../includes/footer.php'; ?>
