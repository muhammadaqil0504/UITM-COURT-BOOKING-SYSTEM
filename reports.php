<?php
session_start();
require_once('db.php');

// 1. Session Access Control (Admin Only)
if (!isset($_SESSION['userRole']) || strtolower($_SESSION['userRole']) !== 'admin') {
    header("Location: login.php");
    exit();
}

$adminName = $_SESSION['loggedInUser'] ?? 'System Administrator';

// AUTOMATION: Automatically move past bookings to 'Complete' status on report compile
try {
    $autoCompleteStmt = $pdo->prepare("
        UPDATE BOOKING 
        SET status = 'Complete' 
        WHERE (LOWER(status) = 'approved' OR LOWER(status) = 'confirmed') 
          AND booking_date < CURDATE()
    ");
    $autoCompleteStmt->execute();
} catch (PDOException $e) {
    error_log("Automation error updating past bookings inside reports: " . $e->getMessage());
}

// 2. Fetch Active Filter Criteria from GET Request URL Parameters
$dateRangeRaw = $_GET['date_range'] ?? '';
$filterCourtId = $_GET['court_id'] ?? 'all';
$reportType = $_GET['report_type'] ?? 'all';

// Default to current month boundaries if no range is specified
$startDate = date('Y-m-01'); 
$endDate = date('Y-m-t');    

if (!empty($dateRangeRaw)) {
    if (strpos($dateRangeRaw, ' to ') !== false) {
        $rangeParts = explode(' to ', $dateRangeRaw);
        $startRaw = trim($rangeParts[0]);
        $endRaw = trim($rangeParts[1]);
    } else {
        $startRaw = trim($dateRangeRaw);
        $endRaw = trim($dateRangeRaw);
    }

    // Robust Date Format Normalizer Helper
    $parseDate = function($dateStr) {
        $d = DateTime::createFromFormat('Y-m-d', $dateStr);
        if ($d && $d->format('Y-m-d') === $dateStr) return $d->format('Y-m-d');
        
        $d = DateTime::createFromFormat('d/m/Y', $dateStr);
        if ($d) return $d->format('Y-m-d');

        $d = DateTime::createFromFormat('j/n/Y', $dateStr);
        if ($d) return $d->format('Y-m-d');
        
        return null;
    };

    $parsedStart = $parseDate($startRaw);
    $parsedEnd = $parseDate($endRaw);

    if ($parsedStart) $startDate = $parsedStart;
    if ($parsedEnd) $endDate = $parsedEnd;
}

// Re-generate formatted display string for input field
$currentRangeValue = (!empty($dateRangeRaw)) ? $dateRangeRaw : "{$startDate} to {$endDate}";

try {
    // 3. Fetch Court Entries Dynamically for Filter Dropdown
    $courtsDropdownList = $pdo->query("SELECT court_id, court_name FROM COURT ORDER BY court_name ASC")->fetchAll(PDO::FETCH_ASSOC);

    // 4. Build Dynamic WHERE Clause Conditions for Booking Queries
    $whereConditions = [
        "b.user_id IS NOT NULL",
        "b.booking_date BETWEEN :start_date AND :end_date"
    ];
    $queryParams = [
        ':start_date' => $startDate,
        ':end_date' => $endDate
    ];

    if ($filterCourtId !== 'all') {
        $whereConditions[] = "b.court_id = :court_id";
        $queryParams[':court_id'] = $filterCourtId;
    }

    if ($reportType === 'completed') {
        $whereConditions[] = "LOWER(b.status) IN ('complete', 'completed', 'approved', 'confirmed')";
    } elseif ($reportType === 'cancelled') {
        $whereConditions[] = "LOWER(b.status) IN ('cancelled', 'rejected')";
    }

    $whereClauseSql = implode(" AND ", $whereConditions);

    // --- KPI Query Block ---
    $kpiStmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_bookings,
            SUM(CASE WHEN LOWER(status) IN ('complete', 'completed', 'approved', 'confirmed') THEN 1 ELSE 0 END) as complete_bookings,
            SUM(CASE WHEN LOWER(status) IN ('cancelled', 'rejected') THEN 1 ELSE 0 END) as cancelled_bookings
        FROM BOOKING b
        WHERE $whereClauseSql
    ");
    $kpiStmt->execute($queryParams);
    $kpiData = $kpiStmt->fetch(PDO::FETCH_ASSOC);

    $totalBookings = $kpiData['total_bookings'] ?? 0;
    $completeBookings = $kpiData['complete_bookings'] ?? 0;
    $cancelledBookings = $kpiData['cancelled_bookings'] ?? 0;
    $totalHours = $totalBookings * 2; 

    // --- Bar Chart Overview Query Block ---
    $dailyStmt = $pdo->prepare("
        SELECT 
            b.booking_date,
            COUNT(*) as total,
            SUM(CASE WHEN LOWER(b.status) IN ('complete', 'completed', 'approved', 'confirmed') THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN LOWER(b.status) IN ('cancelled', 'rejected') THEN 1 ELSE 0 END) as cancelled
        FROM BOOKING b
        WHERE $whereClauseSql
        GROUP BY b.booking_date
        ORDER BY b.booking_date ASC
    ");
    $dailyStmt->execute($queryParams);
    $dailyData = $dailyStmt->fetchAll(PDO::FETCH_ASSOC);

    // --- Doughnut & Summary Table Query Block ---
    $courtJoinConditions = [
        "b.court_id = c.court_id",
        "b.user_id IS NOT NULL",
        "b.booking_date BETWEEN :start_date AND :end_date"
    ];
    $courtJoinParams = [
        ':start_date' => $startDate,
        ':end_date' => $endDate
    ];

    if ($reportType === 'completed') {
        $courtJoinConditions[] = "LOWER(b.status) IN ('complete', 'completed', 'approved', 'confirmed')";
    } elseif ($reportType === 'cancelled') {
        $courtJoinConditions[] = "LOWER(b.status) IN ('cancelled', 'rejected')";
    }

    $courtJoinSql = implode(" AND ", $courtJoinConditions);
    
    $courtWhereClauses = [];
    if ($filterCourtId !== 'all') {
        $courtWhereClauses[] = "c.court_id = :court_id";
        $courtJoinParams[':court_id'] = $filterCourtId;
    }
    $courtWhereSql = !empty($courtWhereClauses) ? "WHERE " . implode(" AND ", $courtWhereClauses) : "";

    $courtSummaryStmt = $pdo->prepare("
        SELECT 
            c.court_id,
            c.court_name, 
            c.court_type,
            COUNT(b.booking_id) as total_bookings,
            SUM(CASE WHEN LOWER(b.status) IN ('complete', 'completed', 'approved', 'confirmed') THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN LOWER(b.status) IN ('cancelled', 'rejected') THEN 1 ELSE 0 END) as cancelled
        FROM COURT c 
        LEFT JOIN BOOKING b ON $courtJoinSql
        $courtWhereSql
        GROUP BY c.court_id, c.court_name, c.court_type
        ORDER BY c.court_name ASC
    ");
    $courtSummaryStmt->execute($courtJoinParams);
    $courtSummary = $courtSummaryStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Report Fetch Engine Failure: " . $e->getMessage());
    $totalBookings = $completeBookings = $totalHours = 0;
    $dailyData = [];
    $courtSummary = [];
    $courtsDropdownList = [];
}

// Parse Dynamic Bar Chart Data Arrays
$barLabels = [];
$barTotal = [];
$barCompleted = [];
$barCancelled = [];
foreach ($dailyData as $d) {
    $barLabels[] = date('d M', strtotime($d['booking_date']));
    $barTotal[] = $d['total'];
    $barCompleted[] = $d['completed'];
    $barCancelled[] = $d['cancelled'];
}

// Parse Dynamic Doughnut Chart Data Arrays
$doughnutLabels = [];
$doughnutData = [];
foreach ($courtSummary as $c) {
    if ($c['total_bookings'] > 0) {
        $doughnutLabels[] = $c['court_name'];
        $doughnutData[] = $c['total_bookings'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - UiTM Court Booking System</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    
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
           2. Core Sidebar Overrides (UiTM Purple & Gold Layout Harmony)
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
            color: #ffd700 !important; /* UiTM Gold */
            background-color: rgba(255, 255, 255, 0.15);
            font-weight: 600;
        }

        .sidebar-brand-showcase img {
            filter: drop-shadow(0px 2px 4px rgba(0, 0, 0, 0.25));
        }

        .text-muted-small { color: #6c757d; font-size: 0.72rem; }

        /* ==========================================================================
           3. KPI Card Deck & Layout Custom Enhancements
           ========================================================================== */
        .kpi-icon-box {
            width: 48px;
            height: 48px;
            flex-shrink: 0;
        }
        .chart-container {
            position: relative;
            width: 100%;
        }
        .bar-chart-container { height: 320px; }
        .doughnut-chart-container { height: 280px; width: 100%; }

        /* ==========================================================================
           4. Data Grid Table Styling (Standard Desktop Behavior)
           ========================================================================== */
        .custom-data-table {
            background-color: #ffffff;
            border-radius: 12px;
            border: 1px solid #eef0f2;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.01), 0 1px 2px rgba(0, 0, 0, 0.02);
            overflow: hidden;
        }

        .custom-data-table thead th {
            background-color: #f8fafc;
            color: #64748b;
            font-size: 0.78rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 16px 24px;
            border-bottom: 1px solid #eef0f2;
        }

        .custom-data-table tbody tr {
            border-bottom: 1px solid #f1f3f5;
            transition: background-color 0.15s ease;
        }

        .custom-data-table tbody tr:hover {
            background-color: #f8fafc;
        }

        .custom-data-table tbody td {
            padding: 16px 24px;
            font-size: 0.88rem;
            color: #333333;
            vertical-align: middle;
        }
        
        .custom-data-table tfoot td {
            padding: 16px 24px;
            font-size: 0.9rem;
            border-top: 2px solid #eef0f2;
        }

        .icon-circle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
        }

        /* Responsive Visibility Classes */
        .mobile-cards-view {
            display: none;
        }
        .desktop-table-view {
            display: block;
        }

        /* ==========================================================================
           5. Mobile-Responsive UX Transformation Engine
           ========================================================================== */
        @media (max-width: 991.98px) {
            .sidebar-navigation { width: 280px; }
            .sidebar-navigation:not(.show) { visibility: hidden; }
            
            .mobile-cards-view {
                display: block;
            }
            .desktop-table-view {
                display: none;
            }
        }
        
        @media (max-width: 575.98px) {
            .workspace-content { padding-left: 1rem !important; padding-right: 1rem !important; }
        }
        
        @media print {
            .sidebar-navigation, form, header button, .reports-action-btn { display: none !important; }
            main { padding: 0 !important; background: transparent !important; }
            .workspace-content { overflow-y: visible !important; }
            .mobile-cards-view { display: none !important; }
            .desktop-table-view { display: block !important; }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                    <a class="nav-link active" href="reports.php">
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

        <main class="flex-grow-1 workspace-content p-3 p-sm-4 p-lg-5 bg-light overflow-y-auto">
            
            <header class="d-flex justify-content-between align-items-center gap-3 mb-4">
                <div class="d-flex align-items-center gap-3">
                    <button class="btn btn-outline-dark d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu">
                        <i class="bi bi-list fs-4"></i>
                    </button>
                    
                    <div class="text-start">
                        <h2 class="fw-bold tracking-tight text-dark mb-1 fs-3 fs-sm-2">Reports & Analytics</h2>
                        <p class="text-muted small mb-0 d-none d-sm-block">View real-time utilization graphs, compile metrics, and audit court reservations.</p>
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

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card border-0 rounded-3 shadow-sm p-4 h-100 text-start">
                        <div class="d-flex align-items-start gap-3">
                            <div class="kpi-icon-box bg-primary-subtle text-primary rounded-3 d-flex align-items-center justify-content-center">
                                <i class="bi bi-calendar-check fs-4"></i>
                            </div>
                            <div>
                                <h6 class="text-dark fw-bold mb-1 fs-6">Total Bookings</h6>
                                <h3 class="fw-bold text-dark mb-2"><?php echo $totalBookings; ?></h3>
                                <span class="text-muted small">Selected Scope</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 rounded-3 shadow-sm p-4 h-100 text-start">
                        <div class="d-flex align-items-start gap-3">
                            <div class="kpi-icon-box bg-success-subtle text-success rounded-3 d-flex align-items-center justify-content-center">
                                <i class="bi bi-check-circle fs-4"></i>
                            </div>
                            <div>
                                <h6 class="text-dark fw-bold mb-1 fs-6">Complete Bookings</h6>
                                <h3 class="fw-bold text-dark mb-2"><?php echo $completeBookings; ?></h3>
                                <span class="text-muted small">Selected Scope</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 rounded-3 shadow-sm p-4 h-100 text-start">
                        <div class="d-flex align-items-start gap-3">
                            <div class="kpi-icon-box bg-warning-subtle text-warning rounded-3 d-flex align-items-center justify-content-center">
                                <i class="bi bi-clock-history fs-4"></i>
                            </div>
                            <div>
                                <h6 class="text-dark fw-bold mb-1 fs-6">Total Hours Booked</h6>
                                <h3 class="fw-bold text-dark mb-2"><?php echo $totalHours; ?></h3>
                                <span class="text-muted small">Selected Scope</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <form method="GET" action="reports.php" class="bg-white p-4 rounded-3 border border-light-subtle shadow-sm mb-4 text-start">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-3">
                        <label class="form-label small fw-semibold text-secondary mb-1">Date Range</label>
                        <div class="input-group input-group-sm shadow-sm">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-calendar3 text-muted small"></i></span>
                            <input type="text" id="dateRangePicker" name="date_range" value="<?php echo htmlspecialchars($currentRangeValue); ?>" class="form-control border-start-0 shadow-none form-control-sm" placeholder="Select Date Range">
                        </div>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label small fw-semibold text-secondary mb-1">Court</label>
                        <select name="court_id" class="form-select form-select-sm shadow-sm">
                            <option value="all" <?php echo $filterCourtId === 'all' ? 'selected' : ''; ?>>All Courts</option>
                            <?php foreach ($courtsDropdownList as $courtItem): ?>
                                <option value="<?php echo $courtItem['court_id']; ?>" <?php echo (string)$filterCourtId === (string)$courtItem['court_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($courtItem['court_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label small fw-semibold text-secondary mb-1">Report Type</label>
                        <select name="report_type" class="form-select form-select-sm shadow-sm">
                            <option value="all" <?php echo $reportType === 'all' ? 'selected' : ''; ?>>All Reports</option>
                            <option value="completed" <?php echo $reportType === 'completed' ? 'selected' : ''; ?>>Completed Only</option>
                            <option value="cancelled" <?php echo $reportType === 'cancelled' ? 'selected' : ''; ?>>Cancelled Only</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-3 text-md-end">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-sm btn-primary w-100 py-2 shadow-sm border-0 d-inline-flex align-items-center justify-content-center gap-2 reports-action-btn" style="background-color: #2b1b54; font-weight: 500; border-radius: 6px;">
                                <i class="bi bi-bar-chart-fill"></i> Generate Report
                            </button>
                            <button type="button" class="btn btn-sm btn-light rounded-3 px-3 py-2 fw-medium border text-secondary shadow-sm" onclick="window.print()">
                                <i class="bi bi-download"></i> Export
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            <div class="row g-4 mb-4">
                <div class="col-lg-7">
                    <div class="card border-0 rounded-3 shadow-sm h-100">
                        <div class="card-header bg-white border-bottom-0 p-4 pb-0 text-start">
                            <h6 class="fw-bold text-dark mb-0 fs-6">Bookings Overview</h6>
                        </div>
                        <div class="card-body p-4">
                            <div class="chart-container bar-chart-container">
                                <canvas id="bookingsBarChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="card border-0 rounded-3 shadow-sm h-100">
                        <div class="card-header bg-white border-bottom-0 p-4 pb-0 text-start">
                            <h6 class="fw-bold text-dark mb-0 fs-6">Bookings by Court</h6>
                        </div>
                        <div class="card-body p-4 d-flex align-items-center justify-content-center">
                            <div class="chart-container doughnut-chart-container">
                                <canvas id="courtDoughnutChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="desktop-table-view card border-0 rounded-3 shadow-sm mb-4 overflow-hidden">
                <div class="table-responsive custom-data-table border-0 text-start">
                    <table class="table table-borderless mb-0 align-middle">
                        <thead>
                            <tr>
                                <th class="ps-3 ps-sm-4 text-start">Court Name</th>
                                <th class="text-center">Total Bookings</th>
                                <th class="text-center">Completed / Active</th>
                                <th class="text-center">Cancelled</th>
                                <th class="pe-3 pe-sm-4 text-center">Total Hours</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $sumTotal = 0; $sumCompleted = 0; $sumCancelled = 0; $sumHours = 0;
                            foreach ($courtSummary as $row): 
                                $courtHours = $row['total_bookings'] * 2;
                                $sumTotal += $row['total_bookings'];
                                $sumCompleted += $row['completed'];
                                $sumCancelled += $row['cancelled'];
                                $sumHours += $courtHours;
                                
                                $icon = "bi-dribbble text-secondary";
                                if(stripos($row['court_name'], 'petanque') !== false) $icon = "bi-record-circle text-primary";
                                if(stripos($row['court_name'], 'futsal') !== false) $icon = "bi-dribbble text-success";
                                if(stripos($row['court_name'], 'takraw') !== false) $icon = "bi-basket text-warning";
                                if(stripos($row['court_name'], 'volleyball') !== false) $icon = "bi-globe text-info";
                            ?>
                            <tr>
                                <td class="ps-3 ps-sm-4 text-start">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="icon-circle bg-light d-flex align-items-center justify-content-center">
                                            <i class="bi <?php echo $icon; ?>"></i>
                                        </div>
                                        <span class="fw-semibold text-dark"><?php echo htmlspecialchars($row['court_name']); ?></span>
                                    </div>
                                </td>
                                <td class="text-center text-dark fw-medium"><?php echo $row['total_bookings']; ?></td>
                                <td class="text-center text-secondary"><?php echo $row['completed']; ?></td>
                                <td class="text-center text-secondary"><?php echo $row['cancelled']; ?></td>
                                <td class="text-center text-dark fw-semibold pe-3 pe-sm-4"><?php echo $courtHours; ?> hrs</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="bg-light fw-bold text-dark">
                                <td class="ps-3 ps-sm-4 text-start">Summary </td>
                                <td class="text-center"><?php echo $sumTotal; ?></td>
                                <td class="text-center"><?php echo $sumCompleted; ?></td>
                                <td class="text-center"><?php echo $sumCancelled; ?></td>
                                <td class="text-center pe-3 pe-sm-4"><?php echo $sumHours; ?> hrs</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="mobile-cards-view mb-4 text-start">
                <h5 class="fw-bold text-dark mb-3 px-1">Court Metrics Details</h5>
                <?php 
                $sumTotalMob = 0; $sumCompletedMob = 0; $sumCancelledMob = 0; $sumHoursMob = 0;
                foreach ($courtSummary as $row): 
                    $courtHoursMob = $row['total_bookings'] * 2;
                    $sumTotalMob += $row['total_bookings'];
                    $sumCompletedMob += $row['completed'];
                    $sumCancelledMob += $row['cancelled'];
                    $sumHoursMob += $courtHoursMob;
                ?>
                <div class="card border-0 shadow-sm rounded-3 mb-3 p-3 bg-white border-start border-4 border-primary">
                    <div class="fw-bold text-dark fs-6 mb-2"><?php echo htmlspecialchars($row['court_name']); ?></div>
                    <div class="row g-2 text-center text-sm-start">
                        <div class="col-6">
                            <span class="text-muted d-block small" style="font-size:0.72rem;">Total Bookings</span>
                            <span class="fw-semibold text-dark"><?php echo $row['total_bookings']; ?></span>
                        </div>
                        <div class="col-6">
                            <span class="text-muted d-block small" style="font-size:0.72rem;">Completed / Active</span>
                            <span class="fw-semibold text-success"><?php echo $row['completed']; ?></span>
                        </div>
                        <div class="col-6 mt-2">
                            <span class="text-muted d-block small" style="font-size:0.72rem;">Cancelled</span>
                            <span class="fw-semibold text-danger"><?php echo $row['cancelled']; ?></span>
                        </div>
                        <div class="col-6 mt-2">
                            <span class="text-muted d-block small" style="font-size:0.72rem;">Total Hours</span>
                            <span class="fw-bold text-dark"><?php echo $courtHoursMob; ?> hrs</span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <div class="card border-0 shadow-sm rounded-3 p-3 bg-dark text-white">
                    <div class="fw-bold tracking-wide small mb-2 text-uppercase text-warning">Summary </div>
                    <div class="row g-2 text-center">
                        <div class="col-3">
                            <span class="text-white-50 d-block small" style="font-size:0.65rem;">Bookings</span>
                            <span class="fw-bold fs-6"><?php echo $sumTotalMob; ?></span>
                        </div>
                        <div class="col-3">
                            <span class="text-white-50 d-block small" style="font-size:0.65rem;">Completed</span>
                            <span class="fw-bold fs-6"><?php echo $sumCompletedMob; ?></span>
                        </div>
                        <div class="col-3">
                            <span class="text-white-50 d-block small" style="font-size:0.65rem;">Cancel</span>
                            <span class="fw-bold fs-6"><?php echo $sumCancelledMob; ?></span>
                        </div>
                        <div class="col-3">
                            <span class="text-white-50 d-block small" style="font-size:0.65rem;">Hours</span>
                            <span class="fw-bold fs-6 text-warning"><?php echo $sumHoursMob; ?>h</span>
                        </div>
                    </div>
                </div>
            </div>
            
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            flatpickr("#dateRangePicker", {
                mode: "range",
                dateFormat: "d/m/Y", 
                maxDate: "today"
            });
        });

        const centerTextPlugin = {
            id: 'centerText',
            beforeDraw: function(chart) {
                if (chart.config.type !== 'doughnut') return;
                var ctx = chart.ctx;
                ctx.restore();
                
                const meta = chart.getDatasetMeta(0);
                if (!meta.data || !meta.data[0]) return;
                const center = meta.data[0];
                const centerX = center.x;
                const centerY = center.y;

                var chartHeight = chart.chartArea.bottom - chart.chartArea.top;
                
                // UX Scale Optimization: Detect mobile view widths to dynamically scale text inside the doughnut center
                var isMobileViewport = window.innerWidth < 576;
                var baseScaleFactor = isMobileViewport ? 165 : 110;
                var fontSize = (chartHeight / baseScaleFactor).toFixed(2);
                
                ctx.font = "bold " + fontSize + "em Poppins";
                ctx.textBaseline = "middle";
                ctx.fillStyle = "#1e293b"; 

                var text = "<?php echo $totalBookings; ?>",
                    textX = Math.round(centerX - (ctx.measureText(text).width / 2)),
                    textY = isMobileViewport ? centerY - 6 : centerY - 10;
                ctx.fillText(text, textX, textY);
                
                ctx.font = "normal " + (fontSize * (isMobileViewport ? 0.45 : 0.4)) + "em Poppins";
                ctx.fillStyle = "#64748b";
                var subText = "Total Bookings",
                    subTextX = Math.round(centerX - (ctx.measureText(subText).width / 2)),
                    subTextY = isMobileViewport ? centerY + 12 : centerY + 15;
                ctx.fillText(subText, subTextX, subTextY);
                ctx.save();
            }
        };

        Chart.register(centerTextPlugin);

        const barCtx = document.getElementById('bookingsBarChart').getContext('2d');
        new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($barLabels); ?>,
                datasets: [
                    {
                        label: 'Total Bookings',
                        data: <?php echo json_encode($barTotal); ?>,
                        backgroundColor: '#4f46e5',
                        borderRadius: 4
                    },
                    {
                        label: 'Completed / Active',
                        data: <?php echo json_encode($barCompleted); ?>,
                        backgroundColor: '#10b981',
                        borderRadius: 4
                    },
                    {
                        label: 'Cancelled',
                        data: <?php echo json_encode($barCancelled); ?>,
                        backgroundColor: '#ef4444',
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            boxWidth: 8,   
                            boxHeight: 8,  
                            font: { family: 'Poppins', size: 11 }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'Poppins', size: 10 } }
                    },
                    y: {
                        beginAtZero: true,
                        border: { display: false },
                        grid: { color: '#f1f5f9' },
                        ticks: { stepSize: 5, font: { family: 'Poppins', size: 10 } }
                    }
                }
            }
        });

        const doughnutCtx = document.getElementById('courtDoughnutChart').getContext('2d');
        const courtData = <?php echo json_encode($doughnutData); ?>;
        const bgColors = ['#a855f7', '#3b82f6', '#f59e0b', '#22c55e', '#ec4899']; 

        new Chart(doughnutCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($doughnutLabels); ?>,
                datasets: [{
                    data: courtData.length ? courtData : [1], 
                    backgroundColor: courtData.length ? bgColors : ['#e2e8f0'],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '75%',
                plugins: {
                    legend: {
                        // Drop legend cleanly to the bottom layout line on mobile screens to prevent text cutting
                        position: window.innerWidth < 576 ? 'bottom' : 'right',
                        labels: {
                            usePointStyle: true,
                            boxWidth: 10,  
                            boxHeight: 10,
                            padding: window.innerWidth < 576 ? 12 : 15,
                            font: { family: 'Poppins', size: 11 },
                            generateLabels: function(chart) {
                                const data = chart.data;
                                if (data.labels.length && data.datasets.length && courtData.length) {
                                    return data.labels.map((label, i) => {
                                        const value = data.datasets[0].data[i];
                                        return {
                                            text: `${label} (${value})`,
                                            fillStyle: data.datasets[0].backgroundColor[i],
                                            hidden: false,
                                            index: i,
                                            pointStyle: 'circle'
                                        };
                                    });
                                }
                                return [{ text: 'No Records Found', fillStyle: '#e2e8f0', pointStyle: 'circle' }];
                            }
                        }
                    }
                },
                layout: {
                    padding: window.innerWidth < 576 
                        ? { left: 10, right: 10, top: 0, bottom: 0 }
                        : { left: 0, right: 10, top: 10, bottom: 10 }
                }
            }
        });

        document.getElementById('logoutBtn').addEventListener('click', function() {
            localStorage.removeItem('userRole');
            localStorage.removeItem('loggedInUser');
        });
    </script>
</body>
</html>