<?php 
$pageTitle = "My Profile";
$hideDefaultHeader = true;
require_once 'includes/header.php'; 

$uID = $_SESSION['user_id'];
$msg = "";
$msgType = "";

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $password = $_POST['password'];
    
    // 1. Handle File Upload
    if (!empty($_FILES['avatar']['name'])) {
        $targetDir = "uploads/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        
        $fileName = time() . "_" . basename($_FILES['avatar']['name']);
        $targetFilePath = $targetDir . $fileName;
        $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);
        
        // Allow certain file formats
        $allowTypes = array('jpg','png','jpeg','gif');
        if(in_array(strtolower($fileType), $allowTypes)){
            if(move_uploaded_file($_FILES['avatar']['tmp_name'], $targetFilePath)){
                // Update DB
                $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?")->execute([$targetFilePath, $uID]);
            }
        }
    }

    // 2. Update Password (if provided)
    if (!empty($password)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $uID]);
    }

    // 3. Update Basic Info
    $pdo->prepare("UPDATE users SET name = ? WHERE id = ?")->execute([$name, $uID]);

    // Refresh Session Name
    $_SESSION['name'] = $name;
    $msg = "Profile updated successfully!";
    $msgType = "success";
}

// Fetch Latest User Data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$uID]);
$user = $stmt->fetch();
?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="ventixe-card bg-primary text-white p-4 d-flex align-items-center justify-content-between shadow-sm border-0 rounded-4">
            <div>
                <h2 class="fw-bold mb-0 text-white"><i class="bi bi-person-bounding-box me-2"></i> Account Profile</h2>
                <p class="mb-0 text-white-50 small">Manage your personal information and security settings</p>
            </div>
            <div class="d-none d-md-block">
                <i class="bi bi-shield-lock" style="font-size: 3rem; opacity: 0.2;"></i>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card card-primary card-outline mb-4">
            <div class="card-body box-profile text-center">
                <div class="mb-3">
                    <img class="profile-user-img img-fluid img-circle border border-3"
                         src="<?= !empty($user['avatar']) ? $user['avatar'] : 'assets/img/default_avatar.png' ?>"
                         alt="User profile picture" style="width: 150px; height: 150px; object-fit: cover;">
                </div>
                <h3 class="profile-username text-center"><?= htmlspecialchars($user['name']) ?></h3>
                <p class="text-muted text-center"><?= ucfirst(str_replace('_', ' ', $user['role'])) ?></p>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card card-primary card-outline">
            <div class="card-header">
                <h3 class="card-title">Edit Profile</h3>
            </div>
            <div class="card-body">
                <?php if($msg): ?>
                    <div class="alert alert-<?= $msgType ?>"><?= $msg ?></div>
                <?php endif; ?>

                <form class="form-horizontal" method="POST" enctype="multipart/form-data">
                    <div class="mb-3 row">
                        <label class="col-sm-3 col-form-label">Full Name</label>
                        <div class="col-sm-9">
                            <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label class="col-sm-3 col-form-label">Email</label>
                        <div class="col-sm-9">
                            <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled readonly>
                            <small class="text-muted">Email cannot be changed.</small>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label class="col-sm-3 col-form-label">Identity / Reg No</label>
                        <div class="col-sm-9">
                            <input type="text" class="form-control" value="<?= htmlspecialchars($user['registration_no'] ?? $user['identity_no']) ?>" disabled readonly>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label class="col-sm-3 col-form-label">New Password</label>
                        <div class="col-sm-9">
                            <input type="password" class="form-control" name="password" placeholder="Leave blank to keep current password">
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label class="col-sm-3 col-form-label">Profile Picture</label>
                        <div class="col-sm-9">
                            <input type="file" class="form-control" name="avatar">
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <div class="offset-sm-3 col-sm-9">
                            <button type="submit" class="btn btn-primary">Update Profile</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>