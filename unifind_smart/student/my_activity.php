<?php
require_once __DIR__ . '/../config.php';
require_role('student');

$userId = get_current_user_id();
$unreadNotifications = get_unread_notifications_count($userId);

// Fetch My Reports
$reportsStmt = $conn->prepare('SELECT * FROM reports WHERE user_id = ? ORDER BY created_at DESC');
$reportsStmt->bind_param('i', $userId);
$reportsStmt->execute();
$myReports = $reportsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$reportsStmt->close();

// Fetch My Claims
$claimsStmt = $conn->prepare('SELECT c.*, r.title AS report_title, r.reference_number, r.category, r.location, r.custody_location
                              FROM item_claims c
                              JOIN reports r ON c.report_id = r.id
                              WHERE c.claimant_id = ? ORDER BY c.created_at DESC');
$claimsStmt->bind_param('i', $userId);
$claimsStmt->execute();
$myClaims = $claimsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$claimsStmt->close();

// Fetch My Favorites
$favStmt = $conn->prepare('SELECT r.*, u.username AS reporter_name FROM favorites f
                           JOIN reports r ON f.report_id = r.id
                           JOIN users u ON r.user_id = u.id
                           WHERE f.user_id = ? ORDER BY f.created_at DESC');
$favStmt->bind_param('i', $userId);
$favStmt->execute();
$myFavs = $favStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$favStmt->close();

$activeTab = $_GET['tab'] ?? 'reports';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Activity Hub | UniFind Smart</title>
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
      <a href="<?= base_url('student/my_activity.php') ?>" class="student-nav-item active">
        <i class="fa-solid fa-clock-rotate-left"></i> My Activity
      </a>
      <a href="<?= base_url('student/smart_matches.php') ?>" class="student-nav-item">
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
      <h1 style="font-size: 1.8rem; margin-bottom: 4px;">My Activity Hub</h1>
      <p style="color: var(--text-muted); font-size: 0.95rem;">Track your reported lost/found items, claim status, and saved bookmarks.</p>
    </div>

    <!-- Navigation Tabs -->
    <div style="display: flex; gap: 12px; margin-bottom: 24px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px;">
      <a href="?tab=reports" class="btn <?= $activeTab === 'reports' ? 'btn-primary' : 'btn-secondary' ?>">
        <i class="fa-solid fa-file-lines"></i> My Reports (<?= count($myReports) ?>)
      </a>
      <a href="?tab=claims" class="btn <?= $activeTab === 'claims' ? 'btn-primary' : 'btn-secondary' ?>">
        <i class="fa-solid fa-hand-holding"></i> My Claims (<?= count($myClaims) ?>)
      </a>
      <a href="?tab=favorites" class="btn <?= $activeTab === 'favorites' ? 'btn-primary' : 'btn-secondary' ?>">
        <i class="fa-solid fa-heart" style="color: #ef4444;"></i> Saved Items (<?= count($myFavs) ?>)
      </a>
    </div>

    <!-- TAB 1: MY REPORTS -->
    <?php if ($activeTab === 'reports'): ?>
      <div class="data-table-card">
        <div class="data-table-header">
          <h3><i class="fa-solid fa-clipboard-list" style="color: var(--primary); margin-right: 8px;"></i> My Submitted Reports</h3>
        </div>
        <?php if (empty($myReports)): ?>
          <div style="padding: 40px; text-align: center; color: var(--text-muted);">
            No reports submitted yet.
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Ref #</th>
                  <th>Type</th>
                  <th>Title & Category</th>
                  <th>Location</th>
                  <th>Date</th>
                  <th>Verification</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($myReports as $rep): ?>
                  <tr>
                    <td><strong><?= e($rep['reference_number']) ?></strong></td>
                    <td>
                      <span class="badge <?= $rep['report_type'] === 'found' ? 'badge-verified' : 'badge-pending' ?>">
                        <?= ucfirst($rep['report_type']) ?>
                      </span>
                    </td>
                    <td>
                      <div style="font-weight: 700;"><?= e($rep['title']) ?></div>
                      <div style="font-size: 0.8rem; color: var(--text-muted);"><?= e($rep['category']) ?></div>
                    </td>
                    <td><?= e($rep['location']) ?></td>
                    <td><?= date('M d, Y', strtotime($rep['item_date'])) ?></td>
                    <td>
                      <span class="badge badge-<?= $rep['verification_status'] ?>">
                        <?= ucfirst($rep['verification_status']) ?>
                      </span>
                    </td>
                    <td>
                      <span class="badge badge-<?= $rep['status'] ?>">
                        <?= ucfirst($rep['status']) ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <!-- TAB 2: MY CLAIMS -->
    <?php if ($activeTab === 'claims'): ?>
      <div class="data-table-card">
        <div class="data-table-header">
          <h3><i class="fa-solid fa-hand-holding-heart" style="color: var(--primary); margin-right: 8px;"></i> My Item Claims</h3>
        </div>
        <?php if (empty($myClaims)): ?>
          <div style="padding: 40px; text-align: center; color: var(--text-muted);">
            You haven't submitted any ownership claims yet.
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Ref #</th>
                  <th>Item Title</th>
                  <th>Proof Submitted</th>
                  <th>Claim Status</th>
                  <th>Custody / Handover Location</th>
                  <th>Admin Review Notes</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($myClaims as $clm): ?>
                  <tr>
                    <td><strong><?= e($clm['reference_number']) ?></strong></td>
                    <td><strong><?= e($clm['report_title']) ?></strong></td>
                    <td style="max-width: 250px; font-size: 0.85rem; color: var(--text-muted);">
                      <?= e($clm['proof_of_ownership']) ?>
                    </td>
                    <td>
                      <span class="badge badge-<?= $clm['status'] ?>">
                        <?= ucfirst(str_replace('_', ' ', $clm['status'])) ?>
                      </span>
                    </td>
                    <td>
                      <?= e($clm['custody_location'] ?? 'Security Office Desk') ?>
                    </td>
                    <td style="font-size: 0.85rem; color: var(--text-muted);">
                      <?= e($clm['handover_notes'] ?? 'Pending review by Admin') ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <!-- TAB 3: SAVED FAVORITES -->
    <?php if ($activeTab === 'favorites'): ?>
      <?php if (empty($myFavs)): ?>
        <div class="glass-card" style="text-align: center; padding: 50px; border-radius: var(--radius-lg);">
          <i class="fa-solid fa-heart-crack" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 12px;"></i>
          <h3>No saved bookmarks</h3>
          <p style="color: var(--text-muted);">Click the heart icon on any feed item to save it here for quick access.</p>
        </div>
      <?php else: ?>
        <div class="item-feed-grid">
          <?php foreach ($myFavs as $item): ?>
            <div class="item-card">
              <span class="type-chip <?= $item['report_type'] === 'found' ? 'found' : 'lost' ?>">
                <?= ucfirst($item['report_type']) ?>
              </span>
              <a href="<?= base_url('student/toggle_favorite.php?id=' . $item['id']) ?>" class="fav-btn active">
                <i class="fa-solid fa-heart"></i>
              </a>

              <?php if (!empty($item['image_path']) && file_exists(__DIR__ . '/../' . $item['image_path'])): ?>
                <img src="<?= base_url($item['image_path']) ?>" alt="<?= e($item['title']) ?>" class="item-card-image">
              <?php else: ?>
                <div class="item-card-placeholder">
                  <i class="fa-solid fa-box"></i>
                </div>
              <?php endif; ?>

              <div class="item-card-body">
                <div class="item-card-category"><?= e($item['category']) ?></div>
                <h3 class="item-card-title"><?= e($item['title']) ?></h3>
                <p class="item-card-desc"><?= e($item['description']) ?></p>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
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
