<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../includes/functions.php';

/**
 * Check authentication - wrapper for require_login()
 * Alias for require_login() for backwards compatibility
 */
function checkAuth()
{
    return require_login();
}

/**
 * Check user permission - wrapper for has_permission()
 * @param string $permission Permission name (can be single or simplified)
 * @return bool
 */
function hasPermission($permission)
{
    // Check if permission exists in user's permissions
    if (!isset($_SESSION['permissions'])) {
        return false;
    }

    $permissions = json_decode($_SESSION['permissions'], true);

    // Administrator has all permissions
    if (isset($permissions['modules']) && in_array('all', $permissions['modules'])) {
        return true;
    }

    // Check in modules array
    if (isset($permissions['modules']) && in_array($permission, $permissions['modules'])) {
        return true;
    }

    // Check in permissions array
    if (isset($permissions['permissions']) && in_array($permission, $permissions['permissions'])) {
        return true;
    }

    return false;
}

/**
 * Set flash message - wrapper for session-based messaging
 * @param string $message Message text
 * @param string $type Message type (success, error, info, warning)
 */
function setFlashMessage($message, $type = 'success')
{
    $_SESSION[$type] = $message;
}

/**
 * Display flash message - wrapper for display_flash_message()
 * @param string $type Message type
 * @return string HTML
 */
function displayFlashMessage($type = 'success')
{
    return display_flash_message($type);
}

/**
 * Redirect to URL
 * @param string $url URL to redirect to
 */
function redirect($url)
{
    // If URL doesn't start with http and isn't a full path, make it relative
    if (!filter_var($url, FILTER_VALIDATE_URL) && strpos($url, '/') !== 0) {
        $url = dirname($_SERVER['PHP_SELF']) . '/' . $url;
    }
    header("Location: $url");
    exit();
}

// Check authentication
checkAuth();

// Check permission
if (!hasPermission('admin') && !hasPermission('branches')) {
    redirect('dashboard.php');
}

$database = new Database();
$db = $database->getConnection();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        try {
            $query = "INSERT INTO branches (
                branch_code, branch_name, branch_type, location, 
                address, contact_phone, contact_email, manager_name, status
            ) VALUES (
                :code, :name, :type, :location, 
                :address, :phone, :email, :manager, :status
            )";

            $stmt = $db->prepare($query);
            $stmt->execute([
                ':code' => $_POST['branch_code'],
                ':name' => $_POST['branch_name'],
                ':type' => $_POST['branch_type'],
                ':location' => $_POST['location'],
                ':address' => $_POST['address'],
                ':phone' => $_POST['contact_phone'],
                ':email' => $_POST['contact_email'],
                ':manager' => $_POST['manager_name'],
                ':status' => $_POST['status']
            ]);

            setFlashMessage('Branch added successfully', 'success');
            redirect('branches.php');
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    } elseif ($action === 'edit') {
        try {
            $query = "UPDATE branches SET 
                branch_code = :code,
                branch_name = :name,
                branch_type = :type,
                location = :location,
                address = :address,
                contact_phone = :phone,
                contact_email = :email,
                manager_name = :manager,
                status = :status
            WHERE branch_id = :id";

            $stmt = $db->prepare($query);
            $stmt->execute([
                ':code' => $_POST['branch_code'],
                ':name' => $_POST['branch_name'],
                ':type' => $_POST['branch_type'],
                ':location' => $_POST['location'],
                ':address' => $_POST['address'],
                ':phone' => $_POST['contact_phone'],
                ':email' => $_POST['contact_email'],
                ':manager' => $_POST['manager_name'],
                ':status' => $_POST['status'],
                ':id' => $_POST['branch_id']
            ]);

            setFlashMessage('Branch updated successfully', 'success');
            redirect('branches.php');
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Get branch for editing if ID provided
$edit_branch = null;
if (isset($_GET['edit'])) {
    $query = "SELECT * FROM branches WHERE branch_id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute([':id' => $_GET['edit']]);
    $edit_branch = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get all branches
$query = "SELECT 
    b.*,
    COUNT(DISTINCT w.warehouse_id) as warehouse_count,
    COUNT(DISTINCT u.user_id) as user_count
FROM branches b
LEFT JOIN warehouses w ON b.branch_id = w.branch_id
LEFT JOIN users u ON b.branch_id = u.branch_id
GROUP BY b.branch_id
ORDER BY b.branch_name";

$stmt = $db->prepare($query);
$stmt->execute();
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Branch Management';
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2><i class="fas fa-building me-2"></i>Branch Management</h2>
        </div>
        <div class="col-md-6 text-end">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#branchModal" onclick="resetForm()">
                <i class="fas fa-plus me-1"></i>Add New Branch
            </button>
        </div>
    </div>

    <?php displayFlashMessage(); ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Branches</h6>
                    <h3><?php echo count($branches); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title">Active Branches</h6>
                    <h3><?php echo count(array_filter($branches, fn($b) => $b['status'] === 'active')); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="card-title">Main Offices</h6>
                    <h3><?php echo count(array_filter($branches, fn($b) => $b['branch_type'] === 'main_office')); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h6 class="card-title">Sales Centers</h6>
                    <h3><?php echo count(array_filter($branches, fn($b) => $b['branch_type'] === 'sales_center')); ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Branches Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">All Branches</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="branchesTable">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Location</th>
                            <th>Contact</th>
                            <th>Manager</th>
                            <th>Warehouses</th>
                            <th>Users</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($branches as $branch): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($branch['branch_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($branch['branch_name']); ?></td>
                                <td>
                                    <?php
                                    $type_badges = [
                                        'main_office' => 'primary',
                                        'sub_office' => 'info',
                                        'sales_center' => 'success',
                                        'production_center' => 'warning',
                                        'warehouse' => 'secondary'
                                    ];
                                    $badge_color = $type_badges[$branch['branch_type']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $badge_color; ?>">
                                        <?php echo ucwords(str_replace('_', ' ', $branch['branch_type'])); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($branch['location'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if ($branch['contact_phone']): ?>
                                        <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($branch['contact_phone']); ?><br>
                                    <?php endif; ?>
                                    <?php if ($branch['contact_email']): ?>
                                        <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($branch['contact_email']); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($branch['manager_name'] ?? 'N/A'); ?></td>
                                <td><span class="badge bg-info"><?php echo $branch['warehouse_count']; ?></span></td>
                                <td><span class="badge bg-success"><?php echo $branch['user_count']; ?></span></td>
                                <td>
                                    <?php if ($branch['status'] === 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="editBranch(<?php echo htmlspecialchars(json_encode($branch)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>

                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Branch Modal -->
<div class="modal fade" id="branchModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add New Branch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="branchForm">
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="branch_id" id="branch_id">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Branch Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="branch_code" id="branch_code" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Branch Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="branch_name" id="branch_name" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Branch Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="branch_type" id="branch_type" required>
                                <option value="">Select Type</option>
                                <option value="main_office">Main Office</option>
                                <option value="sub_office">Sub Office</option>
                                <option value="sales_center">Sales Center</option>
                                <option value="production_center">Production Center</option>
                                <option value="warehouse">Warehouse</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" class="form-control" name="location" id="location">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" name="address" id="address" rows="2"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Phone</label>
                            <input type="tel" class="form-control" name="contact_phone" id="contact_phone">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Email</label>
                            <input type="email" class="form-control" name="contact_email" id="contact_email">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Manager Name</label>
                            <input type="text" class="form-control" name="manager_name" id="manager_name">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" name="status" id="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Save Branch
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        $('#branchesTable').DataTable({
            order: [
                [1, 'asc']
            ],
            pageLength: 25
        });
    });

    function resetForm() {
        document.getElementById('branchForm').reset();
        document.getElementById('formAction').value = 'add';
        document.getElementById('branch_id').value = '';
        document.getElementById('modalTitle').textContent = 'Add New Branch';
    }

    function editBranch(branch) {
        document.getElementById('formAction').value = 'edit';
        document.getElementById('branch_id').value = branch.branch_id;
        document.getElementById('branch_code').value = branch.branch_code;
        document.getElementById('branch_name').value = branch.branch_name;
        document.getElementById('branch_type').value = branch.branch_type;
        document.getElementById('location').value = branch.location || '';
        document.getElementById('address').value = branch.address || '';
        document.getElementById('contact_phone').value = branch.contact_phone || '';
        document.getElementById('contact_email').value = branch.contact_email || '';
        document.getElementById('manager_name').value = branch.manager_name || '';
        document.getElementById('status').value = branch.status;
        document.getElementById('modalTitle').textContent = 'Edit Branch';

        var modal = new bootstrap.Modal(document.getElementById('branchModal'));
        modal.show();
    }
</script>

<?php include '../includes/footer.php'; ?>