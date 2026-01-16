<?php

/**
 * User Profile Page
 * Display user information
 */

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

require_login();

$database = new Database();
$db = $database->getConnection();

$page_title = 'My Profile';

// Get user details
$query = "SELECT u.*, r.role_name, b.branch_name
          FROM users u
          INNER JOIN user_roles r ON u.role_id = r.role_id
          LEFT JOIN branches b ON u.branch_id = b.branch_id
          WHERE u.user_id = :user_id";

$stmt = $db->prepare($query);
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$user = $stmt->fetch();

include 'includes/header.php';
?>

<div class="container-fluid">
    <h2 class="mb-4"><i class="fas fa-user-circle"></i> My Profile</h2>

    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Profile Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <th width="40%">Username:</th>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                        </tr>
                        <tr>
                            <th>Full Name:</th>
                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                        </tr>
                        <tr>
                            <th>Email:</th>
                            <td><?php echo htmlspecialchars($user['email'] ?? '-'); ?></td>
                        </tr>
                        <tr>
                            <th>Phone:</th>
                            <td><?php echo htmlspecialchars($user['phone'] ?? '-'); ?></td>
                        </tr>
                        <tr>
                            <th>Role:</th>
                            <td><span class="badge bg-primary"><?php echo htmlspecialchars($user['role_name']); ?></span></td>
                        </tr>
                        <tr>
                            <th>Branch:</th>
                            <td><?php echo htmlspecialchars($user['branch_name'] ?? 'All Branches'); ?></td>
                        </tr>
                        <tr>
                            <th>Status:</th>
                            <td><span class="badge bg-success"><?php echo ucfirst($user['status']); ?></span></td>
                        </tr>
                        <tr>
                            <th>Last Login:</th>
                            <td><?php echo format_datetime($user['last_login']); ?></td>
                        </tr>
                        <tr>
                            <th>Account Created:</th>
                            <td><?php echo format_datetime($user['created_at']); ?></td>
                        </tr>
                    </table>

                    <div class="mt-3">
                        <a href="change_password.php" class="btn btn-warning">
                            <i class="fas fa-key"></i> Change Password
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Session Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <th width="40%">Session Started:</th>
                            <td><?php echo date('d/m/Y h:i A', $_SESSION['last_activity'] ?? time()); ?></td>
                        </tr>
                        <tr>
                            <th>IP Address:</th>
                            <td><?php echo get_client_ip(); ?></td>
                        </tr>
                        <tr>
                            <th>Browser:</th>
                            <td><?php echo htmlspecialchars($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'); ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="dashboard.php" class="btn btn-outline-secondary">
                            <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                        </a>
                        <a href="logout.php" class="btn btn-outline-danger">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>