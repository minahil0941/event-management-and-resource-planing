<?php
require_once 'core/session.php';
require_once 'core/auth.php';

$auth = new Auth($pdo);
$roles = $auth->getPublicRoles(); // Fetch roles from DB

// Fetch System Settings
$settings = [];
$stmt = $pdo->query("SELECT * FROM system_settings");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name' => trim($_POST['name']),
        'email' => trim($_POST['email']),
        'password' => $_POST['password'],
        'role' => $_POST['role'],
        'organization' => trim($_POST['organization']),
        'identity_no' => trim($_POST['identity_no'] ?? ''),
        'registration_no' => trim($_POST['registration_no'] ?? ''),
        'is_active' => 0 // External registrations are pending by default
    ];

    if ($data['password'] !== $_POST['retype_password']) {
        $msg = "Passwords do not match!";
        $msgType = "danger";
    } else {
        $result = $auth->register($data);
        if ($result === true) {
            $msg = "Registration successful! Your account is <strong>Pending Approval</strong>. <a href='login.php' class='alert-link'>Return to Login</a>";
            $msgType = "success";
            
            // Notify Admins
            require_once 'core/booking_helper.php';
            notifyAdmins($pdo, "New user registration: " . $data['name'] . " (" . $data['organization'] . ")", 'info', 'dashboards/super_admin/manage_users.php');
        } else {
            $msg = $result;
            $msgType = "danger";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registration | <?= htmlspecialchars($settings['system_name'] ?? 'University Portal') ?></title>
    
    <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="assets/css/bootstrap-icons.css" />
    <link rel="stylesheet" href="assets/css/dashboard.css" />
    <link rel="stylesheet" href="assets/css/celestial.css" />
    
    <style>
        body, html { min-height: 100vh; margin: 0; font-family: 'Inter', sans-serif; background: #f4f7fe; }
        
        .login-bg {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(135deg, rgba(30, 27, 75, 0.75), rgba(79, 70, 229, 0.55)), 
                        url('assets/img/uos_bg.png') center/cover no-repeat;
            z-index: -2;
            transition: opacity 0.5s ease;
        }

        body.celestial-active .login-bg {
            opacity: 0.3; /* Blend with stars */
        }

        .login-container {
            padding: 60px 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .glass-login-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(40px);
            -webkit-backdrop-filter: blur(40px);
            border-radius: 40px;
            border: 1px solid rgba(255, 255, 255, 0.6);
            box-shadow: 0 60px 120px rgba(0, 0, 0, 0.4);
            width: 100%;
            max-width: 640px;
            padding: 55px;
            animation: slideInUp 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes slideInUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .brand-logo-img { 
            height: 90px; 
            margin-bottom: 24px;
            display: block;
            margin-left: auto;
            margin-right: auto;
            filter: drop-shadow(0 10px 20px rgba(0,0,0,0.15));
        }

        .brand-name { 
            font-size: 2rem; 
            font-weight: 800; 
            color: #1e1b4b; 
            text-align: center;
            margin-bottom: 8px;
            letter-spacing: -1.2px;
        }
        
        .form-control, .form-select { 
            background: #f8fafc !important;
            border: 1px solid #e2e8f0;
            border-radius: 18px !important;
            padding: 14px 22px;
            font-size: 0.95rem;
        }

        .form-control:focus, .form-select:focus {
            background: #fff !important;
            border-color: #4f46e5;
            box-shadow: 0 0 0 0.25rem rgba(79, 70, 229, 0.1);
        }

        .btn-ventixe {
            background: #4f46e5;
            color: white;
            border-radius: 18px;
            font-weight: 700;
            padding: 16px;
            font-size: 1rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: none;
        }
        
        .btn-ventixe:hover {
            background: #4338ca;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 20px 40px rgba(79, 70, 229, 0.35);
        }

        .text-indigo { color: #4f46e5; }
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
        <div class="glass-login-card shadow-2xl">
            <img src="assets/img/university_logo.png" alt="UOS Logo" class="brand-logo-img">
            <div class="brand-name">University of Sahiwal</div>
            <h5 class="fw-bold text-center mb-1 text-indigo">Join the Smart Campus Network</h5>
            <p class="text-muted text-center mb-5 small">Integrated Resource & Event Management System</p>
            
            <?php if($msg): ?>
                <div class="alert alert-<?= $msgType ?> border-0 rounded-4 p-3 mb-4 small fw-medium shadow-sm"><?= $msg ?></div>
            <?php endif; ?>

            <form action="" method="post" class="row g-4">
                <div class="col-12">
                    <label class="form-label small fw-bold text-dark opacity-75 ps-1">Full Name</label>
                    <input type="text" name="name" class="form-control" placeholder="Enter your full name" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label small fw-bold text-dark opacity-75 ps-1">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="example@uos.edu.pk" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label small fw-bold text-dark opacity-75 ps-1">Organization / Dept</label>
                    <input type="text" name="organization" class="form-control" placeholder="e.g. IT Department" required>
                </div>

                <div class="col-12">
                    <label class="form-label small fw-bold text-dark opacity-75 ps-1">Account Role</label>
                    <select name="role" class="form-select shadow-sm" required>
                        <option value="" disabled selected>Who are you joining as?</option>
                        <?php foreach($roles as $r): ?>
                            <option value="<?= $r['role_key'] ?>"><?= $r['role_name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label small fw-bold text-dark opacity-75 ps-1">CNIC / ID Number (Optional)</label>
                    <input type="text" name="identity_no" class="form-control" placeholder="35202-xxxxxxx-x">
                </div>

                <div class="col-md-6">
                    <label class="form-label small fw-bold text-dark opacity-75 ps-1">University Reg No (Optional)</label>
                    <input type="text" name="registration_no" class="form-control" placeholder="2022-US-123">
                </div>

                <div class="col-md-6">
                    <label class="form-label small fw-bold text-dark opacity-75 ps-1">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label small fw-bold text-dark opacity-75 ps-1">Confirm Password</label>
                    <input type="password" name="retype_password" class="form-control" placeholder="••••••••" required>
                </div>

                <div class="col-12 mt-5">
                    <button type="submit" class="btn btn-ventixe w-100 shadow-lg">Complete Registration <i class="bi bi-arrow-right-short ms-1 fs-5"></i></button>
                </div>
                
                <p class="text-center text-muted small mb-0 mt-4">
                    Already an active member? <a href="login.php" class="text-indigo fw-bold text-decoration-none">Sign In to Dashboard</a>
                </p>
            </form>
        </div>
    </div>
</body>
</html>
