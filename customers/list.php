<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();
require_permission('customers', 'read');

$database = new Database();
$db = $database->getConnection();

$page_title = 'Customers';

// Filters
$customer_type = $_GET['customer_type'] ?? '';
$status = $_GET['status'] ?? '';
$search = sanitize_input($_GET['search'] ?? '');

$where_conditions = ["1=1"];
$params = [];

if (!empty($customer_type)) {
    $where_conditions[] = "customer_type = :customer_type";
    $params[':customer_type'] = $customer_type;
}

if (!empty($status)) {
    $where_conditions[] = "status = :status";
    $params[':status'] = $status;
}

if (!empty($search)) {
    $where_conditions[] = "(customer_code LIKE :search OR customer_name LIKE :search OR phone LIKE :search)";
    $params[':search'] = "%$search%";
}

$where_clause = implode(" AND ", $where_conditions);

$query = "SELECT * FROM customers WHERE $where_clause ORDER BY customer_name";
$stmt = $db->prepare($query);
$stmt->execute($params);
$customers = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-users"></i> Customers</h2>
        <?php if (has_permission('customers', 'create')): ?>
            <a href="create.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Customer
            </a>
        <?php endif; ?>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="search"
                        placeholder="Search customer..."
                        value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="customer_type">
                        <option value="">All Types</option>
                        <option value="retail" <?php echo ($customer_type == 'retail') ? 'selected' : ''; ?>>Retail</option>
                        <option value="wholesale" <?php echo ($customer_type == 'wholesale') ? 'selected' : ''; ?>>Wholesale</option>
                        <option value="distributor" <?php echo ($customer_type == 'distributor') ? 'selected' : ''; ?>>Distributor</option>
                        <option value="manufacturer" <?php echo ($customer_type == 'manufacturer') ? 'selected' : ''; ?>>Manufacturer</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="status">
                        <option value="">All Status</option>
                        <option value="active" <?php echo ($status == 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo ($status == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        <option value="suspended" <?php echo ($status == 'suspended') ? 'selected' : ''; ?>>Suspended</option>
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

    <!-- Customers Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Customer Name</th>
                            <th>Type</th>
                            <th>Contact</th>
                            <th>City</th>
                            <th>Credit Limit</th>
                            <th>Credit Days</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($customers)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted">No customers found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($customers as $customer): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($customer['customer_code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($customer['customer_name']); ?></td>
                                    <td><span class="badge bg-info"><?php echo ucfirst($customer['customer_type']); ?></span></td>
                                    <td>
                                        <?php if ($customer['phone']): ?>
                                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($customer['phone']); ?><br>
                                        <?php endif; ?>
                                        <?php if ($customer['email']): ?>
                                            <small><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($customer['email']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($customer['city'] ?? '-'); ?></td>
                                    <td><?php echo format_currency($customer['credit_limit']); ?></td>
                                    <td><?php echo $customer['credit_days']; ?> days</td>
                                    <td>
                                        <span class="badge bg-<?php
                                                                echo $customer['status'] === 'active' ? 'success' : ($customer['status'] === 'suspended' ? 'danger' : 'secondary');
                                                                ?>">
                                            <?php echo ucfirst($customer['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="view.php?id=<?php echo $customer['customer_id']; ?>"
                                            class="btn btn-sm btn-info" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if (has_permission('customers', 'update')): ?>
                                            <a href="edit.php?id=<?php echo $customer['customer_id']; ?>"
                                                class="btn btn-sm btn-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
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