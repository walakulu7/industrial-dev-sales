<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();
require_permission('all', 'read'); // Admin only

$database = new Database();
$db = $database->getConnection();

$page_title = 'User Management';

// Get users
$query = "SELECT u.*, r.role_name, b.branch_name
          FROM users u
          INNER JOIN user_roles r ON u.role_id = r.role_id
          LEFT JOIN branches b ON u.branch_id = b.branch_id
          ORDER BY u.full_name";
$users = $db->query($query)->fetchAll();

// Get roles
$roles = $db->query("SELECT * FROM user_roles ORDER BY role_name")->fetchAll();

// Get branches
$branches = $db->query("SELECT * FROM branches WHERE status = 'active' ORDER BY branch_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        try {
            $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);

            $insert = $db->prepare("INSERT INTO users 
                                   (username, password_hash, full_name, email, phone, role_id, branch_id, status)
                                   VALUES 
                                   (:username, :password_hash, :full_name, :email, :phone, :role_id, :branch_id, 'active')");
            $insert->execute([
                ':username' => sanitize_input($_POST['username']),
                ':password_hash' => $password_hash,
                ':full_name' => sanitize_input($_POST['full_name']),
                ':email' => sanitize_input($_POST['email']),
                ':phone' => sanitize_input($_POST['phone']),
                ':role_id' => (int)$_POST['role_id'],
                ':branch_id' => !empty($_POST['branch_id']) ? (int)$_POST['branch_id'] : null
            ]);

            $_SESSION['success'] = 'User created successfully';
            header("Location: users.php");
            exit();
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
    } elseif ($_POST['action'] === 'delete') {
        try {
            $user_id = (int)$_POST['user_id'];

            // Prevent self-deletion
            if ($user_id == $_SESSION['user_id']) {
                throw new Exception("You cannot delete your own account");
            }

            // Check if user is System Administrator (role_id = 1)
            $check_query = "SELECT role_id FROM users WHERE user_id = :current_user_id";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->execute([':current_user_id' => $_SESSION['user_id']]);
            $current_user = $check_stmt->fetch();

            if ($current_user['role_id'] != 1) {
                throw new Exception("Only System Administrators can delete users");
            }

            // Check if user has related records
            $check_related = $db->prepare("SELECT 
                (SELECT COUNT(*) FROM sales_invoices WHERE created_by = :user_id1) as invoices,
                (SELECT COUNT(*) FROM production_orders WHERE created_by = :user_id2) as orders,
                (SELECT COUNT(*) FROM stock_transactions WHERE created_by = :user_id3) as transactions,
                (SELECT COUNT(*) FROM audit_log WHERE user_id = :user_id4) as audit_logs,
                (SELECT COUNT(*) FROM credit_payments WHERE created_by = :user_id5) as payments
            ");
            $check_related->execute([
                ':user_id1' => $user_id,
                ':user_id2' => $user_id,
                ':user_id3' => $user_id,
                ':user_id4' => $user_id,
                ':user_id5' => $user_id
            ]);
            $related = $check_related->fetch();

            // Check if user has any related records
            $has_related = $related['invoices'] > 0 ||
                $related['orders'] > 0 ||
                $related['transactions'] > 0 ||
                $related['audit_logs'] > 0 ||
                $related['payments'] > 0;

            if ($has_related) {
                // Don't delete, just deactivate instead
                $update = $db->prepare("UPDATE users SET status = 'inactive' WHERE user_id = :user_id");
                $update->execute([':user_id' => $user_id]);

                $record_info = [];
                if ($related['invoices'] > 0) $record_info[] = $related['invoices'] . ' invoices';
                if ($related['orders'] > 0) $record_info[] = $related['orders'] . ' orders';
                if ($related['transactions'] > 0) $record_info[] = $related['transactions'] . ' transactions';
                if ($related['audit_logs'] > 0) $record_info[] = $related['audit_logs'] . ' audit logs';
                if ($related['payments'] > 0) $record_info[] = $related['payments'] . ' payments';

                $_SESSION['success'] = 'User has related records (' . implode(', ', $record_info) . ') and has been deactivated instead of deleted';
            } else {
                // Safe to delete - no foreign key constraints
                $delete = $db->prepare("DELETE FROM users WHERE user_id = :user_id");
                $delete->execute([':user_id' => $user_id]);
                $_SESSION['success'] = 'User deleted successfully';
            }

            header("Location: users.php");
            exit();
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header("Location: users.php");
            exit();
        }
    } elseif ($_POST['action'] === 'toggle_status') {
        try {
            $user_id = (int)$_POST['user_id'];

            if ($user_id == $_SESSION['user_id']) {
                throw new Exception("You cannot change your own status");
            }

            $update = $db->prepare("UPDATE users SET status = IF(status = 'active', 'inactive', 'active') WHERE user_id = :user_id");
            $update->execute([':user_id' => $user_id]);

            $_SESSION['success'] = 'User status updated successfully';
            header("Location: users.php");
            exit();
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header("Location: users.php");
            exit();
        }
    } elseif ($_POST['action'] === 'reset_password') {
        try {
            $user_id = (int)$_POST['user_id'];
            $new_password = $_POST['new_password'];

            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);

            $update = $db->prepare("UPDATE users SET password_hash = :password_hash WHERE user_id = :user_id");
            $update->execute([
                ':password_hash' => $password_hash,
                ':user_id' => $user_id
            ]);

            $_SESSION['success'] = 'Password reset successfully';
            header("Location: users.php");
            exit();
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header("Location: users.php");
            exit();
        }
    }
}

// Check if current user is System Administrator
$is_admin = false;
$check_query = "SELECT role_id FROM users WHERE user_id = :user_id";
$check_stmt = $db->prepare($check_query);
$check_stmt->execute([':user_id' => $_SESSION['user_id']]);
$current_user_data = $check_stmt->fetch();
if ($current_user_data && $current_user_data['role_id'] == 1) {
    $is_admin = true;
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-users-cog"></i> User Management</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="fas fa-plus"></i> Add User
        </button>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['success'];
                                                    unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $_SESSION['error'];
                                                            unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- User Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Users</h6>
                    <h3><?php echo count($users); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title">Active Users</h6>
                    <h3><?php echo count(array_filter($users, fn($u) => $u['status'] === 'active')); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h6 class="card-title">Inactive Users</h6>
                    <h3><?php echo count(array_filter($users, fn($u) => $u['status'] === 'inactive')); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="card-title">User Roles</h6>
                    <h3><?php echo count($roles); ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">All Users</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="usersTable">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <th>Branch</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($user['phone'] ?? '-'); ?></td>
                                <td><span class="badge bg-primary"><?php echo htmlspecialchars($user['role_name']); ?></span></td>
                                <td><?php echo htmlspecialchars($user['branch_name'] ?? 'All Branches'); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo $user['last_login'] ? format_datetime($user['last_login']) : 'Never'; ?></td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-warning"
                                            onclick="showResetPasswordModal(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')"
                                            title="Reset Password">
                                            <i class="fas fa-key"></i>
                                        </button>
                                        <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                            <button class="btn btn-sm btn-<?php echo $user['status'] === 'active' ? 'secondary' : 'success'; ?>"
                                                onclick="toggleStatus(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')"
                                                title="Toggle Status">
                                                <i class="fas fa-<?php echo $user['status'] === 'active' ? 'ban' : 'check'; ?>"></i>
                                            </button>
                                            <?php if ($is_admin): ?>
                                                <button class="btn btn-sm btn-danger"
                                                    onclick="deleteUser(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')"
                                                    title="Delete User">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
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

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="password" required minlength="6">
                        <small class="text-muted">Minimum 6 characters</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="full_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" class="form-control" name="phone">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role <span class="text-danger">*</span></label>
                        <select class="form-select" name="role_id" required>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['role_id']; ?>">
                                    <?php echo htmlspecialchars($role['role_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Branch</label>
                        <select class="form-select" name="branch_id">
                            <option value="">All Branches</option>
                            <?php foreach ($branches as $branch): ?>
                                <option value="<?php echo $branch['branch_id']; ?>">
                                    <?php echo htmlspecialchars($branch['branch_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="resetPasswordForm">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="reset_user_id">
                <div class="modal-header">
                    <h5 class="modal-title">Reset Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Reset password for user: <strong id="reset_username"></strong></p>
                    <div class="mb-3">
                        <label class="form-label">New Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="new_password" id="new_password" required minlength="6">
                        <small class="text-muted">Minimum 6 characters</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="confirm_password" required minlength="6">
                        <small class="text-danger d-none" id="password_error">Passwords do not match!</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning" id="resetPasswordBtn">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Form -->
<form method="POST" id="deleteForm" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="user_id" id="delete_user_id">
</form>

<!-- Toggle Status Form -->
<form method="POST" id="toggleStatusForm" style="display: none;">
    <input type="hidden" name="action" value="toggle_status">
    <input type="hidden" name="user_id" id="toggle_user_id">
</form>

<script>
    $(document).ready(function() {
        $('#usersTable').DataTable({
            order: [
                [1, 'asc']
            ],
            pageLength: 25
        });

        // Password match validation
        $('#confirm_password').on('keyup', function() {
            var password = $('#new_password').val();
            var confirm = $(this).val();

            if (password !== confirm) {
                $('#password_error').removeClass('d-none');
                $('#resetPasswordBtn').prop('disabled', true);
            } else {
                $('#password_error').addClass('d-none');
                $('#resetPasswordBtn').prop('disabled', false);
            }
        });
    });

    function showResetPasswordModal(userId, username) {
        $('#reset_user_id').val(userId);
        $('#reset_username').text(username);
        $('#new_password').val('');
        $('#confirm_password').val('');
        $('#password_error').addClass('d-none');
        $('#resetPasswordBtn').prop('disabled', false);

        var modal = new bootstrap.Modal(document.getElementById('resetPasswordModal'));
        modal.show();
    }

    function toggleStatus(userId, username) {
        if (confirm('Are you sure you want to toggle status for user: ' + username + '?')) {
            $('#toggle_user_id').val(userId);
            $('#toggleStatusForm').submit();
        }
    }

    function deleteUser(userId, username) {
        if (confirm('WARNING: Are you sure you want to DELETE user: ' + username + '?\n\nThis action cannot be undone. If the user has related records (invoices, orders, etc.), they will be deactivated instead of deleted.')) {
            $('#delete_user_id').val(userId);
            $('#deleteForm').submit();
        }
    }
</script>

<?php include '../includes/footer.php'; ?>
