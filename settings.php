<?php
session_start();
require_once('db.php'); 

if (!isset($_SESSION['userRole'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['userId'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0;
$userName = $_SESSION['loggedInUser'] ?? $_SESSION['username'] ?? $_SESSION['name'] ?? 'Student';
$successAlert = "";
$errorAlert = "";

// Handle Form Post Requests (Profile Modifications)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Action Variant A: Update General Profile Details (Full Name & Student ID)
    if (isset($_POST['action']) && $_POST['action'] === 'update_profile') {
        $updatedName = trim($_POST['profile_name'] ?? '');
        $updatedStudentId = trim($_POST['student_id'] ?? '');
        
        if (!empty($updatedName) && !empty($updatedStudentId)) {
            try {
                // Securely execute updates targeting structural column declarations
                $sql = "UPDATE USER SET full_name = ?, student_id = ? WHERE user_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$updatedName, $updatedStudentId, $userId]);
                
                $_SESSION['loggedInUser'] = $updatedName;
                $userName = $updatedName;
                $successAlert = "Success! Your profile modifications have been successfully updated.";
            } catch (PDOException $e) {
                $errorAlert = "Database Error: Could not save profile fields. " . $e->getMessage();
            }
        } else {
            $errorAlert = "Profile fields cannot be left empty.";
        }
    }
    
    // Action Variant B: Update Security Password Credentials
    if (isset($_POST['action']) && $_POST['action'] === 'update_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (!empty($currentPassword) && !empty($newPassword) && !empty($confirmPassword)) {
            if ($newPassword !== $confirmPassword) {
                $errorAlert = "Validation Error: New passwords do not match.";
            } else {
                try {
                    $stmtFetch = $pdo->prepare("SELECT password FROM USER WHERE user_id = ?");
                    $stmtFetch->execute([$userId]);
                    $userRecord = $stmtFetch->fetch();
                    
                    if ($userRecord && password_verify($currentPassword, $userRecord['password'])) {
                        $newSecureHash = password_hash($newPassword, PASSWORD_BCRYPT);
                        
                        $stmtUpdate = $pdo->prepare("UPDATE USER SET password = ? WHERE user_id = ?");
                        $stmtUpdate->execute([$newSecureHash, $userId]);
                        $successAlert = "Security Credentials successfully written. Please keep your updated password safe.";
                    } else {
                        $errorAlert = "Verification Failure: The current password you entered is incorrect.";
                    }
                } catch (PDOException $e) {
                    $errorAlert = "Database Error: Could not overwrite credential logs.";
                }
            }
        } else {
            $errorAlert = "All password fields are required to perform security changes.";
        }
    }
}

// Fetch current live Student data elements
$studentIdField = "";
$emailField = "";

try {
    $stmtUser = $pdo->prepare("SELECT student_id, email FROM USER WHERE user_id = ?");
    $stmtUser->execute([$userId]);
    $currentUserData = $stmtUser->fetch(PDO::FETCH_ASSOC);
    if ($currentUserData) {
        $studentIdField = $currentUserData['student_id'] ?? '';
        $emailField = $currentUserData['email'] ?? '';
    }
} catch (PDOException $e) {
    // Graceful baseline safe fallback
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Court - UiTM Court Booking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="book-court.css">
    <style>
        .court-card { position: relative; overflow: visible; }
        .status-pill-badge { position: absolute; top: -10px; start: 50%; transform: translateX(-50%); font-size: 0.7rem; padding: 2px 8px; border-radius: 20px; font-weight: 600; z-index: 5; }
    </style>
</head>
<body>

    <div class="d-flex min-vh-100 layout-wrapper">
        
        <!-- Updated Responsive Sidebar Layout System Integration -->
        <aside class="sidebar-navigation text-white p-3 d-flex flex-column justify-content-between flex-shrink-0">
            <div>
                <!-- Brand Title Showcase with Responsive Dropdown Menu Trigger Block -->
                <div class="sidebar-brand-showcase d-flex align-items-center justify-content-between w-100 px-2 py-3 mb-0 mb-md-4">
                    <div class="d-flex align-items-center gap-2">
                        <img src="images/uitm-logo.png" alt="UiTM Logo" height="40" class="me-2">
                        <span class="fw-bold tracking-wide fs-5">UiTM Court Booking</span>
                    </div>
                    <!-- Framework Structural Menu Activation Handle for Mobile Views -->
                    <button class="btn btn-link text-white d-md-none p-0 border-0" type="button" onclick="document.getElementById('mobileNavTray').classList.toggle('show')">
                        <i class="bi bi-list fs-3"></i>
                    </button>
                </div>
                
                <!-- Shared Collapsible Responsive Desktop/Mobile Structural Navigation Tray Component -->
                <div class="mobile-collapsed-nav" id="mobileNavTray">
                    <nav class="nav flex-column gap-2 mb-auto pt-2 pt-md-0 w-100">
                        <a class="nav-link text-white-50 d-flex align-items-center gap-3 px-3 py-2.5 rounded-3" href="dashboard.php">
                            <i class="bi bi-house-door"></i> <span>Dashboard</span>
                        </a>
                        <a class="nav-link text-white-50 d-flex align-items-center gap-3 px-3 py-2.5 rounded-3" href="book-court.php">
                            <i class="bi bi-calendar3"></i> <span>Book Court</span>
                        </a>
                        <a class="nav-link text-white-50 d-flex align-items-center gap-3 px-3 py-2.5 rounded-3" href="my-booking.php">
                            <i class="bi bi-list-task"></i> <span>My Booking</span>
                        </a>
                        <a class="nav-link text-white-50 d-flex align-items-center gap-3 px-3 py-2.5 rounded-3" href="history.php">
                            <i class="bi bi-clock-history"></i> <span>History</span>
                        </a>
                        <a class="nav-link text-white-50 d-flex align-items-center gap-3 px-3 py-2.5 rounded-3" href="notifications.php">
                            <i class="bi bi-bell"></i> <span>Notifications</span>
                        </a>
                    </nav>
                    <nav class="nav flex-column gap-2 mt-auto pt-3 border-top border-secondary-subtle w-100">
                       <a class="nav-link active d-flex align-items-center gap-3 px-3 py-2.5 rounded-3" href="settings.php">
                            <i class="bi bi-gear"></i> <span>Settings</span>
                        </a>
                        <a class="nav-link text-white-50 d-flex align-items-center gap-3 px-3 py-2.5 rounded-3" href="logout.php" id="logoutBtn">
                            <i class="bi bi-box-arrow-left"></i> <span>Logout</span>
                        </a>
                    </nav>
                </div>
            </div>
        </aside>

        <main class="flex-grow-1 workspace-content p-4 p-lg-5 bg-light overflow-y-auto">
            
            <header class="d-flex justify-content-between align-items-start mb-4">
                <div>
                    <h4 class="fw-bold tracking-tight text-dark mb-1">Account Settings</h4>
                    <span class="text-muted small">Maintain your personal student identities and profile visibility layers.</span>
                </div>
                
                <div class="d-flex align-items-center gap-3 bg-white px-3 py-2 rounded-3 border cursor-pointer shadow-sm">
                    <div class="bg-light rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="bi bi-person text-secondary"></i>
                    </div>
                    <div class="text-start d-none d-sm-block">
                        <div class="fw-semibold text-dark small lh-1 mb-1" id="headerProfileName">
                            <?php echo htmlspecialchars($userName); ?> <i class="bi bi-chevron-down ms-1 extra-small-text text-muted"></i>
                        </div>
                        <span class="text-muted-small text-uppercase tracking-wider font-monospace">Student</span>
                    </div>
                </div>
            </header>

            <?php if (!empty($successAlert)): ?>
                <div class="alert alert-success alert-dismissible fade show small mb-4" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i><?php echo htmlspecialchars($successAlert); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($errorAlert)): ?>
                <div class="alert alert-danger alert-dismissible fade show small mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($errorAlert); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                        <h5 class="fw-bold text-dark mb-1"><i class="bi bi-person-lines-fill me-2 text-purple"></i>Profile Details</h5>
                        <p class="text-muted small mb-4">Update your general identity descriptors utilized inside the reservation lists.</p>
                        
                        <form action="settings.php" method="POST" id="profileDetailsNativeForm" class="text-start">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-secondary mb-1">Full Name</label>
                                <input type="text" class="form-control rounded-3 p-2 small border-light-subtle shadow-sm" name="profile_name" value="<?php echo htmlspecialchars($userName); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-secondary mb-1">Student ID Matrix</label>
                                <input type="text" class="form-control rounded-3 p-2 small border-light-subtle shadow-sm" name="student_id" value="<?php echo htmlspecialchars($studentIdField); ?>" required>
                                <span class="text-extra-small text-muted mt-1 d-block"><i class="bi bi-info-circle me-1"></i>You can modify your Student ID if there is a mistake in your records.</span>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label small fw-semibold text-secondary mb-1">Email Address</label>
                                <input type="email" class="form-control rounded-3 p-2 small bg-light text-muted border-light-subtle shadow-sm" value="<?php echo htmlspecialchars($emailField); ?>" readonly disabled>
                                <span class="text-extra-small text-muted mt-1 d-block"><i class="bi bi-info-circle me-1"></i>Email addresses are bound to your account sign-in logs and cannot be changed.</span>
                            </div>

                            <button type="submit" class="btn btn-sm py-2 px-4 rounded-3 text-white shadow-sm text-uppercase" style="background-color: #6f42c1; border-color: #6f42c1; font-weight: 500; font-size: 0.8rem;">
                                <i class="bi bi-box-arrow-in-down me-2"></i>SAVE CHANGES
                            </button>
                            
                        </form>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                        <h5 class="fw-bold text-dark mb-1"><i class="bi bi-shield-lock-fill me-2 text-success"></i>Security Credentials</h5>
                        <p class="text-muted small mb-4">Modify your active portal password configurations regularly to prevent intrusion risks.</p>
                        
                        <form action="settings.php" method="POST" id="securityCredentialForm" class="text-start">
                            <input type="hidden" name="action" value="update_password">
                            
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-secondary mb-1">Current Password</label>
                                <input type="password" class="form-control rounded-3 p-2 small border-light-subtle shadow-sm" name="current_password" placeholder="••••••••" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label small fw-semibold text-secondary mb-1">New Password</label>
                                <input type="password" class="form-control rounded-3 p-2 small border-light-subtle shadow-sm" name="new_password" placeholder="Minimum 6 characters" required>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label small fw-semibold text-secondary mb-1">Confirm New Password</label>
                                <input type="password" class="form-control rounded-3 p-2 small border-light-subtle shadow-sm" name="confirm_password" placeholder="Repeat new configuration" required>
                            </div>

                             <button type="submit" class="btn btn-sm py-2 px-4 rounded-3 text-white shadow-sm text-uppercase" style="background-color: #6f42c1; border-color: #6f42c1; font-weight: 500; font-size: 0.8rem;">
                                <i class="bi bi-key me-2"></i>UPDATE CREDENTIALS
                             </button>
                            
                        </form>
                    </div>
                </div>

            </div>
            
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
