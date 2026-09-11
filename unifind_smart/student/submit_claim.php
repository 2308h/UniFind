<?php
require_once __DIR__ . '/../config.php';
require_role('student');

$userId = get_current_user_id();
$reportId = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';

// Fetch the found report
$stmt = $conn->prepare('SELECT r.*, u.username AS finder_name FROM reports r JOIN users u ON r.user_id = u.id WHERE r.id = ? AND r.report_type = "found"');
$stmt->bind_param('i', $reportId);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$report) {
    header('Location: ' . base_url('student/dashboard.php'));
    exit();
}

// Check if user already claimed this item
$chk = $conn->prepare('SELECT id, status FROM item_claims WHERE report_id = ? AND claimant_id = ?');
$chk->bind_param('ii', $reportId, $userId);
$chk->execute();
$existingClaim = $chk->get_result()->fetch_assoc();
$chk->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_valid_csrf_token($token)) {
        $error = 'Security validation failed.';
    } elseif ($existingClaim) {
        $error = 'You have already submitted an ownership claim for this item.';
    } else {
        $proofDetails = trim($_POST['proof_of_ownership'] ?? '');
        if (empty($proofDetails)) {
            $error = 'Please provide detailed proof of ownership (e.g., serial numbers, specific scratches, or item contents).';
        } else {
            // Strict 2MB image upload
            $proofImage = handle_file_upload('proof_image');
            if ($proofImage === false) {
                $error = 'Invalid image file or size exceeds 2MB limit. Allowed formats: JPG, PNG, WEBP.';
            } else {
                $ins = $conn->prepare('INSERT INTO item_claims (report_id, claimant_id, proof_of_ownership, proof_image, status) VALUES (?, ?, ?, ?, "pending")');
                $ins->bind_param('iiss', $reportId, $userId, $proofDetails, $proofImage);
                if ($ins->execute()) {
                    $ins->close();

                    // Update report status to 'claimed' pending review
                    $upd = $conn->prepare('UPDATE reports SET status = "claimed" WHERE id = ?');
                    $upd->bind_param('i', $reportId);
                    $upd->execute();
                    $upd->close();

                    log_activity($userId, 'Claim Submitted', "Submitted ownership claim for item Ref: {$report['reference_number']}");
                    
                    // Create notification for finder user
                    create_notification($report['user_id'], 'Ownership Claim Submitted', "A student has submitted an ownership claim for item '{$report['title']}'. Security is evaluating the proof.");

                    $success = 'Your ownership claim has been submitted successfully! Security will evaluate your proof and notify you for item handover.';
                    $existingClaim = ['status' => 'pending'];
                } else {
                    $error = 'Database error while submitting claim.';
                }
            }
        }
    }
}

$unreadNotifications = get_unread_notifications_count($userId);
$pageTitle = 'Submit Proof of Ownership';
require_once __DIR__ . '/../header.php';
?>
<div class="student-body">

  <header class="student-navbar">
    <div class="student-brand">
      <i class="fa-solid fa-graduation-cap gradient-text" style="font-size: 1.5rem;"></i>
      <span>UniFind <span class="gradient-text">Student</span></span>
    </div>

    <div class="student-nav-links">
      <a href="<?= base_url('student/dashboard.php') ?>" class="student-nav-item">
        <i class="fa-solid fa-house"></i> Feed
      </a>
      <a href="<?= base_url('student/report_item.php') ?>" class="student-nav-item">
        <i class="fa-solid fa-plus-circle"></i> Report Item
      </a>
      <a href="<?= base_url('student/my_activity.php') ?>" class="student-nav-item">
        <i class="fa-solid fa-clock-rotate-left"></i> My Activity
      </a>
      <a href="<?= base_url('student/notifications.php') ?>" class="student-nav-item">
        <i class="fa-solid fa-bell"></i>
        <?php if ($unreadNotifications > 0): ?>
          <span style="position: absolute; top: -6px; right: -8px; background: var(--danger); color: #fff; font-size: 0.7rem; font-weight: 800; border-radius: 50%; width: 18px; height: 18px; display: flex; align-items: center; justify-content: center;">
            <?= $unreadNotifications ?>
          </span>
        <?php endif; ?>
      </a>
    </div>

    <div style="display: flex; align-items: center; gap: 12px;">
      <a href="<?= base_url('logout.php') ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-right-from-bracket"></i> Sign Out</a>
    </div>
  </header>

  <main class="student-container" style="max-width: 720px;">
    
    <div style="margin-bottom: 24px;">
      <h1 style="font-size: 1.8rem; margin-bottom: 4px;">Submit Proof of Ownership</h1>
      <p style="color: var(--text-muted); font-size: 0.95rem;">
        Marwadi University Security verification portal. Submit confidential details to verify item ownership.
      </p>
    </div>

    <!-- Found Item Overview Card -->
    <div class="glass-card" style="border-radius: var(--radius-lg); padding: 22px; margin-bottom: 24px; display: flex; gap: 20px; align-items: center;">
      <?php if (!empty($report['image_path']) && file_exists(__DIR__ . '/../' . $report['image_path'])): ?>
        <img src="<?= base_url($report['image_path']) ?>" alt="<?= e($report['title']) ?>" style="width: 100px; height: 100px; object-fit: cover; border-radius: var(--radius-md);">
      <?php else: ?>
        <div style="width: 100px; height: 100px; background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 2rem;">
          <i class="fa-solid fa-hand-holding"></i>
        </div>
      <?php endif; ?>

      <div>
        <span class="badge badge-verified" style="margin-bottom: 6px;">Ref: <?= e($report['reference_number']) ?></span>
        <h3 style="font-size: 1.25rem; margin-bottom: 4px;"><?= e($report['title']) ?></h3>
        <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 6px;">
          <i class="fa-solid fa-location-dot"></i> Found Location: <?= e($report['location']) ?> • Custody: <strong><?= e($report['custody_location'] ?? 'Campus Security Office') ?></strong>
        </p>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i> <?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= e($success) ?></div>
    <?php endif; ?>

    <?php if ($existingClaim): ?>
      <div class="glass-card" style="text-align: center; padding: 40px 20px; border-radius: var(--radius-lg);">
        <i class="fa-solid fa-clock-rotate-left" style="font-size: 3rem; color: var(--warning); margin-bottom: 12px;"></i>
        <h3>Claim Status: <?= ucfirst(str_replace('_', ' ', $existingClaim['status'])) ?></h3>
        <p style="color: var(--text-muted); max-width: 450px; margin: 8px auto 20px;">
          You have already submitted an ownership claim for this item. Track your status under "My Activity".
        </p>
        <a href="<?= base_url('student/my_activity.php?tab=claims') ?>" class="btn btn-primary">
          View My Activity & Claims
        </a>
      </div>
    <?php else: ?>
      <div class="glass-card" style="border-radius: var(--radius-lg); padding: 30px; box-shadow: var(--shadow-md);">
        <form method="POST" action="" enctype="multipart/form-data">
          <?= csrf_input() ?>

          <div class="form-group">
            <label for="proof_of_ownership">
              <i class="fa-solid fa-user-shield" style="color: var(--primary); margin-right: 6px;"></i>
              Confidential Proof of Ownership Details *
            </label>
            <textarea id="proof_of_ownership" name="proof_of_ownership" class="form-control" rows="5" placeholder="Describe confidential identifier details: e.g. device serial numbers, passcode wallpaper description, receipt invoice number, or specific contents inside bag compartments..." required></textarea>
          </div>

          <div class="form-group">
            <label>Upload Supporting Ownership Image (Receipt, Serial Number Tag, or Proof Photo - Max 2MB)</label>
            <input type="file" name="proof_image" class="form-control" accept="image/*">
          </div>

          <button type="submit" class="btn btn-primary" style="width: 100%; padding: 14px; font-size: 1rem; margin-top: 10px;">
            Submit Ownership Claim <i class="fa-solid fa-lock"></i>
          </button>
        </form>
      </div>
    <?php endif; ?>

  </main>
</div>
<?php require_once __DIR__ . '/../footer.php'; ?>
