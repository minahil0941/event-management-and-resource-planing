<?php
require_once 'core/session.php';
require_once 'core/booking_helper.php';
require_once 'core/db.php';

$user_id = $_SESSION['user_id'];
$message = "";
$msg_type = "";

// Handle Feedback Submission
if (isset($_POST['submit_feedback'])) {
    $booking_id = $_POST['booking_id'];
    $rating = $_POST['rating'];
    $comments = $_POST['comments'];
    $resource_id = $_POST['resource_id'];

    // Prevent duplicates
    $checkFeedback = $pdo->prepare("SELECT id FROM sys_feedback WHERE booking_id = ?");
    $checkFeedback->execute([$booking_id]);
    
    if (!$checkFeedback->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO sys_feedback (booking_id, user_id, resource_id, rating, comments) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$booking_id, $user_id, $resource_id, $rating, $comments])) {
            $message = "Thank you for your feedback! Your rating helps us improve.";
            $msg_type = "success";
        } else {
            $message = "Error submitting feedback.";
            $msg_type = "danger";
        }
    }
}

// Handle Cancellation
if (isset($_GET['cancel_id'])) {
    $cancel_id = $_GET['cancel_id'];
    
    // Safety check: ensure booking belongs to user
    $checkStmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ? AND user_id = ? AND status IN ('pending', 'approved')");
    $checkStmt->execute([$cancel_id, $user_id]);
    $booking = $checkStmt->fetch();
    
    if ($booking) {
        if (updateBookingStatus($pdo, $cancel_id, 'cancelled')) {
            // Trigger Waitlist Notification
            notifyWaitlistedUsers($pdo, $booking['resource_id'], $booking['start_time'], $booking['end_time']);
            
            $message = "Your booking for " . date('d M Y', strtotime($booking['start_time'])) . " has been cancelled.";
            $msg_type = "success";
        } else {
            $message = "Error cancelling booking.";
            $msg_type = "danger";
        }
    }
}

// Handle Late Receipt Upload Logic
if (isset($_POST['upload_receipt'])) {
    $booking_id = (int)($_POST['booking_id'] ?? 0);
    $user_id = $_SESSION['user_id'];

    // Check if booking exists for this user
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE id = ? AND user_id = ?");
    $stmt->execute([$booking_id, $user_id]);
    $isValid = $stmt->fetchColumn() > 0;

    if ($isValid) {
        if (!empty($_FILES['receipt_file']['name'])) {
            $target_dir = "uploads/receipts/";
            if (!is_dir($target_dir)) @mkdir($target_dir, 0777, true);
            
            $file_ext = strtolower(pathinfo($_FILES["receipt_file"]["name"], PATHINFO_EXTENSION));
            $file_name = time() . "_" . $booking_id . "." . $file_ext;
            $target_file = $target_dir . $file_name;
            
            if (move_uploaded_file($_FILES["receipt_file"]["tmp_name"], $target_file)) {
                $upStmt = $pdo->prepare("UPDATE bookings SET payment_receipt = ?, payment_status = 'under_review' WHERE id = ?");
                if ($upStmt->execute([$target_file, $booking_id])) {
                    $message = "<strong>SUCCESS!</strong> Your payment proof has been submitted for review.";
                    $msg_type = "success";
                    // Set session message to survive redirect
                    $_SESSION['flash_msg'] = $message;
                    $_SESSION['flash_type'] = $msg_type;
                    header("Location: my_bookings.php");
                    exit();
                } else {
                    $message = "Database Error: Could not save receipt."; $msg_type = "danger";
                }
            } else {
                $message = "Upload Failed: Could not save file. Check folder permissions."; $msg_type = "danger";
            }
        } else {
            $message = "Please select a file to upload."; $msg_type = "danger";
        }
    } else {
        $message = "Action Denied: Invalid Booking ID."; $msg_type = "danger";
    }
}

$title = "My Bookings";
$hideDefaultHeader = true;
include 'includes/header.php';

// Fetch user's bookings
$stmt = $pdo->prepare("
    SELECT b.*, r.name as resource_name, r.location, r.type, e.title as event_name, 
           f.rating as feedback_rating, f.comments as feedback_comments
    FROM bookings b 
    JOIN resources r ON b.resource_id = r.id 
    LEFT JOIN events e ON b.event_id = e.id 
    LEFT JOIN sys_feedback f ON b.id = f.booking_id
    WHERE b.user_id = ? 
    ORDER BY b.created_at DESC
");
$stmt->execute([$user_id]);
$bookings = $stmt->fetchAll();

// Fetch System Settings for Bank Details
$settings = [];
$sStmt = $pdo->query("SELECT * FROM system_settings");
while ($row = $sStmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>

            <?php 
            if (isset($_SESSION['flash_msg'])) {
                $message = $_SESSION['flash_msg'];
                $msg_type = $_SESSION['flash_type'];
                unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
            }
            if ($message): 
            ?>
                <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show shadow-sm border-0 rounded-4">
                    <i class="bi <?= ($msg_type=='success'?'bi-check-circle-fill':'bi-exclamation-triangle-fill') ?> me-2"></i>
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
            <?php endif; ?>
            
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="ventixe-card bg-primary text-white p-4 d-flex align-items-center justify-content-between shadow-sm border-0 rounded-4">
                        <div>
                            <h2 class="fw-bold mb-0 text-white"><i class="bi bi-journal-bookmark-fill me-2"></i> My Booking History</h2>
                            <p class="mb-0 text-white-50 small">View and manage your resource reservations</p>
                        </div>
                        <div class="d-none d-md-block">
                            <i class="bi bi-clock-history" style="font-size: 3rem; opacity: 0.2;"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card ventixe-card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header">
                    <h3 class="card-title">Recent Requests</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Resource</th>
                                    <th>Schedule</th>
                                    <th>Purpose</th>
                                    <th>Status</th>
                                    <th>Submitted On</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($bookings)): ?>
                                    <tr><td colspan="6" class="text-center py-4">You haven't made any booking requests yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($bookings as $b): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($b['resource_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($b['location']); ?></small>
                                            <?php if ($b['event_name']): ?>
                                                <div class="mt-1"><span class="badge border text-primary small"><i class="bi bi-calendar-event"></i> <?php echo htmlspecialchars($b['event_name']); ?></span></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="small">
                                                <i class="bi bi-calendar"></i> <?php echo date('d M Y', strtotime($b['start_time'])); ?><br>
                                                <i class="bi bi-clock"></i> <?php echo date('h:i A', strtotime($b['start_time'])); ?> - <?php echo date('h:i A', strtotime($b['end_time'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($b['purpose']); ?></td>
                                        <td>
                                            <?php 
                                            $status_class = [
                                                'pending' => 'warning',
                                                'approved' => 'success',
                                                'rejected' => 'danger',
                                                'cancelled' => 'secondary'
                                            ][$b['status']];
                                            ?>
                                            <div class="d-flex flex-column align-items-center">
                                                <span class="badge bg-<?php echo $status_class; ?> shadow-sm mb-1">
                                                    <?php echo strtoupper($b['status']); ?>
                                                </span>
                                                <?php if (isset($b['total_price']) && $b['total_price'] > 0): ?>
                                                    <div class="mt-1 d-flex flex-column align-items-center">
                                                        <span class="badge bg-dark rounded-pill mb-1" style="font-size: 0.7rem;">
                                                            Rs. <?= number_format($b['total_price'], 2) ?>
                                                        </span>
                                                        <?php 
                                                        $p_status = $b['payment_status'] ?? 'pending';
                                                        if ($p_status == 'paid') {
                                                            $p_badge = 'success'; $p_text = 'PAID'; $p_icon = 'bi-patch-check-fill';
                                                        } elseif ($p_status == 'under_review') {
                                                            $p_badge = 'info'; $p_text = 'VERIFICATION PENDING'; $p_icon = 'bi-clock-history';
                                                        } elseif ($p_status == 'waived') {
                                                            $p_badge = 'primary'; $p_text = 'WAIVED'; $p_icon = 'bi-gift';
                                                        } else {
                                                            $p_badge = 'warning'; $p_text = 'PAYMENT PENDING'; $p_icon = 'bi-exclamation-circle';
                                                        }
                                                        ?>
                                                        <span class="badge bg-<?= $p_badge ?>-subtle text-<?= $p_badge ?> border extra-small px-2 py-1" style="font-size: 0.65rem; letter-spacing: 0.5px;">
                                                            <i class="bi <?= $p_icon ?> me-1"></i> <?= $p_text ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><?php echo date('d M, Y', strtotime($b['created_at'])); ?></td>
                                        <td class="text-end">
                                            <?php if ($b['status'] == 'pending' || $b['status'] == 'approved'): ?>
                                                <?php 
                                                $isPassed = strtotime($b['end_time']) < time();
                                                if ($b['status'] == 'approved'): ?>
                                                    <!-- QR Ticket Button -->
                                                    <button class="btn btn-sm btn-info rounded-pill px-3 shadow-sm me-1" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#ticketModal" 
                                                            onclick="showTicket('<?= $b['v_code'] ?>', '<?= htmlspecialchars($b['resource_name']) ?>', '<?= date('d M Y', strtotime($b['start_time'])) ?>', '<?= date('h:i A', strtotime($b['start_time'])) ?>')">
                                                        <i class="bi bi-qr-code"></i> Ticket
                                                    </button>

                                                    <!-- Professional Receipt Link -->
                                                    <a href="view_receipt.php?id=<?= $b['id'] ?>" target="_blank" class="btn btn-sm btn-dark rounded-pill px-3 shadow-sm me-1">
                                                        <i class="bi bi-file-earmark-pdf"></i> Invoice
                                                    </a>

                                                    <?php if ($isPassed && !$b['feedback_rating']): ?>
                                                        <button class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#feedbackModal" 
                                                                onclick="setFeedbackData(<?= $b['id'] ?>, <?= $b['resource_id'] ?>, '<?= htmlspecialchars($b['resource_name']) ?>')">
                                                            <i class="bi bi-star-fill me-1"></i> Rate
                                                        </button>
                                                    <?php elseif ($b['feedback_rating']): ?>
                                                        <span class="badge bg-light text-dark border small">
                                                            <i class="bi bi-star-fill text-warning"></i> <?= $b['feedback_rating'] ?> / 5
                                                        </span>
                                                    <?php endif; ?>
                                                <?php endif; ?>

                                                <?php if (!$isPassed || $b['status'] == 'pending'): ?>
                                                    <?php if ($b['total_price'] > 0 && !in_array(($b['payment_status'] ?? 'pending'), ['paid', 'under_review'])): ?>
                                                        <?php if ($b['status'] == 'approved'): ?>
                                                            <button class="btn btn-sm btn-outline-primary shadow-sm rounded-pill me-1" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#uploadReceiptModal" 
                                                                    onclick="setReceiptBookingId(<?= $b['id'] ?>)">
                                                                <i class="bi bi-wallet2"></i> Pay & Upload Proof
                                                            </button>
                                                        <?php else: ?>
                                                            <span class="badge bg-light text-muted border extra-small rounded-pill">
                                                                <i class="bi bi-lock-fill"></i> Pay after Approval
                                                            </span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                    <a href="?cancel_id=<?= $b['id'] ?>" class="btn btn-sm btn-outline-danger shadow-sm rounded-pill" onclick="return confirm('Are you sure you want to CANCEL this booking?')">
                                                        <i class="bi bi-x-circle"></i> Cancel
                                                    </a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted small">Closed</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

<!-- Ticket / QR Modal -->
<div class="modal fade" id="ticketModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white border-0 py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-ticket-perforated me-2"></i> Entry Ticket</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-5">
                <div class="mb-4">
                    <h3 class="fw-bold mb-1" id="ticket_res_name"></h3>
                    <p class="text-muted small" id="ticket_date_time"></p>
                </div>
                
                <div class="qr-container bg-light p-4 d-inline-block rounded-4 shadow-sm mb-4">
                    <canvas id="qr-canvas"></canvas>
                </div>
                
                <div class="alert alert-info border-0 rounded-pill extra-small py-2 mb-0">
                    <i class="bi bi-info-circle me-1"></i> Present this QR code to the admin at the entrance.
                </div>
            </div>
            <div class="modal-footer bg-light justify-content-center border-0 pb-4">
                <button type="button" class="btn btn-dark rounded-pill px-5" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- QR Library -->
<script src="<?= BASE_URL ?>assets/vendor/qrious/qrious.min.js"></script>
<script>
    function showTicket(vCode, resName, date, time) {
        document.getElementById('ticket_res_name').innerText = resName;
        document.getElementById('ticket_date_time').innerText = date + " @ " + time;
        
        // Generate QR Code
        const qr = new QRious({
            element: document.getElementById('qr-canvas'),
            value: vCode,
            size: 200,
            padding: 10,
            level: 'H',
            foreground: '#111827'
        });
    }
</script>

<!-- Feedback Modal -->
<div class="modal fade" id="feedbackModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-star-fill me-2"></i> Rate <span id="resource_name_display"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="booking_id" id="modal_booking_id">
                <input type="hidden" name="resource_id" id="modal_resource_id">
                <div class="modal-body text-center py-4">
                    <p class="text-muted mb-4">How was your experience with this resource?</p>
                    
                    <div class="star-rating h3 mb-4">
                        <i class="bi bi-star rating-star cursor-pointer" data-value="1"></i>
                        <i class="bi bi-star rating-star cursor-pointer" data-value="2"></i>
                        <i class="bi bi-star rating-star cursor-pointer" data-value="3"></i>
                        <i class="bi bi-star rating-star cursor-pointer" data-value="4"></i>
                        <i class="bi bi-star rating-star cursor-pointer" data-value="5"></i>
                        <input type="hidden" name="rating" id="rating_value" value="5" required>
                    </div>

                    <div class="mb-3 text-start">
                        <label class="form-label small fw-bold">Comments (Optional)</label>
                        <textarea name="comments" class="form-control" rows="3" placeholder="Share your thoughts..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="submit_feedback" class="btn btn-primary px-4">Submit Review</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.cursor-pointer { cursor: pointer; }
.rating-star { color: #dee2e6; transition: color 0.2s; }
.rating-star.text-warning { color: #ffc107 !important; }
.rating-star.hover { color: #ffda6a; }
</style>

<script>
function setFeedbackData(bookingId, resourceId, resourceName) {
    document.getElementById('modal_booking_id').value = bookingId;
    document.getElementById('modal_resource_id').value = resourceId;
    document.getElementById('resource_name_display').innerText = resourceName;
    resetStars();
}

function resetStars() {
    const stars = document.querySelectorAll('.rating-star');
    stars.forEach(s => {
        s.classList.remove('text-warning', 'bi-star-fill');
        s.classList.add('bi-star');
    });
    // Set default to 5
    updateStars(5);
}

function updateStars(val) {
    const stars = document.querySelectorAll('.rating-star');
    stars.forEach(s => {
        if (s.getAttribute('data-value') <= val) {
            s.classList.add('text-warning', 'bi-star-fill');
            s.classList.remove('bi-star');
        } else {
            s.classList.remove('text-warning', 'bi-star-fill');
            s.classList.add('bi-star');
        }
    });
    document.getElementById('rating_value').value = val;
}

document.querySelectorAll('.rating-star').forEach(star => {
    star.addEventListener('click', function() {
        updateStars(this.getAttribute('data-value'));
    });
    star.addEventListener('mouseover', function() {
        const val = this.getAttribute('data-value');
        document.querySelectorAll('.rating-star').forEach(s => {
            if (s.getAttribute('data-value') <= val) s.classList.add('hover');
        });
    });
    star.addEventListener('mouseout', function() {
        document.querySelectorAll('.rating-star').forEach(s => s.classList.remove('hover'));
    });
});
</script>

<?php include 'includes/footer.php'; ?>

<!-- Upload Receipt Modal -->
<div class="modal fade" id="uploadReceiptModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="modal-title fw-bold text-primary"><i class="bi bi-cloud-upload me-2"></i> Upload Payment Proof</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="my_bookings.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="booking_id" id="receipt_booking_id">
                <div class="modal-body p-4">
                    <div class="bg-indigo-subtle p-3 rounded-4 border border-indigo-subtle mb-4">
                        <h6 class="fw-bold text-indigo mb-2"><i class="bi bi-bank me-2"></i> Payment Instructions</h6>
                        <div class="small text-muted mb-2">Please transfer the total amount to the account below:</div>
                        <div class="bg-white p-3 rounded-3 border mb-0">
                            <div class="row">
                                <div class="col-6 border-end">
                                    <div class="extra-small text-muted text-uppercase fw-bold">Bank Name</div>
                                    <div class="fw-bold small"><?= htmlspecialchars($settings['bank_name'] ?? 'N/A') ?></div>
                                </div>
                                <div class="col-6 ps-3">
                                    <div class="extra-small text-muted text-uppercase fw-bold">Account Number</div>
                                    <div class="fw-bold small"><?= htmlspecialchars($settings['bank_account'] ?? 'N/A') ?></div>
                                </div>
                            </div>
                            <?php if(!empty($settings['bank_iban'])): ?>
                                <div class="extra-small text-muted mt-2 text-uppercase fw-bold">IBAN</div>
                                <div class="fw-bold small"><?= htmlspecialchars($settings['bank_iban']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <p class="small text-muted mb-4">After transfer, please upload a clear screenshot or photo of your receipt. Our team will verify it within 24 hours.</p>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Select Image File</label>
                        <input type="file" name="receipt_file" class="form-control rounded-3" accept="image/*" required>
                    </div>
                    
                    <div class="alert bg-primary-subtle border-0 rounded-4 small py-2 px-3 mb-0">
                        <i class="bi bi-info-circle-fill text-primary me-2"></i>
                        Verification is required for final resource activation.
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <input type="submit" name="upload_receipt" value="Upload Proof Now" class="btn btn-primary rounded-pill px-5 shadow">
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function setReceiptBookingId(id) {
    document.getElementById('receipt_booking_id').value = id;
}
</script>
