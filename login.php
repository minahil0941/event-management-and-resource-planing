<?php
require_once 'core/session.php'; // Use the updated session file above
require_once 'core/auth.php';
$auth = new Auth($pdo);

$error = '';
if (isset($_GET['debug'])) {
    echo "<pre>SESSION DATA: "; print_r($_SESSION); echo "</pre>";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier']);
    $password = $_POST['password'];

    $result = $auth->login($identifier, $password);
    
    if (isset($_GET['debug'])) {
        echo "<pre>LOGIN ATTEMPT: " . htmlspecialchars($identifier) . " | RESULT: " . var_export($result, true) . "</pre>";
    }

    if ($result === true) {
        $redirect = $_SESSION['redirect_url'] ?? 'index.php';
        unset($_SESSION['redirect_url']);
        header("Location: " . $redirect);
        exit;
    } elseif ($result === 'pending') {
        $error = "Your account is <strong>Pending Approval</strong>.";
    } else {
        $error = "Invalid Credentials or Account Suspended.";
    }
}

// Fetch System Settings for Celestial Background & Branding
$settings = [];
$stmt = $pdo->query("SELECT * FROM system_settings");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | <?= htmlspecialchars($settings['system_name'] ?? 'Universal System') ?></title>
    
    <!-- Ventixe Style Imports -->
    <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="assets/css/bootstrap-icons.css" />
    <link rel="stylesheet" href="assets/css/dashboard.css" />
    <link rel="stylesheet" href="assets/css/celestial.css" />
    
    <style>
        body, html { height: 100%; margin: 0; font-family: 'Outfit', sans-serif; overflow: hidden; }
        
        /* Full-Screen Background */
        .login-bg {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(rgba(30, 27, 75, 0.6), rgba(79, 70, 229, 0.4)), 
                        url('assets/img/uos_bg.png') center/cover no-repeat;
            z-index: -2;
            transition: opacity 0.5s ease;
        }

        body.celestial-active .login-bg {
            opacity: 0.3; /* Blend with stars */
        }

        .login-container {
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        /* Glassmorphism Card */
        .glass-login-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border-radius: 32px;
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 40px 80px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 480px;
            padding: 45px;
            text-align: center;
        }
        
        .brand-logo-img { 
            height: 80px; 
            margin-bottom: 20px;
            filter: drop-shadow(0 5px 15px rgba(0,0,0,0.1));
        }

        .brand-name { 
            font-size: 1.8rem; 
            font-weight: 800; 
            color: #1e1b4b; 
            margin-bottom: 5px;
            letter-spacing: -1px;
        }
        
        .form-control { 
            background: rgba(243, 244, 246, 0.6) !important;
            border: 1px solid rgba(79, 70, 229, 0.1);
            border-radius: 16px !important;
            padding: 14px 22px;
        }

        .form-control:focus {
            background: #fff !important;
            border-color: #4f46e5;
            box-shadow: 0 0 0 0.25rem rgba(79, 70, 229, 0.1);
        }

        .btn-ventixe {
            background: #4f46e5;
            color: white;
            border-radius: 16px;
            font-weight: 700;
            padding: 14px;
            letter-spacing: 0.5px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .btn-ventixe:hover {
            background: #4338ca;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(79, 70, 229, 0.3);
        }
    </style>
</head>
<body class="<?= (($settings['celestial_background'] ?? '1') == '1') ? 'celestial-active' : '' ?>">
    <?php if (($settings['celestial_background'] ?? '1') == '1'): ?>
        <div class="celestial-bg">
            <div class="stars-layer stars-layer-1"></div>
            <div class="stars-layer stars-layer-2"></div>
            <div class="stars-layer stars-layer-3"></div>
            <div class="shooting-star"></div>
            <div class="shooting-star"></div>
            <div class="shooting-star"></div>
        </div>
    <?php endif; ?>
    <div class="login-bg"></div>
    
    <div class="login-container">
        <div class="glass-login-card">
            <img src="assets/img/university_logo.png" alt="UOS Logo" class="brand-logo-img">
            <div class="brand-name">University of Sahiwal</div>
            <p class="text-muted mb-4 small fw-semibold">Integrated Smart Campus Resource Portal</p>
            
            <?php if($error): ?>
                <div class="alert alert-danger border-0 rounded-4 p-3 mb-4 small"><?= $error ?></div>
            <?php endif; ?>

            <form action="" method="post" class="text-start">
                <div class="mb-3">
                    <label class="form-label small fw-bold ps-1 text-dark opacity-75">ID / Email / Reg No</label>
                    <input type="text" name="identifier" class="form-control" placeholder="Your identification" required>
                </div>
                
                <div class="mb-4">
                    <div class="d-flex justify-content-between px-1">
                        <label class="form-label small fw-bold text-dark opacity-75">Password</label>
                        <a href="resetpassword.php" class="small text-primary text-decoration-none fw-bold">Forgot?</a>
                    </div>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>

                <button type="submit" class="btn btn-ventixe w-100 shadow-sm">Enter Dashboard <i class="bi bi-arrow-right-short ms-1"></i></button>
                
                <p class="text-center text-muted small mb-0">
                    Don't have an account? <a href="user_registration.php" class="text-primary fw-bold text-decoration-none">Register here</a>
                </p>
            </form>
        </div>
    </div>
</body>
</html>
