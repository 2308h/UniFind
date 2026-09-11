<?php
require_once __DIR__ . '/../config.php';
require_role('student');

$userId = get_current_user_id();
$unreadNotifications = get_unread_notifications_count($userId);

// Fetch student's open lost items
$lostStmt = $conn->prepare('SELECT * FROM reports WHERE user_id = ? AND report_type = "lost" AND status = "open"');
$lostStmt->bind_param('i', $userId);
$lostStmt->execute();
$myLostReports = $lostStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$lostStmt->close();

$matches = [];

if (!empty($myLostReports)) {
    foreach ($myLostReports as $lost) {
        // Query verified found reports in the same category
        $findStmt = $conn->prepare('SELECT r.*, u.username AS finder_name FROM reports r
                                    JOIN users u ON r.user_id = u.id
                                    WHERE r.report_type = "found" AND r.verification_status = "verified" AND r.status = "open" AND r.category = ? AND r.user_id != ?');
        $findStmt->bind_param('si', $lost['category'], $userId);
        $findStmt->execute();
        $foundItems = $findStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $findStmt->close();

        foreach ($foundItems as $found) {
            // Calculate similarity confidence score based on location & title keyword overlap
            $score = 70; // Base category match score
            if (stripos($found['location'], $lost['location']) !== false || stripos($lost['location'], $found['location']) !== false) {
                $score += 25;
            }
            if (stripos($found['title'], $lost['title']) !== false || stripos($lost['title'], $found['title']) !== false) {
                $score += 15;
            }
            if ($score > 98) $score = 98;

            $matches[] = [
                'lost_item' => $lost,
                'found_item' => $found,
                'match_score' => $score
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Smart Match Alerts | UniFind Smart</title>
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
      <a href="<?= base_url('student/smart_matches.php') ?>" class="student-nav-item active">
        <i class="fa-solid fa-wand-magic-sparkles" style="color: var(--accent);"></i> Matches
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

  <main class="student-container">
    
    <div style="margin-bottom: 24px;">
      <h1 style="font-size: 1.8rem; margin-bottom: 4px;">
        <i class="fa-solid fa-wand-magic-sparkles" style="color: var(--accent);"></i> Smart Match Alerts
      </h1>
      <p style="color: var(--text-muted); font-size: 0.95rem;">
        Our AI matching algorithm cross-references your reported lost items against newly verified found items on campus.
      </p>
    </div>

    <?php if (empty($matches)): ?>
      <div class="glass-card" style="text-align: center; padding: 60px 20px; border-radius: var(--radius-lg);">
        <i class="fa-solid fa-shield-cat" style="font-size: 3.5rem; color: var(--text-muted); margin-bottom: 16px;"></i>
        <h3>No active matches right now</h3>
        <p style="color: var(--text-muted); max-width: 450px; margin: 8px auto 20px;">
          Make sure you've reported your lost item with precise category and location details so our system can alert you as soon as a matching item is found!
        </p>
        <a href="<?= base_url('student/report_item.php') ?>" class="btn btn-primary">
          <i class="fa-solid fa-plus"></i> Report Lost Item
        </a>
      </div>
    <?php else: ?>
      <div style="display: flex; flex-direction: column; gap: 20px;">
        <?php foreach ($matches as $m): ?>
          <div class="glass-card" style="border-radius: var(--radius-lg); padding: 24px; border-left: 6px solid var(--accent); box-shadow: var(--shadow-md);">
            
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
              <span class="badge" style="background: rgba(245, 158, 11, 0.2); color: #d97706; font-size: 0.85rem; padding: 6px 14px;">
                <i class="fa-solid fa-bolt"></i> <?= $m['match_score'] ?>% Match Confidence
              </span>
              <span style="font-size: 0.82rem; color: var(--text-muted);">
                Found on <?= date('M d, Y', strtotime($m['found_item']['item_date'])) ?>
              </span>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; background: var(--input-bg); padding: 18px; border-radius: var(--radius-md); margin-bottom: 18px;">
              <div>
                <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #ef4444;">Your Lost Item Report</div>
                <h4 style="font-size: 1.1rem; margin: 4px 0;"><?= e($m['lost_item']['title']) ?></h4>
                <div style="font-size: 0.85rem; color: var(--text-muted);"><i class="fa-solid fa-location-dot"></i> <?= e($m['lost_item']['location']) ?></div>
              </div>
              <div style="border-left: 1px solid var(--border-color); padding-left: 20px;">
                <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #10b981;">Matched Found Item</div>
                <h4 style="font-size: 1.1rem; margin: 4px 0;"><?= e($m['found_item']['title']) ?></h4>
                <div style="font-size: 0.85rem; color: var(--text-muted);"><i class="fa-solid fa-location-dot"></i> <?= e($m['found_item']['location']) ?></div>
              </div>
            </div>

            <div style="display: flex; align-items: center; justify-content: space-between;">
              <p style="font-size: 0.88rem; color: var(--text-muted); margin: 0;">
                Found by <strong><?= e($m['found_item']['finder_name']) ?></strong> • Custody: <strong><?= e($m['found_item']['custody_location'] ?? 'Security Desk Locker') ?></strong>
              </p>
              <a href="<?= base_url('student/claim_item.php?id=' . $m['found_item']['id']) ?>" class="btn btn-primary">
                Submit Ownership Proof <i class="fa-solid fa-arrow-right"></i>
              </a>
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
