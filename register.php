<?php
session_start();
require_once('db.php'); 

$errorMessage = "";
$successMessage = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullName    = trim($_POST['fullName'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $studentId   = trim($_POST['studentId'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');
    $password    = $_POST['password'] ?? '';
    $confirmPass = $_POST['confirmPassword'] ?? '';
    $role        = 'student'; 

    if (empty($fullName) || empty($email) || empty($studentId) || empty($password) || empty($confirmPass)) {
        $errorMessage = "Please fill out all mandatory registration fields.";
    } elseif ($password !== $confirmPass) {
        $errorMessage = "Passwords do not match. Please verify your choices!";
    } else {
        try {
            // Collision Check: Verify that neither the email nor the student ID is already taken
            $stmt = $pdo->prepare("SELECT user_id FROM USER WHERE email = ? OR student_id = ?");
            $stmt->execute([$email, $studentId]);
            
            if ($stmt->rowCount() > 0) {
                $errorMessage = "This email address or Student ID is already registered.";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                
                // Write structural identity inputs directly into the database table logs
                $insertStmt = $pdo->prepare("INSERT INTO USER (student_id, full_name, email, phone, password, role) VALUES (?, ?, ?, ?, ?, ?)");
                $insertStmt->execute([$studentId, $fullName, $email, $phone, $hashedPassword, $role]);
                
                $successMessage = "Registration successful! You can now log in.";
            }
        } catch (PDOException $e) {
            $errorMessage = "Database connection error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - UiTM Court Booking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="login.css">
    <style>
        /* Dark overlay mask to ensure white text remains readable over your background image */
        .login-left-premium::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(45, 21, 99, 0.75); /* Deep purple tinted shade screen */
            z-index: 1;
        }
        .brand-showcase, .row, .extra-small-text {
            position: relative;
            z-index: 2;
        }
        .uitm-custom-logo {
            width: 120px;
            height: auto;
            object-fit: contain;
        }
    </style>
</head>
<body>

    <div class="container-fluid p-0 login-container d-flex flex-column flex-md-row">
        
        <div class="login-left-premium text-white p-5 d-flex flex-column justify-content-between text-center position-relative" 
             style="background-image: url('https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQmBPo3TywsSDcRtTHMX0BK9bHbRmOQP30EfHF_wl7QyA&s=10'); background-size: cover; background-position: center; background-repeat: no-repeat;">
            
            <div class="brand-showcase my-auto">
               <div class="logo-wrapper-badge mb-4 mx-auto d-inline-block">
                    <img src="images/uitm-logo.png" alt="UiTM Logo" class="uitm-custom-logo">
                </div>
                <h2 class="fw-bold mb-3 tracking-wide">UiTM Court Booking</h2>
                <p class="hero-subtext mx-auto mb-5">
                    Book courts for futsal, volleyball, petanque, and takraw quickly and easily anytime and anywhere
                </p>
            </div>

            <div class="row g-3 justify-content-center text-center px-lg-4 mb-4">
                <div class="col-4">
                    <div class="icon-circle-translucent mx-auto mb-2"><i class="bi bi-calendar3"></i></div>
                    <div class="fw-semibold grid-title mb-1">Easy Booking</div>
                    <div class="grid-desc text-white-50">Book your court in just a few clicks.</div>
                </div>
                <div class="col-4">
                    <div class="icon-circle-translucent mx-auto mb-2"><i class="bi bi-clock"></i></div>
                    <div class="fw-semibold grid-title mb-1">Real-time Availability</div>
                    <div class="grid-desc text-white-50">Check available time slots instantly.</div>
                </div>
                <div class="col-4">
                    <div class="icon-circle-translucent mx-auto mb-2"><i class="bi bi-shield-check"></i></div>
                    <div class="fw-semibold grid-title mb-1">Secure & Reliable</div>
                    <div class="grid-desc text-white-50">Your bookings are safe and well managed.</div>
                </div>
            </div>

            <div class="text-white-50 extra-small-text text-center w-100 mb-1">
                &copy; <?php echo date("Y"); ?> Universiti Teknologi MARA. All rights reserved.
            </div>
        </div>

        <div class="login-right bg-white p-5 d-flex align-items-center justify-content-center">
            <div class="auth-form-card w-100" style="max-width: 440px;">
                
                <h2 class="fw-bold text-center dark-text mb-1 tracking-tight">Create an Account</h2>
                <p class="text-muted text-center small mb-4">Register to get started with UiTM Court Booking</p>

                <form id="registerForm" action="register.php" method="POST">
                    
                    <?php if (!empty($errorMessage)): ?>
                        <div class="alert alert-danger small py-2 text-center mb-3" role="alert">
                            <?php echo htmlspecialchars($errorMessage); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($successMessage)): ?>
                        <div class="alert alert-success small py-2 text-center mb-3" role="alert">
                            <?php echo htmlspecialchars($successMessage); ?>
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label fw-medium text-secondary small mb-1">Full Name</label>
                        <div class="input-group-custom d-flex align-items-center px-3 bg-white border rounded">
                            <i class="bi bi-person text-muted fs-5 me-3"></i>
                            <input type="text" class="clean-input flex-grow-1 border-0 outline-none w-100 py-2" name="fullName" id="nameInput" placeholder="Enter your full name" value="<?php echo htmlspecialchars($fullName ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-medium text-secondary small mb-1">Email Address</label>
                        <div class="input-group-custom d-flex align-items-center px-3 bg-white border rounded">
                            <i class="bi bi-envelope text-muted fs-5 me-3"></i>
                            <input type="email" class="clean-input flex-grow-1 border-0 outline-none w-100 py-2" name="email" id="emailInput" placeholder="Enter your email address" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-medium text-secondary small mb-1">Student / Staff ID</label>
                        <div class="input-group-custom d-flex align-items-center px-3 bg-white border rounded">
                            <i class="bi bi-card-text text-muted fs-5 me-3"></i>
                            <input type="text" class="clean-input flex-grow-1 border-0 outline-none w-100 py-2" name="studentId" id="idInput" placeholder="Enter your student or staff ID" value="<?php echo htmlspecialchars($studentId ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-medium text-secondary small mb-1">Phone Number</label>
                        <div class="input-group-custom d-flex align-items-center px-3 bg-white border rounded">
                            <i class="bi bi-telephone text-muted fs-5 me-3"></i>
                            <input type="tel" class="clean-input flex-grow-1 border-0 outline-none w-100 py-2" name="phone" id="phoneInput" placeholder="Enter your phone number" value="<?php echo htmlspecialchars($phone ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-medium text-secondary small mb-1">Password</label>
                        <div class="input-group-custom d-flex align-items-center px-3 bg-white border rounded">
                            <i class="bi bi-lock text-muted fs-5 me-3"></i>
                            <input type="password" class="clean-input flex-grow-1 border-0 outline-none w-100 py-2" name="password" id="passwordInput" placeholder="Create a password" required>
                            <i class="bi bi-eye-slash text-muted cursor-pointer fs-5" id="togglePassword" style="cursor: pointer;"></i>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-medium text-secondary small mb-1">Confirm Password</label>
                        <div class="input-group-custom d-flex align-items-center px-3 bg-white border rounded">
                            <i class="bi bi-lock text-muted fs-5 me-3"></i>
                            <input type="password" class="clean-input flex-grow-1 border-0 outline-none w-100 py-2" name="confirmPassword" id="confirmPasswordInput" placeholder="Confirm your password" required>
                            <i class="bi bi-eye-slash text-muted cursor-pointer fs-5" id="toggleConfirmPassword" style="cursor: pointer;"></i>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-purple-block btn-primary w-100 py-2 mb-4 rounded-3 fw-medium">Register</button>

                    <div class="text-center mt-2">
                        <p class="small text-muted mb-0">Already have an account? <a href="login.php" class="text-purple fw-semibold text-decoration-none">Login here</a></p>
                    </div>

                </form>
            </div>
        </div>
        
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password Visibility Masking Toggle
        document.getElementById('togglePassword').addEventListener('click', function () {
            const input = document.getElementById('passwordInput');
            if (input.type === 'password') {
                input.type = 'text';
                this.classList.replace('bi-eye-slash', 'bi-eye');
            } else {
                input.type = 'password';
                this.classList.replace('bi-eye', 'bi-eye-slash');
            }
        });

        // Confirm Password Visibility Masking Toggle
        document.getElementById('toggleConfirmPassword').addEventListener('click', function () {
            const input = document.getElementById('confirmPasswordInput');
            if (input.type === 'password') {
                input.type = 'text';
                this.classList.replace('bi-eye-slash', 'bi-eye');
            } else {
                input.type = 'password';
                this.classList.replace('bi-eye', 'bi-eye-slash');
            }
        });
    </script>
</body>
</html>