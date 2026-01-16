<?php

/**
 * Authentication Class
 * Handles user authentication and authorization
 */

class Auth
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Authenticate user
     * @param string $username Username
     * @param string $password Password
     * @return array|false User data or false
     */
    public function login($username, $password)
    {
        $query = "SELECT u.*, r.role_name, r.permissions, b.branch_name 
                  FROM users u 
                  INNER JOIN user_roles r ON u.role_id = r.role_id 
                  LEFT JOIN branches b ON u.branch_id = b.branch_id 
                  WHERE u.username = :username AND u.status = 'active'";

        $stmt = $this->db->prepare($query);
        $stmt->execute([':username' => $username]);

        if ($stmt->rowCount() == 1) {
            $user = $stmt->fetch();

            if (password_verify($password, $user['password_hash'])) {
                return $user;
            }
        }

        return false;
    }

    /**
     * Change user password
     * @param int $user_id User ID
     * @param string $old_password Current password
     * @param string $new_password New password
     * @return array Result
     */
    public function changePassword($user_id, $old_password, $new_password)
    {
        // Get current password
        $query = "SELECT password_hash FROM users WHERE user_id = :user_id";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':user_id' => $user_id]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($old_password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Current password is incorrect'];
        }

        // Validate new password
        if (strlen($new_password) < 6) {
            return ['success' => false, 'message' => 'Password must be at least 6 characters'];
        }

        // Update password
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $update_query = "UPDATE users SET password_hash = :password WHERE user_id = :user_id";
        $update_stmt = $this->db->prepare($update_query);
        $update_stmt->execute([
            ':password' => $new_hash,
            ':user_id' => $user_id
        ]);

        return ['success' => true, 'message' => 'Password changed successfully'];
    }

    /**
     * Reset user password
     * @param int $user_id User ID
     * @return string Temporary password
     */
    public function resetPassword($user_id)
    {
        $temp_password = generate_random_string(10);
        $password_hash = password_hash($temp_password, PASSWORD_DEFAULT);

        $query = "UPDATE users SET password_hash = :password WHERE user_id = :user_id";
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':password' => $password_hash,
            ':user_id' => $user_id
        ]);

        return $temp_password;
    }

    /**
     * Create new user
     * @param array $data User data
     * @return array Result
     */
    public function createUser($data)
    {
        try {
            // Validate username uniqueness
            $check = $this->db->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
            $check->execute([':username' => $data['username']]);

            if ($check->fetchColumn() > 0) {
                return ['success' => false, 'message' => 'Username already exists'];
            }

            // Hash password
            $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);

            // Insert user
            $query = "INSERT INTO users 
                     (username, password_hash, full_name, email, phone, role_id, branch_id, status)
                     VALUES 
                     (:username, :password_hash, :full_name, :email, :phone, :role_id, :branch_id, 'active')";

            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':username' => $data['username'],
                ':password_hash' => $password_hash,
                ':full_name' => $data['full_name'],
                ':email' => $data['email'] ?? null,
                ':phone' => $data['phone'] ?? null,
                ':role_id' => $data['role_id'],
                ':branch_id' => $data['branch_id'] ?? null
            ]);

            $user_id = $this->db->lastInsertId();

            return ['success' => true, 'user_id' => $user_id, 'message' => 'User created successfully'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Update user
     * @param int $user_id User ID
     * @param array $data User data
     * @return array Result
     */
    public function updateUser($user_id, $data)
    {
        try {
            $query = "UPDATE users 
                     SET full_name = :full_name,
                         email = :email,
                         phone = :phone,
                         role_id = :role_id,
                         branch_id = :branch_id,
                         status = :status
                     WHERE user_id = :user_id";

            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':full_name' => $data['full_name'],
                ':email' => $data['email'] ?? null,
                ':phone' => $data['phone'] ?? null,
                ':role_id' => $data['role_id'],
                ':branch_id' => $data['branch_id'] ?? null,
                ':status' => $data['status'],
                ':user_id' => $user_id
            ]);

            return ['success' => true, 'message' => 'User updated successfully'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Delete user
     * @param int $user_id User ID
     * @return array Result
     */
    public function deleteUser($user_id)
    {
        try {
            // Don't allow deleting own account
            if ($user_id == $_SESSION['user_id']) {
                return ['success' => false, 'message' => 'Cannot delete your own account'];
            }

            $query = "UPDATE users SET status = 'inactive' WHERE user_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':user_id' => $user_id]);

            return ['success' => true, 'message' => 'User deactivated successfully'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get user by ID
     * @param int $user_id User ID
     * @return array|false User data or false
     */
    public function getUserById($user_id)
    {
        $query = "SELECT u.*, r.role_name, b.branch_name
                  FROM users u
                  INNER JOIN user_roles r ON u.role_id = r.role_id
                  LEFT JOIN branches b ON u.branch_id = b.branch_id
                  WHERE u.user_id = :user_id";

        $stmt = $this->db->prepare($query);
        $stmt->execute([':user_id' => $user_id]);

        return $stmt->fetch();
    }

    /**
     * Get all users
     * @return array Users list
     */
    public function getAllUsers()
    {
        $query = "SELECT u.*, r.role_name, b.branch_name
                  FROM users u
                  INNER JOIN user_roles r ON u.role_id = r.role_id
                  LEFT JOIN branches b ON u.branch_id = b.branch_id
                  ORDER BY u.full_name";

        return $this->db->query($query)->fetchAll();
    }

    /**
     * Get user roles
     * @return array Roles list
     */
    public function getRoles()
    {
        return $this->db->query("SELECT * FROM user_roles ORDER BY role_name")->fetchAll();
    }
}
