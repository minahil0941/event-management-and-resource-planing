<?php
require_once 'core/session.php';
require_once 'core/db.php';

$pageTitle = "My Notifications";
$hideDefaultHeader = true;
include 'includes/header.php';

$user_id = $_SESSION['user_id'];

// Handle Mark as Read
if (isset($_GET['mark_read'])) {
    $notif_id = $_GET['mark_read'];
    $stmt = $pdo->prepare("UPDATE sys_notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$notif_id, $user_id]);
    header("Location: notifications.php");
    exit;
}

// Handle Mark All as Read
if (isset($_GET['mark_all'])) {
    $stmt = $pdo->prepare("UPDATE sys_notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$user_id]);
    header("Location: notifications.php");
    exit;
}

// Fetch All Notifications
$stmt = $pdo->prepare("SELECT * FROM sys_notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$allNotifs = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="ventixe-card bg-primary text-white p-4 d-flex align-items-center justify-content-between shadow-sm border-0 rounded-4">
            <div>
                <h2 class="fw-bold mb-0 text-white"><i class="bi bi-bell-fill me-2"></i> Notification Center</h2>
                <p class="mb-0 text-white-50 small">Stay updated on your booking status and system alerts.</p>
            </div>
            <div class="d-none d-md-block">
                <a href="?mark_all=1" class="btn btn-outline-light rounded-pill px-3 shadow-sm btn-sm">
                    <i class="bi bi-check2-all me-1"></i> Mark All as Read
                </a>
            </div>
        </div>
    </div>
</div>

    <div class="row">
        <div class="col-lg-8 mx-auto">
            <?php if (empty($allNotifs)): ?>
                <div class="text-center py-5 ventixe-card">
                    <i class="bi bi-bell-slash display-1 opacity-25"></i>
                    <p class="mt-3 text-muted">You have no notifications at the moment.</p>
                </div>
            <?php else: ?>
                <div class="ventixe-card overflow-hidden">
                    <div class="list-group list-group-flush">
                        <?php foreach ($allNotifs as $n): ?>
                            <div class="list-group-item p-4 border-0 mb-2 rounded-4 <?= $n['is_read'] ? 'bg-white' : 'bg-light-subtle shadow-sm' ?>" style="border-left: 5px solid var(--bs-<?= $n['type'] ?>) !important;">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="d-flex">
                                        <div class="p-3 bg-<?= $n['type'] ?>-subtle text-<?= $n['type'] ?> rounded-circle me-4 h-100">
                                            <i class="bi <?= $n['type'] === 'success' ? 'bi-check-circle-fill' : ($n['type'] === 'danger' ? 'bi-exclamation-octagon-fill' : 'bi-info-circle-fill') ?> fs-4"></i>
                                        </div>
                                        <div>
                                            <?php 
                                            $nTitle = "Notification Center";
                                            if($n['type'] == 'success') $nTitle = "Task Success";
                                            if($n['type'] == 'danger') $nTitle = "System Alert";
                                            if($n['type'] == 'warning') $nTitle = "Action Required";
                                            if($n['type'] == 'info') $nTitle = "Update Notice";
                                            ?>
                                            <h6 class="fw-bold mb-1 <?= $n['is_read'] ? 'text-dark' : 'text-primary' ?>">
                                                <?= $n['is_read'] ? '' : '<span class="badge bg-primary me-2">NEW</span>' ?>
                                                <?= $nTitle ?>
                                            </h6>
                                            <p class="mb-2 text-secondary"><?= htmlspecialchars($n['message']) ?></p>
                                            <div class="small d-flex gap-3">
                                                <span class="text-muted"><i class="bi bi-clock"></i> <?= date('d M Y, h:i A', strtotime($n['created_at'])) ?></span>
                                                <?php if($n['link_url']): ?>
                                                    <a href="<?= BASE_URL . htmlspecialchars($n['link_url']) ?>" class="fw-bold text-decoration-none border-bottom border-primary pb-1">Take Action <i class="bi bi-arrow-right"></i></a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php if(!$n['is_read']): ?>
                                        <a href="?mark_read=<?= $n['id'] ?>" class="btn btn-light btn-sm rounded-circle shadow-sm" title="Mark as Read">
                                            <i class="bi bi-check-lg"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
