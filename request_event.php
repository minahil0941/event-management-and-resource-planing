<?php 
require_once 'core/session.php'; 
require_once 'core/booking_helper.php';

// Only logged in users (External Clients, Faculty, etc.)
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $type = trim($_POST['event_type']);
    $organizer_id = $_SESSION['user_id'];

    if (empty($title)) {
        $error = "Title is required.";
    } else {
        // If External Client, status is 'pending_approval'
        $status = ($_SESSION['role'] === 'external_client') ? 'pending_approval' : 'planning';
        
        $eventId = createEvent($pdo, $title, $description, $organizer_id, $type, $status);
        if ($eventId) {
            // Notify Admins of new event request
            $notifMsg = "New event request: " . $title . " by " . $_SESSION['name'];
            notifyAdmins($pdo, $notifMsg, 'info', 'dashboards/super_admin/manage_events.php');

            if ($status === 'pending_approval') {
                header("Location: my_events.php?msg=Event request submitted for approval");
                exit;
            } else {
                header("Location: my_events.php?msg=Event created successfully");
                exit;
            }
        } else {
            $error = "Failed to submit request. Please try again.";
        }
    }
}

$pageTitle = "Request New Event";
$hideDefaultHeader = true;
require_once 'includes/header.php'; 
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card card-outline card-primary shadow">
                <div class="card-header text-center">
                    <h3 class="card-title fw-bold"><i class="bi bi-calendar-event"></i> Request New Event</h3>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= $success ?></div>
                        <div class="text-center">
                            <a href="index.php" class="btn btn-primary">Back to Dashboard</a>
                        </div>
                    <?php else: ?>
                        <form action="" method="POST">
                            <div class="mb-3">
                                <label for="title" class="form-label fw-bold">Event Title</label>
                                <input type="text" name="title" id="title" class="form-control" placeholder="e.g. Science Fair 2024" required>
                                <small class="text-muted">Give your event a clear, descriptive name.</small>
                            </div>

                            <div class="mb-3">
                                <label for="event_type" class="form-label fw-bold">Event Type</label>
                                <input type="text" name="event_type" id="event_type" class="form-control" placeholder="e.g. Seminar, Workshop, Competition">
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label fw-bold">Event Background / Description</label>
                                <textarea name="description" id="description" class="form-control" rows="4" placeholder="Briefly describe what this event is about..."></textarea>
                            </div>

                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> <strong>Note:</strong> Your event request will be reviewed by the administration. You can start booking resources for this event only after it has been approved.
                            </div>

                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-send"></i> Submit Event Request
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
