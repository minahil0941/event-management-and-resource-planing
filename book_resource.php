<?php
require_once 'core/session.php';
require_once 'core/db.php';
require_once 'core/booking_helper.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: login.php?msg=Please login to book a resource");
    exit;
}

$resource_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'];

// Fetch resource details
$stmt = $pdo->prepare("SELECT * FROM resources WHERE id = ? AND status = 'available'");
$stmt->execute([$resource_id]);
$resource = $stmt->fetch();

if (!$resource) {
    die("Resource not found or not available.");
}

// Fetch active events for the dropdown
$eventStmt = $pdo->query("SELECT id, title FROM events WHERE status IN ('planning', 'active') ORDER BY title ASC");
$activeEvents = $eventStmt->fetchAll();

$title = "Book: " . $resource['name'];
$hideDefaultHeader = true;
include 'includes/header.php';

$message = "";
$msg_type = "";
$start = "";
$end = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $start = $_POST['start_time'];
    $end = $_POST['end_time'];
    $purpose = $_POST['purpose'];
    $event_id = !empty($_POST['event_id']) ? $_POST['event_id'] : null;

    if (isset($_POST['join_waitlist'])) {
        // Double check if already on waitlist
        $check = $pdo->prepare("SELECT COUNT(*) FROM sys_waitlist WHERE user_id = ? AND resource_id = ? AND start_time = ? AND end_time = ? AND status = 'pending'");
        $check->execute([$user_id, $resource_id, $start, $end]);
        
        if ($check->fetchColumn() > 0) {
            $message = "You are already on the waitlist for this slot.";
            $msg_type = "warning";
        } else {
            $stmt = $pdo->prepare("INSERT INTO sys_waitlist (resource_id, user_id, start_time, end_time) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$resource_id, $user_id, $start, $end])) {
                $message = "You have successfully joined the waitlist for this slot! We will notify you if it becomes available.";
                $msg_type = "info";
                addNotification($pdo, $user_id, "Joined waitlist for " . $resource['name'], 'info', 'my_waitlist.php');
            } else {
                $message = "Error joining waitlist.";
                $msg_type = "danger";
            }
        }
    }
    elseif (isset($_POST['submit_booking'])) {
        if (strtotime($end) <= strtotime($start)) {
            $message = "Error: End time must be after start time.";
            $msg_type = "danger";
        } 
        elseif (strtotime($start) < time()) {
            $message = "Error: Cannot book a slot in the past.";
            $msg_type = "danger";
        }
        elseif (!isResourceAvailable($pdo, $resource_id, $start, $end)) {
            $message = "<strong>This slot is already taken.</strong><br>Would you like to join the waitlist? 
                        <button type='submit' name='join_waitlist' class='btn btn-sm btn-outline-primary mt-2'>Yes, Join Waitlist</button>";
            $msg_type = "warning";
        } 
        else {
            // Collect requested requirements
            $req_data = [];
            if (!empty($_POST['req_key'])) {
                foreach ($_POST['req_key'] as $index => $key) {
                    if (isset($_POST['req_value'][$index]) && $_POST['req_value'][$index] !== '') {
                        $req_data[$key] = $_POST['req_value'][$index];
                    }
                }
            }
            $requirements = !empty($req_data) ? json_encode($req_data) : null;
            $inventory_ids = $_POST['inventory_ids'] ?? [];

            // Calculate total price based on duration
            $total_price = 0.00;
            if ($_SESSION['role'] === 'external_client' && $resource['price'] > 0) {
                $duration_seconds = strtotime($end) - strtotime($start);
                $hours = ceil($duration_seconds / 3600);
                $total_price = $hours * $resource['price'];
            }

            // Payment will be handled after approval
            $payment_receipt = null;

            $bookingResult = saveBooking($pdo, $resource_id, $user_id, $start, $end, $purpose, $event_id, $requirements, $inventory_ids, $total_price, $payment_receipt);
            
            if (is_numeric($bookingResult)) {
                $message = "Success! Your booking request for " . htmlspecialchars($resource['name']) . " has been submitted. ";
                if ($total_price > 0) $message .= "Once approved, you will be able to upload your payment receipt.";
                else $message .= "It is now pending approval.";
                
                $msg_type = "success";
                
                // Notify Admins of new booking
                $notifMsg = "New booking request: " . $resource['name'] . " by " . $_SESSION['name'];
                notifyAdmins($pdo, $notifMsg, 'info', 'dashboards/super_admin/manage_events.php');
                
                // Notify User
                addNotification($pdo, $user_id, "Booking request for " . $resource['name'] . " submitted.", 'success', 'my_bookings.php');
            } else {
                $message = "System Error: " . htmlspecialchars($bookingResult);
                $msg_type = "danger";
            }
        }
    }
}
?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="ventixe-card bg-primary text-white p-4 d-flex align-items-center justify-content-between shadow-sm border-0 rounded-4">
            <div>
                <h2 class="fw-bold mb-0 text-white"><i class="bi bi-calendar-plus me-2"></i> Book Resource</h2>
                <p class="mb-0 text-white-50 small">Finalize your reservation for <strong><?php echo htmlspecialchars($resource['name']); ?></strong></p>
            </div>
            <div class="d-none d-md-block">
                <span class="badge bg-white text-primary rounded-pill px-3 py-2">
                    <i class="bi bi-geo-alt me-1"></i> <?= htmlspecialchars($resource['location']) ?>
                </span>
            </div>
        </div>
    </div>
</div>

<div class="row">
                <div class="col-md-6">
                    <div class="card card-primary shadow">
                        <div class="card-header">
                            <h3 class="card-title">Booking Request Form</h3>
                        </div>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="card-body">
                                <?php if ($message): ?>
                                    <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show border-0 shadow-sm mb-4">
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-<?= $msg_type === 'success' ? 'check-circle' : ($msg_type === 'danger' ? 'x-circle' : 'info-circle') ?>-fill me-2 fs-5"></i>
                                            <div><?= $message ?></div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($resource['price'] > 0): ?>
                                    <div class="alert alert-light border shadow-sm mb-4">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-cash-stack text-success me-3 fs-3"></i>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-0 fw-bold">Booking Fee Details</h6>
                                                <p class="mb-0 small text-muted">Base rate: <strong>Rs. <?= number_format($resource['price'], 2) ?></strong> / hour.</p>
                                                <div id="price_breakdown" class="mt-1 d-none">
                                                    <span class="badge bg-success-subtle text-success border-0 px-2 py-1">Estimated Total: Rs. <span id="total_amt">0</span></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="mb-3">
                                    <label class="form-label">Start Date & Time</label>
                                    <input type="datetime-local" name="start_time" class="form-control" required value="<?php echo $_POST['start_time'] ?? ''; ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">End Date & Time</label>
                                    <input type="datetime-local" id="end_time_input" name="end_time" class="form-control" required value="<?php echo $_POST['end_time'] ?? ''; ?>">
                                </div>
                                
                                <!-- Real-time Slot Status Alert -->
                                <div id="slot_status_alert" class="alert d-none shadow-sm small"></div>

                                <?php if ($_SESSION['role'] === 'external_client' && $resource['price'] > 0): ?>
                                    <div class="alert bg-indigo-subtle border shadow-sm mb-3 rounded-4">
                                        <div class="d-flex">
                                            <i class="bi bi-info-circle-fill text-indigo me-3 fs-4"></i>
                                            <div>
                                                <h6 class="fw-bold text-indigo mb-1">Payment Required After Approval</h6>
                                                <p class="mb-0 small text-muted">Since this is a paid resource, you will be required to upload a payment receipt <strong>after</strong> an administrator approves your request.</p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    const startInput = document.querySelector('input[name="start_time"]');
                                    const endInput = document.getElementById('end_time_input');
                                    const statusAlert = document.getElementById('slot_status_alert');
                                    const submitBtn = document.getElementById('submit_booking_btn');
                                    const resourceId = <?= json_encode($resource_id) ?>;

                                    function checkSlotAvailability() {
                                        const startTime = startInput.value;
                                        const endTime = endInput.value;
                                        const hourlyRate = <?= (float)$resource['price'] ?>;
                                        const isExternal = <?= $_SESSION['role'] === 'external_client' ? 'true' : 'false' ?>;

                                        // Only check if both dates are filled
                                        if (startTime && endTime) {
                                            // Calculate Price Breakdown
                                            if (isExternal && hourlyRate > 0) {
                                                const start = new Date(startTime);
                                                const end = new Date(endTime);
                                                const diff = (end - start) / (1000 * 60 * 60);
                                                const hours = Math.ceil(diff);
                                                if (hours > 0) {
                                                    document.getElementById('price_breakdown').classList.remove('d-none');
                                                    document.getElementById('total_amt').innerText = (hours * hourlyRate).toLocaleString();
                                                } else {
                                                    document.getElementById('price_breakdown').classList.add('d-none');
                                                }
                                            }

                                            statusAlert.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning');
                                            statusAlert.className = 'alert alert-info shadow-sm small';
                                            statusAlert.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Checking availability...';
                                            
                                            fetch('ajax_check_slot.php', {
                                                method: 'POST',
                                                headers: { 'Content-Type': 'application/json' },
                                                body: JSON.stringify({ resource_id: resourceId, start_time: startTime, end_time: endTime })
                                            })
                                            .then(response => response.json())
                                            .then(data => {
                                                statusAlert.classList.remove('alert-info');
                                                if (data.available) {
                                                    statusAlert.classList.add('alert-success');
                                                    statusAlert.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i> ' + data.message;
                                                    if(submitBtn) submitBtn.disabled = false;
                                                } else {
                                                    statusAlert.classList.add('alert-danger');
                                                    let waitlistBtn = '';
                                                    if (data.message.includes('booked') || data.message.includes('already')) {
                                                        waitlistBtn = '<button type="button" onclick="joinWaitlist()" class="btn btn-sm btn-outline-light ms-2 rounded-pill"><i class="bi bi-hourglass-split me-1"></i> Join Waitlist</button>';
                                                    }
                                                    statusAlert.innerHTML = '<div class="d-flex align-items-center justify-content-between"><span><i class="bi bi-exclamation-triangle-fill me-2"></i> <strong>' + data.message + '</strong></span>' + waitlistBtn + '</div>';
                                                    if(submitBtn) submitBtn.disabled = true;
                                                }
                                            })
                                            .catch(error => {
                                                console.error("AJAX Error:", error);
                                                statusAlert.classList.remove('alert-info');
                                                statusAlert.classList.add('alert-warning');
                                                statusAlert.innerHTML = '<i class="bi bi-exclamation-circle me-2"></i> Error checking availability. You can still try to submit.';
                                                if(submitBtn) submitBtn.disabled = false; // Never permanently lock the user out!
                                            });
                                        } else {
                                            statusAlert.classList.add('d-none');
                                            document.getElementById('price_breakdown').classList.add('d-none');
                                            if(submitBtn) submitBtn.disabled = false; // Reset if empty
                                        }
                                    }

                                    startInput.addEventListener('change', checkSlotAvailability);
                                    endInput.addEventListener('change', checkSlotAvailability);
                                    
                                    // Initial check if values exist (e.g. back button or pre-filled from catalog)
                                    if(startInput.value && endInput.value) {
                                        checkSlotAvailability();
                                    }

                                    window.joinWaitlist = function() {
                                        const startTime = startInput.value;
                                        const endTime = endInput.value;
                                        
                                        if(!startTime || !endTime) return;

                                        fetch('ajax_join_waitlist.php', {
                                            method: 'POST',
                                            headers: { 'Content-Type': 'application/json' },
                                            body: JSON.stringify({ resource_id: resourceId, start_time: startTime, end_time: endTime })
                                        })
                                        .then(response => response.json())
                                        .then(data => {
                                            if(data.success) {
                                                statusAlert.className = 'alert alert-success shadow-sm small';
                                                statusAlert.innerHTML = '<i class="bi bi-check-lg me-2"></i> ' + data.message;
                                            } else {
                                                Swal.fire('Error', data.message, 'error');
                                            }
                                        });
                                    };
                                });
                                </script>
                                <div class="mb-3">
                                    <label class="form-label">Booking Priority</label>
                                    <select name="priority" class="form-select border-<?= (isset($_POST['priority']) && $_POST['priority'] == 'Urgent') ? 'danger' : 'primary' ?>">
                                        <option value="Normal">Normal</option>
                                        <option value="High">High (Important Event)</option>
                                        <option value="Urgent">Urgent (Last minute change)</option>
                                    </select>
                                    <input type="hidden" name="req_key[]" value="_priority">
                                    <input type="hidden" name="req_value[]" id="priority_val" value="Normal">
                                    <script>
                                        document.querySelector('select[name="priority"]').onchange = function() {
                                            document.getElementById('priority_val').value = this.value;
                                            this.className = 'form-select border-' + (this.value == 'Urgent' ? 'danger' : 'primary');
                                        };
                                    </script>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Purpose of Booking</label>
                                    <textarea name="purpose" class="form-control" rows="3" placeholder="Explain why you need this resource..." required><?php echo $_POST['purpose'] ?? ''; ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Link to Event (Optional)</label>
                                    <select name="event_id" class="form-select">
                                        <option value="">-- No Specific Event --</option>
                                        <?php foreach ($activeEvents as $evt): ?>
                                            <?php $selected_evt = ($_POST['event_id'] ?? $_GET['event_id'] ?? '') == $evt['id'] ? 'selected' : ''; ?>
                                            <option value="<?= $evt['id'] ?>" <?= $selected_evt ?>>
                                                <?= htmlspecialchars($evt['title']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Linking the booking to an event helps in management and reporting.</small>
                                </div>

                                <?php 
                                $amenities = json_decode($resource['amenities'] ?? '[]', true);
                                if (!empty($amenities)): 
                                ?>
                                    <hr>
                                    <h5 class="mb-3 text-primary"><i class="bi bi-gift"></i> Specific Requirements / Demands</h5>
                                    <p class="small text-muted mb-3">Please specify how many or which of these you need:</p>
                                    
                                    <?php foreach ($amenities as $key => $limit): ?>
                                        <div class="row align-items-center mb-3">
                                            <div class="col-7">
                                                <label class="small fw-bold mb-0"><?= htmlspecialchars($key) ?></label>
                                                <div class="text-muted" style="font-size: 0.75rem;">Capacity/Max: <?= htmlspecialchars($limit) ?></div>
                                                <input type="hidden" name="req_key[]" value="<?= htmlspecialchars($key) ?>">
                                            </div>
                                            <div class="col-5">
                                                <?php if (is_numeric($limit)): ?>
                                                    <input type="number" name="req_value[]" class="form-control form-control-sm" min="0" max="<?= (int)$limit ?>" placeholder="Qnty">
                                                <?php else: ?>
                                                    <select name="req_value[]" class="form-select form-select-sm">
                                                        <option value="">No</option>
                                                        <option value="Yes">Yes</option>
                                                    </select>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>

                                    <div class="mb-3 mt-4">
                                        <label class="form-label small fw-bold"><i class="bi bi-info-circle"></i> Special Setup Instructions</label>
                                        <input type="hidden" name="req_key[]" value="Special Instructions">
                                        <textarea name="req_value[]" class="form-control form-control-sm" rows="2" placeholder="e.g. Technician needed at 9 AM, Stage needs extra lighting..."></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label small fw-bold"><i class="bi bi-people"></i> Support Services Needed</label>
                                        <div class="form-check">
                                            <input type="checkbox" name="support_staff[]" value="Security" class="form-check-input" id="staffSec">
                                            <label class="form-check-label small" for="staffSec">Security Personnel</label>
                                        </div>
                                        <div class="form-check">
                                            <input type="checkbox" name="support_staff[]" value="Technician" class="form-check-input" id="staffTech">
                                            <label class="form-check-label small" for="staffTech">Technical Support (IT/Audio-Visual)</label>
                                        </div>
                                    </div>
                                    
                                    <input type="hidden" name="req_key[]" value="Staff Needed">
                                    <input type="hidden" name="req_value[]" id="staff_combined" value="None">
                                    
                                    <script>
                                        const checks = document.querySelectorAll('input[name="support_staff[]"]');
                                        checks.forEach(c => {
                                            c.onchange = () => {
                                                const selected = Array.from(checks).filter(i => i.checked).map(i => i.value);
                                                document.getElementById('staff_combined').value = selected.length > 0 ? selected.join(', ') : 'None';
                                            };
                                        });
                                    </script>

                                    <!-- TECHNICAL EQUIPMENT (ADD-ON) -->
                                    <hr>
                                    <h5 class="mb-3 text-primary"><i class="bi bi-tools"></i> Bundle Technical Equipment</h5>
                                    <p class="small text-muted mb-3">Select additional items for this booking (availability checked):</p>
                                    
                                    <?php 
                                    $availableInventory = getAvailableInventory($pdo, $start, $end);
                                    if (empty($start) || empty($end)) {
                                        echo "<p class='extra-small text-muted'><i class='bi bi-info-circle'></i> Select dates above to see real-time availability for these items.</p>";
                                    }
                                    if (empty($availableInventory)): 
                                    ?>
                                        <div class="alert bg-light border-0 small text-muted"> <i class="bi bi-info-circle me-1"></i> No portable equipment available for this time slot.</div>
                                    <?php else: ?>
                                        <div class="row g-2">
                                            <?php foreach ($availableInventory as $inv): ?>
                                                <div class="col-md-6 mb-2">
                                                    <div class="form-check p-2 border rounded shadow-sm h-100 bg-white">
                                                        <input class="form-check-input ms-1" type="checkbox" name="inventory_ids[]" value="<?= $inv['id'] ?>" id="inv_<?= $inv['id'] ?>">
                                                        <label class="form-check-label ms-4 w-100" for="inv_<?= $inv['id'] ?>" style="cursor:pointer;">
                                                            <div class="fw-bold small"><?= htmlspecialchars($inv['item_name']) ?></div>
                                                            <span class="badge bg-info-subtle text-info extra-small" style="font-size: 0.65rem;"><?= htmlspecialchars($inv['category']) ?></span>
                                                        </label>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer">
                                <button type="submit" name="submit_booking" id="submit_booking_btn" class="btn btn-primary btn-block">Submit Reservation Request</button>
                                <a href="availability.php?id=<?php echo $resource_id; ?>" class="btn btn-default btn-block">View Schedule Again</a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card card-info card-outline">
                        <div class="card-header">
                            <h3 class="card-title">Resource Details</h3>
                        </div>
                        <div class="card-body">
                            <p><strong>Location:</strong> <?php echo htmlspecialchars($resource['location']); ?></p>
                            <p><strong>Capacity:</strong> <?php echo $resource['capacity']; ?> People</p>
                            <p><strong>Type:</strong> <?php echo ucfirst($resource['type']); ?></p>
                            <hr>
                            <p class="text-muted"><?php echo htmlspecialchars($resource['description']); ?></p>
                        </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
