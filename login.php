<?php
session_start();
require_once('db.php');

// Define default initial fallback values for the GET request (initial page load)
$typedUsername = "";
$selectedRole  = "student"; 
$phpErrorMsg   = "";
$jsAuthBypass  = false; 
$adminNameStr  = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $typedUsername = isset($_POST['username']) ? trim($_POST['username']) : ''; 
    $typedPassword = isset($_POST['password']) ? trim($_POST['password']) : ''; 
    $selectedRole  = isset($_POST['role']) ? trim($_POST['role']) : 'student'; 

    // 1. Administrator Bypass Rule matching your credentials
    if (strtolower($typedUsername) === 'admin@gmail.com' && $typedPassword === '123' && $selectedRole === 'admin') { 
        $_SESSION['userRole'] = 'admin'; 
        $_SESSION['loggedInUser'] = 'System Administrator'; 
        
        // Signal browser to set local storage before migrating page states
        $jsAuthBypass = true;
        $adminNameStr = 'System Administrator';
    } else {
        try {
            // 2. Query Student Users table inside container database
            $stmt = $pdo->prepare("SELECT * FROM USER WHERE email = ? AND role = ?"); 
            $stmt->execute([$typedUsername, $selectedRole]); 
            $user = $stmt->fetch(); 

            // 3. Match secure Hashed Cryptographic strings
            if ($user && password_verify($typedPassword, $user['password'])) { 
                $_SESSION['userRole'] = $user['role']; 
                $_SESSION['loggedInUser'] = $user['full_name']; 
                $_SESSION['userId'] = $user['user_id'];
                
                // Allow JavaScript to capture student variables before redirect
                $jsAuthBypass = true;
                $adminNameStr = $user['full_name'];
            } else {
                $phpErrorMsg = "Invalid username/email or password matching this account role tier.";
            }
        } catch (PDOException $e) {
            $phpErrorMsg = "Database Connection Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - UiTM Court Booking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            color: #333333;
            background-color: #ffffff;
            margin: 0;
            padding: 0;
        }

        /* Responsive container layout */
        .login-container {
            min-height: 100vh;
        }

        /* Premium Left Pane Setup with Dark Overlay image map */
        .login-left-premium {
            flex: 1;
            min-height: 50vh; /* Stacks nicely on mobile */
            background: linear-gradient(rgba(15, 23, 52, 0.9), rgba(15, 23, 52, 0.9)), 
                        url('https://images.unsplash.com/photo-1544698310-74ea9d1c8258?auto=format&fit=crop&q=80&w=1200') no-repeat center center/cover;
            z-index: 1;
        }

        /* On medium-up screens, take up equal 50% width columns */
        @media (min-width: 768px) {
            .login-left-premium {
                min-height: 100vh;
            }
            .login-right {
                min-height: 100vh;
            }
        }

        .login-right {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem 1.5rem;
            background-color: #ffffff;
        }

        /* Dark overlay mask to ensure white text remains readable over your background image */
        .login-left-premium::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(45, 21, 99, 0.75); /* Deep purple tinted shade screen */
            z-index: -1;
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

        .hero-subtext {
            font-size: 0.88rem;
            max-width: 380px;
            opacity: 0.75;
            line-height: 1.5;
        }

        /* Translucent Circle Icon Sets */
        .icon-circle-translucent {
            width: 46px;
            height: 46px;
            background-color: rgba(111, 66, 193, 0.25);
            color: #a070f0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .grid-title {
            font-size: 0.75rem;
        }

        .grid-desc {
            font-size: 0.65rem;
            line-height: 1.3;
            max-width: 110px;
            margin: 0 auto;
        }

        /* Custom Outlined Forms Layout */
        .input-group-custom {
            border: 1px solid #ced4da;
            border-radius: 8px;
            height: 48px;
            transition: border-color 0.15s ease;
        }

        .input-group-custom:focus-within {
            border-color: #4a229d;
        }

        .clean-input {
            border: none;
            outline: none;
            font-size: 0.88rem;
            color: #495057;
            height: 100%;
        }

        .clean-input::placeholder {
            color: #adb5bd;
        }

        /* Role Selection Outlined Purple Buttons */
        .btn-outline-purple {
            border: 1px solid #ced4da;
            color: #495057;
            background-color: #ffffff;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .btn-outline-purple:hover {
            border-color: #4a229d;
            color: #4a229d;
            background-color: #f3f0fa;
        }

        /* Selection/Active states for Role toggle selectors */
        .btn-check:checked + .btn-outline-purple {
            border-color: #4a229d !important;
            background-color: #f3f0fa !important;
            color: #4a229d !important;
            box-shadow: 0 0 0 0.25rem rgba(111, 66, 193, 0.15);
        }

        /* Button solid colors block mappings */
        .btn-purple-block {
            background-color: #4a229d !important;
            color: white !important;
            height: 46px;
            border: none;
            transition: background-color 0.2s ease;
        }

        .btn-purple-block:hover {
            background-color: #38197a !important;
            color: white !important;
        }

        .cursor-pointer {
            cursor: pointer;
        }

        .text-purple {
            color: #4a229d;
        }

        .extra-small-text {
            font-size: 0.75rem;
        }

        /* Return to Index Smooth Navigation Link Settings */
        .fallback-home-link {
            transition: color 0.2s ease-in-out;
        }
        .fallback-home-link:hover {
            color: #4a229d !important; /* Changes to theme purple color on hover */
        }
    </style>
</head>
<body>

    <div class="container-fluid p-0 login-container d-flex flex-column flex-md-row">
        
        <div class="login-left-premium text-white p-5 d-flex flex-column justify-content-between text-center position-relative" 
             style="background-image: url('https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQmBPo3TywsSDcRtTHMX0BK9bHbRmOQP30EfHF_wl7QyA&s=10');">
            
            <div class="brand-showcase my-auto">
               <div class="logo-wrapper-badge mb-4 mx-auto d-inline-block">
                    <img src="images/uitm-logo.png" alt="UiTM Logo" class="uitm-custom-logo">
                </div>
                <h2 class="fw-bold mb-3 tracking-wide">UiTM Court Booking</h2>
                <p class="hero-subtext mx-auto mb-5">
                    Book courts for futsal, volleyball, petanque, and takraw quickly and easily anytime and anywhere.
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

        <div class="login-right">
            <div class="auth-form-card w-100" style="max-width: 440px;">
                
                <h2 class="fw-bold text-center dark-text mb-1 tracking-tight">Welcome Back</h2>
                <p class="text-muted text-center small mb-4">Log in to manage your court reservations</p>

                <form id="loginForm" action="login.php" method="POST">
                    
                    <?php if (!empty($phpErrorMsg)): ?>
                        <div class="alert alert-danger small py-2 text-center mb-3" role="alert">
                            <?php echo htmlspecialchars($phpErrorMsg); ?>
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label fw-medium text-secondary small mb-1">Select Role</label>
                        <div class="d-flex gap-2">
                            <input type="radio" class="btn-check" name="role" id="roleStudent" value="student" <?php echo ($selectedRole === 'student') ? 'checked' : ''; ?> autocomplete="off">
                            <label class="btn btn-outline-purple flex-grow-1 py-2 text-center fw-medium small" for="roleStudent">
                                <i class="bi bi-mortarboard me-2"></i>Student
                            </label>

                            <input type="radio" class="btn-check" name="role" id="roleAdmin" value="admin" <?php echo ($selectedRole === 'admin') ? 'checked' : ''; ?> autocomplete="off">
                            <label class="btn btn-outline-purple flex-grow-1 py-2 text-center fw-medium small" for="roleAdmin">
                                <i class="bi bi-person-badge me-2"></i>Admin
                            </label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-medium text-secondary small mb-1">Email Address</label>
                        <div class="input-group-custom d-flex align-items-center px-3 bg-white">
                            <i class="bi bi-person text-muted fs-5 me-3"></i>
                            <input type="text" class="clean-input flex-grow-1" name="username" id="usernameInput" value="<?php echo htmlspecialchars($typedUsername); ?>" placeholder="Enter your email" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-medium text-secondary small mb-1">Password</label>
                        <div class="input-group-custom d-flex align-items-center px-3 bg-white">
                            <i class="bi bi-lock text-muted fs-5 me-3"></i>
                            <input type="password" class="clean-input flex-grow-1" name="password" id="passwordInput" placeholder="Enter password" required>
                            <i class="bi bi-eye-slash text-muted cursor-pointer fs-5" id="togglePassword"></i>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-purple-block w-100 py-2 mb-4 rounded-3 fw-medium">Log In</button>

                    <div class="text-center mt-2">
                        <p class="small text-muted mb-0">Don't have an account? <a href="register.php" class="text-purple fw-semibold text-decoration-none">Register here</a></p>
                    </div>

                    <div class="text-center mt-3 pt-2 border-top border-light-subtle">
                        <a href="index.php" class="text-secondary small text-decoration-none d-inline-flex align-items-center gap-1 fallback-home-link">
                            <i class="bi bi-arrow-left-short fs-5"></i> Return to Homepage
                        </a>
                    </div>

                </form>
            </div>
        </div>
        
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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

        <?php if ($jsAuthBypass): ?>
            localStorage.setItem('userRole', '<?php echo $selectedRole; ?>');
            localStorage.setItem('loggedInUser', '<?php echo esc_js($adminNameStr); ?>');
            
            <?php if ($selectedRole === 'admin'): ?>
                window.location.href = "admin-dashboard.php";
            <?php else: ?>
                window.location.href = "dashboard.php";
            <?php endif; ?>
        <?php endif; ?>
    </script>
</body>
</html>
<?php
function esc_js($string) {
    return str_replace(array("\r", "\n", "'", '"'), array('', '', "\\'", '\\"'), $string);
}
?>