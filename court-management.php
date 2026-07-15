<?php
session_start();
require_once('db.php');

// 1. Session Access Control (Server-Side Authorization matching admin-booking.php)
if (!isset($_SESSION['userRole']) || strtolower($_SESSION['userRole']) !== 'admin') {
    header("Location: login.php");
    exit();
}

$adminName = $_SESSION['loggedInUser'] ?? 'System Administrator';
$successAlert = "";
$errorAlert = "";

// 2. Process Status Updates, Registration, or Deletions from Admin Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_court') {
        $courtName = trim($_POST['court_name'] ?? '');
        $courtType = trim($_POST['court_type'] ?? '');
        $location = trim($_POST['location'] ?? 'UiTM Kampus Kuala Terengganu');
        $capacity = intval($_POST['capacity'] ?? 12);
        $status = trim($_POST['status'] ?? 'Active');

        // Store structured meta inside the location field to match default DB schema safely
        $fullLocationPayload = "{$location} | Capacity: {$capacity} | Status: {$status}";

        if (!empty($courtName) && !empty($courtType)) {
            try {
                $insertStmt = $pdo->prepare("INSERT INTO COURT (court_name, court_type, location) VALUES (?, ?, ?)");
                $insertStmt->execute([$courtName, $courtType, $fullLocationPayload]);
                $successAlert = "Court asset '{$courtName}' registered successfully.";
            } catch (PDOException $e) {
                $errorAlert = "Database asset addition failure: " . $e->getMessage();
            }
        } else {
            $errorAlert = "All fields are required to register a court asset.";
        }
    } elseif ($_POST['action'] === 'edit_court') {
        $courtId = intval($_POST['court_id'] ?? 0);
        $courtName = trim($_POST['court_name'] ?? '');
        $courtType = trim($_POST['court_type'] ?? '');
        $location = trim($_POST['location'] ?? 'UiTM Kampus Kuala Terengganu');
        $capacity = intval($_POST['capacity'] ?? 12);
        $status = trim($_POST['status'] ?? 'Active');

        $fullLocationPayload = "{$location} | Capacity: {$capacity} | Status: {$status}";

        if ($courtId > 0 && !empty($courtName) && !empty($courtType)) {
            try {
                $updateStmt = $pdo->prepare("UPDATE COURT SET court_name = ?, court_type = ?, location = ? WHERE court_id = ?");
                $updateStmt->execute([$courtName, $courtType, $fullLocationPayload, $courtId]);
                $successAlert = "Court asset #CRT-{$courtId} updated successfully.";
            } catch (PDOException $e) {
                $errorAlert = "Database asset update failure: " . $e->getMessage();
            }
        } else {
            $errorAlert = "Invalid data submitted for updating court asset.";
        }
    } elseif ($_POST['action'] === 'delete_court') {
        $targetCourtId = intval($_POST['court_id'] ?? 0);
        if ($targetCourtId > 0) {
            try {
                // Delete associated bookings first to prevent foreign key constraint violations
                $deleteBookings = $pdo->prepare("DELETE FROM BOOKING WHERE court_id = ?");
                $deleteBookings->execute([$targetCourtId]);

                $deleteStmt = $pdo->prepare("DELETE FROM COURT WHERE court_id = ?");
                $deleteStmt->execute([$targetCourtId]);
                $successAlert = "Court #CRT-{$targetCourtId} and associated bookings removed successfully.";
            } catch (PDOException $e) {
                $errorAlert = "Database deletion failure: " . $e->getMessage();
            }
        }
    }
}

// 3. Fetch Master Infrastructure List directly from the live database
$masterCourtsArray = [];
try {
    $fetchStmt = $pdo->query("SELECT court_id, court_name, court_type, location FROM COURT ORDER BY court_id ASC");
    while ($row = $fetchStmt->fetch(PDO::FETCH_ASSOC)) {
        $type = $row['court_type'] ?? 'Futsal';
        $rawLocation = $row['location'] ?? 'UiTM Kampus Kuala Terengganu';
        
        $parsedLocation = "UiTM Kampus Kuala Terengganu";
        $parsedCapacity = "12";
        $parsedStatus = "Active";

        // Parse structured location payload safely if exists
        if (strpos($rawLocation, '|') !== false) {
            $parts = explode('|', $rawLocation);
            $parsedLocation = trim($parts[0] ?? 'UiTM Kampus Kuala Terengganu');
            
            $capPart = trim($parts[1] ?? 'Capacity: 12');
            $parsedCapacity = trim(str_replace('Capacity:', '', $capPart));
            
            $statPart = trim($parts[2] ?? 'Status: Active');
            $parsedStatus = trim(str_replace('Status:', '', $statPart));
        } else {
            if (stripos($type, 'Petanque') !== false) { $parsedCapacity = "12"; }
            elseif (stripos($type, 'Futsal') !== false) { $parsedCapacity = "16"; }
            elseif (stripos($type, 'Takraw') !== false) { $parsedCapacity = "6"; }
        }

        $masterCourtsArray[] = [
            'id' => $row['court_id'],
            'court_name' => $row['court_name'] ?? 'Unknown Court',
            'court_type' => $type,
            'location' => $parsedLocation,
            'capacity' => $parsedCapacity,
            'status' => ucfirst(strtolower($parsedStatus))
        ];
    }
} catch (PDOException $e) {
    $errorAlert = "Database Registry Loading Failure: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Courts Management - UiTM Court Booking System</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    
    <style>
        /* ==========================================================================
           1. Base Setup & Layout Elements (Aligned with admin-booking.php)
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
            color: #ffd700 !important; /* UiTM Distinct Gold/Yellow */
            background-color: rgba(255, 255, 255, 0.15);
            font-weight: 600;
        }

        .sidebar-brand-showcase img {
            filter: drop-shadow(0px 2px 4px rgba(0, 0, 0, 0.25));
        }

        .text-muted-small { color: #6c757d; font-size: 0.72rem; }

        /* ==========================================================================
           3. Data Grid Table & Filter Component Styling (Matched exactly)
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
            padding: 18px 24px;
            font-size: 0.88rem;
            color: #333333;
            vertical-align: middle;
        }

        /* Status Badges */
        .status-pill-badge {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 6px;
            display: inline-block;
            text-transform: capitalize;
        }
        .badge-status-confirmed { background-color: #e6f6ec; color: #15803d; }
        .badge-status-pending { background-color: #ffedd5; color: #9a3412; }
        .badge-status-complete { background-color: #dbeafe; color: #1e40af; }
        .badge-status-cancelled { background-color: #fee2e2; color: #991b1b; }

        .btn-action-delete {
            background: none;
            border: none;
            color: #ef4444;
            font-size: 1.15rem;
            padding: 6px 10px;
            border-radius: 6px;
            transition: all 0.2s;
        }
        .btn-action-delete:hover {
            background-color: #fee2e2;
        }

        .font-monospace-id {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            color: #64748b;
            font-size: 0.82rem;
        }

        /* ==========================================================================
           4. Mobile-Responsive Framework Layout Override Engines
           ========================================================================== */
        #mobileBookingsCardDeck {
            display: none;
        }

        @media (max-width: 991.98px) {
            .sidebar-navigation { width: 280px; }
            .sidebar-navigation:not(.show) { visibility: hidden; }
            
            /* Hide traditional table grid view on smaller screens */
            .desktop-table-container {
                display: none !important;
            }
            
            /* Enable responsive layout deck framework */
            #mobileBookingsCardDeck {
                display: block;
            }
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
                    <a class="nav-link active" href="court-management.php">
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

        <main class="flex-grow-1 workspace-content p-3 p-sm-4 p-lg-5 bg-light overflow-y-auto">
            
            <header class="d-flex justify-content-between align-items-center gap-3 mb-4">
                <div class="d-flex align-items-center gap-3">
                    <button class="btn btn-outline-dark d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu">
                        <i class="bi bi-list fs-4"></i>
                    </button>
                    
                    <div class="text-start">
                        <h2 class="fw-bold tracking-tight text-dark mb-1 fs-3 fs-sm-2">Courts Management</h2>
                        <p class="text-muted small mb-0 d-none d-sm-block">Manage registered sporting structures, availability status updates, capacity limits, and records.</p>
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

            <?php if (!empty($successAlert)): ?>
                <div class="alert alert-success alert-dismissible fade show small mb-4 text-start" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i><?php echo htmlspecialchars($successAlert); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($errorAlert)): ?>
                <div class="alert alert-danger alert-dismissible fade show small mb-4 text-start" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($errorAlert); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="bg-white p-4 rounded-3 border border-light-subtle shadow-sm mb-4 text-start">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-3">
                        <label class="form-label small fw-semibold text-secondary mb-1">Search Court</label>
                        <div class="input-group input-group-sm shadow-sm">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-search small text-muted"></i></span>
                            <input type="text" class="form-control border-start-0 shadow-none" id="tableSearchEngine" placeholder="Search court name or type...">
                        </div>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label small fw-semibold text-secondary mb-1">Sport Category</label>
                        <select class="form-select form-select-sm shadow-sm" id="tableCourtFilter">
                            <option value="all">All Sports</option>
                            <option value="Petanque">Petanque</option>
                            <option value="Futsal">Futsal</option>
                            <option value="Takraw">Takraw</option>
                            <option value="Volleyball">Volleyball</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label small fw-semibold text-secondary mb-1">Court Status</label>
                        <select class="form-select form-select-sm shadow-sm" id="tableStatusFilter">
                            <option value="all">All Statuses</option>
                            <option value="Active">Active (Operational)</option>
                            <option value="Maintenance">Maintenance</option>
                            <option value="Inactive">Inactive (Offline)</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-3 text-md-end">
                        <button type="button" class="btn btn-sm btn-primary w-100 py-2 shadow-sm border-0 d-inline-flex align-items-center justify-content-center gap-2" style="background-color: #2b1b54; font-weight: 500; border-radius: 6px;" data-bs-toggle="modal" data-bs-target="#addCourtModal">
                            <i class="bi bi-plus-lg"></i> Add New Court
                        </button>
                    </div>
                </div>
            </div>

            <div class="table-responsive custom-data-table desktop-table-container text-start mb-4 shadow-sm">
                <table class="table table-borderless mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Court ID</th>
                            <th>Court Details</th>
                            <th>Classification</th>
                            <th>Location Details</th>
                            <th>Capacity Limit</th>
                            <th>Status Flag</th>
                            <th>Actions Control</th>
                        </tr>
                    </thead>
                    <tbody id="courtsRenderMasterGrid">
                        </tbody>
                </table>
            </div>

            <div id="mobileBookingsCardDeck" class="text-start mb-4">
                </div>

        </main>
    </div>

    <div class="modal fade" id="addCourtModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow-lg text-start">
                <div class="modal-header border-bottom-0 p-4 pb-2">
                    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-plus-circle-fill me-2" style="color: #2b1b54;"></i>Register Court Asset</h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="court-management.php">
                    <input type="hidden" name="action" value="add_court">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Court Asset Name</label>
                            <input type="text" class="form-control shadow-none rounded-3 py-2" name="court_name" placeholder="e.g. Futsal Court C" required style="border-color: rgba(0,0,0,0.08);">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Classification Category</label>
                            <select class="form-select shadow-none rounded-3 py-2" name="court_type" required style="border-color: rgba(0,0,0,0.08);">
                                <option value="" selected disabled>Select Category...</option>
                                <option value="Petanque">Petanque</option>
                                <option value="Futsal">Futsal</option>
                                <option value="Takraw">Takraw</option>
                                <option value="Volleyball">Volleyball</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Location Area</label>
                            <input type="text" class="form-control shadow-none rounded-3 py-2" name="location" value="UiTM Kampus Kuala Terengganu" required style="border-color: rgba(0,0,0,0.08);">
                        </div>
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label small fw-semibold text-secondary">Max Capacity Limit</label>
                                <input type="number" class="form-control shadow-none rounded-3 py-2" name="capacity" value="12" required style="border-color: rgba(0,0,0,0.08);">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold text-secondary">Operational Status</label>
                                <select class="form-select shadow-none rounded-3 py-2" name="status" style="border-color: rgba(0,0,0,0.08);">
                                    <option value="Active">Active</option>
                                    <option value="Maintenance">Maintenance</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top-0 p-4 pt-0">
                        <button type="button" class="btn btn-light rounded-3 px-3 py-2 fw-medium border text-secondary shadow-none" data-bs-dismiss="modal" style="border-color: rgba(0,0,0,0.08);">Cancel</button>
                        <button type="submit" class="btn btn-primary rounded-3 px-4 py-2 fw-medium border-0 shadow-none" style="background-color: #2b1b54;">Register Asset</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editCourtModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow-lg text-start">
                <div class="modal-header border-bottom-0 p-4 pb-2">
                    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-pencil-square me-2" style="color: #2b1b54;"></i>Modify Court Details</h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="court-management.php">
                    <input type="hidden" name="action" value="edit_court">
                    <input type="hidden" name="court_id" id="editCourtId">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Court Asset Name</label>
                            <input type="text" class="form-control shadow-none rounded-3 py-2" id="editCourtName" name="court_name" required style="border-color: rgba(0,0,0,0.08);">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Classification Category</label>
                            <select class="form-select shadow-none rounded-3 py-2" id="editCourtType" name="court_type" required style="border-color: rgba(0,0,0,0.08);">
                                <option value="Petanque">Petanque</option>
                                <option value="Futsal">Futsal</option>
                                <option value="Takraw">Takraw</option>
                                <option value="Volleyball">Volleyball</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-secondary">Location Area</label>
                            <input type="text" class="form-control shadow-none rounded-3 py-2" id="editCourtLocation" name="location" required style="border-color: rgba(0,0,0,0.08);">
                        </div>
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label small fw-semibold text-secondary">Max Capacity Limit</label>
                                <input type="number" class="form-control shadow-none rounded-3 py-2" id="editCourtCapacity" name="capacity" required style="border-color: rgba(0,0,0,0.08);">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold text-secondary">Operational Status</label>
                                <select class="form-select shadow-none rounded-3 py-2" id="editCourtStatus" name="status" style="border-color: rgba(0,0,0,0.08);">
                                    <option value="Active">Active</option>
                                    <option value="Maintenance">Maintenance</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top-0 p-4 pt-0">
                        <button type="button" class="btn btn-light rounded-3 px-3 py-2 fw-medium border text-secondary shadow-none" data-bs-dismiss="modal" style="border-color: rgba(0,0,0,0.08);">Discard</button>
                        <button type="submit" class="btn btn-primary rounded-3 px-4 py-2 fw-medium border-0 shadow-none" style="background-color: #2b1b54;">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <form id="hiddenAdminActionForm" method="POST" action="court-management.php" style="display: none;">
        <input type="hidden" name="action" id="hiddenFormActionInput">
        <input type="hidden" name="court_id" id="hiddenFormIdInput">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const masterCourtsArray = <?php echo json_encode($masterCourtsArray, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

            const searchFieldDOM = document.getElementById('tableSearchEngine');
            const courtFilterDOM = document.getElementById('tableCourtFilter');
            const statusFilterDOM = document.getElementById('tableStatusFilter');
            
            const tbodyWrapperDOM = document.getElementById('courtsRenderMasterGrid');
            const mobileDeckWrapperDOM = document.getElementById('mobileBookingsCardDeck');

            function updateCourtsTableGrid() {
                const searchString = searchFieldDOM.value.toLowerCase().trim();
                const selectedCourt = courtFilterDOM.value;
                const selectedStatus = statusFilterDOM.value;

                let itemsList = masterCourtsArray.filter(c => {
                    const nameStr = c.court_name ? c.court_name : '';
                    const typeStr = c.court_type ? c.court_type : '';
                    const idStr = c.id ? c.id : '';
                    const statusStr = c.status ? c.status : 'Active';

                    const searchTarget = `${nameStr} ${typeStr} #CRT-${idStr}`.toLowerCase();
                    if (searchString && !searchTarget.includes(searchString)) return false;
                    
                    if (selectedCourt !== 'all' && !typeStr.toLowerCase().includes(selectedCourt.toLowerCase())) return false;
                    if (selectedStatus !== 'all' && statusStr.toLowerCase() !== selectedStatus.toLowerCase()) return false;

                    return true;
                });

                // Clear previous view traces
                tbodyWrapperDOM.innerHTML = "";
                mobileDeckWrapperDOM.innerHTML = "";

                if (itemsList.length === 0) {
                    const emptyTemplate = `
                        <div class="text-center text-muted py-5 small bg-white border rounded-3 shadow-sm">
                            <i class="bi bi-building-x d-block fs-3 mb-2 text-secondary"></i> No matching records found inside live database tables.
                        </div>`;
                    
                    tbodyWrapperDOM.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-5 small"><i class="bi bi-building-x d-block fs-3 mb-2 text-secondary"></i> No matching records found inside live database tables.</td></tr>`;
                    mobileDeckWrapperDOM.innerHTML = emptyTemplate;
                    return;
                }

                itemsList.forEach(court => {
                    const cStatus = court.status ? court.status : 'Active';
                    
                    // Match visual status badges with bookings panel aesthetics
                    let pillClass = "status-pill-badge badge-status-confirmed"; // Active
                    if (cStatus === 'Maintenance') pillClass = "status-pill-badge badge-status-pending";
                    if (cStatus === 'Inactive') pillClass = "status-pill-badge badge-status-cancelled";

                    // Controls match the action systems
                    const contextActionButtons = `
                        <button type="button" class="btn btn-sm btn-primary px-2 py-1 shadow-sm border-0 d-inline-flex align-items-center gap-1" style="font-size:0.75rem; border-radius:6px; font-weight:500; background-color: #2b1b54;" onclick="triggerEdit(${court.id})">
                            <i class="bi bi-pencil-square"></i> Edit
                        </button>
                    `;

                    // 1. Desktop Row Injection Engine
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td class="font-monospace-id">#CRT-${court.id}</td>
                        <td>
                            <div class="fw-semibold text-dark mb-0" style="font-size:0.88rem;">${court.court_name}</div>
                            <span class="text-muted" style="font-size: 0.76rem;">UiTM Facility Asset</span>
                        </td>
                        <td class="fw-medium text-dark" style="font-size:0.88rem;">${court.court_type}</td>
                        <td class="text-secondary" style="font-size:0.85rem;"><i class="bi bi-geo-alt me-1 text-muted"></i> ${court.location}</td>
                        <td class="text-dark fw-semibold" style="font-size:0.85rem;"><i class="bi bi-people me-1 text-muted"></i> ${court.capacity} pax</td>
                        <td><span class="${pillClass}">${cStatus}</span></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                ${contextActionButtons}
                                <button type="button" class="btn-action-delete" onclick="deleteCourtRecord(${court.id})">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </div>
                        </td>
                    `;
                    tbodyWrapperDOM.appendChild(tr);

                    // 2. Mobile Dynamic Display Component Generation Engine
                    const mobileCardItem = document.createElement('div');
                    mobileCardItem.className = "bg-white p-3 border rounded-3 shadow-sm mb-3";
                    mobileCardItem.innerHTML = `
                        <div class="d-flex justify-content-between align-items-start border-bottom pb-2 mb-2">
                            <div>
                                <span class="font-monospace-id d-block" style="font-size:0.8rem;">#CRT-${court.id}</span>
                                <span class="fw-bold text-dark d-block" style="font-size:0.95rem;">${court.court_name}</span>
                            </div>
                            <span class="${pillClass}">${cStatus}</span>
                        </div>
                        <div class="mb-2" style="font-size:0.85rem;">
                            <div class="text-dark fw-medium"><i class="bi bi-grid-1x2 me-2 text-muted"></i>Classification: ${court.court_type}</div>
                            <div class="text-muted ms-4" style="font-size:0.78rem;">Capacity: ${court.capacity} pax</div>
                        </div>
                        <div class="mb-3" style="font-size:0.82rem; color:#475569;">
                            <div><i class="bi bi-geo-alt me-2 text-muted"></i>${court.location}</div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center border-top pt-2">
                            <div class="d-flex gap-2">
                                ${contextActionButtons}
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger px-2 border-0" onclick="deleteCourtRecord(${court.id})" style="font-size: 1rem;">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </div>
                    `;
                    mobileDeckWrapperDOM.appendChild(mobileCardItem);
                });
            }

            window.triggerEdit = function(id) {
                const targetCourt = masterCourtsArray.find(c => c.id == id);
                if (targetCourt) {
                    document.getElementById('editCourtId').value = targetCourt.id;
                    document.getElementById('editCourtName').value = targetCourt.court_name;
                    document.getElementById('editCourtType').value = targetCourt.court_type;
                    document.getElementById('editCourtLocation').value = targetCourt.location;
                    document.getElementById('editCourtCapacity').value = targetCourt.capacity;
                    document.getElementById('editCourtStatus').value = targetCourt.status;
                    
                    const modalObj = new bootstrap.Modal(document.getElementById('editCourtModal'));
                    modalObj.show();
                }
            };

            window.deleteCourtRecord = function(courtId) {
                if (confirm(`Are you absolutely sure you want to permanently delete court record #CRT-${courtId}?\n\nWARNING: Doing so will automatically clear all student booking logs referencing this court inside the system database.`)) {
                    document.getElementById('hiddenFormActionInput').value = 'delete_court';
                    document.getElementById('hiddenFormIdInput').value = courtId;
                    document.getElementById('hiddenAdminActionForm').submit();
                }
            };

            searchFieldDOM.addEventListener('input', updateCourtsTableGrid);
            courtFilterDOM.addEventListener('change', updateCourtsTableGrid);
            statusFilterDOM.addEventListener('change', updateCourtsTableGrid);

            document.getElementById('logoutBtn').addEventListener('click', function() {
                localStorage.removeItem('userRole');
                localStorage.removeItem('loggedInUser');
            });

            updateCourtsTableGrid();
        });
    </script>
</body>
</html>