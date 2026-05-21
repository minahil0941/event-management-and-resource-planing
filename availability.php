<?php
require_once 'core/session.php';
require_once 'core/db.php';
require_once 'core/booking_helper.php';

$resource_id = $_GET['id'] ?? 0;

// Case 1: No Resource Selected - Show Selection Gallery
if (!$resource_id) {
    $stmt = $pdo->query("SELECT * FROM resources WHERE status = 'available'");
    $all_resources = $stmt->fetchAll();
    
    $title = "Availability Map | Select Venue";
    $hideDefaultHeader = true;
    include 'includes/header.php';
    ?>
    <div class="ventixe-hero fade-in-up">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="fw-bold text-white mb-2">Available Venues Map</h1>
                <p class="text-white-50 mb-0">Select a specialized university resource to view its interactive schedule and free slots in real-time.</p>
            </div>
            <div class="col-md-4 text-end d-none d-md-block">
                <i class="bi bi-map text-white-50" style="font-size: 5rem;"></i>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <?php foreach ($all_resources as $res): ?>
        <div class="col-md-4">
            <div class="card ventixe-card border-0 shadow-sm h-100 overflow-hidden">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="p-3 bg-indigo-subtle text-indigo rounded-3 me-3">
                            <i class="bi <?= $res['type'] === 'lab' ? 'bi-pc-display' : ($res['type'] === 'auditorium' ? 'bi-mic-fill' : 'bi-building') ?> fs-4"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0"><?= htmlspecialchars($res['name']) ?></h5>
                            <span class="badge bg-light text-dark extra-small border"><?= strtoupper($res['type']) ?></span>
                        </div>
                    </div>
                    <p class="text-muted small mb-4" style="line-height: 1.5; height: 3.2em; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">
                        <?= htmlspecialchars(strip_tags($res['description'])) ?>
                    </p>
                    <a href="availability.php?id=<?= $res['id'] ?>" class="btn btn-primary w-100 rounded-pill fw-bold shadow-sm">
                        View Schedule <i class="bi bi-calendar3 ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php
    include 'includes/footer.php';
    exit;
}

// Case 2: Specific Resource Selected - Show Calendar
$stmt = $pdo->prepare("SELECT * FROM resources WHERE id = ?");
$stmt->execute([$resource_id]);
$resource = $stmt->fetch();

if (!$resource) {
    die('<div class="alert alert-danger m-5">Resource not found. <a href="availability.php">Go Back</a></div>');
}

$title = "Availability: " . $resource['name'];
$hideDefaultHeader = true;
include 'includes/header.php';

// Handle Conflict Check Form
$check_result = null;
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['check_slot'])) {
    $start = $_POST['start_time'];
    $end = $_POST['end_time'];
    
    if (isResourceAvailable($pdo, $resource_id, $start, $end)) {
        $check_result = ['status' => 'success', 'message' => '✅ This slot is FREE! You can proceed with booking.'];
    } else {
        $check_result = ['status' => 'danger', 'message' => '❌ This slot is ALREADY BOOKED. Please choose another time.'];
    }
}

// Fetch approved bookings for this resource (Visual Schedule)
$stmt = $pdo->prepare("SELECT b.*, e.title as event_title FROM bookings b LEFT JOIN events e ON b.event_id = e.id WHERE b.resource_id = ? AND b.status = 'approved' AND b.end_time >= NOW() ORDER BY b.start_time ASC");
$stmt->execute([$resource_id]);
$bookings = $stmt->fetchAll();
?>

<!-- FullCalendar Dependencies -->
<script src="assets/vendor/fullcalendar/index.global.min.js"></script>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="ventixe-card bg-indigo text-white p-4 d-flex align-items-center justify-content-between shadow-sm border-0 rounded-4">
            <div>
                <h2 class="fw-bold mb-0 text-white"><i class="bi bi-calendar-event me-2"></i> Resource Schedule</h2>
                <p class="mb-0 text-white-50 small">Viewing availability timeline for <strong><?= htmlspecialchars($resource['name']) ?></strong></p>
            </div>
            <div class="d-none d-md-block text-end">
                <a href="availability.php" class="btn btn-outline-light rounded-pill px-3 shadow-sm btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Switch Resource
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- 1. Visual Calendar View -->
    <div class="col-xl-8">
        <div class="card ventixe-card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="fw-bold mb-0">Interactive Schedule</h5>
                        <p class="text-muted extra-small mb-0">Visual timeline of reservations for <?= htmlspecialchars($resource['name']) ?></p>
                    </div>
                </div>
            </div>
            <div class="card-body p-4">
                <div id="calendar" style="min-height: 500px;"></div>
            </div>
        </div>
    </div>

    <!-- 2. Side Panel Info -->
    <div class="col-xl-4">
        <div class="card ventixe-card border-0 shadow-sm mb-4 bg-indigo text-white overflow-hidden p-1">
            <div class="card-body p-4 position-relative z-index-1">
                <div class="d-flex align-items-center mb-3">
                    <div class="p-2 bg-white bg-opacity-25 rounded-3 me-3">
                        <i class="bi bi-info-circle-fill text-white fs-4"></i>
                    </div>
                    <h6 class="text-white-50 small mb-0 fw-bold text-uppercase tracking-wider">Resource Snapshot</h6>
                </div>
                <h3 class="fw-bold mb-3"><?= htmlspecialchars($resource['name']) ?></h3>
                <div class="d-flex flex-wrap gap-2 mb-0">
                    <span class="badge bg-white bg-opacity-10 text-white border border-white border-opacity-25 rounded-pill px-3 py-2 small">
                        <i class="bi bi-people-fill me-1"></i> <?= $resource['capacity'] ?> Seats
                    </span>
                    <span class="badge bg-white bg-opacity-10 text-white border border-white border-opacity-25 rounded-pill px-3 py-2 small">
                        <i class="bi bi-geo-alt-fill me-1"></i> <?= htmlspecialchars($resource['location']) ?>
                    </span>
                </div>
                <!-- Abstract Design Ornament -->
                <i class="bi bi-cpu position-absolute bottom-0 end-0 m-n4 opacity-10" style="font-size: 10rem;"></i>
            </div>
        </div>

        <div class="card ventixe-card border-0 shadow-sm mb-4 overflow-hidden">
            <div class="card-header bg-light border-0 py-3 px-4">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-shield-check me-1 text-primary"></i> Conflict Checker</h6>
            </div>
            <form method="POST">
                <div class="card-body p-4">
                    <?php if ($check_result): ?>
                        <div class="alert alert-<?php echo $check_result['status']; ?> shadow-sm small">
                            <?php echo $check_result['message']; ?>
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Start Time</label>
                        <input type="datetime-local" name="start_time" class="form-control rounded-3" required value="<?php echo $_POST['start_time'] ?? ''; ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">End Time</label>
                        <input type="datetime-local" name="end_time" class="form-control rounded-3" required value="<?php echo $_POST['end_time'] ?? ''; ?>">
                    </div>
                    <button type="submit" name="check_slot" class="btn btn-indigo w-100 rounded-pill fw-bold py-2 mt-2">
                        <i class="bi bi-search me-1"></i> Check Slot
                    </button>
                    <a href="book_resource.php?id=<?= $resource_id ?>" class="btn btn-outline-success w-100 rounded-pill fw-bold py-2 mt-3">
                        Book Now <i class="bi bi-plus-lg ms-1"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        themeSystem: 'bootstrap5',
        events: [
            <?php foreach ($bookings as $b): ?>
            {
                title: <?= json_encode(!empty($b['event_title']) ? $b['event_title'] : $b['purpose']) ?>,
                start: '<?php echo str_replace(' ', 'T', $b['start_time']); ?>',
                end: '<?php echo str_replace(' ', 'T', $b['end_time']); ?>',
                backgroundColor: '#4f46e5',
                borderColor: '#4f46e5',
                allDay: false
            },
            <?php endforeach; ?>
        ]
    });
    calendar.render();
});
</script>

<?php include 'includes/footer.php'; ?>
