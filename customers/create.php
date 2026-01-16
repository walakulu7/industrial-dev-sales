<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();
require_permission('customers', 'create');

$database = new Database();
$db = $database->getConnection();

$page_title = 'Add Customer';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Generate customer code
        $result = $db->query("SELECT COUNT(*) as count FROM customers")->fetch();
        $customer_code = 'CUST-' . str_pad($result['count'] + 1, 4, '0', STR_PAD_LEFT);

        $query = "INSERT INTO customers 
                  (customer_code, customer_name, customer_type, contact_person, phone, email, 
                   address, city, credit_limit, credit_days, status) 
                  VALUES 
                  (:customer_code, :customer_name, :customer_type, :contact_person, :phone, :email, 
                   :address, :city, :credit_limit, :credit_days, 'active')";

        $stmt = $db->prepare($query);
        $stmt->execute([
            ':customer_code' => $customer_code,
            ':customer_name' => sanitize_input($_POST['customer_name']),
            ':customer_type' => $_POST['customer_type'],
            ':contact_person' => sanitize_input($_POST['contact_person']),
            ':phone' => sanitize_input($_POST['phone']),
            ':email' => sanitize_input($_POST['email']),
            ':address' => sanitize_input($_POST['address']),
            ':city' => sanitize_input($_POST['city']),
            ':credit_limit' => (float)$_POST['credit_limit'],
            ':credit_days' => (int)$_POST['credit_days']
        ]);

        log_audit($_SESSION['user_id'], 'create_customer', 'customers', $db->lastInsertId());

        $_SESSION['success'] = 'Customer created successfully';
        header("Location: list.php");
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-user-plus"></i> Add New Customer</h2>
        <a href="list.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Customers
        </a>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo $_SESSION['error'];
            unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Customer Information</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Customer Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="customer_name" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Customer Type <span class="text-danger">*</span></label>
                                <select class="form-select" name="customer_type" required>
                                    <option value="">Select Type</option>
                                    <option value="retail">Retail</option>
                                    <option value="wholesale">Wholesale</option>
                                    <option value="distributor">Distributor</option>
                                    <option value="manufacturer">Manufacturer</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Contact Person</label>
                                <input type="text" class="form-control" name="contact_person">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="text" class="form-control" name="phone">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">City</label>
                                <input type="text" class="form-control" name="city">
                            </div>

                            <div class="col-12">
                                <label class="form-label">Address</label>
                                <textarea class="form-control" name="address" rows="3"></textarea>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Credit Limit (LKR)</label>
                                <input type="number" class="form-control" name="credit_limit"
                                    step="0.01" min="0" value="0">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Credit Days</label>
                                <input type="number" class="form-control" name="credit_days"
                                    min="0" value="0">
                            </div>

                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Customer
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>