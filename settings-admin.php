<?php
session_start();
require_once('db.php');

// 1. Session Access Control (Admin Only)
if (!isset($_SESSION['userRole']) || strtolower($_SESSION['userRole']) !== 'admin') {
    header("Location: login.php");
    exit();
}

$adminId = $_SESSION['userId'] ?? 1;
$adminName = $_SESSION['loggedInUser'] ?? 'Liqa';

// 2. File-based Configuration Persistence for Site Info & General Settings
$settingsFile = 'system_settings.json';
$defaultSettings = [
    'site_name' => 'UiTM Court Booking System',
    'site_email' => 'uitmcourtbooking@gmail.com',
    'contact_number' => '03-5544 2000',
    'site_address' => "Universiti Teknologi MARA,\n40450 Shah Alam, Selangor, Malaysia.",
    'timezone' => '(GMT+08:00) Kuala Lumpur, Singapore',
    'date_format' => '12 June 2026 (DD MMM YYYY)',
    'time_format' => '12-Hour (01:00 PM)'
];

if (!file_exists($settingsFile)) {
    file_put_contents($settingsFile, json_encode($defaultSettings, JSON_PRETTY_PRINT));
}
$sysSettings = json_decode(file_get_contents($settingsFile), true);

// 3. Process Form Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Action A: Site Info Save
    if ($action === 'save_site_info') {
        $sysSettings['site_name'] = trim($_POST['site_name'] ?? '');
        $sysSettings['site_email'] = trim($_POST['site_email'] ?? '');
        $sysSettings['contact_number'] = trim($_POST['contact_number'] ?? '');
        $sysSettings['site_address'] = trim($_POST['site_address'] ?? '');
        
        file_put_contents($settingsFile, json_encode($sysSettings, JSON_PRETTY_PRINT));
        $_SESSION['success_flash'] = "Site Information successfully saved.";
        header("Location: settings-admin.php");
        exit();
    }

    // Action B: Admin Account Save
    if ($action === 'save_admin_account') {
        $newName = trim($_POST['admin_name'] ?? '');
        $newEmail = trim($_POST['admin_email'] ?? '');
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!empty($newName) && !empty($newEmail)) {
            try {
                // Update Name & Email
                $updateStmt = $pdo->prepare("UPDATE ADMIN SET name = ?, email = ? WHERE admin_id = ?");
                $updateStmt->execute([$newName, $newEmail, $adminId]);
                $_SESSION['loggedInUser'] = $newName;

                // Process optional password change
                if (!empty($newPassword)) {
                    if ($newPassword === $confirmPassword) {
                        $hashedPass = password_hash($newPassword, PASSWORD_DEFAULT);
                        $passStmt = $pdo->prepare("UPDATE ADMIN SET password = ? WHERE admin_id = ?");
                        $passStmt->execute([$hashedPass, $adminId]);
                    } else {
                        $_SESSION['error_flash'] = "Passwords do not match. Profile updated, but password was skipped.";
                        header("Location: settings-admin.php");
                        exit();
                    }
                }
                $_SESSION['success_flash'] = "Admin Account details successfully updated.";
            } catch (PDOException $e) {
                $_SESSION['error_flash'] = "Database Error: " . $e->getMessage();
            }
        }
        header("Location: settings-admin.php");
        exit();
    }

    // Action C: General Preferences Save
    if ($action === 'save_general_settings') {
        $sysSettings['timezone'] = trim($_POST['timezone'] ?? '');
        $sysSettings['date_format'] = trim($_POST['date_format'] ?? '');
        $sysSettings['time_format'] = trim($_POST['time_format'] ?? '');
        
        file_put_contents($settingsFile, json_encode($sysSettings, JSON_PRETTY_PRINT));
        $_SESSION['success_flash'] = "General configurations successfully updated.";
        header("Location: settings-admin.php");
        exit();
    }
}

// 4. Load Live Admin Data from SQL Database
try {
    $dbStmt = $pdo->prepare("SELECT name, email FROM ADMIN WHERE admin_id = ?");
    $dbStmt->execute([$adminId]);
    $adminRow = $dbStmt->fetch();
    if ($adminRow) {
        $adminName = $adminRow['name'];
        $dbAdminEmail = $adminRow['email'];
    }
} catch (PDOException $e) {
    $dbAdminEmail = 'admin@uitm.edu.my';
}

$successAlert = $_SESSION['success_flash'] ?? "";
$errorAlert = $_SESSION['error_flash'] ?? "";
unset($_SESSION['success_flash'], $_SESSION['error_flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - UiTM Court Admin</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    
    <style>
        /* ==========================================================================
           1. Base Setup & Layout Elements
           ========================================================================== */
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            color: #212529;
            overflow-x: hidden;
        }
        .tracking-wide { letter-spacing: 0.05em; }
        .tracking-tight { letter-spacing: -0.025em; }
        .layout-wrapper { width: 100%; }
        .workspace-content { min-width: 0; }

        /* ==========================================================================
           2. Core Sidebar Navigation (UiTM Corporate Purple & Gold)
           ========================================================================== */
        .sidebar-navigation {
            width: 260px;
            background-color: #2b1b54 !important; /* UiTM Corporate Purple */
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            transition: transform 0.3s ease-in-out;
            z-index: 1045;
        }
        
        .sidebar-navigation .nav-link {
            color: rgba(255, 255, 255, 0.75) !important;
            font-weight: 500;
            font-size: 0.95rem;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.2s ease-in-out;
            text-decoration: none;
        }
        
        .sidebar-navigation .nav-link i {
            font-size: 1.15rem;
            color: inherit !important;
        }

        .sidebar-navigation .nav-link:hover {
            color: #ffffff !important;
            background-color: rgba(255, 255, 255, 0.1);
        }

        .sidebar-navigation .nav-link.active {
            color: #ffd700 !important; /* UiTM Distinct Gold/Yellow */
            background-color: rgba(255, 255, 255, 0.15);
            font-weight: 600;
        }

        .sidebar-brand-showcase img {
            filter: drop-shadow(0px 2px 4px rgba(0, 0, 0, 0.25));
        }

        .text-muted-small { color: #6c757d; font-size: 0.72rem; }

        /* ==========================================================================
           3. Form & Card UI Standard Elements
           ========================================================================== */
        .settings-showcase-card {
            border: 1px solid #eef0f2;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -1px rgba(0, 0, 0, 0.02);
        }

        .settings-badge-circle {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            background-color: #f1f5f9;
            color: #4f46e5;
            font-size: 1.25rem;
        }

        .form-meta-label {
            font-size: 0.88rem;
            font-weight: 500;
            color: #475569;
        }

        .form-control-custom {
            font-size: 0.9rem;
            padding: 0.55rem 0.85rem;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            color: #1e293b;
            transition: all 0.15s ease-in-out;
        }

        .form-control-custom:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
            outline: 0;
        }

        .btn-purple-custom {
            background-color: #2b1b54 !important;
            color: #ffffff !important;
            font-weight: 500;
            font-size: 0.88rem;
            border-radius: 6px;
            padding: 0.5rem 1.25rem;
            transition: opacity 0.2s;
            border: none;
        }
        
        .btn-purple-custom:hover {
            opacity: 0.9;
            color: #ffffff !important;
        }

        /* ==========================================================================
           4. Mobile-Responsive Engine Breakpoint Setup
           ========================================================================== */
        @media (max-width: 991.98px) {
            .sidebar-navigation { width: 280px; }
            .sidebar-navigation:not(.show) { visibility: hidden; }
        }
        
        @media (max-width: 575.98px) {
            .workspace-content { padding-left: 1rem !important; padding-right: 1rem !important; }
        }
    </style>
</head>
<body>

    <div class="d-flex min-vh-100 layout-wrapper">
        
        <aside class="offcanvas-lg offcanvas-start sidebar-navigation text-white p-3 d-flex flex-column justify-content-between flex-shrink-0" 
               tabindex="-1" id="sidebarMenu" aria-labelledby="sidebarMenuLabel">
            
            <div>
                <div class="sidebar-brand-showcase d-flex align-items-center justify-content-between mb-4">
                    <div class="d-flex align-items-center gap-2">
                        <img src="images/uitm-logo.png" alt="UiTM Logo" height="40" class="me-2">
                        <span class="fw-bold tracking-wide fs-5">UiTM Court Admin</span>
                    </div>
                    <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#sidebarMenu" aria-label="Close"></button>
                </div>
                
                <nav class="nav flex-column">
                    <a class="nav-link" href="admin-dashboard.php">
                        <i class="bi bi-house-door"></i> <span>Dashboard</span>
                    </a>
                    <a class="nav-link" href="admin-booking.php">
                        <i class="bi bi-calendar-check"></i> <span>Bookings</span>
                    </a>
                    <a class="nav-link" href="court-management.php">
                        <i class="bi bi-building-gear"></i> <span>Courts Management</span>
                    </a>
                    <a class="nav-link" href="reports.php">
                        <i class="bi bi-graph-up-arrow"></i> <span>Reports</span>
                    </a>
                    <a class="nav-link active" href="settings-admin.php">
                        <i class="bi bi-gear"></i> <span>Settings</span>
                    </a>
                </nav>
            </div>
            
            <div class="nav flex-column pt-3 border-top border-secondary">
                <a class="nav-link" href="logout.php" id="logoutBtn">
                    <i class="bi bi-box-arrow-left"></i> <span>Logout</span>
                </a>
            </div>
        </aside>

        <main class="flex-grow-1 workspace-content p-3 p-sm-4 p-lg-5 bg-light overflow-y-auto">
            
            <header class="d-flex justify-content-between align-items-center gap-3 mb-4">
                <div class="d-flex align-items-center gap-3">
                    <button class="btn btn-outline-dark d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu">
                        <i class="bi bi-list fs-4"></i>
                    </button>
                    
                    <div class="text-start">
                        <h2 class="fw-bold tracking-tight text-dark mb-1 fs-3 fs-sm-2">Settings</h2>
                        <p class="text-muted small mb-0 d-none d-sm-block">Manage system settings and configurations.</p>
                    </div>
                </div>
                
                <div class="d-flex align-items-center gap-2 gap-sm-3 bg-white px-3 py-2 rounded-3 border shadow-sm">
                    <div class="bg-secondary text-white rounded-circle p-1 d-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">
                        <i class="bi bi-person-fill"></i>
                    </div>
                    <div class="text-start d-none d-md-block">
                        <div class="fw-semibold text-dark small lh-1 mb-1"><?php echo htmlspecialchars($adminName); ?></div>
                        <span class="text-muted-small text-uppercase tracking-wider font-monospace" style="font-size: 0.65rem;">Admin</span>
                    </div>
                </div>
            </header>

            <?php if (!empty($successAlert)): ?>
                <div class="alert alert-success border-0 shadow-sm rounded-3 small text-start mb-4 py-2" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i><?php echo htmlspecialchars($successAlert); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errorAlert)): ?>
                <div class="alert alert-danger border-0 shadow-sm rounded-3 small text-start mb-4 py-2" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($errorAlert); ?>
                </div>
            <?php endif; ?>

            <div class="d-flex flex-column gap-4 text-start">

                <div class="card settings-showcase-card p-4 border-0 rounded-4 shadow-sm bg-white">
                    <div class="row g-4">
                        <div class="col-lg-4 d-flex align-items-start gap-3">
                            <div class="settings-badge-circle flex-shrink-0 d-flex align-items-center justify-content-center">
                                <i class="bi bi-building"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold text-dark mb-1 card-tier-title">Site Information</h5>
                                <p class="text-muted small mb-0">Update your application details and contact information.</p>
                            </div>
                        </div>
                        <div class="col-lg-8">
                            <form method="POST" action="settings-admin.php">
                                <input type="hidden" name="action" value="save_site_info">
                                
                                <div class="row mb-3 align-items-center">
                                    <label class="col-sm-3 form-meta-label mb-1 mb-sm-0">Site Name</label>
                                    <div class="col-sm-9">
                                        <input type="text" class="form-control form-control-custom" name="site_name" value="<?php echo htmlspecialchars($sysSettings['site_name']); ?>" required>
                                    </div>
                                </div>
                                <div class="row mb-3 align-items-center">
                                    <label class="col-sm-3 form-meta-label mb-1 mb-sm-0">Site Email</label>
                                    <div class="col-sm-9">
                                        <input type="email" class="form-control form-control-custom" name="site_email" value="<?php echo htmlspecialchars($sysSettings['site_email']); ?>" required>
                                    </div>
                                </div>
                                <div class="row mb-3 align-items-center">
                                    <label class="col-sm-3 form-meta-label mb-1 mb-sm-0">Contact Number</label>
                                    <div class="col-sm-9">
                                        <input type="text" class="form-control form-control-custom" name="contact_number" value="<?php echo htmlspecialchars($sysSettings['contact_number']); ?>" required>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label class="col-sm-3 form-meta-label pt-sm-2 mb-1 mb-sm-0">Site Address</label>
                                    <div class="col-sm-9">
                                        <textarea class="form-control form-control-custom" name="site_address" rows="3" required><?php echo htmlspecialchars($sysSettings['site_address']); ?></textarea>
                                    </div>
                                </div>
                                <div class="text-end mt-4">
                                    <button type="submit" class="btn btn-purple-custom shadow-sm px-4">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="card settings-showcase-card p-4 border-0 rounded-4 shadow-sm bg-white">
                    <div class="row g-4">
                        <div class="col-lg-4 d-flex align-items-start gap-3">
                            <div class="settings-badge-circle flex-shrink-0 d-flex align-items-center justify-content-center">
                                <i class="bi bi-person"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold text-dark mb-1 card-tier-title">Admin Account</h5>
                                <p class="text-muted small mb-0">Manage authorization logs, names, and passwords.</p>
                            </div>
                        </div>
                        <div class="col-lg-8">
                            <form method="POST" action="settings-admin.php">
                                <input type="hidden" name="action" value="save_admin_account">
                                
                                <div class="row mb-3 align-items-center">
                                    <label class="col-sm-3 form-meta-label mb-1 mb-sm-0">Admin Name</label>
                                    <div class="col-sm-9">
                                        <input type="text" class="form-control form-control-custom" name="admin_name" value="<?php echo htmlspecialchars($adminName); ?>" required>
                                    </div>
                                </div>
                                <div class="row mb-3 align-items-center">
                                    <label class="col-sm-3 form-meta-label mb-1 mb-sm-0">Email Address</label>
                                    <div class="col-sm-9">
                                        <input type="email" class="form-control form-control-custom" name="admin_email" value="<?php echo htmlspecialchars($dbAdminEmail); ?>" required>
                                    </div>
                                </div>
                                <div class="row mb-3 align-items-center">
                                    <label class="col-sm-3 form-meta-label mb-1 mb-sm-0">New Password</label>
                                    <div class="col-sm-9">
                                        <input type="password" class="form-control form-control-custom" name="new_password" placeholder="Leave blank to skip">
                                    </div>
                                </div>
                                <div class="row mb-3 align-items-center">
                                    <label class="col-sm-3 form-meta-label mb-1 mb-sm-0">Confirm Password</label>
                                    <div class="col-sm-9">
                                        <input type="password" class="form-control form-control-custom" name="confirm_password" placeholder="Confirm your new password">
                                    </div>
                                </div>
                                <div class="text-end mt-4">
                                    <button type="submit" class="btn btn-purple-custom shadow-sm px-4">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="card settings-showcase-card p-4 border-0 rounded-4 shadow-sm bg-white mb-4">
                    <div class="row g-4">
                        <div class="col-lg-4 d-flex align-items-start gap-3">
                            <div class="settings-badge-circle flex-shrink-0 d-flex align-items-center justify-content-center">
                                <i class="bi bi-sliders"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold text-dark mb-1 card-tier-title">General Settings</h5>
                                <p class="text-muted small mb-0">Configure basic time, zone, and metadata visualization formats.</p>
                            </div>
                        </div>
                        <div class="col-lg-8">
                            <form method="POST" action="settings-admin.php">
                                <input type="hidden" name="action" value="save_general_settings">
                                
                                <div class="row mb-3 align-items-center">
                                    <label class="col-sm-3 form-meta-label mb-1 mb-sm-0">Timezone</label>
                                    <div class="col-sm-9">
                                        <select class="form-select form-control-custom" name="timezone">
                                            <option value="(GMT+08:00) Kuala Lumpur, Singapore" <?php echo ($sysSettings['timezone'] === '(GMT+08:00) Kuala Lumpur, Singapore') ? 'selected' : ''; ?>>(GMT+08:00) Kuala Lumpur, Singapore</option>
                                            <option value="(GMT+00:00) UTC / London" <?php echo ($sysSettings['timezone'] === '(GMT+00:00) UTC / London') ? 'selected' : ''; ?>>(GMT+00:00) UTC / London</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row mb-3 align-items-center">
                                    <label class="col-sm-3 form-meta-label mb-1 mb-sm-0">Date Format</label>
                                    <div class="col-sm-9">
                                        <select class="form-select form-control-custom" name="date_format">
                                            <option value="12 June 2026 (DD MMM YYYY)" <?php echo ($sysSettings['date_format'] === '12 June 2026 (DD MMM YYYY)') ? 'selected' : ''; ?>>12 June 2026 (DD MMM YYYY)</option>
                                            <option value="2026-06-12 (YYYY-MM-DD)" <?php echo ($sysSettings['date_format'] === '2026-06-12 (YYYY-MM-DD)') ? 'selected' : ''; ?>>2026-06-12 (YYYY-MM-DD)</option>
                                            <option value="12/06/2026 (DD/MM/YYYY)" <?php echo ($sysSettings['date_format'] === '12/06/2026 (DD/MM/YYYY)') ? 'selected' : ''; ?>>12/06/2026 (DD/MM/YYYY)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row mb-3 align-items-center">
                                    <label class="col-sm-3 form-meta-label mb-1 mb-sm-0">Time Format</label>
                                    <div class="col-sm-9">
                                        <select class="form-select form-control-custom" name="time_format">
                                            <option value="12-Hour (01:00 PM)" <?php echo ($sysSettings['time_format'] === '12-Hour (01:00 PM)') ? 'selected' : ''; ?>>12-Hour (01:00 PM)</option>
                                            <option value="24-Hour (13:00)" <?php echo ($sysSettings['time_format'] === '24-Hour (13:00)') ? 'selected' : ''; ?>>24-Hour (13:00)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="text-end mt-4">
                                    <div class="btn-group shadow-sm">
                                        <button type="submit" class="btn btn-purple-custom px-4">Save Changes</button>
                                        <button type="button" class="btn btn-purple-custom dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                                            <span class="visually-hidden">Toggle Dropdown</span>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end border-0 shadow-sm small">
                                            <li><a class="dropdown-item" href="#">Export Settings</a></li>
                                            <li><a class="dropdown-item text-danger" href="#">Reset Preferences</a></li>
                                        </ul>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('logoutBtn').addEventListener('click', function() {
            localStorage.removeItem('userRole');
            localStorage.removeItem('loggedInUser');
        });
    </script>
</body>
</html>