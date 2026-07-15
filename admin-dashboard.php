<?php
session_start();
require_once('db.php');

// 1. Server-Side Security Verification[cite: 1]
if (!isset($_SESSION['userRole']) || $_SESSION['userRole'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$adminName = $_SESSION['loggedInUser'] ?? 'Admin Portal';

// 2. Fetch Live Totals from Database for the 5 Stats Cards[cite: 1]
$totalBookingsCount = 0;
$completeBookingsCount = 0;
$upcomingBookingsCount = 0;
$cancelledBookingsCount = 0;
$totalUsersCount = 0;

try {
    // Optimized: Combined sequential queries into a single database hit to scale smoothly
    $stats = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN LOWER(status) = 'complete' THEN 1 ELSE 0 END) as complete,
            SUM(CASE WHEN LOWER(status) IN ('confirmed', 'approved', 'pending') THEN 1 ELSE 0 END) as upcoming,
            SUM(CASE WHEN LOWER(status) IN ('cancelled', 'rejected') THEN 1 ELSE 0 END) as cancelled
        FROM BOOKING
    ")->fetch(PDO::FETCH_ASSOC);

    $totalBookingsCount = $stats['total'] ?? 0;
    $completeBookingsCount = $stats['complete'] ?? 0;
    $upcomingBookingsCount = $stats['upcoming'] ?? 0;
    $cancelledBookingsCount = $stats['cancelled'] ?? 0;
    
    $totalUsersCount = $pdo->query("SELECT COUNT(*) FROM USER")->fetchColumn();
} catch (PDOException $e) {
    // Fallback if tables are initializing[cite: 1]
}

// 3. Fetch Master Registry Array to power the interactive chart and stream filters[cite: 1]
$masterBookingsArray = [];
try {
    $fetchStmt = $pdo->query("
        SELECT 
            b.booking_id AS id, 
            c.court_name AS court, 
            b.booking_date AS date, 
            b.time_slot AS timeSlot, 
            b.status AS status,
            u.full_name AS bookedBy
        FROM BOOKING b
        JOIN COURT c ON b.court_id = c.court_id
        JOIN USER u ON b.user_id = u.user_id
        ORDER BY b.booking_date DESC, b.time_slot DESC
    ");
    

        while ($row = $fetchStmt->fetch(PDO::FETCH_ASSOC)) {
        $dbStatus = strtolower($row['status']);
        if ($dbStatus === 'approved' || $dbStatus === 'pending' || $dbStatus === 'confirmed') {
            $statusNormalized = 'Confirmed';
        } elseif ($dbStatus === 'complete') {
            $statusNormalized = 'Complete';
        } else {
            $statusNormalized = 'Cancelled';
        }

        $masterBookingsArray[] = [
            'id' => $row['id'],
            'court' => $row['court'],
            'date' => $row['date'],
            'timeSlot' => $row['timeSlot'],
            'status' => $statusNormalized,
            'bookedBy' => $row['bookedBy']
        ];
    }
} catch (PDOException $e) {
    // Safe fallback[cite: 1]
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - UiTM Court Booking System</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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
           2. Core Sidebar Overrides (Fixes Low-Contrast Ghosting Bug)
           ========================================================================== */
        .sidebar-navigation {
            width: 260px;
            background-color: #2b1b54 !important; /* UiTM Corporate Purple */
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            transition: transform 0.3s ease-in-out;
            z-index: 1045;
        }
        
        /* High contrast nav links forcing vibrant text over the dark background */
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
        }
        
        .sidebar-navigation .nav-link i {
            font-size: 1.15rem;
            color: inherit !important;
        }
        
        /* Fallback safety for class extensions */
        .sidebar-navigation .text-white-50 {
            color: rgba(255, 255, 255, 0.75) !important;
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

        /* ==========================================================================
           3. Dashboard Component Widgets
           ========================================================================== */
        .admin-stat-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border-color: rgba(0, 0, 0, 0.05) !important;
            border-radius: 12px !important;
        }
        .admin-stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08) !important;
        }
        .stat-icon-wrapper {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
        }

        /* Card Icon Accent Schemes */
        .bg-purple-light { background-color: rgba(111, 66, 193, 0.1); color: #6f42c1; }
        .bg-success-light { background-color: rgba(25, 135, 84, 0.1); color: #198754; }
        .bg-blue-light { background-color: rgba(13, 110, 253, 0.1); color: #0d6efd; }
        .bg-danger-light { background-color: rgba(220, 53, 69, 0.1); color: #dc3545; }
        .bg-orange-light { background-color: rgba(253, 126, 20, 0.1); color: #fd7e14; }
        .text-muted-small { color: #6c757d; font-size: 0.72rem; }

        /* Booking Card Stream */
        .recent-booking-item-card {
            background-color: #f8f9fa;
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: background-color 0.15s ease, border-color 0.15s ease;
        }
        .recent-booking-item-card:hover {
            background-color: #f1f3f5;
            border-color: rgba(0, 0, 0, 0.1);
        }
        .court-avatar-wrapper {
            width: 42px;
            height: 42px;
            background-color: #fff;
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 8px;
            flex-shrink: 0;
        }

        #recentBookingsCardStream::-webkit-scrollbar { width: 6px; }
        #recentBookingsCardStream::-webkit-scrollbar-track { background: transparent; }
        #recentBookingsCardStream::-webkit-scrollbar-thumb { background: rgba(0, 0, 0, 0.15); border-radius: 10px; }
        #recentBookingsCardStream::-webkit-scrollbar-thumb:hover { background: rgba(0, 0, 0, 0.3); }

        /* Responsive Breakpoint Adaptations */
        @media (max-width: 991.98px) {
            .sidebar-navigation { width: 280px; }
            .sidebar-navigation:not(.show) { visibility: hidden; }
        }
        @media (max-width: 575.98px) {
            .workspace-content { padding-left: 1rem !important; padding-right: 1rem !important; }
            .admin-stat-card { padding: 1rem !important; }
        }
    </style>
</head>
<body>

    <div class="d-flex min-vh-100 layout-wrapper">
        
        <!-- RESPONSIVE NAV DRAWER: Sidebar on Desktop, Floating offcanvas panel on Mobile/Tablet -->
        <aside class="offcanvas-lg offcanvas-start sidebar-navigation text-white p-3 d-flex flex-column justify-content-between flex-shrink-0" 
               tabindex="-1" id="sidebarMenu" aria-labelledby="sidebarMenuLabel">
            
            <div>
                <div class="sidebar-brand-showcase d-flex align-items-center justify-content-between mb-4">
                    <div class="d-flex align-items-center gap-2">
                        <img src="images/uitm-logo.png" alt="UiTM Logo" height="40" class="me-2">
                        <span class="fw-bold tracking-wide fs-5">UiTM Court Admin</span>
                    </div>
                    <!-- Close button for compact viewports -->
                    <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#sidebarMenu" aria-label="Close"></button>
                </div>
                
                <nav class="nav flex-column">
                    <a class="nav-link active" href="admin-dashboard.php">
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
                    <a class="nav-link" href="settings-admin.php">
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

        <!-- MAIN APP SPACE -->
        <main class="flex-grow-1 workspace-content p-3 p-sm-4 p-lg-5 bg-light overflow-y-auto">
            
            <!-- TOOLBAR NAV HEADER WITH RESPONSIVE HAMBURGER TOGGLE -->
            <header class="d-flex justify-content-between align-items-center gap-3 mb-4">
                <div class="d-flex align-items-center gap-3">
                    <!-- Hamburger Menu Button: Automatically targets offcanvas sidebar overlay -->
                    <button class="btn btn-outline-dark d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu">
                        <i class="bi bi-list fs-4"></i>
                    </button>
                    
                    <div class="text-start">
                        <h2 class="fw-bold tracking-tight text-dark mb-1 fs-3 fs-sm-2">Welcome back, Admin!</h2>
                        <p class="text-muted small mb-0 d-none d-sm-block">Here's an overview of the court booking system.</p>
                    </div>
                </div>
                
                <div class="d-flex align-items-center gap-2 gap-sm-3 bg-white px-3 py-2 rounded-3 border shadow-sm">
                    <div class="bg-secondary text-white rounded-circle p-1 d-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">
                        <i class="bi bi-person-fill"></i>
                    </div>
                    <div class="text-start d-none d-md-block">
                        <div class="fw-semibold text-dark small lh-1 mb-1" id="adminNameDisplay"><?php echo htmlspecialchars($adminName); ?></div>
                        <span class="text-muted-small text-uppercase tracking-wider font-monospace" style="font-size: 0.65rem;">Admin</span>
                    </div>
                </div>
            </header>

            <!-- STATS COUNTER BLOCKS -->
            <div class="row g-3 mb-4 row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-xl-5 text-start">
                <div class="col">
                    <div class="admin-stat-card p-3 shadow-sm d-flex align-items-center justify-content-between bg-white rounded-3 border">
                        <div>
                            <span class="text-muted small fw-medium d-block mb-1 text-nowrap">Total Bookings</span>
                            <h3 class="fw-bold text-dark mb-0" id="statTotalBookings"><?php echo $totalBookingsCount; ?></h3>
                            <span class="text-muted-small font-monospace">Live Registry</span>
                        </div>
                        <div class="stat-icon-wrapper bg-purple-light"><i class="bi bi-calendar-range fs-5"></i></div>
                    </div>
                </div>
                <div class="col">
                    <div class="admin-stat-card p-3 shadow-sm d-flex align-items-center justify-content-between bg-white rounded-3 border">
                        <div>
                            <span class="text-muted small fw-medium d-block mb-1 text-nowrap">Complete Bookings</span>
                            <h3 class="fw-bold text-dark mb-0" id="statCompleteBookings"><?php echo $completeBookingsCount; ?></h3>
                            <span class="text-muted-small font-monospace">Finished</span>
                        </div>
                        <div class="stat-icon-wrapper bg-success-light"><i class="bi bi-calendar-check-fill fs-5"></i></div>
                    </div>
                </div>
                <div class="col">
                    <div class="admin-stat-card p-3 shadow-sm d-flex align-items-center justify-content-between bg-white rounded-3 border">
                        <div>
                            <span class="text-muted small fw-medium d-block mb-1 text-nowrap">Upcoming Bookings</span>
                            <h3 class="fw-bold text-dark mb-0" id="statUpcomingBookings"><?php echo $upcomingBookingsCount; ?></h3>
                            <span class="text-muted-small font-monospace">Scheduled</span>
                        </div>
                        <div class="stat-icon-wrapper bg-blue-light"><i class="bi bi-clock-history fs-5"></i></div>
                    </div>
                </div>
                <div class="col">
                    <div class="admin-stat-card p-3 shadow-sm d-flex align-items-center justify-content-between bg-white rounded-3 border">
                        <div>
                            <span class="text-muted small fw-medium d-block mb-1 text-nowrap">Cancelled Bookings</span>
                            <h3 class="fw-bold text-dark mb-0" id="statCancelledBookings"><?php echo $cancelledBookingsCount; ?></h3>
                            <span class="text-muted-small font-monospace">Revoked</span>
                        </div>
                        <div class="stat-icon-wrapper bg-danger-light"><i class="bi bi-calendar-x"></i></div>
                    </div>
                </div>
                <div class="col">
                    <div class="admin-stat-card p-3 shadow-sm d-flex align-items-center justify-content-between bg-white rounded-3 border">
                        <div>
                            <span class="text-muted small fw-medium d-block mb-1 text-nowrap">Total Users</span>
                            <h3 class="fw-bold text-dark mb-0" id="statTotalStudents"><?php echo $totalUsersCount; ?></h3>
                            <span class="text-muted-small font-monospace">Active Users</span>
                        </div>
                        <div class="stat-icon-wrapper bg-orange-light"><i class="bi bi-people-fill fs-5"></i></div>
                    </div>
                </div>
            </div>

            <!-- INTERACTIVE REGISTRY FILTERS -->
            <div class="bg-white p-4 rounded-3 border border-light-subtle shadow-sm mb-4 text-start">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-semibold text-secondary mb-1">Date Window Filter</label>
                        <div class="input-group input-group-sm shadow-sm">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-calendar-event small text-muted"></i></span>
                            <input type="date" class="form-control border-start-0" id="toolbarDateFilter">
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-semibold text-secondary mb-1">Court Category</label>
                        <select class="form-select form-select-sm shadow-sm border-primary" id="toolbarCourtSelector" style="border-width: 2px;">
                            <option value="all">All Courts</option>
                            <option value="Petanque Court">Petanque Court</option>
                            <option value="Futsal Court">Futsal Court</option>
                            <option value="Takraw Court">Takraw Court</option>
                            <option value="Volleyball Court">Volleyball Court</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-semibold text-secondary mb-1">Quick Status Filter</label>
                        <select class="form-select form-select-sm shadow-sm" id="toolbarQuickFilter">
                            <option value="all">All Bookings</option>
                            <option value="Confirmed">Confirmed</option>
                            <option value="Complete">Complete</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- TREND VISUALIZATIONS AND RECENT ACTIVITY STREAM -->
            <div class="row g-4 mb-4 text-start">
                <div class="col-12 col-xl-7">
                    <div class="bg-white p-4 rounded-3 border border-light-subtle shadow-sm h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold text-dark mb-0">Bookings Overview</h5>
                            <span class="badge bg-light text-secondary border px-2 py-1 small">Live Track</span>
                        </div>
                        <div class="chart-container position-relative" style="min-height: 250px; max-height: 350px; width: 100%;">
                            <canvas id="adminActivityTrendsChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-xl-5">
                    <div class="bg-white p-4 rounded-3 border border-light-subtle shadow-sm h-100">
                        <div class="mb-3">
                            <h5 class="fw-bold text-dark mb-0">Recent Bookings</h5>
                        </div>
                        
                        <div id="recentBookingsCardStream" class="d-flex flex-column gap-3" style="max-height: 330px; overflow-y: auto; padding-right: 4px;">
                            <!-- Loaded via JS Engine Loop -->
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Escaped safely using Hex entity flags to explicitly prevent script injection vectors
            const masterBookingsArray = <?php echo json_encode($masterBookingsArray, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

            const dateFilterInput = document.getElementById('toolbarDateFilter');
            const courtSelector = document.getElementById('toolbarCourtSelector');
            const quickFilterSelector = document.getElementById('toolbarQuickFilter');
            const streamContainerDOM = document.getElementById('recentBookingsCardStream');

            // Generate responsive line chart trend graphs via internal tracking variables
            const ctxChart = document.getElementById('adminActivityTrendsChart').getContext('2d');
            
            const daysLabels = [];
            const bookingVolumeData = [];
            for (let i = 6; i >= 0; i--) {
                const d = new Date();
                d.setDate(d.getDate() - i);
                const dateString = d.toISOString().split('T')[0];
                daysLabels.push(d.toLocaleDateString('en-US', { day: 'numeric', month: 'short' }));
                
                const matches = masterBookingsArray.filter(b => b.date === dateString).length;
                bookingVolumeData.push(matches);
            }

            new Chart(ctxChart, {
                type: 'line',
                data: {
                    labels: daysLabels,
                    datasets: [{
                        label: 'Live Bookings Volume',
                        data: bookingVolumeData,
                        borderColor: '#2b1b54',
                        backgroundColor: 'rgba(43, 27, 84, 0.05)',
                        tension: 0.35,
                        fill: true,
                        borderWidth: 2,
                        pointBackgroundColor: '#ffd700'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { stepSize: 1 } }
                    }
                }
            });

            // Filtering stream renderer loop
            function renderLiveDashboardStream() {
                const chosenDate = dateFilterInput.value;
                const chosenCourt = courtSelector.value;
                const chosenStatus = quickFilterSelector.value;

                let dataset = masterBookingsArray.filter(booking => {
                    if(chosenDate && booking.date !== chosenDate) return false;
                    if(chosenCourt !== "all" && booking.court !== chosenCourt) return false;
                    
                    const actualStatus = booking.status || "Confirmed";
                    if(chosenStatus !== "all" && actualStatus !== chosenStatus) return false;
                    
                    return true;
                });

                streamContainerDOM.innerHTML = "";

                if(dataset.length === 0) {
                    streamContainerDOM.innerHTML = `
                        <div class="text-center text-muted py-5 small border border-dashed rounded-3 bg-light">
                            <i class="bi bi-folder-x d-block fs-3 mb-2 text-secondary"></i>
                            No dynamic booking entries recorded.
                        </div>`;
                    return;
                }

                dataset.forEach(booking => {
                    const statusVal = booking.status || "Confirmed";
                    let matchIcon = "bi-calendar-week";
                    
                    if(booking.court && booking.court.includes("Futsal")) matchIcon = "bi-universal-access";
                    else if(booking.court && booking.court.includes("Petanque")) matchIcon = "bi-life-preserver";
                    else if(booking.court && booking.court.includes("Takraw")) matchIcon = "bi-grid-3x3-gap";
                    else if(booking.court && booking.court.includes("Volleyball")) matchIcon = "bi-globe";

                    let badgeClass = "badge bg-warning-subtle text-warning border border-warning-subtle";
                    if(statusVal === "Cancelled") badgeClass = "badge bg-danger-subtle text-danger border border-danger-subtle";
                    else if(statusVal === "Complete") badgeClass = "badge bg-primary-subtle text-primary border border-primary-subtle";
                    
                    const cardRow = document.createElement('div');
                    cardRow.className = "recent-booking-item-card d-flex align-items-center justify-content-between p-2 rounded-3";
                    cardRow.innerHTML = `
                        <div class="d-flex align-items-center gap-3">
                            <div class="court-avatar-wrapper d-flex align-items-center justify-content-center">
                                <i class="bi ${matchIcon} text-secondary fs-5"></i>
                            </div>
                            <div class="text-start">
                                <h6 class="fw-bold text-dark mb-0" style="font-size: 0.88rem;">${booking.court || 'Court Facility'}</h6>
                                <div class="text-muted text-nowrap styling-meta mb-1" style="font-size: 0.78rem;">
                                    <i class="bi bi-calendar3 me-1"></i> ${booking.date || 'N/A'}, ${booking.timeSlot || 'N/A'}
                                </div>
                                <div class="text-secondary small" style="font-size: 0.78rem;">
                                    <i class="bi bi-person me-1"></i> ${booking.bookedBy || 'Student'}
                                </div>
                            </div>
                        </div>
                        <div class="text-end d-flex flex-column align-items-end gap-1">
                            <span class="${badgeClass} px-2 py-1" style="font-size: 0.72rem; font-weight: 600;">${statusVal}</span>
                        </div>
                    `;
                    streamContainerDOM.appendChild(cardRow);
                });
            }

            dateFilterInput.addEventListener('change', renderLiveDashboardStream);
            courtSelector.addEventListener('change', renderLiveDashboardStream);
            quickFilterSelector.addEventListener('change', renderLiveDashboardStream);
            
            renderLiveDashboardStream();
        });
    </script>
</body>
</html>