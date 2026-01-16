<?php

/**
 * Products Management Page
 * Add, edit, and manage products
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();
require_permission('all', 'read');

$database = new Database();
$db = $database->getConnection();

$page_title = 'Products Management';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && has_permission('all', 'create')) {
    try {
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'create') {
                // Create new product
                $query = "INSERT INTO products 
                         (product_code, product_name, category_id, unit_of_measure, 
                          specifications, reorder_level, standard_cost, standard_price, status)
                         VALUES 
                         (:product_code, :product_name, :category_id, :unit_of_measure, 
                          :specifications, :reorder_level, :standard_cost, :standard_price, 'active')";

                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':product_code' => sanitize_input($_POST['product_code']),
                    ':product_name' => sanitize_input($_POST['product_name']),
                    ':category_id' => (int)$_POST['category_id'],
                    ':unit_of_measure' => sanitize_input($_POST['unit_of_measure']),
                    ':specifications' => !empty($_POST['specifications']) ? json_encode($_POST['specifications']) : null,
                    ':reorder_level' => (float)$_POST['reorder_level'],
                    ':standard_cost' => (float)$_POST['standard_cost'],
                    ':standard_price' => (float)$_POST['standard_price']
                ]);

                log_audit($_SESSION['user_id'], 'create_product', 'products', $db->lastInsertId());
                redirect_with_message('products.php', 'Product created successfully', 'success');
            } elseif ($_POST['action'] === 'update') {
                // Update existing product
                $query = "UPDATE products 
                         SET product_name = :product_name,
                             category_id = :category_id,
                             unit_of_measure = :unit_of_measure,
                             specifications = :specifications,
                             reorder_level = :reorder_level,
                             standard_cost = :standard_cost,
                             standard_price = :standard_price,
                             status = :status
                         WHERE product_id = :product_id";

                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':product_name' => sanitize_input($_POST['product_name']),
                    ':category_id' => (int)$_POST['category_id'],
                    ':unit_of_measure' => sanitize_input($_POST['unit_of_measure']),
                    ':specifications' => !empty($_POST['specifications']) ? json_encode($_POST['specifications']) : null,
                    ':reorder_level' => (float)$_POST['reorder_level'],
                    ':standard_cost' => (float)$_POST['standard_cost'],
                    ':standard_price' => (float)$_POST['standard_price'],
                    ':status' => $_POST['status'],
                    ':product_id' => (int)$_POST['product_id']
                ]);

                log_audit($_SESSION['user_id'], 'update_product', 'products', $_POST['product_id']);
                redirect_with_message('products.php', 'Product updated successfully', 'success');
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Get products
$search = sanitize_input($_GET['search'] ?? '');
$category_filter = $_GET['category'] ?? '';

$where = ["1=1"];
$params = [];

if (!empty($search)) {
    $where[] = "(p.product_code LIKE :search OR p.product_name LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($category_filter)) {
    $where[] = "p.category_id = :category_id";
    $params[':category_id'] = $category_filter;
}

$where_clause = implode(" AND ", $where);

$query = "SELECT p.*, pc.category_name
          FROM products p
          INNER JOIN product_categories pc ON p.category_id = pc.category_id
          WHERE $where_clause
          ORDER BY p.product_name";

$stmt = $db->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get categories
$categories = $db->query("SELECT * FROM product_categories ORDER BY category_name")->fetchAll();

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-box"></i> Products Management</h2>
        <?php if (has_permission('all', 'create')): ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                <i class="fas fa-plus"></i> Add Product
            </button>
        <?php endif; ?>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="search"
                        placeholder="Search product code or name..."
                        value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['category_id']; ?>"
                                <?php echo ($category_filter == $category['category_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['category_name']); ?>
                            </option>
                        <?php endforeach; ?>
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

    <!-- Products Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>UOM</th>
                            <th>Reorder Level</th>
                            <th>Standard Cost</th>
                            <th>Standard Price</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted">No products found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($product['product_code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                    <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                    <td><?php echo htmlspecialchars($product['unit_of_measure']); ?></td>
                                    <td><?php echo number_format($product['reorder_level'], 2); ?></td>
                                    <td><?php echo format_currency($product['standard_cost']); ?></td>
                                    <td><?php echo format_currency($product['standard_price']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $product['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($product['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (has_permission('all', 'update')): ?>
                                            <button class="btn btn-sm btn-primary"
                                                onclick="editProduct(<?php echo htmlspecialchars(json_encode($product)); ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
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

<!-- Add Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Product Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="product_code" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Product Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="product_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <select class="form-select" name="category_id" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['category_id']; ?>">
                                        <?php echo htmlspecialchars($category['category_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Unit of Measure <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="unit_of_measure"
                                placeholder="e.g., KG, MTR, PCS" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Reorder Level</label>
                            <input type="number" class="form-control" name="reorder_level"
                                step="0.001" min="0" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Standard Cost</label>
                            <input type="number" class="form-control" name="standard_cost"
                                step="0.01" min="0" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Standard Price</label>
                            <input type="number" class="form-control" name="standard_price"
                                step="0.01" min="0" value="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Product Modal -->
<div class="modal fade" id="editProductModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="editProductForm">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="product_id" id="edit_product_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Product Code</label>
                            <input type="text" class="form-control" id="edit_product_code" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Product Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="product_name" id="edit_product_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <select class="form-select" name="category_id" id="edit_category_id" required>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['category_id']; ?>">
                                        <?php echo htmlspecialchars($category['category_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Unit of Measure <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="unit_of_measure" id="edit_unit_of_measure" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Reorder Level</label>
                            <input type="number" class="form-control" name="reorder_level" id="edit_reorder_level"
                                step="0.001" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Standard Cost</label>
                            <input type="number" class="form-control" name="standard_cost" id="edit_standard_cost"
                                step="0.01" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Standard Price</label>
                            <input type="number" class="form-control" name="standard_price" id="edit_standard_price"
                                step="0.01" min="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="edit_status">
                                <option value="active">Active</option>
                                <option value="discontinued">Discontinued</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function editProduct(product) {
        document.getElementById('edit_product_id').value = product.product_id;
        document.getElementById('edit_product_code').value = product.product_code;
        document.getElementById('edit_product_name').value = product.product_name;
        document.getElementById('edit_category_id').value = product.category_id;
        document.getElementById('edit_unit_of_measure').value = product.unit_of_measure;
        document.getElementById('edit_reorder_level').value = product.reorder_level;
        document.getElementById('edit_standard_cost').value = product.standard_cost;
        document.getElementById('edit_standard_price').value = product.standard_price;
        document.getElementById('edit_status').value = product.status;

        new bootstrap.Modal(document.getElementById('editProductModal')).show();
    }
</script>

<?php include '../includes/footer.php'; ?>