<?php
require_once 'core/session.php';
require_once 'core/db.php';
require_once 'core/booking_helper.php';

$user_id = $_SESSION['user_id'];
$title = "My Events";
$hideDefaultHeader = true;
require_once 'includes/header.php';

// Fetch events created by this user
$stmt = $pdo->prepare("
    SELECT e.*, 
           (SELECT COUNT(*) FROM bookings WHERE event_id = e.id) as booking_count
    FROM events e 
    WHERE e.organizer_id = ? 
    ORDER BY e.created_at DESC
");
$stmt->execute([$user_id]);
$events = $stmt->fetchAll();
?>

<div class="container-fluid py-4">
    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 rounded-4 mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($_GET['msg']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Header Card -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="ventixe-card bg-dark text-white p-4 d-flex align-items-center justify-content-between shadow-sm border-0 rounded-4">
                <div>
                    <h2 class="fw-bold mb-0 text-white"><i class="bi bi-calendar4-event me-2 text-primary"></i> My Event Requests</h2>
                    <p class="mb-0 text-white-50 small">Track and manage your planned campus activities</p>
                </div>
                <div class="d-none d-md-block text-end">
                    <a href="request_event.php" class="btn btn-primary rounded-pill px-4 shadow-sm">
                        <i class="bi bi-plus-circle me-1"></i> New Request
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <?php if (empty($events)): ?>
            <div class="col-12 text-center py-5">
                <div class="opacity-25 mb-3">
                    <i class="bi bi-calendar-x" style="font-size: 5rem;"></i>
                </div>
                <h4 class="text-muted">No events found</h4>
                <p class="text-muted small">You haven't requested any events yet.</p>
                <a href="request_event.php" class="btn btn-outline-primary rounded-pill px-4">Create Your First Event</a>
            </div>
        <?php else: ?>
            <?php foreach ($events as $e): 
                $statusColor = 'secondary';
                $statusIcon = 'clock';
                
                switch($e['status']) {
                    case 'approved': $statusColor = 'success'; $statusIcon = 'check-circle-fill'; break;
                    case 'planning': $statusColor = 'info'; $statusIcon = 'pencil-square'; break;
                    case 'pending_approval': $statusColor = 'warning'; $statusIcon = 'hourglass-split'; break;
                    case 'rejected': $statusColor = 'danger'; $statusIcon = 'x-circle-fill'; break;
                }
            ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card ventixe-card border-0 shadow-sm rounded-4 h-100 overflow-hidden">
                        <div class="card-header border-0 bg-transparent pt-3 px-3 pb-0 d-flex justify-content-between align-items-start">
                             <span class="badge bg-<?= $statusColor ?>-subtle text-<?= $statusColor ?> rounded-pill border border-<?= $statusColor ?>-subtle px-3 py-2 small">
                                <i class="bi bi-<?= $statusIcon ?> me-1"></i> <?= strtoupper(str_replace('_', ' ', $e['status'])) ?>
                            </span>
                            <small class="text-muted"><?= date('M d, Y', strtotime($e['created_at'])) ?></small>
                        </div>
                        <div class="card-body p-4">
                            <h5 class="fw-bold text-dark mb-2"><?= htmlspecialchars($e['title']) ?></h5>
                            <p class="text-muted small mb-3 line-clamp-2"><?= htmlspecialchars($e['description'] ?: 'No description provided.') ?></p>
                            
                            <hr class="opacity-10 my-3">
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="small">
                                    <i class="bi bi-tag-fill text-muted me-1"></i>
                                    <span class="text-muted"><?= htmlspecialchars($e['event_type'] ?: 'General') ?></span>
                                </div>
                                <div class="small">
                                    <i class="bi bi-box-seam text-muted me-1"></i>
                                    <span class="fw-bold"><?= $e['booking_count'] ?></span> <span class="text-muted">Bookings</span>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-light border-0 p-3">
                            <?php if ($e['status'] === 'approved' || $e['status'] === 'planning'): ?>
                                <div class="d-grid">
                                    <a href="resources_list.php?event_id=<?= $e['id'] ?>" class="btn btn-sm btn-primary rounded-pill py-2">
                                        <i class="bi bi-plus-lg me-1"></i> Add Booking
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="d-grid">
                                    <button class="btn btn-sm btn-secondary disabled rounded-pill py-2" disabled>
                                        <i class="bi bi-lock-fill me-1"></i> Booking Locked
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<style>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;  
    overflow: hidden;
}
</style>

<?php require_once 'includes/footer.php'; ?>
