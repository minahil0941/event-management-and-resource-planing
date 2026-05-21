<?php
$hideDefaultHeader = true;
require_once 'includes/header.php'; 

// Fetch Counts for Admin
$userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$roleCount = $pdo->query("SELECT COUNT(*) FROM sys_roles")->fetchColumn();
$pageCount = $pdo->query("SELECT COUNT(*) FROM sys_pages")->fetchColumn();
$bookingCount = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$pendingRegCount = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 0")->fetchColumn();
$activeMaintCount = $pdo->query("SELECT COUNT(*) FROM sys_maintenance WHERE end_time > NOW()")->fetchColumn();

// Fetch specific data for current user
$myPerms = $pdo->prepare("SELECT COUNT(*) FROM role_access WHERE role_key = ?");
$myPerms->execute([$_SESSION['role']]);
$myPermCount = $myPerms->fetchColumn();

// Analytics Queries (Admin Only)
$bookingTrends = [];
$resourceStats = [];
$orgStats = [];

if ($_SESSION['role'] === 'super_admin') {
    // 1. Booking Trends (Last 6 Months)
    $stmt = $pdo->query("SELECT DATE_FORMAT(created_at, '%b %Y') as month, COUNT(*) as count 
                        FROM bookings 
                        GROUP BY DATE_FORMAT(created_at, '%b %Y'), YEAR(created_at), MONTH(created_at)
                        ORDER BY YEAR(created_at) ASC, MONTH(created_at) ASC LIMIT 6");
    $bookingTrends = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Popular Resources
    $stmt = $pdo->query("SELECT r.name as resource_name, COUNT(b.id) as count 
                        FROM resources r 
                        LEFT JOIN bookings b ON r.id = b.resource_id 
                        GROUP BY r.id 
                        ORDER BY count DESC LIMIT 5");
    $resourceStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Top Organizations
    $stmt = $pdo->query("SELECT u.organization, COUNT(b.id) as count 
                        FROM users u 
                        JOIN bookings b ON u.id = b.user_id 
                        WHERE u.organization IS NOT NULL AND u.organization != ''
                        GROUP BY u.organization 
                        ORDER BY count DESC LIMIT 5");
    $orgStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Personalized Stats for Non-Admins ("Outsiders")
    $uid = $_SESSION['user_id'];
    
    $pendingStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ? AND status = 'pending'");
    $pendingStmt->execute([$uid]);
    $myPendingCount = $pendingStmt->fetchColumn();

    $approvedStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ? AND status = 'approved'");
    $approvedStmt->execute([$uid]);
    $myApprovedCount = $approvedStmt->fetchColumn();

    $eventsStmt = $pdo->prepare("SELECT COUNT(*) FROM events WHERE organizer_id = ?");
    $eventsStmt->execute([$uid]);
    $myTotalEventsCount = $eventsStmt->fetchColumn();
}

// Fetch Live Occupancy Data for all roles
require_once 'core/booking_helper.php';
$liveOccupancy = getOccupancyTickerData($pdo);

// Fetch Free Resources Count (Currently not busy)
$freeResStmt = $pdo->query("SELECT COUNT(*) FROM resources WHERE status = 'available' AND id NOT IN (
    SELECT resource_id FROM bookings WHERE status = 'approved' AND NOW() BETWEEN start_time AND end_time
)");
$freeCount = $freeResStmt->fetchColumn();
?>

<div class="row mb-4">
    <div class="col-12 text-end">
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-2 shadow-sm">
            <i class="bi bi-shield-check me-1"></i> Ventixe Smart Pulse: V1.1 (Enhanced)
        </span>
    </div>
</div>

<!-- Ventixe Smart Pulse Ticker -->
<div class="row mb-4">
    <div class="col-12">
        <div class="ventixe-card bg-dark text-white p-2 d-flex align-items-center shadow-lg border-0 rounded-pill overflow-hidden" style="background: linear-gradient(90deg, #0f172a 0%, #1e1b4b 100%) !important;">
            <div class="badge bg-primary ms-2 me-3 py-2 px-3 shadow-sm">
                <i class="bi bi-activity me-1"></i> SMART PULSE
            </div>
            <div class="ticker-wrapper flex-grow-1 overflow-hidden">
                <style>
                    .ticker-content { display: inline-block; white-space: nowrap; animation: ticker 40s linear infinite; }
                    @keyframes ticker { 0% { transform: translateX(50%); } 100% { transform: translateX(-100%); } }
                    .animate-pulse { animation: pulse 2s infinite; }
                    @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.6; } 100% { opacity: 1; } }
                    .ticker-item { border-right: 1px solid rgba(255,255,255,0.1); padding-right: 2rem; margin-right: 2rem; }
                </style>
                <div class="ticker-content py-1">
                    <!-- 1. General Status -->
                    <span class="ticker-item small text-white-50">
                        <i class="bi bi-info-circle text-info me-1"></i> 
                        <strong class="text-white"><?= $freeCount ?></strong> Resources are currently free for booking.
                    </span>

                    <!-- 2. Dynamic Occupancy & Upcoming -->
                    <?php if (!empty($liveOccupancy)): ?>
                        <?php foreach ($liveOccupancy as $occ): 
                            $isLive = ($occ['ticker_type'] === 'LIVE');
                            $badgeClass = $isLive ? 'bg-danger' : 'bg-info';
                            $iconClass = $isLive ? 'bi-record-circle-fill' : 'bi-clock-history';
                            $statusText = $isLive ? 'BUSY' : 'SOON';
                            $timePrefix = $isLive ? 'Until' : 'Starts at';
                            
                            // Resource Icon Logic
                            $resIcon = 'bi-building';
                            if ($occ['resource_type'] === 'lab') $resIcon = 'bi-pc-display';
                            if ($occ['resource_type'] === 'ground') $resIcon = 'bi-trophy';
                        ?>
                            <span class="ticker-item small">
                                <span class="badge <?= $badgeClass ?> rounded-pill me-2 py-1 px-2 <?= $isLive ? 'animate-pulse' : '' ?>" style="font-size: 0.65rem;">
                                    <i class="bi <?= $iconClass ?> me-1"></i> <?= $statusText ?>
                                </span>
                                <i class="bi <?= $resIcon ?> text-warning me-1"></i>
                                <strong class="text-white"><?= htmlspecialchars($occ['resource_name']) ?>:</strong> 
                                <span class="text-white-50"><?= htmlspecialchars($occ['user_name']) ?></span> 
                                <span class="text-info ms-1"><?= $timePrefix ?> <?= date('H:i', strtotime($isLive ? $occ['end_time'] : $occ['start_time'])) ?></span>
                            </span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="ticker-item small text-white-50">
                            <i class="bi bi-stars text-warning me-1"></i> All systems reporting normal. No major events scheduled for the next few hours.
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Energetic Hero Section -->
<div class="ventixe-hero fade-in-up">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h1>Welcome back, <?= explode(' ', $_SESSION['name'])[0] ?>! 👋</h1>
            <p>Manage your events and resource bookings efficiently from one centralized hub.</p>
            <div class="mt-4">
                <a href="resources_list.php" class="btn btn-light rounded-pill px-4 fw-bold text-primary shadow-sm">
                    <i class="bi bi-plus-lg me-1"></i> New Booking
                </a>
            </div>
        </div>
        <div class="col-md-4 d-none d-md-block text-end">
            <div class="h4 mb-0 fw-bold"><?= date('l') ?></div>
            <div class="opacity-75"><?= date('M d, Y') ?></div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <?php if($_SESSION['role'] === 'super_admin'): ?>
    <div class="col-lg-3 col-6 fade-in-up delay-1">
        <div class="ventixe-card stat-box stat-purple">
            <i class="bi bi-people-fill"></i>
            <div class="count"><?= $userCount ?></div>
            <div class="label">Registered Users</div>
            <a href="dashboards/super_admin/manage_users.php" class="stretched-link"></a>
        </div>
    </div>
    <div class="col-lg-3 col-6 fade-in-up delay-2">
        <div class="ventixe-card stat-box stat-pink">
            <i class="bi bi-shield-check"></i>
            <div class="count"><?= $roleCount ?></div>
            <div class="label">System Roles</div>
            <a href="dashboards/super_admin/manage_roles.php" class="stretched-link"></a>
        </div>
    </div>
    <div class="col-lg-3 col-6 fade-in-up delay-3">
        <div class="ventixe-card stat-box stat-indigo">
            <i class="bi bi-tools"></i>
            <div class="count"><?= $activeMaintCount ?></div>
            <div class="label">Active Maintenance</div>
            <a href="dashboards/super_admin/manage_maintenance.php" class="stretched-link"></a>
        </div>
    </div>
    <div class="col-lg-3 col-6 fade-in-up delay-4">
        <div class="ventixe-card stat-box stat-blue">
            <i class="bi bi-person-check-fill"></i>
            <div class="count"><?= $pendingRegCount ?></div>
            <div class="label">Pending Signups</div>
            <a href="dashboards/super_admin/manage_users.php?view=pending" class="stretched-link"></a>
        </div>
    </div>
    <?php endif; ?>

    <?php if($_SESSION['role'] !== 'super_admin'): ?>
    <!-- Outsider / Student Personalized Stats -->
    <div class="col-lg-4 col-md-6 mb-4 fade-in-up delay-1">
        <div class="ventixe-card stat-box stat-purple h-100">
            <i class="bi bi-calendar4-event"></i>
            <div class="count"><?= $myTotalEventsCount ?></div>
            <div class="label">My Event Requests</div>
            <a href="my_events.php" class="stretched-link"></a>
        </div>
    </div>
    <div class="col-lg-4 col-md-6 mb-4 fade-in-up delay-2">
        <div class="ventixe-card stat-box stat-green h-100">
            <i class="bi bi-check-circle-fill"></i>
            <div class="count"><?= $myApprovedCount ?></div>
            <div class="label">Approved Bookings</div>
            <a href="my_bookings.php" class="stretched-link"></a>
        </div>
    </div>
    <div class="col-lg-4 col-md-6 mb-4">
        <div class="ventixe-card stat-box stat-yellow h-100">
            <i class="bi bi-hourglass-split"></i>
            <div class="count"><?= $myPendingCount ?></div>
            <div class="label">Pending Approvals</div>
            <a href="my_bookings.php" class="stretched-link"></a>
        </div>
    </div>

    <!-- Quick Actions Panel for Outsiders -->
    <div class="col-12 mb-2">
        <div class="card ventixe-card border-0 shadow-sm p-3">
            <h6 class="fw-bold mb-3 text-primary"><i class="bi bi-lightning-charge-fill me-1"></i> Quick Actions</h6>
            <div class="d-flex flex-wrap gap-2">
                <a href="resources_list.php" class="btn btn-outline-primary rounded-pill btn-sm">
                    <i class="bi bi-plus-circle me-1"></i> Book a Resource
                </a>
                <a href="request_event.php" class="btn btn-outline-success rounded-pill btn-sm">
                    <i class="bi bi-calendar-plus me-1"></i> Request Event
                </a>
                <a href="#" data-bs-toggle="modal" data-bs-target="#digitalIdModal" class="btn btn-outline-dark rounded-pill btn-sm">
                    <i class="bi bi-person-badge me-1"></i> My Digital ID
                </a>
                <a href="my_waitlist.php" class="btn btn-outline-warning rounded-pill btn-sm">
                    <i class="bi bi-list-ol me-1"></i> Waitlist Status
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- Featured Venues (Minahil's Visual Overhaul) -->
<div class="row g-4 mb-5">
    <div class="col-12">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h4 class="fw-bold mb-0"><i class="bi bi-star-fill text-warning me-2"></i> Featured University Venues</h4>
            <a href="resources_list.php" class="btn btn-sm btn-outline-primary rounded-pill px-3">View All Catalog</a>
        </div>
    </div>
    <?php 
    // Fetch 3 top resources for the dashboard
    $featuredStmt = $pdo->query("SELECT * FROM resources WHERE status = 'available' LIMIT 3");
    $featuredResources = $featuredStmt->fetchAll();
    
    foreach ($featuredResources as $feat): 
        $occ = getCurrentOccupancy($pdo, $feat['id']);
        $img_src = $feat['image_url'] ?: 'assets/img/boxed-bg.jpg';
        if ($feat['image_url'] && strpos($feat['image_url'], 'http') !== 0) { 
            $img_src = BASE_URL . $feat['image_url']; 
        }
    ?>
    <div class="col-md-4">
        <div class="card ventixe-card border-0 shadow-sm overflow-hidden h-100" style="min-height: 250px;">
            <div class="position-relative h-100">
                <img src="<?= $img_src ?>?v=<?= time() ?>" class="w-100 h-100 object-fit-cover position-absolute top-0 start-0" alt="<?= $feat['name'] ?>">
                <div class="position-absolute top-0 start-0 w-100 h-100 bg-dark opacity-50"></div>
                
                <!-- Status Badge -->
                <div class="position-absolute top-0 end-0 m-3 z-index-1">
                    <?php 
                        $under_maintenance = isResourceMaintenance($pdo, $feat['id']);
                        if ($under_maintenance): 
                    ?>
                        <span class="badge rounded-pill bg-warning text-dark border border-white shadow-sm px-3 py-2">
                            <i class="bi bi-tools me-1"></i> MAINTENANCE
                        </span>
                    <?php elseif ($occ): ?>
                        <span class="badge rounded-pill bg-danger border border-white shadow-sm px-3 py-2 animate-pulse">
                            <i class="bi bi-record-fill me-1"></i> BUSY
                        </span>
                    <?php else: ?>
                        <span class="badge rounded-pill bg-success border border-white shadow-sm px-3 py-2">
                            <i class="bi bi-check-circle-fill me-1"></i> FREE
                        </span>
                    <?php endif; ?>
                </div>

                <div class="position-absolute bottom-0 start-0 p-4 w-100 text-white">
                    <span class="badge bg-primary-subtle text-primary border-0 small mb-2 px-2 py-1"><?= ucfirst($feat['type']) ?></span>
                    <h4 class="fw-bold mb-1"><?= htmlspecialchars($feat['name']) ?></h4>
                    <p class="small opacity-75 mb-3"><i class="bi bi-geo-alt me-1"></i> <?= htmlspecialchars($feat['location']) ?></p>
                    <a href="book_resource.php?id=<?= $feat['id'] ?>" class="btn btn-sm btn-light rounded-pill px-4 fw-bold">Book Now</a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if ($_SESSION['role'] === 'super_admin'): ?>
<!-- Analytics Section -->
<div class="row mt-4">
    <div class="col-md-8">
        <div class="card ventixe-card mb-4">
            <div class="card-header ventixe-chart-header">
                <h6><i class="bi bi-graph-up-arrow text-primary"></i> Booking Overview</h6>
                <small class="text-muted">Tracking requests across the last 6 months</small>
            </div>
            <div class="card-body">
                <div class="chart-area" style="height: 350px;">
                    <canvas id="bookingTrendChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card ventixe-card mb-4">
            <div class="card-header ventixe-chart-header">
                <h6><i class="bi bi-pie-chart text-success"></i> Category Split</h6>
                <small class="text-muted">Resource usage distribution</small>
            </div>
            <div class="card-body">
                <canvas id="resourcePieChart" style="max-height: 350px;"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card ventixe-card mb-4">
            <div class="card-header ventixe-chart-header">
                <h6><i class="bi bi-building text-info"></i> Organizational Engagement</h6>
                <small class="text-muted">Top contributing organizations</small>
            </div>
            <div class="card-body">
                <div style="height: 300px;">
                    <canvas id="orgBarChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="assets/vendor/chartjs/chart.umd.js"></script>
<script>
    // 1. Booking Overview
    const trendCtx = document.getElementById('bookingTrendChart');
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_column($bookingTrends, 'month')) ?>,
            datasets: [{
                label: 'Bookings',
                data: <?= json_encode(array_column($bookingTrends, 'count')) ?>,
                borderColor: '#7c3aed',
                backgroundColor: 'rgba(124, 58, 237, 0.08)',
                fill: true,
                tension: 0.5,
                pointRadius: 6,
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#7c3aed',
                pointBorderWidth: 2
            }]
        },
        options: { 
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { grid: { color: 'rgba(0,0,0,0.03)' }, border: { display: false } },
                x: { grid: { display: false }, border: { display: false } }
            }
        }
    });

    // 2. Category Split
    const resourceCtx = document.getElementById('resourcePieChart');
    new Chart(resourceCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_column($resourceStats, 'resource_name')) ?>,
            datasets: [{
                data: <?= json_encode(array_column($resourceStats, 'count')) ?>,
                backgroundColor: ['#7c3aed', '#db2777', '#f59e0b', '#3b82f6', '#10b981'],
                hoverOffset: 15,
                borderWidth: 0
            }]
        },
        options: {
            plugins: { legend: { position: 'bottom', labels: { padding: 20, usePointStyle: true } } },
            cutout: '75%'
        }
    });

    // 3. Org Engagement - Refined Soft Horizontal Bar
    const orgCanvas = document.getElementById('orgBarChart');
    const orgCtx = orgCanvas.getContext('2d');

    new Chart(orgCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($orgStats, 'organization')) ?>,
            datasets: [{
                label: 'Total Bookings',
                data: <?= json_encode(array_column($orgStats, 'count')) ?>,
                backgroundColor: 'rgba(124, 58, 237, 0.7)', // Softer Ventixe Purple
                hoverBackgroundColor: 'rgba(124, 58, 237, 1)',
                borderRadius: 12, // Softer pills
                borderSkipped: false,
                barThickness: 16 // Thinner, more elegant bars
            }]
        },
        options: { 
            indexAxis: 'y', // Convert to horizontal bar
            maintainAspectRatio: false,
            layout: {
                padding: { left: 10, right: 20, top: 10, bottom: 10 }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(255, 255, 255, 0.95)',
                    titleColor: '#1e293b',
                    bodyColor: '#64748b',
                    borderColor: 'rgba(0,0,0,0.05)',
                    borderWidth: 1,
                    padding: 12,
                    titleFont: { family: 'Outfit', size: 13 },
                    bodyFont: { family: 'Outfit', size: 12 },
                    cornerRadius: 8,
                    boxPadding: 4
                }
            },
            scales: {
                x: { 
                    grid: { color: 'rgba(0,0,0,0.04)', drawTicks: false }, 
                    border: { display: false }, 
                    ticks: { font: { family: 'Outfit', size: 11 }, padding: 10 } 
                },
                y: { 
                    grid: { display: false }, 
                    border: { display: false }, 
                    ticks: { 
                        font: { family: 'Outfit', weight: '500', size: 12 }, 
                        color: '#64748b',
                        crossAlign: 'far', // Align text properly
                        padding: 10 // Space between text and bars
                    } 
                }
            }
        }
    });
</script>
<?php endif; ?>

<div class="card ventixe-card mt-5 py-4">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-1 text-center">
                <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px; font-size: 1.5rem;">
                    <i class="bi bi-lightning-charge-fill"></i>
                </div>
            </div>
            <div class="col-md-8">
                <h4 class="mb-1">Hello, <?= htmlspecialchars($_SESSION['name']) ?>!</h4>
                <p class="text-muted mb-0">You're currently managing the system as <span class="fw-bold text-primary"><?= ucfirst(str_replace('_', ' ', $_SESSION['role'])) ?></span>. Here's what's happening today.</p>
            </div>
            <div class="col-md-3 text-end">
                <a href="profile.php" class="btn btn-ventixe shadow-sm me-2">My Profile</a>
                <a href="logout.php" class="btn btn-outline-danger" style="border-radius: 12px;">Logout</a>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>