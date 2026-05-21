<?php
require_once 'core/session.php';
require_once 'core/db.php';
require_once 'core/booking_helper.php';

$booking_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'];

// Fetch Booking Details (Ensure student can only see their own receipt unless admin)
$sql = "SELECT b.*, r.name as resource_name, r.type as resource_type, r.location, u.name as user_name, u.organization, u.email
        FROM bookings b
        JOIN resources r ON b.resource_id = r.id
        JOIN users u ON b.user_id = u.id
        WHERE b.id = ?";
$params = [$booking_id];

if ($_SESSION['role'] !== 'super_admin') {
    $sql .= " AND b.user_id = ?";
    $params[] = $user_id;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$b = $stmt->fetch();

if (!$b) {
    die("Unauthorized or Invalid Receipt Request.");
}

// Fetch System Settings for Branding
$settings = [];
$sStmt = $pdo->query("SELECT * FROM system_settings");
while ($row = $sStmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$system_name = $settings['system_name'] ?? 'University Booking System';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - <?= htmlspecialchars($b['resource_name']) ?></title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            --ventixe-dark: #0f172a;
        }
        body { 
            background: #f8fafc; 
            font-family: 'Outfit', sans-serif; 
            color: #1e293b;
        }
        .invoice-box {
            max-width: 850px;
            margin: 50px auto;
            background: white;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            position: relative;
        }
        .invoice-header {
            background: var(--ventixe-dark);
            color: white;
            padding: 60px 50px;
            position: relative;
        }
        .invoice-header::after {
            content: '';
            position: absolute;
            top: 0; right: 0;
            width: 300px; height: 100%;
            background: linear-gradient(to left, rgba(59, 130, 246, 0.1), transparent);
            pointer-events: none;
        }
        .header-logo {
            height: 50px;
            margin-bottom: 20px;
        }
        .invoice-body { padding: 50px; }
        .label-text {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #64748b;
            margin-bottom: 5px;
        }
        .value-text {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1e293b;
        }
        .status-pill {
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 700;
            display: inline-block;
        }
        .status-paid { background: #dcfce7; color: #166534; }
        .status-pending { background: #fef9c3; color: #854d0e; }
        
        .item-row {
            padding: 20px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .total-section {
            background: #f8fafc;
            border-radius: 16px;
            padding: 30px;
            margin-top: 30px;
        }
        .qr-box {
            background: white;
            padding: 10px;
            border-radius: 12px;
            display: inline-block;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        .footer-stamp {
            border: 2px dashed #e2e8f0;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            margin-top: 40px;
        }
        @media print {
            .no-print { display: none !important; }
            body { 
                background: white !important; 
                margin: 0 !important; 
                padding: 0 !important;
            }
            .invoice-box { 
                box-shadow: none !important; 
                margin: 0 !important; 
                max-width: 100% !important; 
                border: none !important;
                border-radius: 0 !important;
            }
            .invoice-header {
                background: #0f172a !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                color: white !important;
            }
            .invoice-header h1, .invoice-header p, .invoice-header .badge {
                color: white !important;
            }
            .invoice-body { padding: 30px !important; }
            .total-section {
                background: #f8fafc !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                border: 1px solid #eee !important;
            }
            .footer-stamp {
                border: 1px dashed #ccc !important;
            }
        }
    </style>
</head>
<body onload="generateQR()">

<div class="container py-5 no-print">
    <div class="d-flex justify-content-center gap-3">
        <button onclick="window.print()" class="btn btn-dark rounded-pill px-4 shadow-sm">
            <i class="bi bi-printer-fill me-2"></i> Print Document
        </button>
        <button onclick="window.location.href='my_bookings.php'" class="btn btn-outline-secondary rounded-pill px-4">
            <i class="bi bi-arrow-left me-2"></i> Dashboard
        </button>
    </div>
</div>

<div class="invoice-box">
    <div class="invoice-header">
        <div class="row align-items-center">
            <div class="col-8">
                <img src="<?= $settings['system_logo'] ?>" class="header-logo" alt="Logo">
                <h2 class="fw-bold mb-1">Official Resource Voucher</h2>
                <p class="mb-0 opacity-50 small tracking-widest">TRANSACTION ID: BOK-<?= str_pad($b['id'], 6, '0', STR_PAD_LEFT) ?></p>
            </div>
            <div class="col-4 text-end">
                <div class="qr-box">
                    <canvas id="voucher-qr"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="invoice-body">
        <div class="row mb-5">
            <div class="col-md-6 border-end">
                <div class="label-text">Issued To</div>
                <div class="value-text"><?= htmlspecialchars($b['user_name']) ?></div>
                <div class="small text-muted mt-1"><?= htmlspecialchars($b['organization'] ?: 'University Associate') ?></div>
                <div class="small text-muted"><?= htmlspecialchars($b['email']) ?></div>
            </div>
            <div class="col-md-6 ps-md-5">
                <div class="label-text">Voucher Details</div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small text-muted">Booking Status:</span>
                    <span class="status-pill <?= $b['status'] == 'approved' ? 'status-paid' : 'status-pending' ?>"><?= strtoupper($b['status']) ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="small text-muted">Issue Date:</span>
                    <span class="small fw-bold"><?= date('d M, Y') ?></span>
                </div>
            </div>
        </div>

        <h5 class="fw-bold mb-4 text-primary"><i class="bi bi-info-circle me-2"></i> Service Information</h5>
        <div class="item-row">
            <div class="row">
                <div class="col-md-7">
                    <div class="fw-bold fs-5"><?= htmlspecialchars($b['resource_name']) ?></div>
                    <div class="text-muted small"><?= htmlspecialchars($b['resource_type']) ?> &bull; <?= htmlspecialchars($b['location']) ?></div>
                    <div class="mt-2 p-2 bg-light rounded small italic">"<?= htmlspecialchars($b['purpose']) ?>"</div>
                </div>
                <div class="col-md-5 text-md-end mt-3 mt-md-0">
                    <div class="label-text">Scheduled Slot</div>
                    <div class="small fw-bold text-dark">
                        <?= date('d M, h:i A', strtotime($b['start_time'])) ?><br>
                        <i class="bi bi-arrow-down-short text-primary"></i><br>
                        <?= date('d M, h:i A', strtotime($b['end_time'])) ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="total-section">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="label-text">Payment Summary</div>
                    <div class="h3 fw-bold mb-0 text-dark">Rs. <?= number_format($b['total_price'], 2) ?></div>
                    <div class="extra-small text-muted mt-1">Total inclusive of all reservation charges.</div>
                </div>
                <div class="col-md-6 text-md-end mt-3 mt-md-0">
                    <div class="label-text">Payment Status</div>
                    <div class="h5 fw-bold mb-1 <?= $b['payment_status'] == 'paid' ? 'text-success' : 'text-warning' ?>">
                        <i class="bi <?= $b['payment_status'] == 'paid' ? 'bi-patch-check-fill' : 'bi-hourglass-split' ?> me-1"></i>
                        <?= strtoupper($b['payment_status']) ?>
                    </div>
                    <?php if($b['payment_status'] == 'paid'): ?>
                        <div class="extra-small text-success fw-bold">Digitally Verified by Finance</div>
                    <?php else: ?>
                        <div class="extra-small text-warning fw-bold">Verification Request Pending</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="footer-stamp">
            <i class="bi bi-shield-check text-primary fs-3 d-block mb-1"></i>
            <h6 class="fw-bold mb-1">Authenticity Guaranteed</h6>
            <p class="extra-small text-muted mb-0">
                This document is a legally binding reservation voucher generated by <?= $system_name ?>.<br>
                For any disputes, please contact the Administration at <?= htmlspecialchars($settings['contact_email'] ?? '') ?>.
            </p>
        </div>
    </div>
    
    <div class="p-3 bg-light text-center extra-small text-muted">
        &copy; <?= date('Y') ?> <?= $system_name ?>. All Rights Reserved.
    </div>
</div>

<script src="assets/vendor/qrious/qrious.min.js"></script>
<script>
    function generateQR() {
        new QRious({
            element: document.getElementById('voucher-qr'),
            value: '<?= $b['v_code'] ?>',
            size: 100,
            padding: 0,
            level: 'H',
            foreground: '#0f172a'
        });
    }
</script>
</body>
</html>
