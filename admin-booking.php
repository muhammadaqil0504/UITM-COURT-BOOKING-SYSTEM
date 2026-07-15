<?php
session_start();
require_once('db.php');

// 1. Session Access Control (Server-Side Authorization)[cite: 6]
if (!isset($_SESSION['userRole']) || strtolower($_SESSION['userRole']) !== 'admin') {
    header("Location: login.php");
    exit();
}

$adminName = $_SESSION['loggedInUser'] ?? 'System Administrator';
$successAlert = "";
$errorAlert = "";

// AUTOMATION: Automatically move past bookings to 'Complete' status on load[cite: 6]
try {
    $autoCompleteStmt = $pdo->prepare("
        UPDATE BOOKING 
        SET status = 'Complete' 
        WHERE (LOWER(status) = 'approved' OR LOWER(status) = 'confirmed') 
          AND booking_date < CURDATE()
    ");
    $autoCompleteStmt->execute();
} catch (PDOException $e) {
    error_log("Automation error updating past bookings: " . $e->getMessage());
}

// 2. Process Status Updates or Deletions from Admin Actions[cite: 6]
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $targetBookingId = intval($_POST['booking_id'] ?? 0);
    
    if ($targetBookingId > 0) {
        try {
            if ($_POST['action'] === 'update_status') {
                $newStatus = trim($_POST['status'] ?? '');
                $allowedStatuses = ['Pending', 'Confirmed', 'Approved', 'Complete', 'Cancelled', 'Rejected'];
                
                if (in_array($newStatus, $allowedStatuses)) {
                    $updateStmt = $pdo->prepare("UPDATE BOOKING SET status = ? WHERE booking_id = ?");
                    $updateStmt->execute([$newStatus, $targetBookingId]);
                    $successAlert = "Booking #BK-{$targetBookingId} updated to " . htmlspecialchars($newStatus) . " successfully.";
                } else {
                    $errorAlert = "Invalid status modification requested.";
                }
            } elseif ($_POST['action'] === 'delete_booking') {
                $deleteStmt = $pdo->prepare("DELETE FROM BOOKING WHERE booking_id = ?");
                $deleteStmt->execute([$targetBookingId]);
                $successAlert = "Booking #BK-{$targetBookingId} removed permanently from registry.";
            }
        } catch (PDOException $e) {
            $errorAlert = "Database change failure: " . $e->getMessage();
        }
    }
}

// 3. Fetch Master Registry Array directly from the live database[cite: 6]
$masterBookingsArray = [];
try {
    $fetchStmt = $pdo->query("
        SELECT 
            b.booking_id AS id, 
            IFNULL(c.court_name, 'Unknown Court') AS court, 
            b.booking_date AS date, 
            b.time_slot AS timeSlot, 
            b.status AS status,
            IFNULL(u.full_name, 'Unknown User') AS bookedBy,
            IFNULL(u.email, '') AS userEmail
        FROM BOOKING b
        LEFT JOIN COURT c ON b.court_id = c.court_id
        LEFT JOIN USER u ON b.user_id = u.user_id
        ORDER BY b.booking_date DESC, b.time_slot DESC
    ");
    
    while ($row = $fetchStmt->fetch(PDO::FETCH_ASSOC)) {
        $masterBookingsArray[] = [
            'id' => $row['id'],
            'court' => $row['court'],
            'date' => $row['date'],
            'timeSlot' => $row['timeSlot'],
            'status' => ucfirst($row['status'] ?? 'Pending'),
            'bookedBy' => $row['bookedBy'],
            'userEmail' => $row['userEmail']
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
    <title>Bookings Management - UiTM Court Booking System</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    
    <style>
        /* ==========================================================================
           1. Base Setup & Layout Elements (Aligned with Dashboard)
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
           3. Data Grid Table & Filter Component Styling
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

        /* Responsive Breakpoint Adaptations */
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
        
        <!-- RESPONSIVE NAV DRAWER: Aligned to Dashboard Layout -->
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
                    <a class="nav-link active" href="admin-booking.php">
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
            
            <!-- TOOLBAR NAV HEADER WITH HAMBURGER TOGGLE -->
            <header class="d-flex justify-content-between align-items-center gap-3 mb-4">
                <div class="d-flex align-items-center gap-3">
                    <button class="btn btn-outline-dark d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu">
                        <i class="bi bi-list fs-4"></i>
                    </button>
                    
                    <div class="text-start">
                        <h2 class="fw-bold tracking-tight text-dark mb-1 fs-3 fs-sm-2">Bookings Queue</h2>
                        <p class="text-muted small mb-0 d-none d-sm-block">Monitor live schedules, update reservation queues, and approve or reject database records.</p>
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

            <!-- STATUS MESSAGES -->
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

            <!-- DASHBOARD MATCHED FILTERS GRID -->
            <div class="bg-white p-4 rounded-3 border border-light-subtle shadow-sm mb-4 text-start">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-semibold text-secondary mb-1">Search Booking</label>
                        <div class="input-group input-group-sm shadow-sm">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-search small text-muted"></i></span>
                            <input type="text" class="form-control border-start-0 shadow-none" id="tableSearchEngine" placeholder="Search student name or court...">
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-semibold text-secondary mb-1">Court Category</label>
                        <select class="form-select form-select-sm shadow-sm" id="tableCourtFilter">
                            <option value="all">All Courts</option>
                            <option value="Petanque">Petanque Court</option>
                            <option value="Futsal">Futsal Court</option>
                            <option value="Takraw">Takraw Court</option>
                            <option value="Volleyball">Volleyball Court</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small fw-semibold text-secondary mb-1">Quick Status Filter</label>
                        <select class="form-select form-select-sm shadow-sm" id="tableStatusFilter">
                            <option value="all">All Status Queues</option>
                            <option value="Pending">Pending</option>
                            <option value="Confirmed">Confirmed</option>
                            <option value="Approved">Approved</option>
                            <option value="Complete">Complete</option>
                            <option value="Cancelled">Cancelled</option>
                            <option value="Rejected">Rejected</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- REGISTRY SYSTEM DATA GRID -->
            <div class="table-responsive custom-data-table text-start mb-4 shadow-sm">
                <table class="table table-borderless mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Student Details</th>
                            <th>Court Details</th>
                            <th>Date & Schedule</th>
                            <th>Status Flag</th>
                            <th>Actions Control</th>
                        </tr>
                    </thead>
                    <tbody id="bookingsRenderMasterGrid">
                        <!-- Loaded dynamically via Script Data Loop Engine -->
                    </tbody>
                </table>
            </div>

        </main>
    </div>

    <!-- Hidden Action Routing Form -->
    <form id="hiddenAdminActionForm" method="POST" action="admin-booking.php" style="display: none;">
        <input type="hidden" name="action" id="hiddenFormActionInput">
        <input type="hidden" name="booking_id" id="hiddenFormIdInput">
        <input type="hidden" name="status" id="hiddenFormStatusInput">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const masterBookingsArray = <?php echo json_encode($masterBookingsArray, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

            const searchFieldDOM = document.getElementById('tableSearchEngine');
            const courtFilterDOM = document.getElementById('tableCourtFilter');
            const statusFilterDOM = document.getElementById('tableStatusFilter');
            const tbodyWrapperDOM = document.getElementById('bookingsRenderMasterGrid');

            function updateBookingsTableGrid() {
                const searchString = searchFieldDOM.value.toLowerCase().trim();
                const selectedCourt = courtFilterDOM.value;
                const selectedStatus = statusFilterDOM.value;

                let itemsList = masterBookingsArray.filter(b => {
                    const bookedByStr = b.bookedBy ? b.bookedBy : '';
                    const courtStr = b.court ? b.court : '';
                    const idStr = b.id ? b.id : '';
                    const statusStr = b.status ? b.status : 'Pending';

                    const searchTarget = `${bookedByStr} ${courtStr} #BK-${idStr}`.toLowerCase();
                    if (searchString && !searchTarget.includes(searchString)) return false;
                    
                    if (selectedCourt !== 'all' && !courtStr.toLowerCase().includes(selectedCourt.toLowerCase())) return false;
                    if (selectedStatus !== 'all' && statusStr.toLowerCase() !== selectedStatus.toLowerCase()) return false;

                    return true;
                });

                tbodyWrapperDOM.innerHTML = "";

                if (itemsList.length === 0) {
                    tbodyWrapperDOM.innerHTML = `
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5 small">
                                <i class="bi bi-calendar-x d-block fs-3 mb-2 text-secondary"></i> No matching records found inside live database tables.
                            </td>
                        </tr>`;
                    return;
                }

                itemsList.forEach(booking => {
                    const tr = document.createElement('tr');
                    
                    const bStatus = booking.status ? booking.status : 'Pending';
                    let pillClass = "status-pill-badge badge-status-confirmed";
                    if (bStatus === 'Pending') pillClass = "status-pill-badge badge-status-pending";
                    if (bStatus === 'Cancelled' || bStatus === 'Rejected') pillClass = "status-pill-badge badge-status-cancelled";
                    if (bStatus === 'Complete' || bStatus === 'Completed') pillClass = "status-pill-badge badge-status-complete";

                    let contextActionButtons = "";
                    
                    if (bStatus.toLowerCase() === 'pending') {
                        contextActionButtons = `
                            <button type="button" class="btn btn-sm btn-success px-2 py-1 shadow-sm border-0 d-inline-flex align-items-center gap-1" style="font-size:0.75rem; border-radius:6px; font-weight:500;" onclick="updateBookingStateIndex(${booking.id}, 'Approved')" title="Approve Request">
                                <i class="bi bi-check-circle-fill"></i> Approve
                            </button>
                            <button type="button" class="btn btn-sm btn-danger px-2 py-1 shadow-sm border-0 d-inline-flex align-items-center gap-1" style="font-size:0.75rem; border-radius:6px; font-weight:500;" onclick="updateBookingStateIndex(${booking.id}, 'Rejected')" title="Reject Request">
                                <i class="bi bi-x-circle-fill"></i> Reject
                            </button>
                        `;
                    } else if (bStatus.toLowerCase() === 'approved' || bStatus.toLowerCase() === 'confirmed') {
                        contextActionButtons = `
                            <button type="button" class="btn btn-sm btn-primary px-2 py-1 shadow-sm border-0 d-inline-flex align-items-center gap-1" style="font-size:0.75rem; border-radius:6px; font-weight:500;" onclick="updateBookingStateIndex(${booking.id}, 'Complete')" title="Mark as Complete">
                                <i class="bi bi-flag-fill"></i> Complete
                            </button>
                        `;
                    } else {
                        contextActionButtons = `
                            <span class="text-muted small text-capitalize px-1" style="font-size:0.8rem; font-weight:500;"><i class="bi bi-lock-fill me-1"></i>Archived</span>
                        `;
                    }

                    tr.innerHTML = `
                        <td class="font-monospace-id">#BK-${booking.id}</td>
                        <td>
                            <div class="fw-semibold text-dark mb-0" style="font-size:0.88rem;">${booking.bookedBy || 'Unknown User'}</div>
                            <span class="text-muted" style="font-size: 0.76rem;">${booking.userEmail || ''}</span>
                        </td>
                        <td class="fw-medium text-dark" style="font-size:0.88rem;">${booking.court || 'Unknown Court'}</td>
                        <td>
                            <div class="mb-0 text-dark" style="font-size:0.85rem;"><i class="bi bi-calendar3 me-1 text-muted"></i> ${booking.date || ''}</div>
                            <span class="text-secondary small" style="font-size:0.76rem;">${booking.timeSlot || ''}</span>
                        </td>
                        <td><span class="${pillClass}">${bStatus}</span></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                ${contextActionButtons}
                                <button type="button" class="btn-action-delete" onclick="deleteBookingRecordIndex(${booking.id})" title="Remove Log Entry">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </div>
                        </td>
                    `;
                    tbodyWrapperDOM.appendChild(tr);
                });
            }

            window.updateBookingStateIndex = function(bookingId, strictlyNewState) {
                document.getElementById('hiddenFormActionInput').value = 'update_status';
                document.getElementById('hiddenFormIdInput').value = bookingId;
                document.getElementById('hiddenFormStatusInput').value = strictlyNewState;
                document.getElementById('hiddenAdminActionForm').submit();
            };

            window.deleteBookingRecordIndex = function(bookingId) {
                if (confirm(`Are you sure you want to delete booking entry #BK-${bookingId}?`)) {
                    document.getElementById('hiddenFormActionInput').value = 'delete_booking';
                    document.getElementById('hiddenFormIdInput').value = bookingId;
                    document.getElementById('hiddenAdminActionForm').submit();
                }
            };

            searchFieldDOM.addEventListener('input', updateBookingsTableGrid);
            courtFilterDOM.addEventListener('change', updateBookingsTableGrid);
            statusFilterDOM.addEventListener('change', updateBookingsTableGrid);

            document.getElementById('logoutBtn').addEventListener('click', function() {
                localStorage.removeItem('userRole');
                localStorage.removeItem('loggedInUser');
            });

            updateBookingsTableGrid();
        });
    </script>
</body>
</html>