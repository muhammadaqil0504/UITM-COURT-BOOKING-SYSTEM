<?php
// Start secure session layer tracking 
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UiTM Court Booking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            color: #333333;
        }

        /* Specific wireframe color match values */
        .bg-uitm {
            background-color: #0b112c !important; /* Deep Dark Space Navy */
        }

        .dark-text {
            color: #0a0a0a;
        }

        /* Hero Section Image Background & Dark Overlay */
        .hero-section {
            background: linear-gradient(rgba(11, 17, 44, 0.75), rgba(11, 17, 44, 0.75)), 
                        url('https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQmBPo3TywsSDcRtTHMX0BK9bHbRmOQP30EfHF_wl7QyA&s=10') no-repeat center center/cover;
            height: 52vh;
            min-height: 380px;
        }

        .hero-lead {
            font-size: 0.95rem;
            max-width: 500px;
            opacity: 0.85;
            font-weight: 300;
        }

        /* Rounded Core CTA Buttons */
        .btn-purple-action {
            background-color: #551a8b !important; /* Solid Matte Purple */
            color: #ffffff !important;             /* Explicitly force white text visibility */
            font-size: 0.9rem;
            font-weight: 500;                      /* Enhanced weight for better readability */
            border: none;
            min-width: 130px;
            transition: background-color 0.2s ease;
        }

        .btn-purple-action:hover {
            background-color: #722bb4 !important; /* Brighter purple tint on hover interaction */
            color: #ffffff !important;
        }

        /* Feature Grid Icon Subsets */
        .icon-circle {
            width: 54px;
            height: 54px;
            background-color: #eaddf7; /* Very Light Tint Purple */
            color: #551a8b;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
        }

        .feature-p {
            font-size: 0.85rem;
            max-width: 210px;
            line-height: 1.4;
        }

        /* Footer Adjustments */
        .footer-title {
            font-size: 0.85rem;
        }

        .extra-small-text {
            font-size: 0.75rem;
        }

        footer .small {
            font-size: 0.8rem;
        }
        
        /* Ensure footer text and links remain perfectly crisp and visible */
        .text-white-50 {
            color: rgba(255, 255, 255, 0.7) !important;
        }

        .hover-white:hover {
            color: #ffffff !important;
        }

        /* Enhancing icon size alignment inside the feature circles */
        .icon-circle i {
            font-size: 1.5rem;
            display: inline-block;
            line-height: 1;
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">

    <nav class="navbar navbar-dark bg-uitm py-3">
        <div class="container-fluid px-4 d-flex justify-content-between align-items-center">
           <a class="navbar-brand d-flex align-items-center fw-semibold fs-6" href="index.php">
                <img src="images/uitm-logo.png" alt="UiTM Logo" height="30" class="me-2"> 
                 UiTM Court Booking
            </a>
            
            <?php if (isset($_SESSION['userRole'])): ?>
                <a class="btn btn-outline-light btn-sm px-3 border-opacity-20 small" href="dashboard.php">Go to Dashboard</a>
            <?php else: ?>
                <a class="btn btn-outline-light btn-sm px-3 border-opacity-20 small" href="login.php">Login</a>
            <?php endif; ?>
        </div>
    </nav>

    <header class="hero-section text-center text-white d-flex align-items-center position-relative">
        <div class="container px-4">
            <h1 class="display-5 fw-normal mb-3">Welcome to<br><span class="fw-bold">UiTM Court Booking</span></h1>
            <p class="mb-4 mx-auto hero-lead">
                Book courts for futsal, badminton and volleyball quickly and easily anytime, anywhere.
            </p>
            <div class="d-flex justify-content-center gap-4 mt-4">
                <?php if (isset($_SESSION['userRole'])): ?>
                    <a href="dashboard.php" class="btn btn-purple-action px-4 py-2 rounded text-decoration-none d-flex align-items-center justify-content-center">Go to Dashboard</a>
                <?php else: ?>
                    <a href="register.php" class="btn btn-purple-action px-4 py-2 rounded text-decoration-none d-flex align-items-center justify-content-center">Get Started</a>
                    <a href="login.php" class="btn btn-purple-action px-4 py-2 rounded text-decoration-none d-flex align-items-center justify-content-center">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="flex-grow-1 bg-white d-flex align-items-center py-5">
        <div class="container">
            <h3 class="text-center fw-bold mb-5 dark-text">Why Choose UiTM Court Booking?</h3>
            
            <div class="row g-4 justify-content-center text-center">
                <div class="col-md-4 px-4">
                    <div class="icon-circle mx-auto mb-3">
                        <i class="bi bi-lightning"></i>
                    </div>
                    <h6 class="fw-bold mb-2">Easy Booking</h6>
                    <p class="text-muted feature-p mx-auto">Book your preferred court in just a few simple steps.</p>
                </div>

                <div class="col-md-4 px-4">
                    <div class="icon-circle mx-auto mb-3">
                        <i class="bi bi-calendar3"></i>
                    </div>
                    <h6 class="fw-bold mb-2">Real-time Availability</h6>
                    <p class="text-muted feature-p mx-auto">Check court availability and choose the best time for you.</p>
                </div>

                <div class="col-md-4 px-4">
                    <div class="icon-circle mx-auto mb-3">
                        <i class="bi bi-clipboard-data-fill"></i>
                    </div>
                    <h6 class="fw-bold mb-2">Manage Bookings</h6>
                    <p class="text-muted feature-p mx-auto">View, manage and track all your bookings in one place.</p>
                </div>
            </div>
        </div>
    </main>

    <footer class="bg-uitm text-white py-4 mt-auto border-top border-secondary border-opacity-25">
        <div class="container-fluid px-4">
            <div class="row align-items-center g-3">
                
                <div class="col-md-5 d-flex align-items-center justify-content-center justify-content-md-start">
                    <img src="images/uitm-logo.png" alt="UiTM Logo" height="40" class="me-2">
                    <div class="text-start">
                        <div class="fw-semibold footer-title">UiTM Court Booking System</div>
                        <div class="text-white-50 extra-small-text">&copy; <?php echo date("Y"); ?> Universiti Teknologi MARA. All rights reserved</div>
                    </div>
                </div>

                <div class="col-md-4 d-flex flex-column flex-sm-row justify-content-center gap-3 gap-sm-4 small text-white-50">
                    <span class="d-flex align-items-center justify-content-center">
                        <i class="bi bi-envelope me-2 text-white"></i> admin@uitm.edu.my
                    </span>
                    <span class="d-flex align-items-center justify-content-center">
                        <i class="bi bi-telephone me-2 text-white"></i> 03-5544 2000
                    </span>
                </div>

                <div class="col-md-3 d-flex justify-content-center justify-content-md-end gap-3 fs-5 text-white-50">
                    <a href="#" class="text-reset hover-white"><i class="bi bi-facebook"></i></a>
                    <a href="#" class="text-reset hover-white"><i class="bi bi-instagram"></i></a>
                    <a href="#" class="text-reset hover-white"><i class="bi bi-globe"></i></a>
                </div>

            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>