<?php
session_start();
require_once('db.php'); // Establish live database connection map instance

// 1. Session & Access Control Verification
if (!isset($_SESSION['userRole'])) {
    header("Location: login.php");
    exit();
} elseif ($_SESSION['userRole'] === 'admin') {
    header("Location: admin-dashboard.php");
    exit();
}

$userId = $_SESSION['userId'] ?? 0;
$userName = $_SESSION['loggedInUser'] ?? 'Student';

// Helper function to turn timestamps into relative humanized text
function getRelativeTimeSpan($timestampString) {
    if (empty($timestampString)) return "Recently";
    
    try {
        $currentTime = new DateTime();
        $targetTime = new DateTime($timestampString);
        $timeDifference = $currentTime->diff($targetTime);
        
        if ($timeDifference->y > 0) return $timeDifference->y . " year" . ($timeDifference->y > 1 ? "s" : "") . " ago";
        if ($timeDifference->m > 0) return $timeDifference->m . " month" . ($timeDifference->m > 1 ? "s" : "") . " ago";
        if ($timeDifference->d > 0) return $timeDifference->d . " day" . ($timeDifference->d > 1 ? "s" : "") . " ago";
        if ($timeDifference->h > 0) return $timeDifference->h . " hour" . ($timeDifference->h > 1 ? "s" : "") . " ago";
        if ($timeDifference->i > 0) return $timeDifference->i . " minute" . ($timeDifference->i > 1 ? "s" : "") . " ago";
        
        return "Just now";
    } catch (Exception $e) {
        return "Recently";
    }
}

// 2. Fetch Live Dashboard Metrics dynamically from MySQL tables matching user_id
try {
    // Total Bookings count
    $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM BOOKING WHERE user_id = ?");
    $stmtTotal->execute([$userId]);
    $totalBookings = $stmtTotal->fetchColumn();

    // Approved Bookings count
    $stmtApproved = $pdo->prepare("SELECT COUNT(*) FROM BOOKING WHERE user_id = ? AND LOWER(status) IN ('approved', 'confirmed', 'complete', 'completed')");
    $stmtApproved->execute([$userId]);
    $approvedBookings = $stmtApproved->fetchColumn();

    // Pending Bookings count
    $stmtPending = $pdo->prepare("SELECT COUNT(*) FROM BOOKING WHERE user_id = ? AND LOWER(status) = 'pending'");
    $stmtPending->execute([$userId]);
    $pendingBookings = $stmtPending->fetchColumn();

    // Cancelled Bookings count
    $stmtCancelled = $pdo->prepare("SELECT COUNT(*) FROM BOOKING WHERE user_id = ? AND LOWER(status) = 'cancelled'");
    $stmtCancelled->execute([$userId]);
    $cancelledBookings = $stmtCancelled->fetchColumn();

} catch (PDOException $e) {
    $totalBookings = $approvedBookings = $pendingBookings = $cancelledBookings = 0;
}

// 3. Fetch System Announcements + Dynamically Generate Active & Maintenance Court Alerts
$announcementsList = [];
try {
    $courtStmt = $pdo->query("SELECT court_id, court_name, court_type, location FROM COURT ORDER BY court_id DESC");
    while ($courtRow = $courtStmt->fetch(PDO::FETCH_ASSOC)) {
        $rawLocation = $courtRow['location'] ?? 'UiTM Kampus Kuala Terengganu';
        $parsedStatus = "Active";
        $baseLoc = "UiTM Kampus Kuala Terengganu";

        if (strpos($rawLocation, '|') !== false) {
            $parts = explode('|', $rawLocation);
            $baseLoc = trim($parts[0] ?? 'UiTM Kampus Kuala Terengganu');
            $statPart = trim($parts[2] ?? 'Status: Active');
            $parsedStatus = trim(str_replace('Status:', '', $statPart));
        }

        if (strtolower($parsedStatus) === 'active') {
            $announcementsList[] = [
                'title' => '🟢 Open for Play: ' . htmlspecialchars($courtRow['court_name']),
                'content' => 'The ' . htmlspecialchars($courtRow['court_name']) . ' (' . htmlspecialchars($courtRow['court_type']) . ') at ' . htmlspecialchars($baseLoc) . ' is verified operational and active. Slot reservations are live!',
                'created_at' => date('Y-m-d H:i:s'),
                'is_auto_alert' => true,
                'alert_variant' => 'success'
            ];
        } elseif (strtolower($parsedStatus) === 'maintenance') {
            $announcementsList[] = [
                'title' => '🚧 Facility Lockdown: ' . htmlspecialchars($courtRow['court_name']),
                'content' => 'Notice: The ' . htmlspecialchars($courtRow['court_name']) . ' (' . htmlspecialchars($courtRow['court_type']) . ') has been placed under system maintenance. Reservations are locked until adjustments complete.',
                'created_at' => date('Y-m-d H:i:s'),
                'is_auto_alert' => true,
                'alert_variant' => 'warning'
            ];
        }
    }

    $announcementStmt = $pdo->query("SELECT title, content, created_at FROM ANNOUNCEMENT ORDER BY created_at DESC LIMIT 3");
    while ($row = $announcementStmt->fetch(PDO::FETCH_ASSOC)) {
        $row['is_auto_alert'] = false;
        $row['alert_variant'] = 'secondary';
        $announcementsList[] = $row;
    }

} catch (PDOException $e) {
    if (empty($announcementsList)) {
        $announcementsList = [
            [
                'title' => 'Weekend Maintenance Closure',
                'content' => 'Please be informed that the Futsal Arena will be completely closed for lighting upgrades starting next Saturday morning at 8:00 AM until Sunday afternoon.',
                'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                'is_auto_alert' => false,
                'alert_variant' => 'secondary'
            ]
        ];
    }
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
                        <a class="nav-link active d-flex align-items-center gap-3 px-3 py-2.5 rounded-3" href="dashboard.php">
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
                        <a class="nav-link text-white-50 d-flex align-items-center gap-3 px-3 py-2.5 rounded-3" href="settings.php">
                            <i class="bi bi-gear"></i> <span>Settings</span>
                        </a>
                        <a class="nav-link text-white-50 d-flex align-items-center gap-3 px-3 py-2.5 rounded-3" href="logout.php" id="logoutBtn">
                            <i class="bi bi-box-arrow-left"></i> <span>Logout</span>
                        </a>
                    </nav>
                </div>
            </div>
        </aside>

        <main class="flex-grow-1 workspace-content p-3 p-sm-4 p-lg-5 bg-light">
            
            <header class="d-flex flex-column flex-sm-row justify-content-between align-items-start gap-3 mb-4">
                <div>
                    <h4 class="fw-bold tracking-tight text-dark mb-1">Dashboard</h4>
                    <span class="text-muted small">Overview metrics tracking active account behaviors.</span>
                </div>
                
                <div class="d-flex align-items-center gap-3 bg-white px-3 py-2 rounded-3 border shadow-sm align-self-stretch align-self-sm-auto cursor-pointer">
                    <div class="bg-light rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="bi bi-person text-secondary"></i>
                    </div>
                    <div class="text-start">
                        <div class="fw-semibold text-dark small lh-1 mb-1" id="headerProfileName">
                            <?php echo htmlspecialchars($userName); ?> <i class="bi bi-chevron-down ms-1 extra-small-text text-muted"></i>
                        </div>
                        <span class="text-muted-small text-uppercase tracking-wider font-monospace">Student</span>
                    </div>
                </div>
            </header>

            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-4 gap-3">
                <p class="text-muted small mb-0">Welcome back, <span class="fw-medium text-dark text-capitalize" id="welcomeUserText"><?php echo htmlspecialchars($userName); ?></span>! Here's what's happening today.</p>
                <div class="bg-white border rounded-3 px-3 py-1.5 small text-secondary d-flex align-items-center gap-2 shadow-sm">
                    <i class="bi bi-calendar-event"></i> <span id="currentDateDisplayHub"><?php echo date('d F Y (l)'); ?></span>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
                        <div class="d-flex align-items-center gap-3">
                            <div class="metric-icon-box bg-purple-light text-purple rounded-3"><i class="bi bi-calendar3"></i></div>
                            <div>
                                <div class="text-muted small fw-medium mb-0">Total Bookings</div>
                                <h3 class="fw-bold text-dark mb-0" id="metricTotal"><?php echo $totalBookings; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
                        <div class="d-flex align-items-center gap-3">
                            <div class="metric-icon-box bg-success-light text-success rounded-3"><i class="bi bi-calendar-check"></i></div>
                            <div>
                                <div class="text-muted small fw-medium mb-0">Approved</div>
                                <h3 class="fw-bold text-dark mb-0" id="metricApproved"><?php echo $approvedBookings; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
                        <div class="d-flex align-items-center gap-3">
                            <div class="metric-icon-box bg-warning-light text-warning rounded-3"><i class="bi bi-hourglass-split"></i></div>
                            <div>
                                <div class="text-muted small fw-medium mb-0">Pending</div>
                                <h3 class="fw-bold text-dark mb-0" id="metricPending"><?php echo $pendingBookings; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
                        <div class="d-flex align-items-center gap-3">
                            <div class="metric-icon-box bg-danger-light text-danger rounded-3"><i class="bi bi-calendar-x"></i></div>
                            <div>
                                <div class="text-muted small fw-medium mb-0">Cancelled</div>
                                <h3 class="fw-bold text-dark mb-0" id="metricCancelled"><?php echo $cancelledBookings; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-12 col-lg-6">
                    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                        <div class="d-flex justify-content-between align-items-start mb-3 border-bottom pb-2">
                            <div>
                                <h5 class="fw-bold text-dark mb-1"><i class="bi bi-megaphone me-2 text-purple"></i>System Announcement</h5>
                                <span class="text-muted small">Important operational alerts from Admin management</span>
                            </div>
                            <span class="badge bg-warning text-dark px-2 py-1 small rounded d-flex align-items-center">Live</span>
                        </div>
                        
                        <div class="announcement-body mt-2 pe-1" style="max-height: 440px; overflow-y: auto;">
                            <?php if (empty($announcementsList)): ?>
                                <div class="text-center py-4 text-muted small">
                                    <i class="bi bi-chat-left-dots d-block fs-3 mb-2 opacity-50"></i>
                                    No announcements posted currently.
                                </div>
                            <?php else: ?>
                                <?php foreach ($announcementsList as $announcement): ?>
                                    <?php 
                                        if (isset($announcement['is_auto_alert']) && $announcement['is_auto_alert']) {
                                            $borderTypeClass = ($announcement['alert_variant'] === 'success') 
                                                ? "border-success bg-success-subtle bg-opacity-25" 
                                                : "border-warning bg-warning-subtle bg-opacity-25";
                                        } else {
                                            $borderTypeClass = "border-secondary bg-light";
                                        }
                                    ?>
                                    <div class="p-3 rounded border-start border-4 <?php echo $borderTypeClass; ?> mb-3 shadow-sm">
                                        <div class="fw-bold text-dark small mb-1"><?php echo htmlspecialchars($announcement['title']); ?></div>
                                        <p class="text-secondary small mb-2 text-xs leading-normal">
                                            <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                                        </p>
                                        <span class="text-muted text-extra-small d-block text-end opacity-75">
                                            <i class="bi bi-clock me-1"></i><?php echo getRelativeTimeSpan($announcement['created_at']); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-6">
                    <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100 d-flex flex-column justify-content-between">
                        <div>
                            <h5 class="fw-bold text-dark mb-1"><i class="bi bi-chat-heart me-2 text-success"></i>Rate Our System</h5>
                            <p class="text-muted small mb-4">Your opinion matters! Help us build a better experience for the community.</p>
                            
                            <div class="rating-interactive-zone text-center my-4">
                                <div class="d-flex justify-content-center gap-3 text-warning fs-1 mb-2" id="starContainer">
                                    <i class="bi bi-star cursor-pointer" data-rating="1"></i>
                                    <i class="bi bi-star cursor-pointer" data-rating="2"></i>
                                    <i class="bi bi-star cursor-pointer" data-rating="3"></i>
                                    <i class="bi bi-star cursor-pointer" data-rating="4"></i>
                                    <i class="bi bi-star cursor-pointer" data-rating="5"></i>
                                </div>
                                <div class="small fw-semibold mt-1 text-secondary" id="ratingStatus">Click a star to submit your rating</div>
                            </div>
                        </div>

                        <div class="border-top pt-3 mt-3">
                            <div class="d-flex justify-content-between text-muted small mb-2">
                                <span>Current Rating Progress</span>
                                <span class="fw-bold text-purple" id="ratingValue">0 / 5</span>
                            </div>
                            <div class="progress rounded-pill bg-light" style="height: 8px;">
                                <div class="progress-bar bg-purple rounded-pill transition-all" role="progressbar" id="ratingProgress" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile navigation layout frame utility display handler switch toggles
            const toggleBtn = document.getElementById('mobileMenuToggle');
            const navTray = document.getElementById('navContainerTray');
            
            if(toggleBtn && navTray) {
                toggleBtn.addEventListener('click', function() {
                    navTray.classList.toggle('show');
                });
            }

            // Star Rating engine components execution script
            const stars = document.querySelectorAll('#starContainer .bi');
            const ratingStatus = document.getElementById('ratingStatus');
            const ratingValue = document.getElementById('ratingValue');
            const ratingProgress = document.getElementById('ratingProgress');

            stars.forEach(star => {
                star.addEventListener('click', function() {
                    const chosenRating = parseInt(this.getAttribute('data-rating'));
                    
                    stars.forEach((s, idx) => {
                        if (idx < chosenRating) {
                            s.classList.replace('bi-star', 'bi-star-fill');
                        } else {
                            s.classList.replace('bi-star-fill', 'bi-star');
                        }
                    });

                    ratingValue.innerText = `${chosenRating} / 5`;
                    ratingProgress.style.width = `${(chosenRating / 5) * 100}%`;
                    ratingProgress.setAttribute('aria-valuenow', (chosenRating / 5) * 100);
                    ratingStatus.innerText = "Thank you for your rating!";
                });
            });
        });
    </script>
</body>
</html>