<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();
require_permission('inventory', 'read');

$database = new Database();
$db = $database->getConnection();

$page_title = 'Stock Levels';

// Filters
$warehouse_id = $_GET['warehouse_id'] ?? '';
$category_id = $_GET['category_id'] ?? '';
$stock_status = $_GET['stock_status'] ?? '';
$search = sanitize_input($_GET['search'] ?? '');

// Build query
$where_conditions = ["1=1"];
$params = [];

if (!empty($warehouse_id)) {
    $where_conditions[] = "i.warehouse_id = :warehouse_id";
    $params[':warehouse_id'] = $warehouse_id;
}

if (!empty($category_id)) {
    $where_conditions[] = "p.category_id = :category_id";
    $params[':category_id'] = $category_id;
}

if (!empty($search)) {
    $where_conditions[] = "(p.product_code LIKE :search OR p.product_name LIKE :search)";
    $params[':search'] = "%$search%";
}

$where_clause = implode(" AND ", $where_conditions);

$query = "SELECT i.*, w.warehouse_code, w.warehouse_name, p.product_code, p.product_name,
          pc.category_name, p.unit_of_measure, p.reorder_level,
          CASE 
              WHEN i.quantity_available <= 0 THEN 'Out of Stock'
              WHEN i.quantity_available <= p.reorder_level THEN 'Low Stock'
              ELSE 'Adequate'
          END as stock_status
          FROM inventory i
          INNER JOIN warehouses w ON i.warehouse_id = w.warehouse_id
          INNER JOIN products p ON i.product_id = p.product_id
          INNER JOIN product_categories pc ON p.category_id = pc.category_id
          WHERE $where_clause
          ORDER BY w.warehouse_name, p.product_name";

$stmt = $db->prepare($query);
$stmt->execute($params);
$stock_items = $stmt->fetchAll();

// Apply stock status filter after query
if (!empty($stock_status)) {
    $stock_items = array_filter($stock_items, function ($item) use ($stock_status) {
        return $item['stock_status'] === $stock_status;
    });
}

// Get warehouses for filter
$warehouses = $db->query("SELECT * FROM warehouses WHERE status = 'active' ORDER BY warehouse_name")->fetchAll();

// Get categories for filter
$categories = $db->query("SELECT * FROM product_categories ORDER BY category_name")->fetchAll();

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-boxes"></i> Stock Levels</h2>
        <div>
            <?php if (has_permission('inventory', 'create')): ?>
                <a href="transactions.php" class="btn btn-primary">
                    <i class="fas fa-exchange-alt"></i> Stock Transactions
                </a>
                <a href="transfer.php" class="btn btn-info">
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
                <div class="col-md-3">
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
                    <select class="form-select" name="category_id">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['category_id']; ?>"
                                <?php echo ($category_id == $category['category_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['category_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="stock_status">
                        <option value="">All Status</option>
                        <option value="Out of Stock" <?php echo ($stock_status == 'Out of Stock') ? 'selected' : ''; ?>>Out of Stock</option>
                        <option value="Low Stock" <?php echo ($stock_status == 'Low Stock') ? 'selected' : ''; ?>>Low Stock</option>
                        <option value="Adequate" <?php echo ($stock_status == 'Adequate') ? 'selected' : ''; ?>>Adequate</option>
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

    <!-- Stock Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Warehouse</th>
                            <th>Product Code</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>On Hand</th>
                            <th>Reserved</th>
                            <th>Available</th>
                            <th>Reorder Level</th>
                            <th>Status</th>
                            <th>Last Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($stock_items)): ?>
                            <tr>
                                <td colspan="10" class="text-center text-muted">No stock records found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($stock_items as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['warehouse_name']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($item['product_code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                    <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                    <td><?php echo number_format($item['quantity_on_hand'], 2); ?> <?php echo $item['unit_of_measure']; ?></td>
                                    <td><?php echo number_format($item['quantity_reserved'], 2); ?> <?php echo $item['unit_of_measure']; ?></td>
                                    <td><strong><?php echo number_format($item['quantity_available'], 2); ?></strong> <?php echo $item['unit_of_measure']; ?></td>
                                    <td><?php echo number_format($item['reorder_level'], 2); ?> <?php echo $item['unit_of_measure']; ?></td>
                                    <td>
                                        <span class="badge bg-<?php
                                                                echo $item['stock_status'] === 'Out of Stock' ? 'danger' : ($item['stock_status'] === 'Low Stock' ? 'warning' : 'success');
                                                                ?>">
                                            <?php echo $item['stock_status']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo format_datetime($item['last_updated']); ?></td>
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