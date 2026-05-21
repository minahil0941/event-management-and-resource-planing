<?php
require_once 'core/session.php';
require_once 'core/db.php';

$title = "My Waitlist";
$hideDefaultHeader = true;
include 'includes/header.php';

$user_id = $_SESSION['user_id'];

// Handle Remove from Waitlist
if (isset($_GET['remove'])) {
    $stmt = $pdo->prepare("DELETE FROM sys_waitlist WHERE id = ? AND user_id = ?");
    if ($stmt->execute([$_GET['remove'], $user_id])) {
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: 'Removed!',
                    text: 'You have been removed from the waitlist.',
                    icon: 'success',
                    confirmButtonText: 'OK'
                }).then(() => {
                    window.location.href='my_waitlist.php';
                });
            });
        </script>";
        exit;
    }
}

// Fetch user's waitlist
$stmt = $pdo->prepare("SELECT w.*, r.name as resource_name, r.location FROM sys_waitlist w 
                        JOIN resources r ON w.resource_id = r.id 
                        WHERE w.user_id = ? ORDER BY w.created_at DESC");
$stmt->execute([$user_id]);
$waitlist = $stmt->fetchAll();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h2 mb-1 fw-bold text-dark">Queued Requests</h1>
            <p class="text-muted small">Monitor slots you are currently waiting for.</p>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-10 mx-auto">
            <?php if (empty($waitlist)): ?>
                <div class="text-center py-5 ventixe-card">
                    <i class="bi bi-hourglass display-1 opacity-25"></i>
                    <p class="mt-3 text-muted">You are not on any waitlist at the moment.</p>
                    <a href="resources_list.php" class="btn btn-primary rounded-pill px-4">Browse Resources</a>
                </div>
            <?php else: ?>
                <div class="ventixe-card overflow-hidden">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">Resource</th>
                                    <th>Planned Time</th>
                                    <th>Status</th>
                                    <th>Joined On</th>
                                    <th class="pe-4 text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($waitlist as $w): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($w['resource_name']) ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars($w['location']) ?></div>
                                        </td>
                                        <td>
                                            <div class="small fw-bold text-primary">
                                                <?= date('d M, h:i A', strtotime($w['start_time'])) ?> - <?= date('h:i A', strtotime($w['end_time'])) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($w['status'] == 'pending'): ?>
                                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill">
                                                    <i class="bi bi-clock-history me-1"></i> Waiting
                                                </span>
                                            <?php elseif ($w['status'] == 'notified'): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">
                                                    <i class="bi bi-check-circle me-1"></i> Notified / Available
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill">
                                                    Expired
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small text-muted">
                                            <?= date('d M Y', strtotime($w['created_at'])) ?>
                                        </td>
                                        <td class="pe-4 text-end">
                                            <a href="?remove=<?= $w['id'] ?>" class="btn btn-outline-danger btn-sm rounded-circle shadow-sm" title="Remove" onclick="return confirm('Remove from waitlist?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                            <?php if ($w['status'] == 'notified'): ?>
                                                <a href="book_resource.php?id=<?= $w['resource_id'] ?>&start=<?= urlencode($w['start_time']) ?>&end=<?= urlencode($w['end_time']) ?>" class="btn btn-primary btn-sm rounded-pill px-3 ms-2">
                                                    Book Now
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
