<?php
require_once 'core/session.php';
require_once 'core/db.php';
require_once 'core/booking_helper.php';

$booking_id = $_GET['id'] ?? 0;

// Fetch Booking Details
$sql = "SELECT b.*, r.name as resource_name, r.type as resource_type, r.location, u.name as user_name, u.organization, u.email
        FROM bookings b
        JOIN resources r ON b.resource_id = r.id
        JOIN users u ON b.user_id = u.id
        WHERE b.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$booking_id]);
$b = $stmt->fetch();

if (!$b) {
    die("Invalid Receipt Request.");
}

// Fetch System Settings for Branding
$settings = [];
$sStmt = $pdo->query("SELECT * FROM system_settings");
while ($row = $sStmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Booking Receipt #<?= $b['id'] ?></title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/bootstrap-icons.css">
    <style>
        @media print {
            .no-print { display: none; }
            body { background: white !important; }
            .receipt-card { border: none !important; box-shadow: none !important; }
        }
        body { background: #f4f6f9; font-family: 'Inter', sans-serif; }
        .receipt-container { max-width: 800px; margin: 40px auto; }
        .receipt-card { background: white; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); overflow: hidden; border: 1px solid #eee; }
        .receipt-header { background: #111827; color: white; padding: 40px; }
        .receipt-body { padding: 40px; }
        .university-logo { height: 60px; filter: brightness(0) invert(1); }
        .receipt-label { font-size: 0.75rem; text-transform: uppercase; color: #6b7280; font-weight: 700; letter-spacing: 0.05em; }
        .receipt-value { font-size: 1rem; color: #111827; font-weight: 600; }
        .qr-placeholder { width: 120px; height: 120px; background: white; padding: 10px; border-radius: 10px; }
        .divider { height: 1px; background: #f3f4f6; margin: 25px 0; }
        .footer-note { font-size: 0.8rem; color: #9ca3af; text-align: center; margin-top: 30px; }
        .status-badge { padding: 5px 15px; border-radius: 50px; font-size: 0.8rem; font-weight: 700; }
    </style>
</head>
<body onload="generateQR()">

<div class="receipt-container">
    <div class="no-print mb-4 text-center">
        <button onclick="window.print()" class="btn btn-primary rounded-pill px-4 shadow">
            <i class="bi bi-printer me-2"></i> Print or Save as PDF
        </button>
        <a href="my_bookings.php" class="btn btn-outline-secondary rounded-pill px-4 ms-2">
            <i class="bi bi-arrow-left me-2"></i> Back to My Bookings
        </a>
    </div>

    <div class="receipt-card">
        <!-- Header Section -->
        <div class="receipt-header d-flex justify-content-between align-items-center">
            <div>
                <img src="<?= $settings['system_logo'] ?>" class="university-logo mb-3" alt="Logo">
                <h4 class="mb-0 fw-bold">Official Booking Receipt</h4>
                <p class="mb-0 opacity-75 small text-uppercase tracking-wider">Reference: #BOK-<?= str_pad($b['id'], 6, '0', STR_PAD_LEFT) ?></p>
            </div>
            <div class="text-end">
                <div class="qr-placeholder shadow-sm">
                    <canvas id="receipt-qr"></canvas>
                </div>
            </div>
        </div>

        <div class="receipt-body">
            <div class="row mb-4">
                <div class="col-md-6 border-end">
                    <div class="receipt-label mb-1">Booked By</div>
                    <div class="receipt-value"><?= htmlspecialchars($b['user_name']) ?></div>
                    <div class="text-muted small mt-1"><?= htmlspecialchars($b['email']) ?></div>
                    <div class="text-muted small"><?= htmlspecialchars($b['organization'] ?: 'University Student/Faculty') ?></div>
                </div>
                <div class="col-md-6 ps-md-4">
                    <div class="receipt-label mb-1">Receipt Status</div>
                    <div><span class="status-badge bg-success-subtle text-success border border-success-subtle"><?= strtoupper($b['status']) ?></span></div>
                    <div class="receipt-label mt-3 mb-1">Issue Date</div>
                    <div class="receipt-value small"><?= date('d F, Y | h:i A') ?></div>
                </div>
            </div>

            <div class="divider"></div>

            <div class="row align-items-center">
                <div class="col-md-7">
                    <div class="receipt-label mb-1">Resource / Venue Details</div>
                    <h3 class="fw-bold text-dark mb-1"><?= htmlspecialchars($b['resource_name']) ?></h3>
                    <span class="badge bg-light text-dark border extra-small mb-3"><?= strtoupper($b['resource_type']) ?></span>
                    
                    <div class="bg-light p-3 rounded-3 mt-2">
                        <div class="row">
                            <div class="col-6 border-end">
                                <div class="receipt-label extra-small" style="font-size: 0.6rem;">START TIME</div>
                                <div class="fw-bold small"><?= date('d M Y, h:i A', strtotime($b['start_time'])) ?></div>
                            </div>
                            <div class="col-6 ps-3">
                                <div class="receipt-label extra-small" style="font-size: 0.6rem;">END TIME</div>
                                <div class="fw-bold small"><?= date('d M Y, h:i A', strtotime($b['end_time'])) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-5 text-end">
                    <div class="receipt-label mb-1">Purpose of Visit</div>
                    <p class="fw-semibold text-dark italic d-inline-block p-2 bg-light rounded" style="font-style: italic;">"<?= htmlspecialchars($b['purpose']) ?>"</p>
                </div>
            </div>

            <div class="divider"></div>

            <div class="row align-items-center">
                <div class="col-md-7">
                    <div class="receipt-label mb-1">Financial Summary</div>
                    <div class="bg-light p-3 rounded-4">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="small text-muted">Base Hourly Rate</span>
                            <span class="fw-bold small">Rs. <?= number_format($b['total_price'] / max(1, ceil((strtotime($b['end_time']) - strtotime($b['start_time'])) / 3600)), 2) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="small text-muted">Total Duration</span>
                            <span class="fw-bold small"><?= ceil((strtotime($b['end_time']) - strtotime($b['start_time'])) / 3600) ?> Hour(s)</span>
                        </div>
                        <hr class="my-2 border-secondary-subtle">
                        <div class="d-flex justify-content-between">
                            <span class="fw-bold text-dark">Total Amount Due</span>
                            <h5 class="fw-bold text-dark mb-0">Rs. <?= number_format($b['total_price'], 2) ?></h5>
                        </div>
                    </div>
                </div>
                <div class="col-md-5 text-end">
                    <div class="receipt-label mb-1">Payment Status</div>
                    <h4 class="fw-bold <?= $b['payment_status']=='paid' ? 'text-success' : 'text-warning' ?>">
                        <i class="bi <?= $b['payment_status']=='paid' ? 'bi-check-all' : 'bi-clock-history' ?> me-1"></i>
                        <?= strtoupper($b['payment_status']) ?>
                    </h4>
                    <p class="extra-small text-muted mb-0"><?= $b['payment_status']=='paid' ? 'Transaction verified by Finance Dept.' : 'Awaiting admin verification of receipt.' ?></p>
                </div>
            </div>

            <div class="row mt-4">
                <div class="col-12 text-center">
                    <div class="p-4 border rounded-4 border-dashed bg-light-subtle">
                        <i class="bi bi-shield-check text-success display-6 d-block mb-2"></i>
                        <h6 class="fw-bold">Validated Official Document</h6>
                        <p class="text-muted extra-small mb-0">This receipt is electronically generated and verified by the Campus Resource Management System. No physical signature required.</p>
                    </div>
                </div>
            </div>

            <div class="footer-note">
                Copyright &copy; <?= date('Y') ?> <?= $settings['system_name'] ?>. All rights reserved.
            </div>
        </div>
    </div>
</div>

<script src="assets/vendor/qrious/qrious.min.js"></script>
<script>
    function generateQR() {
        new QRious({
            element: document.getElementById('receipt-qr'),
            value: '<?= $b['v_code'] ?>',
            size: 100,
            padding: 0,
            level: 'H',
            foreground: '#111827'
        });
    }
</script>

</body>
</html>
