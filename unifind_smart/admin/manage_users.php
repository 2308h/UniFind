<?php
require_once __DIR__ . '/../config.php';
require_role('admin');

$userId = get_current_user_id();
$msg = '';
$error = '';

// Handle Role / Points Adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_valid_csrf_token($token)) {
        $error = 'Security validation failed.';
    } else {
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);
        $newRole = $_POST['role'] ?? '';
        $addPoints = (int)($_POST['add_points'] ?? 0);

        if ($targetUserId > 0 && in_array($newRole, ['student', 'security', 'admin'], true)) {
            $upd = $conn->prepare('UPDATE users SET role = ? WHERE id = ?');
            $upd->bind_param('si', $newRole, $targetUserId);
            $upd->execute();
            $upd->close();

            if ($addPoints > 0) {
                add_reward_points($targetUserId, $addPoints);
            }

            log_activity($userId, 'User Account Updated', "Updated user #{$targetUserId} role to {$newRole} (+{$addPoints} pts)");
            $msg = "User #{$targetUserId} profile updated successfully!";
        }
    }
}

// Fetch all users
$usersResult = $conn->query("SELECT * FROM users ORDER BY id ASC");
$users = $usersResult ? $usersResult->fetch_all(MYSQLI_ASSOC) : [];

// Fetch Audit Logs
$logsResult = $conn->query("SELECT l.*, u.username FROM activity_logs l LEFT JOIN users u ON l.user_id = u.id ORDER BY l.created_at DESC LIMIT 50");
$logs = $logsResult ? $logsResult->fetch_all(MYSQLI_ASSOC) : [];

$pageTitle = 'User & Audit Management';
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
      <a href="<?= base_url('admin/resolve_claims.php') ?>" class="admin-nav-item">
        <i class="fa-solid fa-scale-balanced"></i> Claims Resolution
      </a>
      <a href="<?= base_url('admin/manage_users.php') ?>" class="admin-nav-item active">
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
        <h1 style="font-size: 1.8rem; margin-bottom: 4px;">User & Audit Management</h1>
        <p style="color: var(--text-muted); font-size: 0.92rem;">Manage student accounts, security staff roles, gamification reward points, and system logs.</p>
      </div>
    </div>

    <?php if ($msg): ?>
      <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= e($msg) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i> <?= e($error) ?></div>
    <?php endif; ?>

    <!-- User Management Table -->
    <div class="data-table-card">
      <div class="data-table-header">
        <h3><i class="fa-solid fa-users" style="color: var(--primary); margin-right: 8px;"></i> Registered Accounts (<?= count($users) ?>)</h3>
      </div>

      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Username</th>
              <th>University Email</th>
              <th>Role</th>
              <th>Reward Points</th>
              <th>Badge Rank</th>
              <th>Joined Date</th>
              <th>Role & Points Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u): ?>
              <tr>
                <td>#<?= $u['id'] ?></td>
                <td><strong><?= e($u['username']) ?></strong></td>
                <td><?= e($u['email']) ?></td>
                <td>
                  <span class="badge <?= $u['role'] === 'admin' ? 'badge-pending' : ($u['role'] === 'security' ? 'badge-claimed' : 'badge-verified') ?>">
                    <?= ucfirst($u['role']) ?>
                  </span>
                </td>
                <td><strong style="color: var(--primary); font-size: 1rem;"><?= (int)$u['reward_points'] ?></strong> pts</td>
                <td>
                  <span class="reward-badge-pill" style="font-size: 0.75rem; padding: 4px 10px; background: rgba(79,70,229,0.1); color: var(--primary); border: 1px solid rgba(79,70,229,0.2);">
                    <i class="fa-solid fa-award"></i> <?= e($u['badge']) ?>
                  </span>
                </td>
                <td><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                <td>
                  <form method="POST" action="" style="display: flex; gap: 6px; align-items: center;">
                    <?= csrf_input() ?>
                    <input type="hidden" name="target_user_id" value="<?= $u['id'] ?>">
                    <select name="role" class="form-control" style="padding: 4px 8px; font-size: 0.8rem; width: 100px;">
                      <option value="student" <?= $u['role'] === 'student' ? 'selected' : '' ?>>Student</option>
                      <option value="security" <?= $u['role'] === 'security' ? 'selected' : '' ?>>Security</option>
                      <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                    </select>
                    <input type="number" name="add_points" placeholder="+pts" class="form-control" style="padding: 4px 8px; font-size: 0.8rem; width: 60px;">
                    <button type="submit" class="btn btn-primary btn-sm" style="padding: 4px 10px;" title="Save Settings">
                      <i class="fa-solid fa-floppy-disk"></i>
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- System Audit Logs Section -->
    <div class="data-table-card">
      <div class="data-table-header">
        <h3><i class="fa-solid fa-shield-halved" style="color: var(--accent); margin-right: 8px;"></i> Security Audit Log Stream</h3>
      </div>
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>Timestamp</th>
              <th>User</th>
              <th>Action</th>
              <th>Details</th>
              <th>IP Address</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($logs as $l): ?>
              <tr>
                <td><small style="color: var(--text-muted);"><?= date('M d, Y H:i:s', strtotime($l['created_at'])) ?></small></td>
                <td><strong><?= e($l['username'] ?? 'System') ?></strong></td>
                <td><span class="badge badge-claimed"><?= e($l['action']) ?></span></td>
                <td style="font-size: 0.88rem;"><?= e($l['details']) ?></td>
                <td><code><?= e($l['ip_address']) ?></code></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>
</div>
<?php require_once __DIR__ . '/../footer.php'; ?>
