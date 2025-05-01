<?php
/**
 * Authentication class
 */
class Auth {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Check if user is logged in
     * @return boolean
     */
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    /**
     * Login user
     * @param string $email
     * @param string $password
     * @return array
     */
    public function login($email, $password) {
        $stmt = $this->db->prepare('SELECT id, password, role, name FROM users WHERE email = ? AND active = 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            // Set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['name'];
            
            // Sätt system_admin sessionen om användaren har rollen systemadmin
            if ($user['role'] === 'systemadmin') {
                $_SESSION['system_admin'] = true;
            }
            
            return ['success' => true, 'user_id' => $user['id'], 'role' => $user['role'], 'name' => $user['name']];
        }
        
        return ['success' => false, 'message' => 'Ogiltig e-post eller lösenord'];
    }
    
    /**
     * Log out user
     */
    public function logout() {
        unset($_SESSION['user_id']);
        unset($_SESSION['user_role']);
        unset($_SESSION['user_name']);
        unset($_SESSION['admin_login']);
        unset($_SESSION['staff_id']);
        unset($_SESSION['staff_name']);
        unset($_SESSION['shop_id']);
        unset($_SESSION['shop_name']);
        unset($_SESSION['staff_role']);
        unset($_SESSION['company_id']);
        unset($_SESSION['system_admin']); // Glöm inte att ta bort system_admin också
        session_destroy();
    }
    
    /**
     * Create a new user
     * @param string $email
     * @param string $password
     * @param string $name
     * @param string $role
     * @return array
     */
    public function createUser($email, $password, $name, $role = 'customer') {
        // Check if email already exists
        $stmt = $this->db->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'E-postadressen används redan'];
        }
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user
        $stmt = $this->db->prepare('INSERT INTO users (email, password, name, role) VALUES (?, ?, ?, ?)');
        if ($stmt->execute([$email, $hashedPassword, $name, $role])) {
            return ['success' => true, 'user_id' => $this->db->lastInsertId()];
        }
        
        return ['success' => false, 'message' => 'Kunde inte skapa användare'];
    }
    
    /**
     * Get current user
     * @return array|null
     */
    public function getCurrentUser() {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        
        $stmt = $this->db->prepare('SELECT id, email, name, role FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Check if user has role
     * @param string $role
     * @return boolean
     */
    public function hasRole($role) {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
    }
    /**
     * Authenticate staff member
     * @param string $username Email or username
     * @param string $password Password
     * @return array|boolean Staff data or false if authentication fails
     */
    public function authenticateStaff($username, $password) {
        // First try email-based login (looking up in users table)
        $stmt = $this->db->prepare("
            SELECT s.id, s.user_id, u.name, s.shop_id, sh.name as shop_name, s.role, sh.company_id
            FROM staff s
            JOIN users u ON s.user_id = u.id
            JOIN shops sh ON s.shop_id = sh.id
            WHERE u.email = ? AND u.active = 1 AND s.active = 1
        ");
        
        $stmt->execute([$username]);
        $staff = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($staff) {
            // Get the user's password
            $userStmt = $this->db->prepare("SELECT password FROM users WHERE id = ?");
            $userStmt->execute([$staff['user_id']]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                return $staff;
            }
        }
        
        // If email login failed, try username-based login (if staff has separate credentials)
        $stmt = $this->db->prepare("
            SELECT s.id, s.user_id, u.name, s.shop_id, sh.name as shop_name, s.role, sh.company_id, s.password
            FROM staff s
            JOIN users u ON s.user_id = u.id
            JOIN shops sh ON s.shop_id = sh.id
            WHERE s.username = ? AND s.active = 1
        ");
        
        $stmt->execute([$username]);
        $staff = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($staff && !empty($staff['password'])) {
            // Verify password - accept both hashed and plain text passwords
            if (password_verify($password, $staff['password']) || $password === $staff['password']) {
                return $staff;
            }
        }
        
        return false;
    }
    
    /**
     * Check if user is a company admin
     * @param int $userId User ID
     * @return boolean
     */
    public function isCompanyAdmin($userId) {
        $stmt = $this->db->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return ($user && ($user['role'] === 'admin' || $user['role'] === 'manager' || $user['role'] === 'systemadmin'));
    }
    /**
     * Authenticate shop admin
     * @param string $email
     * @param string $password
     * @return array|boolean
     */
    public function authenticateShopAdmin($email, $password) {
        // Först autentisera användaren
        $result = $this->login($email, $password);
        
        if (!$result['success']) {
            return false;
        }
        
        // Om användaren är systemadmin, låt dem komma åt alla butiker
        if ($_SESSION['user_role'] === 'systemadmin') {
            return ['is_system_admin' => true, 'user_id' => $result['user_id']];
        }
        
        // Kontrollera om användaren är kopplad till en shop som admin
        $stmt = $this->db->prepare("
            SELECT s.id, s.user_id, u.name, s.shop_id, sh.name as shop_name, s.role, sh.company_id
            FROM staff s
            JOIN users u ON s.user_id = u.id
            JOIN shops sh ON s.shop_id = sh.id
            WHERE u.id = ? AND s.role = '1' AND s.active = 1
        ");
        
        $stmt->execute([$result['user_id']]);
        $shopAdmin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($shopAdmin) {
            return $shopAdmin;
        }
        
        return false;
    }
    /**
     * Get shops administered by user
     * @param int $userId
     * @return array
     */
    public function getUserShops($userId) {
        $stmt = $this->db->prepare("
            SELECT s.id as staff_id, s.shop_id, sh.name as shop_name, s.role, sh.company_id
            FROM staff s
            JOIN shops sh ON s.shop_id = sh.id
            WHERE s.user_id = ? AND s.active = 1
        ");
        
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    /**
     * Hämtar företagsinformation för en inloggad administratör
     * 
     * @param int $user_id Användar-ID för administratören
     * @return array|false Företagsinformation eller false om ingen hittades
     */
    public function getCompanyForAdmin($user_id) {
        try {
            // Om det är en systemadmin, försök hitta ett företag kopplat till dem
            $stmt = $this->db->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && $user['role'] === 'systemadmin') {
                // Systemadmin kan se alla företag
                // Försöker först hitta om de är kopplad till ett specifikt företag
                $stmt = $this->db->prepare("
                    SELECT c.* 
                    FROM companies c
                    WHERE c.admin_id = ? OR c.created_by = ?
                    LIMIT 1
                ");
                $stmt->execute([$user_id, $user_id]);
                $company = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($company) {
                    return $company;
                }
                
                // Om systemadmin inte är direkt kopplad till något företag, returnera det första
                $stmt = $this->db->prepare("SELECT * FROM companies ORDER BY id ASC LIMIT 1");
                $stmt->execute();
                return $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            // För andra administratörstyper, leta efter direkt koppling
            // Försök först i staff-tabellen
            $stmt = $this->db->prepare("
                SELECT c.*
                FROM staff s
                JOIN shops sh ON s.shop_id = sh.id
                JOIN companies c ON sh.company_id = c.id
                WHERE s.user_id = ? AND s.role = '1' AND s.active = 1
                LIMIT 1
            ");
            $stmt->execute([$user_id]);
            $company = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($company) {
                return $company;
            }
            
            // Om inte i staff, kolla direkt i companies
            $stmt = $this->db->prepare("
                SELECT * FROM companies 
                WHERE admin_id = ? OR created_by = ?
                LIMIT 1
            ");
            $stmt->execute([$user_id, $user_id]);
            $company = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($company) {
                return $company;
            }
            
            // Om inget företag hittas
            return false;
            
        } catch (PDOException $e) {
            // Logga fel och returnera false
            error_log('Error in getCompanyForAdmin: ' . $e->getMessage());
            return false;
        }
    }
    /**
     * Add staff member
     * 
     * @param array $staffData Staff data
     * @return bool|int False on failure, staff ID on success
     */
    public function addStaff($staffData) {
        // Validate required fields
        $requiredFields = ['name', 'email', 'password', 'company_id'];
        foreach ($requiredFields as $field) {
            if (empty($staffData[$field])) {
                return false;
            }
        }
        
        // Check if email already exists
        $stmt = $this->db->prepare("SELECT id FROM staff WHERE email = ?");
        $stmt->execute([$staffData['email']]);
        if ($stmt->rowCount() > 0) {
            return false;
        }
        
        // Hash password
        $hashedPassword = password_hash($staffData['password'], PASSWORD_DEFAULT);
        
        // Insert staff
        $stmt = $this->db->prepare("
            INSERT INTO staff (
                company_id, shop_id, name, email, password, phone, role, active, created_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, 1, NOW()
            )
        ");
        
        $success = $stmt->execute([
            $staffData['company_id'],
            $staffData['shop_id'] ?? null,
            $staffData['name'],
            $staffData['email'],
            $hashedPassword,
            $staffData['phone'] ?? null,
            $staffData['role'] ?? 'staff'
        ]);
        
        if (!$success) {
            return false;
        }
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Update staff member
     * 
     * @param int $staffId Staff ID
     * @param array $staffData Staff data
     * @return bool Success or failure
     */
    public function updateStaff($staffId, $staffData) {
        // Prepare SQL parts and parameters
        $sqlParts = [];
        $params = [];
        
        // Build update parts
        if (isset($staffData['name'])) {
            $sqlParts[] = "name = ?";
            $params[] = $staffData['name'];
        }
        
        if (isset($staffData['email'])) {
            $sqlParts[] = "email = ?";
            $params[] = $staffData['email'];
        }
        
        if (isset($staffData['phone'])) {
            $sqlParts[] = "phone = ?";
            $params[] = $staffData['phone'];
        }
        
        if (isset($staffData['shop_id'])) {
            $sqlParts[] = "shop_id = ?";
            $params[] = $staffData['shop_id'] ?: null;
        }
        
        if (isset($staffData['role'])) {
            $sqlParts[] = "role = ?";
            $params[] = $staffData['role'];
        }
        
        if (isset($staffData['active'])) {
            $sqlParts[] = "active = ?";
            $params[] = $staffData['active'];
        }
        
        if (!empty($staffData['password'])) {
            $sqlParts[] = "password = ?";
            $params[] = password_hash($staffData['password'], PASSWORD_DEFAULT);
        }
        
        // If nothing to update
        if (empty($sqlParts)) {
            return true;
        }
        
        // Add staff ID to params
        $params[] = $staffId;
        
        // Execute update
        $sql = "UPDATE staff SET " . implode(", ", $sqlParts) . ", updated_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($params);
    }
    
    /**
     * Delete staff member
     * 
     * @param int $staffId Staff ID
     * @return bool Success or failure
     */
    public function deleteStaff($staffId) {
        $stmt = $this->db->prepare("DELETE FROM staff WHERE id = ?");
        return $stmt->execute([$staffId]);
    }
}
