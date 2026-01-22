<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../includes/functions.php';

// Check authentication
require_login();
require_permission('customers', 'update');

$database = new Database();
$db = $database->getConnection();

$customer_id = $_GET['id'] ?? 0;

if (!$customer_id) {
    $_SESSION['error'] = 'Invalid customer ID';
    header("Location: list.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $query = "UPDATE customers SET 
            customer_code = :code,
            customer_name = :name,
            customer_type = :type,
            contact_person = :contact_person,
            phone = :phone,
            email = :email,
            address = :address,
            city = :city,
            credit_limit = :credit_limit,
            credit_days = :credit_days,
            status = :status
        WHERE customer_id = :customer_id";

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
            ':credit_days' => $_POST['credit_days'] ?? 0,
            ':status' => $_POST['status'],
            ':customer_id' => $customer_id
        ]);

        $_SESSION['success'] = 'Customer updated successfully';
        header("Location: view.php?id=" . $customer_id);
        exit();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
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

$page_title = 'Edit Customer';
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fas fa-user-edit me-2"></i>Edit Customer</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="list.php">Customers</a></li>
                    <li class="breadcrumb-item"><a href="view.php?id=<?php echo $customer_id; ?>"><?php echo htmlspecialchars($customer['customer_name']); ?></a></li>
                    <li class="breadcrumb-item active">Edit</li>
                </ol>
            </nav>
        </div>
        <div class="col-md-4 text-end">
            <a href="view.php?id=<?php echo $customer_id; ?>" class="btn btn-secondary">
                <i class="fas fa-times me-1"></i>Cancel
            </a>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Customer Information</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Customer Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="customer_code"
                                    value="<?php echo htmlspecialchars($customer['customer_code']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Customer Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="customer_name"
                                    value="<?php echo htmlspecialchars($customer['customer_name']); ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Customer Type <span class="text-danger">*</span></label>
                                <select class="form-select" name="customer_type" required>
                                    <option value="">Select Type</option>
                                    <option value="retail" <?php echo $customer['customer_type'] === 'retail' ? 'selected' : ''; ?>>Retail</option>
                                    <option value="wholesale" <?php echo $customer['customer_type'] === 'wholesale' ? 'selected' : ''; ?>>Wholesale</option>
                                    <option value="distributor" <?php echo $customer['customer_type'] === 'distributor' ? 'selected' : ''; ?>>Distributor</option>
                                    <option value="manufacturer" <?php echo $customer['customer_type'] === 'manufacturer' ? 'selected' : ''; ?>>Manufacturer</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Contact Person</label>
                                <input type="text" class="form-control" name="contact_person"
                                    value="<?php echo htmlspecialchars($customer['contact_person'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="tel" class="form-control" name="phone"
                                    value="<?php echo htmlspecialchars($customer['phone'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email"
                                    value="<?php echo htmlspecialchars($customer['email'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Address</label>
                                <input type="text" class="form-control" name="address"
                                    value="<?php echo htmlspecialchars($customer['address'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">City</label>
                                <input type="text" class="form-control" name="city"
                                    value="<?php echo htmlspecialchars($customer['city'] ?? ''); ?>">
                            </div>
                        </div>

                        <hr>

                        <h6 class="mb-3"><i class="fas fa-credit-card me-2"></i>Credit Information</h6>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Credit Limit (Rs.)</label>
                                <input type="number" class="form-control" name="credit_limit"
                                    step="0.01" min="0"
                                    value="<?php echo htmlspecialchars($customer['credit_limit']); ?>">
                                <small class="text-muted">Maximum credit allowed for this customer</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Credit Days</label>
                                <input type="number" class="form-control" name="credit_days"
                                    min="0"
                                    value="<?php echo htmlspecialchars($customer['credit_days']); ?>">
                                <small class="text-muted">Payment terms in days</small>
                            </div>
                        </div>

                        <hr>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status <span class="text-danger">*</span></label>
                                <select class="form-select" name="status" required>
                                    <option value="active" <?php echo $customer['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $customer['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="suspended" <?php echo $customer['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                </select>
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="view.php?id=<?php echo $customer_id; ?>" class="btn btn-secondary">
                                <i class="fas fa-times me-1"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>Update Customer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Customer Info Summary -->
        <div class="col-md-4">
            <div class="card mb-3">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Current Information</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr>
                            <th>Customer Code:</th>
                            <td><?php echo htmlspecialchars($customer['customer_code']); ?></td>
                        </tr>
                        <tr>
                            <th>Customer Name:</th>
                            <td><?php echo htmlspecialchars($customer['customer_name']); ?></td>
                        </tr>
                        <tr>
                            <th>Type:</th>
                            <td>
                                <span class="badge bg-primary">
                                    <?php echo ucfirst($customer['customer_type']); ?>
                                </span>
                            </td>
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
                        <tr>
                            <th>Created:</th>
                            <td><?php echo date('d M Y', strtotime($customer['created_at'])); ?></td>
                        </tr>
                        <tr>
                            <th>Last Updated:</th>
                            <td><?php echo date('d M Y H:i', strtotime($customer['updated_at'])); ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-warning">
                    <h6 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Important Notes</h6>
                </div>
                <div class="card-body">
                    <ul class="mb-0">
                        <li>Changing customer code may affect reporting</li>
                        <li>Suspending customer will prevent new sales</li>
                        <li>Credit limit changes apply to future transactions</li>
                        <li>Contact information is used for invoices and communications</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
