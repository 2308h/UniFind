<?php
require_once __DIR__ . '/../config.php';
require_role(['admin', 'security']);

$userId = get_current_user_id();
$msg = '';
$error = '';

// Process Claim Resolution Decision
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_valid_csrf_token($token)) {
        $error = 'Security validation failed.';
    } else {
        $claimId = (int)($_POST['claim_id'] ?? 0);
        $action = $_POST['action'] ?? '';
        $handoverNotes = trim($_POST['handover_notes'] ?? '');

        if ($claimId > 0 && in_array($action, ['approve', 'handover', 'reject'], true)) {
            // Fetch claim and related report & finder
            $cStmt = $conn->prepare('SELECT c.*, r.id AS report_id, r.reference_number, r.title AS item_title, r.user_id AS finder_user_id
                                    FROM item_claims c
                                    JOIN reports r ON c.report_id = r.id
                                    WHERE c.id = ?');
            $cStmt->bind_param('i', $claimId);
            $cStmt->execute();
            $claim = $cStmt->get_result()->fetch_assoc();
            $cStmt->close();

            if ($claim) {
                if ($action === 'approve') {
                    $upd = $conn->prepare('UPDATE item_claims SET status = "approved", reviewed_by = ?, reviewed_at = NOW(), handover_notes = ? WHERE id = ?');
                    $upd->bind_param('isi', $userId, $handoverNotes, $claimId);
                    $upd->execute();
                    $upd->close();

                    create_notification($claim['claimant_id'], 'Claim Verified & Approved', "Your ownership claim for '{$claim['item_title']}' has been verified by Marwadi University Security! Please visit the Campus Security Desk for handover.");
                    log_activity($userId, 'Claim Approved', "Approved claim #{$claimId} for item Ref: {$claim['reference_number']}");
                    $msg = "Claim #{$claimId} approved!";
                } elseif ($action === 'handover') {
                    // Mark claim handed over
                    $upd = $conn->prepare('UPDATE item_claims SET status = "handed_over", reviewed_by = ?, reviewed_at = NOW(), handover_notes = ? WHERE id = ?');
                    $upd->bind_param('isi', $userId, $handoverNotes, $claimId);
                    $upd->execute();
                    $upd->close();

                    // Mark report resolved
                    $repUpd = $conn->prepare('UPDATE reports SET status = "resolved" WHERE id = ?');
                    $repUpd->bind_param('i', $claim['report_id']);
                    $repUpd->execute();
                    $repUpd->close();

                    // Award +50 Reward Points to the Finder Student!
                    if (!empty($claim['finder_user_id'])) {
                        add_reward_points($claim['finder_user_id'], 50);
                        create_notification($claim['finder_user_id'], 'Reward Points Issued! 🎉', "You earned +50 Finder Points for successfully returning '{$claim['item_title']}' to its rightful owner!");
                    }

                    create_notification($claim['claimant_id'], 'Asset Handover Complete!', "Item '{$claim['item_title']}' has been successfully handed over to you. Thank you for using UniFind Smart!");
                    log_activity($userId, 'Asset Handover Resolved', "Handed over item Ref: {$claim['reference_number']} to claimant User #{$claim['claimant_id']}");
                    $msg = "Item successfully handed over and resolved! Finder rewarded with +50 points.";
                } elseif ($action === 'reject') {
                    $upd = $conn->prepare('UPDATE item_claims SET status = "rejected", reviewed_by = ?, reviewed_at = NOW(), handover_notes = ? WHERE id = ?');
                    $upd->bind_param('isi', $userId, $handoverNotes, $claimId);
                    $upd->execute();
                    $upd->close();

                    $repUpd = $conn->prepare('UPDATE reports SET status = "open" WHERE id = ?');
                    $repUpd->bind_param('i', $claim['report_id']);
                    $repUpd->execute();
                    $repUpd->close();

                    create_notification($claim['claimant_id'], 'Claim Verification Notice', "Your claim for '{$claim['item_title']}' was not verified by security. Note: {$handoverNotes}");
                    log_activity($userId, 'Claim Rejected', "Rejected claim #{$claimId} for item Ref: {$claim['reference_number']}");
                    $msg = "Claim #{$claimId} rejected.";
                }
            }
        }
    }
}

// Fetch all item claims with details
$claimsResult = $conn->query("SELECT c.*, r.reference_number, r.title AS item_title, r.location, r.custody_location,
                                    u.username AS claimant_name, u.email AS claimant_email
                              FROM item_claims c
                              JOIN reports r ON c.report_id = r.id
                              JOIN users u ON c.claimant_id = u.id
                              ORDER BY c.created_at DESC");
$claims = $claimsResult ? $claimsResult->fetch_all(MYSQLI_ASSOC) : [];

$pageTitle = 'Claims Resolution Center';
require_once __DIR__ . '/../header.php';
?>
<div class="admin-layout">
  
  <aside class="admin-sidebar">
    <div class="admin-brand">
      <i class="fa-solid fa-graduation-cap" style="color: var(--primary);"></i>
      <span>UniFind <span class="gradient-text">Admin</span></span>
    </div>

    <nav class="admin-nav">
      <a href="<?= base_url('admin/dashboard.php') ?>" class="admin-nav-item">
        <i class="fa-solid fa-chart-pie"></i> Executive Overview
      </a>
      <a href="<?= base_url('admin/verification_queue.php') ?>" class="admin-nav-item">
        <i class="fa-solid fa-clipboard-check"></i> Verification Queue
      </a>
      <a href="<?= base_url('admin/resolve_claims.php') ?>" class="admin-nav-item active">
        <i class="fa-solid fa-scale-balanced"></i> Claims Resolution
      </a>
      <a href="<?= base_url('admin/manage_users.php') ?>" class="admin-nav-item">
        <i class="fa-solid fa-users-gear"></i> User & Audit Management
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
        <h1 style="font-size: 1.8rem; margin-bottom: 4px;">Claims Resolution Center</h1>
        <p style="color: var(--text-muted); font-size: 0.92rem;">
          Evaluate confidential proof of ownership submissions, approve claims, and process official item handovers.
        </p>
      </div>
    </div>

    <?php if ($msg): ?>
      <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= e($msg) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i> <?= e($error) ?></div>
    <?php endif; ?>

    <?php if (empty($claims)): ?>
      <div class="glass-card" style="text-align: center; padding: 60px 20px; border-radius: var(--radius-lg);">
        <i class="fa-solid fa-scale-balanced" style="font-size: 3.5rem; color: var(--text-muted); margin-bottom: 16px;"></i>
        <h3>No claims currently pending evaluation</h3>
        <p style="color: var(--text-muted);">Student ownership submissions will appear here for security review.</p>
      </div>
    <?php else: ?>
      <div style="display: flex; flex-direction: column; gap: 24px;">
        <?php foreach ($claims as $c): ?>
          <div class="glass-card" style="border-radius: var(--radius-lg); padding: 24px; box-shadow: var(--shadow-card);">
            
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
              <div>
                <span class="badge badge-claimed">Claim #<?= $c['id'] ?></span>
                <span class="badge badge-verified" style="margin-left: 6px;">Ref: <?= e($c['reference_number']) ?></span>
              </div>
              <span class="badge badge-<?= $c['status'] ?>" style="font-size: 0.85rem; padding: 6px 14px;">
                Status: <?= ucfirst(str_replace('_', ' ', $c['status'])) ?>
              </span>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 16px; background: var(--input-bg); padding: 18px; border-radius: var(--radius-md);">
              <div>
                <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Claimed Found Asset</div>
                <h4 style="font-size: 1.15rem; margin: 4px 0;"><?= e($c['item_title']) ?></h4>
                <p style="font-size: 0.85rem; color: var(--text-muted);"><i class="fa-solid fa-location-dot"></i> Custody: <strong><?= e($c['custody_location'] ?? 'Campus Security Desk') ?></strong></p>
              </div>
              <div>
                <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Claimant Student</div>
                <h4 style="font-size: 1.15rem; margin: 4px 0;"><?= e($c['claimant_name']) ?></h4>
                <p style="font-size: 0.85rem; color: var(--text-muted);"><i class="fa-solid fa-envelope"></i> <?= e($c['claimant_email']) ?></p>
              </div>
            </div>

            <!-- Confidential Proof Box -->
            <div style="background: rgba(79, 70, 229, 0.06); border: 1px solid rgba(79, 70, 229, 0.2); border-radius: var(--radius-md); padding: 16px; margin-bottom: 18px;">
              <div style="font-size: 0.8rem; font-weight: 800; color: var(--primary); text-transform: uppercase; margin-bottom: 6px;">
                <i class="fa-solid fa-user-shield"></i> Submitted Proof of Ownership:
              </div>
              <p style="font-size: 0.95rem; color: var(--text-main); line-height: 1.5;">
                "<?= nl2br(e($c['proof_of_ownership'])) ?>"
              </p>

              <?php if (!empty($c['proof_image']) && file_exists(__DIR__ . '/../' . $c['proof_image'])): ?>
                <div style="margin-top: 12px;">
                  <a href="<?= base_url($c['proof_image']) ?>" target="_blank" class="btn btn-secondary btn-sm">
                    <i class="fa-solid fa-paperclip"></i> View Uploaded Ownership Proof Image / Receipt
                  </a>
                </div>
              <?php endif; ?>
            </div>

            <!-- Resolution Form -->
            <form method="POST" action="" style="display: flex; flex-direction: column; gap: 12px;">
              <?= csrf_input() ?>
              <input type="hidden" name="claim_id" value="<?= $c['id'] ?>">

              <div>
                <input type="text" name="handover_notes" class="form-control" placeholder="Security verification and handover notes..." value="<?= e($c['handover_notes'] ?? '') ?>" style="padding: 10px 14px; font-size: 0.88rem;">
              </div>

              <div style="display: flex; gap: 12px;">
                <?php if ($c['status'] === 'pending'): ?>
                  <button type="submit" name="action" value="approve" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-thumbs-up"></i> Approve Ownership Claim
                  </button>
                <?php endif; ?>

                <?php if ($c['status'] === 'pending' || $c['status'] === 'approved'): ?>
                  <button type="submit" name="action" value="handover" class="btn btn-success btn-sm">
                    <i class="fa-solid fa-hand-holding-dollar"></i> Record Handover & Award Finder +50 Pts
                  </button>
                <?php endif; ?>

                <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm" onclick="return confirm('Reject this ownership claim?')">
                  <i class="fa-solid fa-xmark"></i> Reject Claim
                </button>
              </div>
            </form>

          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </main>
</div>
<?php require_once __DIR__ . '/../footer.php'; ?>
