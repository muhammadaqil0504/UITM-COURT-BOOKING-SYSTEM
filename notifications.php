<?php
session_start();
require_once('db.php'); // Pull live connection mapping instance

// 1. Session & Access Control Verification
if (!isset($_SESSION['userRole'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['userId'] ?? 0;
$userName = $_SESSION['loggedInUser'] ?? 'Student';
$successAlert = "";
$errorAlert = "";

// 2. Handle "Mark All As Read" Action Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_all_read') {
    $_SESSION['read_notifications_registry'] = true;
    $successAlert = "All alerts marked as read successfully.";
}

// 3. Fetch all active rows dynamically to map directly into the responsive layout template
$notificationsList = [];
try {
    $fetchStmt = $pdo->prepare("
        SELECT 
            b.booking_id AS id, 
            c.court_name AS court, 
            b.booking_date AS date, 
            b.time_slot AS timeSlot, 
            b.status AS status 
        FROM BOOKING b
        JOIN COURT c ON b.court_id = c.court_id
        WHERE b.user_id = ?
        ORDER BY b.booking_date DESC, b.time_slot DESC
    ");
    $fetchStmt->execute([$userId]);
    $dbBookings = $fetchStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($dbBookings as $row) {
        $bookingId = $row['id'];
        $isRead = isset($_SESSION['read_notifications_registry']) ? true : false;
        
        if (strtolower($row['status']) === 'approved') {
            $notificationsList[] = [
                'id' => $bookingId,
                'type' => 'approved',
                'title' => 'Booking Approved Successfully',
                'message' => 'Your reservation request for ' . htmlspecialchars($row['court']) . ' Court on ' . htmlspecialchars($row['date']) . ' (' . htmlspecialchars($row['timeSlot']) . ') has been officially confirmed.',
                'timeLabel' => 'System Update',
                'isRead' => $isRead
            ];
        } elseif (strtolower($row['status']) === 'cancelled') {
            $notificationsList[] = [
                'id' => $bookingId,
                'type' => 'cancelled',
                'title' => 'Reservation Cancelled',
                'message' => 'The court reservation slot for ' . htmlspecialchars($row['court']) . ' Court on ' . htmlspecialchars($row['date']) . ' has been marked as cancelled.',
                'timeLabel' => 'User Action',
                'isRead' => $isRead
            ];
        } elseif (strtolower($row['status']) === 'rejected') {
            // FIXED: Added an explicit description card state block mapping for the Rejected status
            $notificationsList[] = [
                'id' => $bookingId,
                'type' => 'rejected',
                'title' => 'Booking Request Rejected',
                'message' => 'Your reservation request for ' . htmlspecialchars($row['court']) . ' Court on ' . htmlspecialchars($row['date']) . ' has been rejected by the administrator.',
                'timeLabel' => 'Management Decision',
                'isRead' => $isRead
            ];
        } else {
            $notificationsList[] = [
                'id' => $bookingId,
                'type' => 'pending',
                'title' => 'Booking Request Received',
                'message' => 'Your reservation slot for ' . htmlspecialchars($row['court']) . ' Court is currently under active evaluation by our operations desk.',
                'timeLabel' => 'Awaiting Review',
                'isRead' => $isRead
            ];
        }
    }
} catch (PDOException $e) {
    // Safe initialization fallback block mapping
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
                        <a class="nav-link active d-flex align-items-center gap-3 px-3 py-2.5 rounded-3" href="notifications.php">
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

        <main class="flex-grow-1 workspace-content p-4 p-lg-5 bg-light overflow-y-auto">
            
            <header class="d-flex justify-content-between align-items-start mb-4">
                <div>
                    <h4 class="fw-bold tracking-tight text-dark mb-1">Notifications</h4>
                    <span class="text-muted small">Stay updated on live change-logs related to your submitted venue slots.</span>
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

            <div class="bg-white p-3 rounded-4 border shadow-sm mb-4 d-flex flex-column flex-sm-row justify-content-between align-items-stretch align-items-sm-center gap-3">
                <div class="d-flex flex-wrap gap-2" id="notificationTabFilterGroup">
                    <button class="btn btn-purple filter-pill active small-btn" data-filter="all">All</button>
                    <button class="btn btn-light border filter-pill small-btn" data-filter="unread">Unread</button>
                    <button class="btn btn-light border filter-pill small-btn" data-filter="read">Read</button>
                </div>

                <form action="notifications.php" method="POST">
                    <input type="hidden" name="action" value="mark_all_read">
                    <button type="submit" class="btn btn-outline-secondary btn-sm rounded-3 py-1.5 px-3 small tracking-wide shadow-sm w-100" id="markAllReadBtn">
                        <i class="bi bi-check2-all me-1"></i> Mark all as read
                    </button>
                </form>
            </div>

            <div class="d-flex flex-column gap-3" id="notificationsFeedWrapper">
            </div>

            <div class="text-center py-5 bg-white border rounded-4 shadow-sm my-4 d-none" id="notificationsEmptyPlaceholder">
                <i class="bi bi-bell-slash text-muted display-4 mb-2"></i>
                <h6 class="fw-bold text-dark mb-1">No Notifications Registered</h6>
                <p class="text-secondary small mb-0">You are completely caught up! No active updates require modification review.</p>
            </div>

        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const serverLiveNotificationsRegistry = <?php echo json_encode($notificationsList); ?>;

        document.addEventListener('DOMContentLoaded', function() {
            let activeFilterCriteria = "all";

            const feedWrapper = document.getElementById('notificationsFeedWrapper');
            const emptyPlaceholder = document.getElementById('notificationsEmptyPlaceholder');

            function buildDynamicNotificationsEngine() {
                feedWrapper.innerHTML = "";

                let processedFeedList = serverLiveNotificationsRegistry.filter(item => {
                    if (activeFilterCriteria === 'unread') return !item.isRead;
                    if (activeFilterCriteria === 'read') return item.isRead;
                    return true;
                });

                if (processedFeedList.length === 0) {
                    emptyPlaceholder.classList.remove('d-none');
                    return;
                }
                emptyPlaceholder.classList.add('d-none');

                processedFeedList.forEach(item => {
                    const trackingCard = document.createElement('div');
                    
                    let unreadDotMarkup = item.isRead ? "" : `<span class="position-absolute top-50 end-0 translate-middle-y me-4 badge rounded-circle bg-purple p-1.5"><span class="visually-hidden">Unread Alert</span></span>`;
                    let inlineCardStyling = item.isRead ? "border-light-subtle opacity-85" : "border-purple-subtle bg-purple-light-row";
                    
                    let visualIconClass = "bi-check-circle text-success bg-success-light";
                    if (item.type === 'cancelled' || item.type === 'rejected') visualIconClass = "bi-x-circle text-danger bg-danger-light";
                    if (item.type === 'pending') visualIconClass = "bi-hourglass-split text-warning bg-warning-light";

                    trackingCard.className = `card border rounded-4 p-3.5 shadow-sm bg-white position-relative transition-all ${inlineCardStyling}`;
                    trackingCard.innerHTML = `
                        <div class="d-flex align-items-start gap-3 text-start">
                            <div class="notification-avatar-icon-box rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 ${visualIconClass.split(' ')[1]}">
                                <i class="bi ${visualIconClass.split(' ')[0]} fs-5 ${visualIconClass.split(' ')[0] === 'bi-hourglass-split' ? 'text-warning' : ''}"></i>
                            </div>
                            <div class="pe-4 flex-grow-1">
                                <div class="d-flex align-items-center gap-2 mb-0.5 flex-wrap">
                                    <h6 class="fw-bold text-dark mb-0 small" style="font-size:0.95rem;">${item.title}</h6>
                                    <span class="text-uppercase font-monospace text-muted tracking-wider extra-small-text opacity-75">#BK-${item.id}</span>
                                </div>
                                <p class="text-secondary small mb-1.5 leading-normal" style="font-size:0.875rem;">${item.message}</p>
                                <span class="text-muted font-monospace text-extra-small d-block opacity-75"><i class="bi bi-clock-history me-1"></i>${item.timeLabel}</span>
                            </div>
                        </div>
                        ${unreadDotMarkup}
                    `;
                    feedWrapper.appendChild(trackingCard);
                });
            }

            document.querySelectorAll('.filter-pill').forEach(pill => {
                pill.addEventListener('click', function() {
                    document.querySelectorAll('.filter-pill').forEach(p => {
                        p.classList.remove('active', 'btn-purple');
                        p.classList.add('btn-light', 'border');
                    });
                    this.classList.add('active', 'btn-purple');
                    this.classList.remove('btn-light', 'border');

                    activeFilterCriteria = this.getAttribute('data-filter');
                    buildDynamicNotificationsEngine();
                });
            });

            buildDynamicNotificationsEngine();
        });
    </script>
</body>
</html>