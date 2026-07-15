<?php
session_start();
require_once('db.php'); // Pull live database connection configuration map instance

// 1. Session & Access Control Verification
if (!isset($_SESSION['userRole'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['userId'] ?? 0;
$userName = $_SESSION['loggedInUser'] ?? 'Student';
$successAlert = "";
$errorAlert = "";

// 2. Handle Booking Cancellation Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_booking') {
    $bookingIdToCancel = intval($_POST['booking_id'] ?? 0);
    
    if ($bookingIdToCancel > 0) {
        try {
            // Verify ownership first before altering table data parameters
            $verifyStmt = $pdo->prepare("SELECT COUNT(*) FROM BOOKING WHERE booking_id = ? AND user_id = ?");
            $verifyStmt->execute([$bookingIdToCancel, $userId]);
            
            if ($verifyStmt->fetchColumn() > 0) {
                $updateStmt = $pdo->prepare("UPDATE BOOKING SET status = 'cancelled' WHERE booking_id = ?");
                $updateStmt->execute([$bookingIdToCancel]);
                $successAlert = "Booking reservation cancelled successfully.";
            } else {
                $errorAlert = "Access Denied: You do not have permissions to modify this booking resource.";
            }
        } catch (PDOException $e) {
            $errorAlert = "Database Modification Error: " . $e->getMessage();
        }
    }
}

// 3. Fetch all active rows dynamically to pass straight into the JavaScript frontend grid table engine
$bookingsArray = [];
try {
    // FIXED: Changed c.name to c.court_name to align with your init.sql schema specifications
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
    $bookingsArray = $fetchStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Return empty array if initialization is ongoing
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
                       <a class="nav-link active d-flex align-items-center gap-3 px-3 py-2.5 rounded-3" href="my-booking.php">
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
        <main class="flex-grow-1 workspace-content p-4 p-lg-5 bg-light overflow-y-auto">
            
            <header class="d-flex justify-content-between align-items-start mb-4">
                <div>
                    <h4 class="fw-bold tracking-tight text-dark mb-1">My Bookings</h4>
                    <span class="text-muted small">Manage your upcoming and active athletic stadium reservation slots here.</span>
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

            <div class="bg-white p-3 rounded-4 border shadow-sm mb-4 d-flex flex-column flex-md-row justify-content-between align-items-stretch align-items-md-center gap-3">
                <div class="d-flex flex-wrap gap-2" id="filterPillGroup">
                    <button class="btn btn-purple filter-pill active small-btn" data-filter="all">All</button>
                    <button class="btn btn-light border filter-pill small-btn" data-filter="pending">Pending</button>
                    <button class="btn btn-light border filter-pill small-btn" data-filter="approved">Approved</button>
                    <button class="btn btn-light border filter-pill small-btn" data-filter="complete">Complete</button>
                    <button class="btn btn-light border filter-pill small-btn" data-filter="cancelled">Cancelled</button>
                    <button class="btn btn-light border filter-pill small-btn" data-filter="rejected">Rejected</button>
                </div>

                <div class="search-box-wrapper position-relative" style="max-width: 320px; width: 100%;">
                    <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                    <input type="text" class="form-control rounded-3 ps-5 py-2 border-light-subtle small shadow-sm" id="searchFilterInput" placeholder="Search court type...">
                </div>
            </div>

            <div class="row g-3" id="studentBookingsGridContainer">
            </div>

            <div class="text-center py-5 bg-white border rounded-4 shadow-sm my-4 d-none" id="emptyStatePlaceholder">
                <i class="bi bi-calendar-x text-muted display-4 mb-3"></i>
                <h6 class="fw-bold text-dark mb-1">No Booking Records Found</h6>
                <p class="text-secondary small mb-0">Try adjusting your active navigation filter metrics or make a new reservation.</p>
            </div>

        </main>
    </div>

    <form id="hiddenCancellationNativeForm" action="my-booking.php" method="POST" style="display:none;">
        <input type="hidden" name="action" value="cancel_booking">
        <input type="hidden" name="booking_id" id="hiddenCancelIdField" value="">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const serverLiveBookingsArray = <?php echo json_encode($bookingsArray); ?>;

        document.addEventListener('DOMContentLoaded', function() {
            let targetFilterState = "all";
            let targetQueryString = "";

            const gridContainer = document.getElementById('studentBookingsGridContainer');
            const emptyPlaceholder = document.getElementById('emptyStatePlaceholder');

            function renderStudentBookingsDataEngine() {
                gridContainer.innerHTML = "";
                
                let processedRecordsList = serverLiveBookingsArray.filter(item => {
                    const statusLower = item.status ? item.status.toLowerCase() : 'pending';
                    const matchesFilter = (targetFilterState === "all") || 
                                          (statusLower === targetFilterState) || 
                                          (targetFilterState === 'complete' && statusLower === 'completed');
                    const matchesSearch = item.court.toLowerCase().includes(targetQueryString.toLowerCase());
                    return matchesFilter && matchesSearch;
                });

                if (processedRecordsList.length === 0) {
                    emptyPlaceholder.classList.remove('d-none');
                    return;
                }
                emptyPlaceholder.classList.add('d-none');

                processedRecordsList.forEach(booking => {
                    const columnElement = document.createElement('div');
                    columnElement.className = "col-12 col-md-6 col-xl-4";

                    const currentStatus = booking.status ? booking.status.toLowerCase() : 'pending';

                    // FIXED: Turn badge green if status is 'complete' or 'completed' or 'approved'/'confirmed'
                    let badgeStylingClass = "bg-warning-light text-warning"; 
                    if (currentStatus === 'approved' || currentStatus === 'confirmed' || currentStatus === 'complete' || currentStatus === 'completed') {
                        badgeStylingClass = "bg-success-light text-success";
                    }
                    if (currentStatus === 'cancelled' || currentStatus === 'rejected') {
                        badgeStylingClass = "bg-danger-light text-danger";
                    }

                    // FIXED: Clear and remove cancel button if the booking status is 'complete' or 'completed'
                    let actionButtonTemplate = "";
                    if (currentStatus === 'complete' || currentStatus === 'completed') {
                        actionButtonTemplate = `
                            <button class="btn btn-light btn-sm w-100 py-1.5 rounded-3 mt-3 small text-success border" disabled>
                                <i class="bi bi-check-all me-1"></i> Activity Completed
                            </button>
                        `;
                    } else if (currentStatus === 'cancelled' || currentStatus === 'rejected') {
                        actionButtonTemplate = `
                            <button class="btn btn-light btn-sm w-100 py-1.5 rounded-3 mt-3 small text-muted border" disabled>
                                <i class="bi bi-ban me-1"></i> Already ${booking.status}
                            </button>
                        `;
                    } else {
                        actionButtonTemplate = `
                            <button class="btn btn-outline-danger btn-sm w-100 py-1.5 rounded-3 mt-3 tracking-wide small" onclick="triggerNativeCancellationAction(${booking.id})">
                                <i class="bi bi-x-circle me-1"></i> Cancel Booking
                            </button>
                        `;
                    }

                    const parsedDateObj = new Date(booking.date);
                    const formattedDisplayDate = parsedDateObj.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });

                    columnElement.innerHTML = `
                        <div class="card border border-light-subtle shadow-sm rounded-4 p-4 bg-white booking-card position-relative h-100">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-uppercase font-monospace text-muted tracking-wider extra-small-text">#BK-${booking.id}</span>
                                <span class="badge ${badgeStylingClass} px-2.5 py-1 rounded small fw-medium text-capitalize">${booking.status}</span>
                            </div>
                            
                            <h5 class="fw-bold text-dark mb-3">${booking.court} Court</h5>
                            
                            <div class="d-flex flex-column gap-2 border-top pt-2">
                                <div class="d-flex justify-content-between small text-secondary">
                                    <span>Date:</span>
                                    <span class="fw-medium text-dark">${formattedDisplayDate}</span>
                                </div>
                                <div class="d-flex justify-content-between small text-secondary">
                                    <span>Time Window:</span>
                                    <span class="fw-medium text-dark">${booking.timeSlot}</span>
                                </div>
                            </div>
                            
                            ${actionButtonTemplate}
                        </div>
                    `;
                    gridContainer.appendChild(columnElement);
                });
            }

            window.triggerNativeCancellationAction = function(targetId) {
                if (confirm("Are you sure you want to cancel this court reservation request block?")) {
                    document.getElementById('hiddenCancelIdField').value = targetId;
                    document.getElementById('hiddenCancellationNativeForm').submit();
                }
            };

            document.querySelectorAll('.filter-pill').forEach(pill => {
                pill.addEventListener('click', function() {
                    document.querySelectorAll('.filter-pill').forEach(p => {
                        p.classList.remove('active', 'btn-purple');
                        p.classList.add('btn-light', 'border');
                    });
                    this.classList.add('active', 'btn-purple');
                    this.classList.remove('btn-light', 'border');

                    targetFilterState = this.getAttribute('data-filter');
                    renderStudentBookingsDataEngine();
                });
            });

            document.getElementById('searchFilterInput').addEventListener('input', function() {
                targetQueryString = this.value;
                renderStudentBookingsDataEngine();
            });

            renderStudentBookingsDataEngine();
        });
    </script>
</body>
</html>