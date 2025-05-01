<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// company_dashboard.php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';
require_once '../Auth.php';
// Skapa Auth-objekt
$auth = new Auth($pdo);
// Kontrollera att användaren är inloggad som admin
if (!isset($_SESSION['admin_login']) || $_SESSION['admin_login'] !== true) {
    header('Location: index.php');
    exit;
}
// Hämta användarinformation
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Admin';
// Hämta företagsinformation
$company = $auth->getCompanyForAdmin($user_id);
if (!$company) {
    // Om av någon anledning administratören inte är kopplad till ett företag
    $_SESSION['error_message'] = 'No company associated with this admin account.';
    header('Location: index.php');
    exit;
}
$company_id = $company['id'];
$company_name = $company['name'];
// Hämta statistik
$stats = [
    'total_coupons' => 0,
    'active_coupons' => 0,
    'redeemed_coupons' => 0,
    'total_shops' => 0,
    'total_staff' => 0,
    'recent_activity' => []
];
// Hämta kupongstatistik
try {
    // Totalt antal kuponger
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM coupons WHERE company_id = ?");
    $stmt->execute([$company_id]);
    $stats['total_coupons'] = $stmt->fetchColumn();
    
    // Aktiva kuponger
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM coupons WHERE company_id = ? AND status = 'active'");
    $stmt->execute([$company_id]);
    $stats['active_coupons'] = $stmt->fetchColumn();
    
    // Inlösta kuponger (baserat på current_uses > 0)
    $stmt = $pdo->prepare("SELECT SUM(current_uses) FROM coupons WHERE company_id = ?");
    $stmt->execute([$company_id]);
    $stats['redeemed_coupons'] = $stmt->fetchColumn() ?: 0;
    
    // Antal butiker
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM shops WHERE company_id = ?");
    $stmt->execute([$company_id]);
    $stats['total_shops'] = $stmt->fetchColumn();
    
    // Antal personal - Uppdaterad för att använda kopplingen via shop
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM staff s
        JOIN shops sh ON s.shop_id = sh.id
        WHERE sh.company_id = ?
    ");
    $stmt->execute([$company_id]);
    $stats['total_staff'] = $stmt->fetchColumn();
    
    // Senaste aktiviteter (t.ex. kuponginlösningar)
    // Anta att vi har en tabell 'coupon_redemptions' eller liknande
    $stmt = $pdo->prepare("
        SELECT cr.id, cr.redemption_time, c.title as coupon_title, s.name as shop_name, st.email as staff_name
        FROM coupon_redemptions cr
        JOIN coupons c ON cr.coupon_id = c.id
        JOIN shops s ON cr.shop_id = s.id
        JOIN staff st ON cr.staff_id = st.id
        WHERE c.company_id = ?
        ORDER BY cr.redemption_time DESC
        LIMIT 5
    ");
    
    try {
        $stmt->execute([$company_id]);
        $stats['recent_activity'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Tabell kanske inte finns, ignorera
        $stats['recent_activity'] = [];
    }
    
} catch (PDOException $e) {
    // Logga felet men fortsätt
    error_log('Error fetching statistics: ' . $e->getMessage());
}
// Hämta butiker (shops) för detta företag
$shops = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, address, contact_info FROM shops WHERE company_id = ? ORDER BY name");
    $stmt->execute([$company_id]);
    $shops = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Hantera fel
    error_log('Error fetching shops: ' . $e->getMessage());
}
// Hämta all personal för detta företag - Uppdaterad för att använda kopplingen via shop
$staff = [];
try {
    $stmt = $pdo->prepare("
        SELECT s.id, s.email, s.role, s.username, s.shop_id, sh.name as shop_name
        FROM staff s
        JOIN shops sh ON s.shop_id = sh.id
        WHERE sh.company_id = ? AND s.active = 1
        ORDER BY s.email
    ");
    $stmt->execute([$company_id]);
    $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Hantera fel
    error_log('Error fetching staff: ' . $e->getMessage());
}
// Meddelanden
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
// Rensa sessionsmeddelanden
unset($_SESSION['success_message'], $_SESSION['error_message']);
// Inkludera sidhuvud
include 'includes/header.php';
?>
<!-- Custom styles for this page -->
<style>
    .stats-card {
        transition: all 0.3s;
        border-radius: 10px;
        overflow: hidden;
    }
    
    .stats-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
    
    .stats-icon {
        font-size: 2.5rem;
        opacity: 0.8;
    }
    
    .stats-card.coupons {
        background: linear-gradient(45deg, #4e73df, #2e59d9);
    }
    
    .stats-card.shops {
        background: linear-gradient(45deg, #1cc88a, #10ac76);
    }
    
    .stats-card.staff {
        background: linear-gradient(45deg, #36b9cc, #258391);
    }
    
    .stats-card.redemptions {
        background: linear-gradient(45deg, #f6c23e, #dda20a);
    }
    
    .action-btn {
        transition: all 0.2s;
    }
    
    .action-btn:hover {
        transform: translateY(-2px);
    }
    
    .table-responsive {
        padding: 0;
    }
    
    .card-body {
        padding: 1.25rem;
    }
    
    .nav-tabs .nav-link {
        border: none;
        color: #5a5c69;
        font-weight: 600;
        padding: 1rem 1.5rem;
    }
    
    .nav-tabs .nav-link.active {
        color: #4e73df;
        border-bottom: 3px solid #4e73df;
        background-color: transparent;
    }
    
    .tab-content {
        padding: 1.5rem 0;
    }
    
    .dashboard-header {
        background-color: #f8f9fc;
        border-left: 4px solid #4e73df;
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 0 5px 5px 0;
    }
    
    .staff-card {
        transition: all 0.3s;
        border: 1px solid #e3e6f0;
        border-radius: 0.35rem;
    }
    
    .staff-card:hover {
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
    }
    
    .staff-card .staff-info {
        padding: 1rem;
    }
    
    .staff-card .staff-actions {
        padding: 0.5rem 1rem;
        background-color: #f8f9fc;
        border-top: 1px solid #e3e6f0;
    }
    
    .shop-card {
        transition: all 0.3s;
        border: 1px solid #e3e6f0;
        border-radius: 0.35rem;
    }
    
    .shop-card:hover {
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
    }
    
    .shop-card .shop-info {
        padding: 1rem;
    }
    
    .shop-card .shop-actions {
        padding: 0.5rem 1rem;
        background-color: #f8f9fc;
        border-top: 1px solid #e3e6f0;
    }
</style>
<div class="container-fluid">
    <div class="dashboard-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="h3 mb-0 text-gray-800">
                    <i class="fas fa-building mr-2"></i> <?php echo htmlspecialchars($company_name); ?> Dashboard
                </h1>
                <p class="text-muted mb-0">Welcome back, <?php echo htmlspecialchars($user_name); ?>!</p>
            </div>
            <div class="col-md-4 text-right">
                <a href="company_profile.php" class="btn btn-outline-primary mr-2">
                    <i class="fas fa-cog"></i> Company Settings
                </a>
                <a href="logout.php" class="btn btn-outline-danger">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>
    
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle mr-2"></i> <?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle mr-2"></i> <?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>
    
    <!-- Statistics Cards -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 stats-card coupons text-white">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-3">
                            <i class="fas fa-ticket-alt stats-icon"></i>
                        </div>
                        <div class="col-9 text-right">
                            <div class="h1 mb-0"><?php echo $stats['total_coupons']; ?></div>
                            <div class="text-white-50">Total Coupons</div>
                            <div class="small mt-2">
                                <span class="font-weight-bold"><?php echo $stats['active_coupons']; ?></span> active
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-dark bg-opacity-25 d-flex justify-content-between align-items-center">
                    <a href="coupons.php" class="text-white small">View Coupons</a>
                    <a href="add_coupon.php?company_id=<?php echo $company_id; ?>" class="text-white small">
                        <i class="fas fa-plus-circle"></i> Add
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 stats-card redemptions text-white">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-3">
                            <i class="fas fa-check-circle stats-icon"></i>
                        </div>
                        <div class="col-9 text-right">
                            <div class="h1 mb-0"><?php echo $stats['redeemed_coupons']; ?></div>
                            <div class="text-white-50">Redeemed Coupons</div>
                            <div class="small mt-2">
                                <span class="font-weight-bold">
                                    <?php 
                                    echo ($stats['total_coupons'] > 0) 
                                        ? round(($stats['redeemed_coupons'] / $stats['total_coupons']) * 100) 
                                        : 0; 
                                    ?>%
                                </span> redemption rate
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-dark bg-opacity-25 d-flex justify-content-between align-items-center">
                    <a href="redemptions.php" class="text-white small">View Redemptions</a>
                    <span class="text-white small">
                        <i class="fas fa-chart-line"></i> Analytics
                    </span>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 stats-card shops text-white">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-3">
                            <i class="fas fa-store stats-icon"></i>
                        </div>
                        <div class="col-9 text-right">
                            <div class="h1 mb-0"><?php echo $stats['total_shops']; ?></div>
                            <div class="text-white-50">Shops/Locations</div>
                            <div class="small mt-2">
                                <span class="font-weight-bold">Manage</span> your locations
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-dark bg-opacity-25 d-flex justify-content-between align-items-center">
                    <a href="#shops-tab" class="text-white small" data-toggle="tab" onclick="$('#shops-tab-btn').tab('show')">View Shops</a>
                    <a href="#" class="text-white small" data-toggle="modal" data-target="#addShopModal">
                        <i class="fas fa-plus-circle"></i> Add
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 stats-card staff text-white">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-3">
                            <i class="fas fa-users stats-icon"></i>
                        </div>
                        <div class="col-9 text-right">
                            <div class="h1 mb-0"><?php echo $stats['total_staff']; ?></div>
                            <div class="text-white-50">Staff Members</div>
                            <div class="small mt-2">
                                <span class="font-weight-bold">Manage</span> your team
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-dark bg-opacity-25 d-flex justify-content-between align-items-center">
                    <a href="#staff-tab" class="text-white small" data-toggle="tab" onclick="$('#staff-tab-btn').tab('show')">View Staff</a>
                    <a href="#" class="text-white small" data-toggle="modal" data-target="#addStaffModal">
                        <i class="fas fa-plus-circle"></i> Add
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content Tabs -->
    <div class="card shadow mb-4">
        <div class="card-header p-0">
            <ul class="nav nav-tabs" id="dashboardTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="overview-tab-btn" data-toggle="tab" href="#overview-tab" role="tab">
                        <i class="fas fa-home mr-1"></i> Overview
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="staff-tab-btn" data-toggle="tab" href="#staff-tab" role="tab">
                        <i class="fas fa-users mr-1"></i> Staff Management
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="shops-tab-btn" data-toggle="tab" href="#shops-tab" role="tab">
                        <i class="fas fa-store mr-1"></i> Shops/Locations
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="coupons-tab-btn" data-toggle="tab" href="#coupons-tab" role="tab">
                        <i class="fas fa-ticket-alt mr-1"></i> Coupons
                    </a>
                </li>
            </ul>
        </div>
        
        <div class="card-body">
            <div class="tab-content" id="dashboardTabContent">
                <!-- Overview Tab -->
                <div class="tab-pane fade show active" id="overview-tab" role="tabpanel">
                    <div class="row">
                        <div class="col-md-8">
                            <h4 class="mb-4">Recent Activity</h4>
                            <?php if (!empty($stats['recent_activity'])): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Date/Time</th>
                                                <th>Coupon</th>
                                                <th>Shop</th>
                                                <th>Redeemed By</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($stats['recent_activity'] as $activity): ?>
                                                <tr>
                                                    <td><?php echo date('M d, Y H:i', strtotime($activity['redemption_time'])); ?></td>
                                                    <td><?php echo htmlspecialchars($activity['coupon_title']); ?></td>
                                                    <td><?php echo htmlspecialchars($activity['shop_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($activity['staff_name']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <a href="redemptions.php" class="btn btn-sm btn-outline-primary">View All Activity</a>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle mr-2"></i> No recent activity to display.
                                </div>
                            <?php endif; ?>
                            
                            <h4 class="mb-4 mt-5">Quick Actions</h4>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <a href="add_coupon.php?company_id=<?php echo $company_id; ?>" class="btn btn-primary btn-block action-btn py-3">
                                        <i class="fas fa-ticket-alt mb-2 d-block" style="font-size: 2rem;"></i>
                                        Add New Coupon
                                    </a>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <a href="#" class="btn btn-success btn-block action-btn py-3" data-toggle="modal" data-target="#addStaffModal">
                                        <i class="fas fa-user-plus mb-2 d-block" style="font-size: 2rem;"></i>
                                        Add Staff Member
                                    </a>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <a href="#" class="btn btn-info btn-block action-btn py-3" data-toggle="modal" data-target="#addShopModal">
                                        <i class="fas fa-store mb-2 d-block" style="font-size: 2rem;"></i>
                                        Add New Shop
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card mb-4">
                                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                    <h6 class="m-0 font-weight-bold text-primary">Company Info</h6>
                                    <a href="company_profile.php" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </div>
                                <div class="card-body">
                                    <div class="text-center mb-3">
                                        <?php if (!empty($company['logo'])): ?>
                                            <img src="<?php echo htmlspecialchars($company['logo']); ?>" class="img-fluid rounded" style="max-height: 80px;" alt="Company Logo">
                                        <?php else: ?>
                                            <div class="bg-light p-3 rounded">
                                                <i class="fas fa-building text-secondary" style="font-size: 3rem;"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span class="text-muted">Name:</span>
                                            <span class="font-weight-bold"><?php echo htmlspecialchars($company_name); ?></span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span class="text-muted">Phone:</span>
                                            <span><?php echo htmlspecialchars($company['phone'] ?? 'Not set'); ?></span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span class="text-muted">Email:</span>
                                            <span><?php echo htmlspecialchars($company['email'] ?? 'Not set'); ?></span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span class="text-muted">Address:</span>
                                            <span><?php echo htmlspecialchars($company['address'] ?? 'Not set'); ?></span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="card">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Need Help?</h6>
                                </div>
                                <div class="card-body">
                                    <p>If you need assistance with your dashboard or have questions, our support team is here to help.</p>
                                    <a href="support.php" class="btn btn-block btn-outline-primary">
                                        <i class="fas fa-headset mr-1"></i> Contact Support
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Staff Management Tab -->
                <div class="tab-pane fade" id="staff-tab" role="tabpanel">
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h4 class="mb-0">Staff Management</h4>
                        <button class="btn btn-success" data-toggle="modal" data-target="#addStaffModal">
                            <i class="fas fa-user-plus mr-1"></i> Add New Staff
                        </button>
                    </div>
                    
                    <?php if (empty($staff)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle mr-2"></i> No staff members added yet. Add your first staff member to get started.
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($staff as $member): ?>
                                <div class="col-lg-4 col-md-6 mb-4">
                                    <div class="staff-card">
                                        <div class="staff-info">
                                            <h5 class="mb-1"><?php echo htmlspecialchars($member['username'] ?: 'No Username'); ?></h5>
                                            
                                            <?php 
                                            $role_label = '';
                                            $role_class = '';
                                            
                                            if ($member['role'] == '1' || $member['role'] == 'admin') {
                                                $role_label = 'Admin';
                                                $role_class = 'badge-danger';
                                            } else {
                                                $role_label = 'Staff';
                                                $role_class = 'badge-secondary';
                                            }
                                            ?>
                                            
                                            <span class="badge <?php echo $role_class; ?> mb-2"><?php echo $role_label; ?></span>
                                            
                                            <?php if (!empty($member['shop_name'])): ?>
                                                <span class="badge badge-info mb-2"><?php echo htmlspecialchars($member['shop_name']); ?></span>
                                            <?php endif; ?>
                                            
                                            <div class="mt-2">
                                                <?php if (!empty($member['email'])): ?>
                                                    <p class="mb-1 small">
                                                        <i class="fas fa-envelope text-muted mr-2"></i> <?php echo htmlspecialchars($member['email']); ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="staff-actions d-flex justify-content-between">
                                            <button class="btn btn-sm btn-outline-primary edit-staff" 
                                                    data-id="<?php echo $member['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($member['username'] ?: ''); ?>"
                                                    data-email="<?php echo htmlspecialchars($member['email'] ?: ''); ?>"
                                                    data-role="<?php echo htmlspecialchars($member['role'] ?: ''); ?>"
                                                    data-shop="<?php echo htmlspecialchars($member['shop_id'] ?: ''); ?>">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger delete-staff"
                                                    data-id="<?php echo $member['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($member['username'] ?: $member['email']); ?>">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Shops/Locations Tab -->
                <div class="tab-pane fade" id="shops-tab" role="tabpanel">
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h4 class="mb-0">Shops/Locations</h4>
                        <button class="btn btn-info" data-toggle="modal" data-target="#addShopModal">
                            <i class="fas fa-plus-circle mr-1"></i> Add New Shop
                        </button>
                    </div>
                    
                    <?php if (empty($shops)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle mr-2"></i> No shops added yet. Add your first shop to get started.
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($shops as $shop): ?>
                                <div class="col-lg-4 col-md-6 mb-4">
                                    <div class="shop-card">
                                        <div class="shop-info">
                                            <h5 class="mb-1"><?php echo htmlspecialchars($shop['name']); ?></h5>
                                            
                                            <div class="mt-3">
                                                <?php if (!empty($shop['address'])): ?>
                                                    <p class="mb-1 small">
                                                        <i class="fas fa-map-marker-alt text-muted mr-2"></i> <?php echo htmlspecialchars($shop['address']); ?>
                                                    </p>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($shop['contact_info'])): ?>
                                                    <p class="mb-1 small">
                                                        <i class="fas fa-phone text-muted mr-2"></i> <?php echo htmlspecialchars($shop['contact_info']); ?>
                                                    </p>
                                                <?php endif; ?>
                                                
                                                <?php
                                                // Count staff for this shop
                                                $shop_staff_count = 0;
                                                foreach ($staff as $member) {
                                                    if (isset($member['shop_id']) && $member['shop_id'] == $shop['id']) {
                                                        $shop_staff_count++;
                                                    }
                                                }
                                                ?>
                                                
                                                <p class="mb-1 small">
                                                    <i class="fas fa-users text-muted mr-2"></i> <?php echo $shop_staff_count; ?> staff members
                                                </p>
                                            </div>
                                        </div>
                                        <div class="shop-actions d-flex justify-content-between">
                                            <button class="btn btn-sm btn-outline-primary edit-shop" 
                                                    data-id="<?php echo $shop['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($shop['name']); ?>"
                                                    data-address="<?php echo htmlspecialchars($shop['address'] ?? ''); ?>"
                                                    data-contact="<?php echo htmlspecialchars($shop['contact_info'] ?? ''); ?>">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger delete-shop"
                                                    data-id="<?php echo $shop['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($shop['name']); ?>"
                                                    data-staff="<?php echo $shop_staff_count; ?>">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Coupons Tab -->
                <div class="tab-pane fade" id="coupons-tab" role="tabpanel">
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h4 class="mb-0">Manage Coupons</h4>
                        <a href="add_coupon.php?company_id=<?php echo $company_id; ?>" class="btn btn-primary">
                            <i class="fas fa-plus-circle mr-1"></i> Add New Coupon
                        </a>
                    </div>
                    
                    <!-- Just show a link to the full coupons page for now -->
                    <div class="text-center py-5">
                        <i class="fas fa-ticket-alt text-primary mb-3" style="font-size: 4rem;"></i>
                        <h5 class="mb-3">Manage Your Coupons</h5>
                        <p class="text-muted mb-4">View, create, and manage all of your company's coupons.</p>
                        <a href="coupons.php" class="btn btn-primary btn-lg px-5">
                            <i class="fas fa-ticket-alt mr-1"></i> Go to Coupons Page
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Add Staff Modal -->
<div class="modal fade" id="addStaffModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="fas fa-user-plus"></i> Add New Staff Member
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="process_staff.php" method="post" id="addStaffForm">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label for="name">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="text" class="form-control" id="phone" name="phone">
                    </div>
                    
                    <div class="form-group">
                        <label for="shop_id">Assign to Shop <span class="text-danger">*</span></label>
                        <select class="form-control" id="shop_id" name="shop_id" required>
                            <option value="">-- Select Shop --</option>
                            <?php if (empty($shops)): ?>
                                <option value="" disabled>No shops available - create a shop first</option>
                            <?php else: ?>
                                <?php foreach ($shops as $shop): ?>
                                    <option value="<?php echo $shop['id']; ?>"><?php echo htmlspecialchars($shop['name']); ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <small class="form-text text-muted">A shop assignment is required for all staff members.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="role">Role</label>
                        <select class="form-control" id="role" name="role">
                            <option value="staff">Regular Staff</option>
                            <option value="admin">Shop Admin</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save mr-1"></i> Add Staff
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Edit Staff Modal -->
<div class="modal fade" id="editStaffModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-user-edit"></i> Edit Staff Member
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="process_staff.php" method="post" id="editStaffForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="staff_id" id="edit_staff_id">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_name">Username/Name</label>
                        <input type="text" class="form-control" id="edit_name" name="name" disabled>
                        <small class="form-text text-muted">Username cannot be changed</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_email">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="edit_email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_shop_id">Assign to Shop <span class="text-danger">*</span></label>
                        <select class="form-control" id="edit_shop_id" name="shop_id" required>
                            <option value="">-- Select Shop --</option>
                            <?php foreach ($shops as $shop): ?>
                                <option value="<?php echo $shop['id']; ?>"><?php echo htmlspecialchars($shop['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">A shop assignment is required for all staff members.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_role">Role</label>
                        <select class="form-control" id="edit_role" name="role">
                            <option value="staff">Regular Staff</option>
                            <option value="admin">Shop Admin</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_password">New Password</label>
                        <input type="password" class="form-control" id="edit_password" name="password" placeholder="Leave blank to keep current password">
                        <small class="form-text text-muted">Fill only if you want to change the password</small>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save mr-1"></i> Update Staff
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Add Shop Modal -->
<div class="modal fade" id="addShopModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="fas fa-store"></i> Add New Shop/Location
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="process_shop.php" method="post" id="addShopForm">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="company_id" value="<?php echo $company_id; ?>">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label for="shop_name">Shop Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="shop_name" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="shop_address">Address</label>
                        <textarea class="form-control" id="shop_address" name="address" rows="2"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="shop_contact">Contact Info</label>
                        <input type="text" class="form-control" id="shop_contact" name="contact_info">
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info">
                        <i class="fas fa-save mr-1"></i> Add Shop
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Edit Shop Modal -->
<div class="modal fade" id="editShopModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-edit"></i> Edit Shop/Location
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="process_shop.php" method="post" id="editShopForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="shop_id" id="edit_shop_id">
                <input type="hidden" name="company_id" value="<?php echo $company_id; ?>">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_shop_name">Shop Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_shop_name" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_shop_address">Address</label>
                        <textarea class="form-control" id="edit_shop_address" name="address" rows="2"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_shop_contact">Contact Info</label>
                        <input type="text" class="form-control" id="edit_shop_contact" name="contact_info">
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save mr-1"></i> Update Shop
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Delete Staff Confirmation Modal -->
<div class="modal fade" id="deleteStaffModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle"></i> Delete Staff Member
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="process_staff.php" method="post">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="staff_id" id="delete_staff_id">
                
                <div class="modal-body">
                    <p>Are you sure you want to delete the staff member: <strong id="delete_staff_name"></strong>?</p>
                    <p class="text-danger">This action cannot be undone. The staff member will no longer be able to log in to the system.</p>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash mr-1"></i> Delete Staff
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Delete Shop Confirmation Modal -->
<div class="modal fade" id="deleteShopModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle"></i> Delete Shop/Location
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="process_shop.php" method="post">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="shop_id" id="delete_shop_id">
                <input type="hidden" name="company_id" value="<?php echo $company_id; ?>">
                
                <div class="modal-body">
                    <p>Are you sure you want to delete the shop: <strong id="delete_shop_name"></strong>?</p>
                    <div id="shop_has_staff_warning" class="alert alert-warning" style="display: none;">
                        <i class="fas fa-exclamation-triangle mr-2"></i> 
                        This shop has <strong id="delete_shop_staff_count"></strong> staff members assigned to it. 
                        If you delete this shop, you'll need to reassign these staff members later.
                    </div>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash mr-1"></i> Delete Shop
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
// Edit Staff
document.querySelectorAll('.edit-staff').forEach(function(button) {
    button.addEventListener('click', function() {
        var id = this.getAttribute('data-id');
        var name = this.getAttribute('data-name');
        var email = this.getAttribute('data-email');
        var role = this.getAttribute('data-role');
        var shop = this.getAttribute('data-shop');
        
        document.getElementById('edit_staff_id').value = id;
        document.getElementById('edit_name').value = name;
        document.getElementById('edit_email').value = email;
        
        // Handle role selection
        var roleSelect = document.getElementById('edit_role');
        if (role === '1' || role === 'admin') {
            roleSelect.value = 'admin';
        } else {
            roleSelect.value = 'staff';
        }
        
        document.getElementById('edit_shop_id').value = shop;
        
        // Clear password field
        document.getElementById('edit_password').value = '';
        
        // Show modal
        $('#editStaffModal').modal('show');
    });
});
// Delete Staff
document.querySelectorAll('.delete-staff').forEach(function(button) {
    button.addEventListener('click', function() {
        var id = this.getAttribute('data-id');
        var name = this.getAttribute('data-name');
        
        document.getElementById('delete_staff_id').value = id;
        document.getElementById('delete_staff_name').textContent = name;
        
        // Show modal
        $('#deleteStaffModal').modal('show');
    });
});
// Edit Shop
document.querySelectorAll('.edit-shop').forEach(function(button) {
    button.addEventListener('click', function() {
        var id = this.getAttribute('data-id');
        var name = this.getAttribute('data-name');
        var address = this.getAttribute('data-address');
        var contact = this.getAttribute('data-contact');
        
        document.getElementById('edit_shop_id').value = id;
        document.getElementById('edit_shop_name').value = name;
        document.getElementById('edit_shop_address').value = address;
        document.getElementById('edit_shop_contact').value = contact;
        
        // Show modal
        $('#editShopModal').modal('show');
    });
});
// Delete Shop
document.querySelectorAll('.delete-shop').forEach(function(button) {
    button.addEventListener('click', function() {
        var id = this.getAttribute('data-id');
        var name = this.getAttribute('data-name');
        var staffCount = parseInt(this.getAttribute('data-staff'));
        
        document.getElementById('delete_shop_id').value = id;
        document.getElementById('delete_shop_name').textContent = name;
        
        // Show warning if shop has staff
        var staffWarning = document.getElementById('shop_has_staff_warning');
        if (staffCount > 0) {
            document.getElementById('delete_shop_staff_count').textContent = staffCount;
            staffWarning.style.display = 'block';
        } else {
            staffWarning.style.display = 'none';
        }
        
        // Show modal
        $('#deleteShopModal').modal('show');
    });
});
// Password validation
document.getElementById('addStaffForm').addEventListener('submit', function(e) {
    var password = document.getElementById('password').value;
    var confirmPassword = document.getElementById('confirm_password').value;
    
    if (password !== confirmPassword) {
        e.preventDefault();
        alert('Passwords do not match!');
    }
});
</script>
<?php include 'includes/footer.php'; ?>
