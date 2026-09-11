<?php
require_once __DIR__ . '/../config.php';
require_role(['admin', 'security']);

$userId = get_current_user_id();
$msg = '';
$error = '';

// Handle Approval / Rejection Post Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_valid_csrf_token($token)) {
        $error = 'Invalid CSRF security token.';
    } else {
        $reportId = (int)($_POST['report_id'] ?? 0);
        $action = $_POST['action'] ?? '';
        $custodyLocation = trim($_POST['custody_location'] ?? 'Security Desk Safe');
        $custodyNotes = trim($_POST['custody_notes'] ?? '');

        if ($reportId > 0 && in_array($action, ['approve', 'reject'], true)) {
            // Fetch report details
            $repStmt = $conn->prepare('SELECT * FROM reports WHERE id = ?');
            $repStmt->bind_param('i', $reportId);
            $repStmt->execute();
            $rep = $repStmt->get_result()->fetch_assoc();
            $repStmt->close();

            if ($rep) {
                if ($action === 'approve') {
                    $newVerStatus = 'verified';
                    $stmt = $conn->prepare('UPDATE reports SET verification_status = ?, verified_by = ?, verified_at = NOW(), custody_location = ?, custody_notes = ? WHERE id = ?');
                    $stmt->bind_param('sissi', $newVerStatus, $userId, $custodyLocation, $custodyNotes, $reportId);
                    $stmt->execute();
                    $stmt->close();

                    log_activity($userId, 'Item Approved', "Approved found item Ref: {$rep['reference_number']}");
                    create_notification($rep['user_id'], 'Report Verified & Published', "Your reported found item '{$rep['title']}' has been verified by security and published on the community feed.");

                    $msg = "Item Ref: {$rep['reference_number']} approved and published on Student Feed!";
                } else {
                    $newVerStatus = 'rejected';
                    $stmt = $conn->prepare('UPDATE reports SET verification_status = ? WHERE id = ?');
                    $stmt->bind_param('si', $newVerStatus, $reportId);
                    $stmt->execute();
                    $stmt->close();

                    log_activity($userId, 'Item Rejected', "Rejected found item report Ref: {$rep['reference_number']}");
                    create_notification($rep['user_id'], 'Report Rejected', "Your reported found item '{$rep['title']}' was rejected by security review.");

                    $msg = "Item Ref: {$rep['reference_number']} rejected.";
                }
            }
        }
    }
}

// Fetch Pending Reports
$pendingResult = $conn->query("SELECT r.*, u.username, u.email FROM reports r JOIN users u ON r.user_id = u.id WHERE r.verification_status = 'pending' ORDER BY r.created_at DESC");
$pendingItems = $pendingResult ? $pendingResult->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verification Queue | UniFind Admin</title>
  <link rel="stylesheet" href="<?= base_url('style.css') ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

  <div class="admin-layout">
    
    <!-- Fixed Sidebar Navigation -->
    <aside class="admin-sidebar">
      <div class="admin-brand">
        <i class="fa-solid fa-shield-halved" style="color: var(--primary);"></i>
        <span>UniFind <span class="gradient-text">Admin</span></span>
      </div>

      <nav class="admin-nav">
        <a href="<?= base_url('admin/dashboard.php') ?>" class="admin-nav-item">
          <i class="fa-solid fa-chart-pie"></i> Executive Overview
        </a>
        <a href="<?= base_url('admin/verification_queue.php') ?>" class="admin-nav-item active">
          <i class="fa-solid fa-clipboard-check"></i> Verification Queue
          <?php if (count($pendingItems) > 0): ?>
            <span class="badge badge-pending" style="margin-left: auto; padding: 2px 8px; font-size: 0.7rem;">
              <?= count($pendingItems) ?>
            </span>
          <?php endif; ?>
        </a>
        <a href="<?= base_url('admin/claims_center.php') ?>" class="admin-nav-item">
          <i class="fa-solid fa-scale-balanced"></i> Claims Resolution
        </a>
        <a href="<?= base_url('admin/user_management.php') ?>" class="admin-nav-item">
          <i class="fa-solid fa-users-gear"></i> User Management
        </a>
        <a href="<?= base_url('admin/audit_logs.php') ?>" class="admin-nav-item">
          <i class="fa-solid fa-clock-rotate-left"></i> Security Audit Logs
        </a>
        <a href="<?= base_url('admin/analytics.php') ?>" class="admin-nav-item">
          <i class="fa-solid fa-chart-line"></i> Analytics & Trends
        </a>
        <a href="<?= base_url('admin/export_csv.php') ?>" class="admin-nav-item" style="margin-top: 10px; background: rgba(16, 185, 129, 0.1); color: #10b981;">
          <i class="fa-solid fa-file-csv"></i> Export CSV Records
        </a>
      </nav>

      <div class="admin-user-widget">
        <div style="width: 38px; height: 38px; border-radius: 50%; background: var(--primary); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700;">
          <?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)) ?>
        </div>
        <div style="flex-grow: 1; overflow: hidden;">
          <div style="font-weight: 700; font-size: 0.88rem; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= e($_SESSION['username']) ?></div>
          <div style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase;"><?= e($_SESSION['role']) ?></div>
        </div>
        <a href="<?= base_url('logout.php') ?>" style="color: #ef4444;" title="Sign Out"><i class="fa-solid fa-right-from-bracket"></i></a>
      </div>
    </aside>

    <main class="admin-main">
      
      <div class="admin-header">
        <div>
          <h1 style="font-size: 1.8rem; margin-bottom: 4px;">Found Item Verification Queue</h1>
          <p style="color: var(--text-muted); font-size: 0.92rem;">
            Review student-submitted found items before they are published publicly on the Community Feed.
          </p>
        </div>
      </div>

      <?php if ($msg): ?>
        <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= e($msg) ?></div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i> <?= e($error) ?></div>
      <?php endif; ?>

      <?php if (empty($pendingItems)): ?>
        <div class="glass-card" style="text-align: center; padding: 60px 20px; border-radius: var(--radius-lg);">
          <i class="fa-solid fa-circle-check" style="font-size: 3.5rem; color: #10b981; margin-bottom: 16px;"></i>
          <h3>Verification Queue Clean!</h3>
          <p style="color: var(--text-muted);">All found items submitted by students have been reviewed and moderated.</p>
        </div>
      <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 24px;">
          <?php foreach ($pendingItems as $item): ?>
            <div class="glass-card" style="border-radius: var(--radius-lg); padding: 24px; box-shadow: var(--shadow-card);">
              
              <div style="display: grid; grid-template-columns: 180px 1fr; gap: 24px; align-items: start;">
                
                <!-- Image or Graphic -->
                <?php if (!empty($item['image_path']) && file_exists(__DIR__ . '/../' . $item['image_path'])): ?>
                  <img src="<?= base_url($item['image_path']) ?>" alt="<?= e($item['title']) ?>" style="width: 100%; height: 160px; object-fit: cover; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                <?php else: ?>
                  <div style="width: 100%; height: 160px; background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-radius: var(--radius-md); display: flex; flex-direction: column; align-items: center; justify-content: center; color: var(--primary);">
                    <i class="fa-solid fa-shield-cat" style="font-size: 2.5rem; margin-bottom: 6px;"></i>
                    <span style="font-size: 0.75rem; font-weight: 700; color: #94a3b8;">NO PHOTO</span>
                  </div>
                <?php endif; ?>

                <!-- Details & Action Form -->
                <div>
                  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                    <div>
                      <span class="badge badge-pending">Ref: <?= e($item['reference_number']) ?></span>
                      <span class="badge badge-claimed" style="margin-left: 6px;"><?= e($item['category']) ?></span>
                    </div>
                    <span style="font-size: 0.82rem; color: var(--text-muted);">
                      Submitted by: <strong><?= e($item['username']) ?></strong> (<?= e($item['email']) ?>)
                    </span>
                  </div>

                  <h3 style="font-size: 1.3rem; margin-bottom: 6px;"><?= e($item['title']) ?></h3>
                  <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 14px;"><?= e($item['description']) ?></p>

                  <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 18px; display: flex; gap: 20px;">
                    <span><i class="fa-solid fa-location-dot" style="color: var(--primary);"></i> Location: <strong><?= e($item['location']) ?></strong></span>
                    <span><i class="fa-solid fa-calendar-day"></i> Date Found: <strong><?= date('M d, Y', strtotime($item['item_date'])) ?></strong></span>
                  </div>

                  <!-- Verification Form -->
                  <form method="POST" action="" style="background: var(--input-bg); padding: 16px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                    <?= csrf_input() ?>
                    <input type="hidden" name="report_id" value="<?= $item['id'] ?>">

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                      <div>
                        <label style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 4px;">Assign Security Custody Locker *</label>
                        <input type="text" name="custody_location" class="form-control" value="Security Desk Locker 04" required style="padding: 8px 12px; font-size: 0.88rem;">
                      </div>
                      <div>
                        <label style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 4px;">Internal Admin Notes</label>
                        <input type="text" name="custody_notes" class="form-control" placeholder="Optional notes..." style="padding: 8px 12px; font-size: 0.88rem;">
                      </div>
                    </div>

                    <div style="display: flex; gap: 12px;">
                      <button type="submit" name="action" value="approve" class="btn btn-primary btn-sm" style="background: #10b981; border: none;">
                        <i class="fa-solid fa-check-circle"></i> Approve & Publish to Feed
                      </button>
                      <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to reject this submission?')">
                        <i class="fa-solid fa-xmark-circle"></i> Reject Report
                      </button>
                    </div>
                  </form>

                </div>

              </div>

            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    </main>

  </div>

  <script>
    function toggleTheme() {
      const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
      const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
      document.documentElement.setAttribute('data-theme', newTheme);
      localStorage.setItem('unifind_theme', newTheme);
    }
    const savedTheme = localStorage.getItem('unifind_theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
  </script>
</body>
</html>
