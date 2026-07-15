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

// Fetch additional phone details if available in the database, or use fallback
$userPhone = "012-3456789";
try {
    $userStmt = $pdo->prepare("SELECT phone FROM USER WHERE user_id = ? LIMIT 1");
    $userStmt->execute([$userId]);
    $dbPhone = $userStmt->fetchColumn();
    if ($dbPhone) {
        $userPhone = $dbPhone;
    }
} catch (PDOException $e) {
    // Fallback if structure varies
}

// FIX NO. 1: Live Status Scanner to provide notices directly to the user dashboard template
$courtStatusesSummary = [
    'Petanque' => 'Active',
    'Futsal' => 'Active',
    'Takraw' => 'Active',
    'Volleyball' => 'Active'
];

try {
    $summaryStmt = $pdo->query("SELECT court_type, location FROM COURT");
    $rawCourts = $summaryStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $categorizedStatuses = [
        'Petanque' => [], 'Futsal' => [], 'Takraw' => [], 'Volleyball' => []
    ];
    
    foreach ($rawCourts as $rc) {
        $cType = $rc['court_type'];
        $loc = $rc['location'] ?? '';
        $stat = 'Active';
        if (!empty($loc) && strpos($loc, '|') !== false) {
            $parts = explode('|', $loc);
            if (isset($parts[2])) {
                $stat = trim(str_replace('Status:', '', $parts[2]));
            }
        }
        if (isset($categorizedStatuses[$cType])) {
            $categorizedStatuses[$cType][] = strtolower($stat);
        }
    }
    
    foreach ($categorizedStatuses as $type => $statusList) {
        if (empty($statusList)) {
            $courtStatusesSummary[$type] = 'Active'; 
        } elseif (in_array('active', $statusList)) {
            $courtStatusesSummary[$type] = 'Active'; // If at least one asset is operational
        } elseif (in_array('maintenance', $statusList)) {
            $courtStatusesSummary[$type] = 'Maintenance'; // All assets under this type are locked out
        } else {
            $courtStatusesSummary[$type] = 'Inactive';
        }
    }
} catch (PDOException $e) {
    // Safe initialization fallback mapping
}

// 2. Handle Booking Creation Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_booking') {
    $courtName = trim($_POST['court_name'] ?? ''); 
    $bookingDate = trim($_POST['booking_date'] ?? '');
    $timeSlot = trim($_POST['time_slot'] ?? '');

    if (empty($courtName) || empty($bookingDate) || empty($timeSlot)) {
        $errorAlert = "Please complete selecting your Date, Court, and Time slot first.";
    } else {
        try {
            $courtTypeMap = strtolower($courtName);
            
            // FIX NO. 2: Query by LOWER(court_type) instead of name to map correctly to existing court records
            $courtCheckStmt = $pdo->prepare("SELECT court_id, court_name, location FROM COURT WHERE LOWER(court_type) = ?");
            $courtCheckStmt->execute([$courtTypeMap]);
            $courtsList = $courtCheckStmt->fetchAll(PDO::FETCH_ASSOC);

            // Create a fallback asset only if no courts exist for this classification
            if (empty($courtsList)) {
                $defaultName = ucfirst($courtName) . " Court 1";
                $defaultPayload = "UiTM Kampus Kuala Terengganu | Capacity: 12 | Status: Active";
                $insertCourt = $pdo->prepare("INSERT INTO COURT (court_name, court_type, location) VALUES (?, ?, ?)");
                $insertCourt->execute([$defaultName, $courtName, $defaultPayload]);
                $newId = $pdo->lastInsertId();
                $courtsList[] = ['court_id' => $newId, 'court_name' => $defaultName, 'location' => $defaultPayload];
            }

            $activeCourtIds = [];
            $maintenanceCount = 0;
            $inactiveCount = 0;

            foreach ($courtsList as $courtRow) {
                $rawLocation = $courtRow['location'] ?? '';
                $status = "Active";
                if (!empty($rawLocation) && strpos($rawLocation, '|') !== false) {
                    $parts = explode('|', $rawLocation);
                    if (isset($parts[2])) {
                        $status = trim(str_replace('Status:', '', $parts[2]));
                    }
                }
                
                if (strtolower($status) === 'active') {
                    $activeCourtIds[] = $courtRow['court_id'];
                } elseif (strtolower($status) === 'maintenance') {
                    $maintenanceCount++;
                } elseif (strtolower($status) === 'inactive') {
                    $inactiveCount++;
                }
            }

            // FIX NO. 3: Throw strict visibility notices and block structural submissions if statuses aren't active
            if (empty($activeCourtIds)) {
                if ($maintenanceCount > 0) {
                    $errorAlert = "Sorry, all courts for " . htmlspecialchars($courtName) . " are currently under maintenance. Reservations are temporarily locked out.";
                } else {
                    $errorAlert = "Sorry, all courts for " . htmlspecialchars($courtName) . " are currently inactive or disabled by the administrator.";
                }
            } else {
                // Check if any active courts are available (not fully booked for this time slot)
                $inClause = implode(',', array_fill(0, count($activeCourtIds), '?'));
                $checkStmt = $pdo->prepare("SELECT court_id FROM BOOKING WHERE court_id IN ($inClause) AND booking_date = ? AND time_slot = ? AND status != 'cancelled' AND status != 'rejected'");
                
                $params = array_merge($activeCourtIds, [$bookingDate, $timeSlot]);
                $checkStmt->execute($params);
                $bookedCourtIds = $checkStmt->fetchAll(PDO::FETCH_COLUMN);

                $availableCourtId = null;
                foreach ($activeCourtIds as $id) {
                    if (!in_array($id, $bookedCourtIds)) {
                        $availableCourtId = $id;
                        break;
                    }
                }

                if ($availableCourtId === null) {
                    $errorAlert = "Sorry, all functional operational " . htmlspecialchars($courtName) . " courts are fully booked for this time window.";
                } else {
                    $insertStmt = $pdo->prepare("INSERT INTO BOOKING (user_id, court_id, booking_date, time_slot, status) VALUES (?, ?, ?, ?, 'pending')");
                    $insertStmt->execute([$userId, $availableCourtId, $bookingDate, $timeSlot]);

                    $successAlert = "Success! Your reservation request for " . htmlspecialchars($courtName) . " Court on " . htmlspecialchars($bookingDate) . " at " . htmlspecialchars($timeSlot) . " has been submitted and is pending administrator approval.";
                }
            }
        } catch (PDOException $e) {
            $errorAlert = "Database Reservation Error: " . $e->getMessage();
        }
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
                        <a class="nav-link active d-flex align-items-center gap-3 px-3 py-2.5 rounded-3" href="book-court.php">
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

        <main class="flex-grow-1 workspace-content p-4 p-lg-5 bg-light overflow-y-auto">
            
            <header class="d-flex justify-content-between align-items-start mb-4">
                <div>
                    <span class="fs-4 fw-bold tracking-tight text-dark align-middle">Make a Booking</span>
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
                <div class="alert alert-danger alert-dismissible fade show small mb-4 text-start" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($errorAlert); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                
                <div class="col-lg-8">
                    <div class="bg-white p-4 rounded-4 border shadow-sm">
                        
                        <div class="mb-4">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <span class="step-badge">1</span>
                                <h6 class="fw-bold text-dark mb-0">Choose Court</h6>
                            </div>
                            <p class="text-muted small mb-3">Select the type of court you want to book.</p>
                            
                            <div class="row g-3">
                                <div class="col-6 col-sm-3">
                                    <div class="court-card text-center p-3 selected" data-court="Petanque" style="cursor: pointer;">
                                        <?php if($courtStatusesSummary['Petanque'] === 'Maintenance'): ?>
                                            <span class="status-pill-badge bg-warning text-dark">Maintenance</span>
                                        <?php elseif($courtStatusesSummary['Petanque'] === 'Inactive'): ?>
                                            <span class="status-pill-badge bg-danger text-white">Unavailable</span>
                                        <?php endif; ?>
                                        <i class="bi bi-check-circle-fill check-badge"></i>
                                        <i class="bi bi-record-circle court-icon mb-2 d-block"></i>
                                        <span class="fw-medium small text-dark">Petanque</span>
                                    </div>
                                </div>
                                <div class="col-6 col-sm-3">
                                    <div class="court-card text-center p-3" data-court="Futsal" style="cursor: pointer;">
                                        <?php if($courtStatusesSummary['Futsal'] === 'Maintenance'): ?>
                                            <span class="status-pill-badge bg-warning text-dark">Maintenance</span>
                                        <?php elseif($courtStatusesSummary['Futsal'] === 'Inactive'): ?>
                                            <span class="status-pill-badge bg-danger text-white">Unavailable</span>
                                        <?php endif; ?>
                                        <i class="bi bi-check-circle-fill check-badge"></i>
                                        <i class="bi bi-dribbble court-icon mb-2 d-block"></i>
                                        <span class="fw-medium small text-dark">Futsal</span>
                                    </div>
                                </div>
                                <div class="col-6 col-sm-3">
                                    <div class="court-card text-center p-3" data-court="Takraw" style="cursor: pointer;">
                                        <?php if($courtStatusesSummary['Takraw'] === 'Maintenance'): ?>
                                            <span class="status-pill-badge bg-warning text-dark">Maintenance</span>
                                        <?php elseif($courtStatusesSummary['Takraw'] === 'Inactive'): ?>
                                            <span class="status-pill-badge bg-danger text-white">Unavailable</span>
                                        <?php endif; ?>
                                        <i class="bi bi-check-circle-fill check-badge"></i>
                                        <i class="bi bi-disc court-icon mb-2 d-block"></i>
                                        <span class="fw-medium small text-dark">Takraw</span>
                                    </div>
                                </div>
                                <div class="col-6 col-sm-3">
                                    <div class="court-card text-center p-3" data-court="Volleyball" style="cursor: pointer;">
                                        <?php if($courtStatusesSummary['Volleyball'] === 'Maintenance'): ?>
                                            <span class="status-pill-badge bg-warning text-dark">Maintenance</span>
                                        <?php elseif($courtStatusesSummary['Volleyball'] === 'Inactive'): ?>
                                            <span class="status-pill-badge bg-danger text-white">Unavailable</span>
                                        <?php endif; ?>
                                        <i class="bi bi-check-circle-fill check-badge"></i>
                                        <i class="bi bi-grid-3x3-gap court-icon mb-2 d-block"></i>
                                        <span class="fw-medium small text-dark">Volleyball</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Live User Status Notice Container -->
                            <div id="courtStatusNoticeBox" class="d-none mt-3"></div>
                        </div>

                        <div class="mb-4">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="step-badge">2</span>
                                <h6 class="fw-bold text-dark mb-0">Select Date</h6>
                            </div>
                            <p class="text-muted small mb-2">Choose the date for your court booking.</p>
                            <div class="date-input-wrapper">
                                <input type="date" class="form-control py-2 shadow-sm rounded-3" id="bookingDatePicker" min="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="step-badge">3</span>
                                <h6 class="fw-bold text-dark mb-0">Select Time Slot</h6>
                            </div>
                            <p class="text-muted small mb-3">Choose an available time slot. Grayed out slots are already booked.</p>
                            
                            <div class="row row-cols-2 row-cols-sm-4 g-2" id="timeSlotsContainer">
                                <div class="col"><button type="button" class="btn slot-btn w-100 rounded-3" data-time="8:00 AM - 10:00 AM">8:00 AM</button></div>
                                <div class="col"><button type="button" class="btn slot-btn w-100 rounded-3" data-time="10:00 AM - 12:00 PM">10:00 AM</button></div>
                                <div class="col"><button type="button" class="btn slot-btn w-100 rounded-3" data-time="12:00 PM - 2:00 PM">12:00 PM</button></div>
                                <div class="col"><button type="button" class="btn slot-btn w-100 rounded-3" data-time="2:00 PM - 4:00 PM">2:00 PM</button></div>
                                <div class="col"><button type="button" class="btn slot-btn w-100 rounded-3" data-time="4:00 PM - 6:00 PM">4:00 PM</button></div>
                                <div class="col"><button type="button" class="btn slot-btn w-100 rounded-3" data-time="6:00 PM - 8:00 PM">6:00 PM</button></div>
                                <div class="col"><button type="button" class="btn slot-btn w-100 rounded-3" data-time="8:00 PM - 10:00 PM">8:00 PM</button></div>
                                <div class="col"><button type="button" class="btn slot-btn w-100 rounded-3" data-time="10:00 PM - 12:00 AM">10:00 PM</button></div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="step-badge">4</span>
                                <h6 class="fw-bold text-dark mb-0">Your Information</h6>
                            </div>
                            <p class="text-muted small mb-3">Verify your contact profile variables before finalizing booking.</p>
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <label class="form-label text-secondary small fw-medium">Full Name</label>
                                    <input type="text" class="form-control bg-light py-2 rounded-3" id="infoFullName" readonly value="<?php echo htmlspecialchars($userName); ?>">
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label text-secondary small fw-medium">Phone Number</label>
                                    <input type="text" class="form-control bg-light py-2 rounded-3" id="infoPhone" readonly value="<?php echo htmlspecialchars($userPhone); ?>">
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="bg-white p-4 rounded-4 border shadow-sm h-100 d-flex flex-column justify-content-between">
                        <div>
                            <h5 class="fw-bold text-dark mb-1 pb-2 border-bottom">Booking Summary</h5>
                            
                            <div class="text-center py-4 bg-light rounded-4 my-3">
                                <i class="bi bi-calendar-check text-purple display-5" id="summaryVisualIcon"></i>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-muted small"><i class="bi bi-geo-alt me-2"></i>Court:</span>
                                <span class="fw-bold text-dark" id="summaryCourtText">Petanque Court</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-muted small"><i class="bi bi-calendar-event me-2"></i>Date:</span>
                                <span class="fw-bold text-dark" id="summaryDateText">Not selected</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-muted small"><i class="bi bi-clock me-2"></i>Time:</span>
                                <span class="fw-bold text-dark" id="summaryTimeText">Not selected</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <span class="text-muted small"><i class="bi bi-hourglass-split me-2"></i>Duration:</span>
                                <span class="fw-bold text-dark" id="summaryDurationText">2 Hours</span>
                            </div>

                            <div class="summary-info-box p-3 mb-4 d-flex gap-2">
                                <i class="bi bi-info-circle-fill text-purple fs-5"></i>
                                <span class="extra-small-text text-secondary" style="font-size:0.8rem;">Please arrive at least 15 minutes before your selected booking time slot starts.</span>
                            </div>
                        </div>

                        <form id="nativeBookingSubmitForm" action="book-court.php" method="POST">
                            <input type="hidden" name="action" value="create_booking">
                            <input type="hidden" name="court_name" id="hiddenCourtInput" value="Petanque">
                            <input type="hidden" name="booking_date" id="hiddenDateInput" value="<?php echo date('Y-m-d'); ?>">
                            <input type="hidden" name="time_slot" id="hiddenTimeInput" value="">

                            <button type="submit" class="btn btn-primary w-100 py-2.5 rounded-3 fw-bold fs-6 shadow-sm btn-purple-block text-white" id="finalizeBookingBtn" disabled>
                                <i class="bi bi-bookmark-plus me-1"></i> Book Now
                            </button>
                        </form>
                    </div>
                </div>

            </div>

        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let selectedCourt = "Petanque";
        let selectedTimeText = "";
        
        const courtStatusesMap = <?php echo json_encode($courtStatusesSummary); ?>;
        const datePicker = document.getElementById('bookingDatePicker');

        const hiddenCourt = document.getElementById('hiddenCourtInput');
        const hiddenDate = document.getElementById('hiddenDateInput');
        const hiddenTime = document.getElementById('hiddenTimeInput');

        document.querySelectorAll('.court-card').forEach(card => {
            card.addEventListener('click', function() {
                document.querySelectorAll('.court-card').forEach(c => c.classList.remove('selected'));
                this.classList.add('selected');
                selectedCourt = this.getAttribute('data-court');
                hiddenCourt.value = selectedCourt;
                selectedTimeText = "";
                hiddenTime.value = "";
                updateBookingSummaryUI();
            });
        });

        // Aligned perfectly to use active styling classes shared across standard metrics
        document.querySelectorAll('#timeSlotsContainer .slot-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('#timeSlotsContainer .slot-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                selectedTimeText = this.getAttribute('data-time');
                hiddenTime.value = selectedTimeText;
                updateBookingSummaryUI();
            });
        });

        datePicker.addEventListener('change', function() {
            hiddenDate.value = this.value;
            selectedTimeText = "";
            hiddenTime.value = "";
            updateBookingSummaryUI();
        });

        function updateBookingSummaryUI() {
            document.getElementById('summaryCourtText').innerText = `${selectedCourt} Court`;
            if (datePicker.value) {
                const options = { year: 'numeric', month: 'long', day: 'numeric' };
                const dateObj = new Date(datePicker.value);
                document.getElementById('summaryDateText').innerText = dateObj.toLocaleDateString('en-US', options);
            } else {
                document.getElementById('summaryDateText').innerText = "Not selected";
            }

            // Notice Box Rendering Engine
            const noticeBox = document.getElementById('courtStatusNoticeBox');
            const currentStatus = courtStatusesMap[selectedCourt] || 'Active';
            
            if (currentStatus.toLowerCase() !== 'active') {
                noticeBox.className = `alert alert-${currentStatus.toLowerCase() === 'maintenance' ? 'warning' : 'danger'} small text-start d-flex align-items-center gap-2 p-2.5 rounded-3`;
                noticeBox.innerHTML = `
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span>Notice: This category is currently marked as <strong>${currentStatus}</strong> by operations management.</span>
                `;
            } else {
                noticeBox.className = 'd-none';
            }

            if (selectedTimeText) {
                document.getElementById('summaryTimeText').innerText = selectedTimeText;
                document.getElementById('finalizeBookingBtn').disabled = false;
            } else {
                document.getElementById('summaryTimeText').innerText = "Not selected";
                document.getElementById('finalizeBookingBtn').disabled = true;
            }

            const iconNode = document.getElementById('summaryVisualIcon');
            iconNode.className = "bi display-5 text-purple ";
            if (selectedCourt === "Petanque") iconNode.classList.add('bi-record-circle');
            else if (selectedCourt === "Futsal") iconNode.classList.add('bi-dribbble');
            else if (selectedCourt === "Takraw") iconNode.classList.add('bi-disc');
            else if (selectedCourt === "Volleyball") iconNode.classList.add('bi-grid-3x3-gap');
        }

        updateBookingSummaryUI();
    </script>
</body>
</html>