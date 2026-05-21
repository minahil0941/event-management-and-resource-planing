<?php
require_once 'core/auth.php';
require_once 'core/db.php';

$title = "University Resources";
$hideDefaultHeader = true;
include 'includes/header.php';

// Fetch all resources with average ratings
$stmt = $pdo->query("
    SELECT r.*, AVG(f.rating) as avg_rating, COUNT(f.id) as review_count
    FROM resources r 
    LEFT JOIN sys_feedback f ON r.id = f.resource_id
    GROUP BY r.id
    ORDER BY r.type ASC
");
$resources = $stmt->fetchAll();
?>

    <div class="ventixe-hero fade-in-up bg-indigo">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="fw-bold text-white mb-2">Resource Catalog</h1>
                <p class="text-white-50 mb-0">Browse and reserve localized university halls, labs, and specialized equipment.</p>
            </div>
            <div class="col-md-4 text-end d-none d-md-block">
                <i class="bi bi-grid-3x3-gap text-white-50" style="font-size: 5rem;"></i>
            </div>
        </div>
    </div>

            <div class="row g-4">
                <?php if (isset($_GET['event_id'])): ?>
                    <div class="col-12 mt-2">
                        <div class="alert alert-primary shadow-sm border-0 rounded-4 d-flex align-items-center">
                            <i class="bi bi-info-circle-fill fs-4 me-3 text-primary"></i>
                            <div>
                                <strong>Event Selection Mode:</strong> Please select a resource from the catalog below to add a booking to your event.
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (empty($resources)): ?>
                    <div class="col-12">
                        <div class="alert alert-info border-0 shadow-sm">No resources are currently available for booking.</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($resources as $resource): ?>
                    <div class="col-md-4 col-sm-6 col-12">
                        <div class="card ventixe-card border-0 shadow-sm overflow-hidden h-100">
                            <!-- Resource Image Header -->
                            <div class="position-relative overflow-hidden" style="height: 180px;">
                                <?php 
                                    // Image Logic
                                    $img_src = $resource['image_url'] ?: 'assets/img/boxed-bg.jpg';
                                    if ($resource['image_url'] && strpos($resource['image_url'], 'http') !== 0) { 
                                        $img_src = BASE_URL . $resource['image_url']; 
                                    }
                                    // Smart Cache Buster
                                    $separator = (strpos($img_src, '?') !== false) ? '&' : '?';
                                    $final_src = $img_src . $separator . "v=" . (time() + $resource['id']);

                                    // Real-time Occupancy Check
                                    require_once 'core/booking_helper.php';
                                    $occupancy = getCurrentOccupancy($pdo, $resource['id']);
                                ?>
                                <img src="<?= $final_src ?>" 
                                     class="w-100 h-100 object-fit-cover transition-transform" 
                                     style="transition: transform 0.5s ease;"
                                     alt="<?php echo htmlspecialchars($resource['name']); ?>"
                                     onerror="this.src='<?= BASE_URL ?>assets/img/boxed-bg.jpg'"
                                     onmouseover="this.style.transform='scale(1.1)'"
                                     onmouseout="this.style.transform='scale(1)'">
                                
                                <!-- Live Status Badge -->
                                <div class="position-absolute top-0 end-0 m-2 z-index-1">
                                    <?php 
                                        $under_maintenance = isResourceMaintenance($pdo, $resource['id']);
                                        if ($under_maintenance): 
                                    ?>
                                        <span class="badge rounded-pill bg-warning border border-white shadow-sm px-2 py-1 small">
                                            <i class="bi bi-tools me-1"></i> Maintenance
                                        </span>
                                    <?php elseif ($occupancy): ?>
                                        <span class="badge rounded-pill bg-danger border border-white shadow-sm px-2 py-1 small animate-pulse">
                                            <i class="bi bi-record-fill me-1"></i> Occupied
                                        </span>
                                    <?php else: ?>
                                        <span class="badge rounded-pill bg-success border border-white shadow-sm px-2 py-1 small">
                                            <i class="bi bi-check-circle-fill me-1"></i> Available
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="position-absolute top-0 start-0 w-100 h-100 bg-dark opacity-25"></div>
                                <div class="position-absolute bottom-0 start-0 p-3 w-100 bg-gradient-to-t from-dark to-transparent d-flex justify-content-between align-items-end">
                                    <h5 class="fw-bold mb-0 text-white"><?php echo htmlspecialchars($resource['name']); ?></h5>
                                    <?php if($resource['price'] > 0): ?>
                                        <div class="text-white text-end">
                                            <div class="small opacity-75" style="font-size: 0.7rem; line-height: 1;">Starts at</div>
                                            <div class="fw-bold" style="font-size: 1.1rem; line-height: 1;">Rs. <?= number_format($resource['price']) ?></div>
                                            <div class="extra-small opacity-50" style="font-size: 0.6rem;">Per Hour</div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="card-header d-flex justify-content-between align-items-center bg-transparent border-0 pt-3">
                                <div>
                                    <span class="badge bg-primary-subtle text-primary border-0 small px-2"><?php echo ucfirst($resource['type']); ?></span>
                                </div>
                                <div>
                                    <small class="text-muted d-block text-end extra-small mb-1">Grade</small>
                                    <?php if ($resource['review_count'] > 0): ?>
                                        <div class="badge bg-warning text-white border-0 me-1 py-1 px-2 shadow-sm rounded-pill" style="font-size: 0.8rem;">
                                            <i class="bi bi-star-fill me-1"></i> <?= number_format($resource['avg_rating'], 1) ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="badge bg-light text-muted border border-secondary-subtle me-1 py-1 px-2 rounded-pill" style="font-size: 0.8rem;">
                                            <i class="bi bi-star me-1"></i> 0.0
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <small class="text-muted d-block">Location</small>
                                    <span class="small fw-semibold"><i class="bi bi-geo-alt text-danger me-1"></i><?php echo htmlspecialchars($resource['location']); ?></span>
                                </div>
                                <div class="mb-3">
                                    <small class="text-muted d-block">Capacity</small>
                                    <span class="small fw-semibold"><i class="bi bi-people text-primary me-1"></i><?php echo $resource['capacity']; ?> People</span>
                                </div>
                                <hr>
                                <p class="text-muted small mb-0"><?php echo htmlspecialchars($resource['description']); ?></p>
                            </div>
                            <div class="card-footer bg-light border-0">
                                <div class="row g-2">
                                    <?php $evt_param = isset($_GET['event_id']) ? '&event_id=' . (int)$_GET['event_id'] : ''; ?>
                                    <div class="col-6">
                                        <a href="availability.php?id=<?php echo $resource['id']; ?><?= $evt_param ?>" class="btn btn-sm btn-outline-info w-100 rounded-pill">Schedule</a>
                                    </div>
                                    <div class="col-6">
                                        <a href="book_resource.php?id=<?php echo $resource['id']; ?><?= $evt_param ?>" class="btn btn-sm btn-primary w-100 rounded-pill">Book Now</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

<?php include 'includes/footer.php'; ?>
