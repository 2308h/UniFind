<?php
require_once __DIR__ . '/../config.php';
require_role('student');

$userId = get_current_user_id();

// Handle Mark as Read Actions
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'mark_read' && isset($_GET['id'])) {
        $notifId = (int)$_GET['id'];
        $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
        $stmt->bind_param('ii', $notifId, $userId);
        $stmt->execute();
        $stmt->close();
    } elseif ($_GET['action'] === 'mark_all_read') {
        $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: ' . base_url('student/notifications.php'));
    exit();
}

// Fetch all notifications
$stmt = $conn->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC');
$stmt->bind_param('i', $userId);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$unreadNotifications = get_unread_notifications_count($userId);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Notifications Inbox | UniFind Smart</title>
  <link rel="stylesheet" href="<?= base_url('style.css') ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="student-body">

  <header class="student-navbar">
    <div class="student-brand">
      <i class="fa-solid fa-magnifying-glass-location gradient-text" style="font-size: 1.5rem;"></i>
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
      <a href="<?= base_url('student/smart_matches.php') ?>" class="student-nav-item">
        <i class="fa-solid fa-wand-magic-sparkles" style="color: var(--accent);"></i> Matches
      </a>
      <a href="<?= base_url('student/notifications.php') ?>" class="student-nav-item active">
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

  <main class="student-container" style="max-width: 760px;">
    
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
      <div>
        <h1 style="font-size: 1.8rem; margin-bottom: 4px;">Notifications Inbox</h1>
        <p style="color: var(--text-muted); font-size: 0.95rem;">Stay updated on your claims, reports, and smart match alerts.</p>
      </div>

      <?php if ($unreadNotifications > 0): ?>
        <a href="?action=mark_all_read" class="btn btn-secondary btn-sm">
          <i class="fa-solid fa-check-double"></i> Mark All as Read
        </a>
      <?php endif; ?>
    </div>

    <?php if (empty($notifications)): ?>
      <div class="glass-card" style="text-align: center; padding: 50px 20px; border-radius: var(--radius-lg);">
        <i class="fa-regular fa-bell-slash" style="font-size: 3.5rem; color: var(--text-muted); margin-bottom: 16px;"></i>
        <h3>Your inbox is empty</h3>
        <p style="color: var(--text-muted);">Notifications regarding your reports or claim resolutions will show up here.</p>
      </div>
    <?php else: ?>
      <div style="display: flex; flex-direction: column; gap: 14px;">
        <?php foreach ($notifications as $n): ?>
          <div class="glass-card" style="border-radius: var(--radius-md); padding: 18px 22px; border-left: 5px solid <?= $n['is_read'] ? 'var(--border-color)' : 'var(--primary)' ?>; background: <?= $n['is_read'] ? 'var(--bg-card)' : 'rgba(79, 70, 229, 0.05)' ?>;">
            
            <div style="display: flex; align-items: start; justify-content: space-between; gap: 16px;">
              <div>
                <h4 style="font-size: 1rem; font-weight: 700; margin-bottom: 4px;"><?= e($n['title']) ?></h4>
                <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 8px;"><?= e($n['message']) ?></p>
                <span style="font-size: 0.78rem; color: var(--text-muted);">
                  <i class="fa-regular fa-clock"></i> <?= date('M d, Y h:i A', strtotime($n['created_at'])) ?>
                </span>
              </div>

              <?php if (!$n['is_read']): ?>
                <a href="?action=mark_read&id=<?= $n['id'] ?>" class="btn btn-secondary btn-sm" title="Mark Read">
                  <i class="fa-solid fa-check"></i>
                </a>
              <?php endif; ?>
            </div>

          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </main>

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
