<?php
session_start();
require_once('db.php'); // Pull live database connection map instance

// 1. Session & Access Control Verification
if (!isset($_SESSION['userRole'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['userId'] ?? 0;
$userName = $_SESSION['loggedInUser'] ?? 'Student';

// 2. Fetch past and inactive booking rows dynamically from the database
$historyBookingsArray = [];
try {
    // UPDATED: Added explicitly 'rejected' parameters into the tracking log mapping criteria
    $fetchStmt = $pdo->prepare("
        SELECT 
            b.booking_id AS id, 
            c.court_name AS court, 
            b.booking_date AS date, 
            b.time_slot AS timeSlot, 
            CASE 
                WHEN LOWER(b.status) = 'cancelled' THEN 'cancelled'
                WHEN LOWER(b.status) = 'rejected' THEN 'rejected'
                ELSE 'completed'
            END AS status 
        FROM BOOKING b
        JOIN COURT c ON b.court_id = c.court_id
        WHERE b.user_id = ? AND (b.booking_date < CURDATE() OR LOWER(b.status) = 'cancelled' OR LOWER(b.status) = 'rejected')
        ORDER BY b.booking_date DESC, b.time_slot DESC
    ");
    $fetchStmt->execute([$userId]);
    $historyBookingsArray = $fetchStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Return empty array safely if database tables are modifying
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
                         <a class="nav-link active d-flex align-items-center gap-3 px-3 py-2.5 rounded-3" href="history.php">
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
                    <h4 class="fw-bold tracking-tight text-dark mb-1">Booking History</h4>
                    <span class="text-muted small">View a comprehensive archival record of your historical account usages.</span>
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

            <div class="bg-white p-3 rounded-4 border shadow-sm mb-4 d-flex flex-column flex-md-row justify-content-between align-items-stretch align-items-md-center gap-3">
                <div class="d-flex flex-wrap gap-2" id="historyPillGroup">
                    <button class="btn btn-purple filter-pill active small-btn" data-filter="all">All Records</button>
                    <button class="btn btn-light border filter-pill small-btn" data-filter="completed">Completed</button>
                    <button class="btn btn-light border filter-pill small-btn" data-filter="cancelled">Cancelled</button>
                    <button class="btn btn-light border filter-pill small-btn" data-filter="rejected">Rejected</button>
                </div>

                <div class="search-box-wrapper position-relative" style="max-width: 320px; width: 100%;">
                    <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                    <input type="text" class="form-control rounded-3 ps-5 py-2 border-light-subtle small shadow-sm" id="historySearchInput" placeholder="Search court type...">
                </div>
            </div>

            <div class="bg-white rounded-4 border shadow-sm overflow-hidden mb-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 custom-history-table">
                        <thead class="bg-light text-secondary small text-uppercase font-monospace tracking-wider border-bottom text-start">
                            <tr>
                                <th class="ps-4 py-3">Booking ID</th>
                                <th class="py-3">Court Type</th>
                                <th class="py-3">Date</th>
                                <th class="py-3">Time Slot</th>
                                <th class="py-3 pe-4 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody id="historyTableRecordsContainer" class="text-start small">
                        </tbody>
                    </table>
                </div>

                <div class="text-center py-5 d-none" id="historyEmptyPlaceholder">
                    <i class="bi bi-folder2-open text-muted display-4 mb-2"></i>
                    <h6 class="fw-bold text-dark mb-1">No Historical Logs Found</h6>
                    <p class="text-secondary small mb-0">No entries match your selected configuration variables.</p>
                </div>
            </div>

        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const serverLiveHistoryArray = <?php echo json_encode($historyBookingsArray); ?>;

        document.addEventListener('DOMContentLoaded', function() {
            let targetFilterState = "all";
            let targetQueryString = "";

            const tableBody = document.getElementById('historyTableRecordsContainer');
            const emptyPlaceholder = document.getElementById('historyEmptyPlaceholder');

            function renderStudentHistoryDataEngine() {
                tableBody.innerHTML = "";
                
                let filteredList = serverLiveHistoryArray.filter(item => {
                    const matchesFilter = (targetFilterState === "all") || (item.status.toLowerCase() === targetFilterState);
                    const matchesSearch = item.court.toLowerCase().includes(targetQueryString.toLowerCase());
                    return matchesFilter && matchesSearch;
                });

                if (filteredList.length === 0) {
                    emptyPlaceholder.classList.remove('d-none');
                    return;
                }
                emptyPlaceholder.classList.add('d-none');

                filteredList.forEach(log => {
                    const rowElement = document.createElement('tr');

                    let statusBadgeClass = "bg-success-light text-success";
                    if (log.status.toLowerCase() === 'cancelled' || log.status.toLowerCase() === 'rejected') {
                        statusBadgeClass = "bg-danger-light text-danger";
                    }

                    const dateObj = new Date(log.date);
                    const formattedDate = dateObj.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });

                    rowElement.innerHTML = `
                        <td class="ps-4 font-monospace fw-semibold text-muted">#BK-${log.id}</td>
                        <td class="fw-bold text-dark">${log.court} Court</td>
                        <td class="text-secondary">${formattedDate}</td>
                        <td class="text-secondary">${log.timeSlot}</td>
                        <td class="pe-4 text-center">
                            <span class="badge ${statusBadgeClass} px-2.5 py-1 rounded small fw-medium text-capitalize">${log.status}</span>
                        </td>
                    `;
                    tableBody.appendChild(rowElement);
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

                    targetFilterState = this.getAttribute('data-filter');
                    renderStudentHistoryDataEngine();
                });
            });

            document.getElementById('historySearchInput').addEventListener('input', function() {
                targetQueryString = this.value;
                renderStudentHistoryDataEngine();
            });

            renderStudentHistoryDataEngine();
        });
    </script>
</body>
</html>