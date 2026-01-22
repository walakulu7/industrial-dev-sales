<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../includes/functions.php';

// Check authentication
require_login();
require_permission('customers', 'read');

$database = new Database();
$db = $database->getConnection();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        try {
            $query = "INSERT INTO customers (
                customer_code, customer_name, customer_type, contact_person,
                phone, email, address, city, credit_limit, credit_days, status
            ) VALUES (
                :code, :name, :type, :contact_person,
                :phone, :email, :address, :city, :credit_limit, :credit_days, 'active'
            )";

            $stmt = $db->prepare($query);
            $stmt->execute([
                ':code' => $_POST['customer_code'],
                ':name' => $_POST['customer_name'],
                ':type' => $_POST['customer_type'],
                ':contact_person' => $_POST['contact_person'] ?? null,
                ':phone' => $_POST['phone'] ?? null,
                ':email' => $_POST['email'] ?? null,
                ':address' => $_POST['address'] ?? null,
                ':city' => $_POST['city'] ?? null,
                ':credit_limit' => $_POST['credit_limit'] ?? 0,
                ':credit_days' => $_POST['credit_days'] ?? 0
            ]);

            $_SESSION['success'] = 'Customer added successfully';
            header("Location: list.php");
            exit();
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Get filter parameters
$filter_type = $_GET['type'] ?? '';
$filter_status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$query = "SELECT 
    c.*,
    COUNT(DISTINCT si.invoice_id) as total_invoices,
    COALESCE(SUM(si.total_amount), 0) as total_sales,
    COALESCE(SUM(si.balance_amount), 0) as outstanding_balance
FROM customers c
LEFT JOIN sales_invoices si ON c.customer_id = si.customer_id
WHERE 1=1";

$params = [];

if ($filter_type) {
    $query .= " AND c.customer_type = :type";
    $params[':type'] = $filter_type;
}

if ($filter_status) {
    $query .= " AND c.status = :status";
    $params[':status'] = $filter_status;
} else {
    $query .= " AND c.status = 'active'"; // Default to active only
}

if ($search) {
    $query .= " AND (c.customer_code LIKE :search OR c.customer_name LIKE :search2 OR c.phone LIKE :search3 OR c.email LIKE :search4)";
    $search_param = "%$search%";
    $params[':search'] = $search_param;
    $params[':search2'] = $search_param;
    $params[':search3'] = $search_param;
    $params[':search4'] = $search_param;
}

$query .= " GROUP BY c.customer_id ORDER BY c.customer_name";

$stmt = $db->prepare($query);
$stmt->execute($params);
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate summary statistics
$total_customers = count($customers);
$total_sales = array_sum(array_column($customers, 'total_sales'));
$total_outstanding = array_sum(array_column($customers, 'outstanding_balance'));

$page_title = 'Customer Management';
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2><i class="fas fa-users me-2"></i>Customer Management</h2>
        </div>
        <div class="col-md-6 text-end">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#customerModal">
                <i class="fas fa-plus me-1"></i>Add Customer
            </button>
        </div>
    </div>

    <?php
    echo display_flash_message('success');
    echo display_flash_message('error');
    ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Customers</h6>
                    <h3><?php echo number_format($total_customers); ?></h3>
                    <small>Active customers</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Sales</h6>
                    <h3>Rs. <?php echo number_format($total_sales, 2); ?></h3>
                    <small>All-time sales</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h6 class="card-title">Outstanding Balance</h6>
                    <h3>Rs. <?php echo number_format($total_outstanding, 2); ?></h3>
                    <small>Credit sales pending</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" name="search"
                        placeholder="Code, Name, Phone, Email..."
                        value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Customer Type</label>
                    <select class="form-select" name="type">
                        <option value="">All Types</option>
                        <option value="retail" <?php echo $filter_type === 'retail' ? 'selected' : ''; ?>>Retail</option>
                        <option value="wholesale" <?php echo $filter_type === 'wholesale' ? 'selected' : ''; ?>>Wholesale</option>
                        <option value="distributor" <?php echo $filter_type === 'distributor' ? 'selected' : ''; ?>>Distributor</option>
                        <option value="manufacturer" <?php echo $filter_type === 'manufacturer' ? 'selected' : ''; ?>>Manufacturer</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="">Active Only</option>
                        <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $filter_status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="suspended" <?php echo $filter_status === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                        <option value="all">All Status</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-1"></i>Search
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Customers Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Customer List</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="customersTable">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Customer Name</th>
                            <th>Type</th>
                            <th>Contact</th>
                            <th>City</th>
                            <th>Credit Limit</th>
                            <th>Total Sales</th>
                            <th>Outstanding</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $customer): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($customer['customer_code']); ?></strong></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($customer['customer_name']); ?></strong>
                                    <?php if ($customer['contact_person']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($customer['contact_person']); ?></small>
                                    <?php endif; ?>
                                </td>
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
                                <td>
                                    <?php if ($customer['phone']): ?>
                                        <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($customer['phone']); ?><br>
                                    <?php endif; ?>
                                    <?php if ($customer['email']): ?>
                                        <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($customer['email']); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($customer['city'] ?? '-'); ?></td>
                                <td>Rs. <?php echo number_format($customer['credit_limit'], 2); ?></td>
                                <td>
                                    <strong>Rs. <?php echo number_format($customer['total_sales'], 2); ?></strong>
                                    <br><small class="text-muted"><?php echo $customer['total_invoices']; ?> invoices</small>
                                </td>
                                <td>
                                    <?php if ($customer['outstanding_balance'] > 0): ?>
                                        <span class="badge bg-warning">
                                            Rs. <?php echo number_format($customer['outstanding_balance'], 2); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Clear</span>
                                    <?php endif; ?>
                                </td>
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
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="view.php?id=<?php echo $customer['customer_id']; ?>"
                                            class="btn btn-sm btn-info" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit.php?id=<?php echo $customer['customer_id']; ?>"
                                            class="btn btn-sm btn-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Customer Modal -->
<div class="modal fade" id="customerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Customer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Customer Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="customer_code" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Customer Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="customer_name" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Customer Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="customer_type" required>
                                <option value="">Select Type</option>
                                <option value="retail">Retail</option>
                                <option value="wholesale">Wholesale</option>
                                <option value="distributor">Distributor</option>
                                <option value="manufacturer">Manufacturer</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Person</label>
                            <input type="text" class="form-control" name="contact_person">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" name="phone">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Address</label>
                            <input type="text" class="form-control" name="address">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">City</label>
                            <input type="text" class="form-control" name="city">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Credit Limit (Rs.)</label>
                            <input type="number" class="form-control" name="credit_limit" step="0.01" min="0" value="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Credit Days</label>
                            <input type="number" class="form-control" name="credit_days" min="0" value="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Add Customer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        $('#customersTable').DataTable({
            order: [
                [1, 'asc']
            ],
            pageLength: 25
        });
    });
</script>

<?php include '../includes/footer.php'; ?>
