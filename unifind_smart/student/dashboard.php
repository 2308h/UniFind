<?php
require_once __DIR__ . '/../config.php';
require_role('student');

$userId = get_current_user_id();

// Fetch Student Profile & Reward Points
$userStmt = $conn->prepare('SELECT username, email, reward_points, badge FROM users WHERE id = ?');
$userStmt->bind_param('i', $userId);
$userStmt->execute();
$studentUser = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

$rewardPoints = (int) ($studentUser['reward_points'] ?? 0);
$userBadge = $studentUser['badge'] ?? 'Novice Finder';

// Fetch Filters
$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$reportType = trim($_GET['type'] ?? '');
$location = trim($_GET['location'] ?? '');

// Build Query for Verified Items (Found & Lost)
$sql = "SELECT r.*, u.username AS reporter_name,
        (SELECT COUNT(*) FROM favorites f WHERE f.report_id = r.id AND f.user_id = ?) AS is_fav
        FROM reports r
        JOIN users u ON r.user_id = u.id
        WHERE r.verification_status = 'verified' AND r.status != 'resolved'";

$params = [$userId];
$types = 'i';

if (!empty($search)) {
    $sql .= " AND (r.title LIKE ? OR r.description LIKE ? OR r.location LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'sss';
}

if (!empty($category)) {
    $sql .= " AND r.category = ?";
    $params[] = $category;
    $types .= 's';
}

if (!empty($reportType)) {
    $sql .= " AND r.report_type = ?";
    $params[] = $reportType;
    $types .= 's';
}

if (!empty($location)) {
    $sql .= " AND r.location LIKE ?";
    $params[] = '%' . $location . '%';
    $types .= 's';
}

$sql .= " ORDER BY r.created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch Unread Notification Count
$unreadNotifications = get_unread_notifications_count($userId);

// Fetch Categories for Filter Dropdown
$catResult = $conn->query("SELECT DISTINCT category FROM reports WHERE category IS NOT NULL AND category != ''");
$categories = $catResult ? $catResult->fetch_all(MYSQLI_ASSOC) : [];

$pageTitle = 'Student Community Feed';
require_once __DIR__ . '/../header.php';
?>
<div class="student-body">

  <!-- Top Navigation Header -->
  <header class="student-navbar">
    <div class="student-brand">
      <i class="fa-solid fa-graduation-cap gradient-text" style="font-size: 1.5rem;"></i>
      <span>UniFind <span class="gradient-text">Student</span></span>
    </div>

    <div class="student-nav-links">
      <a href="<?= base_url('student/dashboard.php') ?>" class="student-nav-item active">
        <i class="fa-solid fa-house"></i> Feed
      </a>
      <a href="<?= base_url('student/report_item.php') ?>" class="student-nav-item">
        <i class="fa-solid fa-plus-circle"></i> Report Item
      </a>
      <a href="<?= base_url('student/my_activity.php') ?>" class="student-nav-item">
        <i class="fa-solid fa-clock-rotate-left"></i> My Activity
      </a>
      <a href="<?= base_url('student/notifications.php') ?>" class="student-nav-item" style="position: relative;">
        <i class="fa-solid fa-bell"></i> Notifications
        <?php if ($unreadNotifications > 0): ?>
          <span style="position: absolute; top: -6px; right: -8px; background: var(--danger); color: #fff; font-size: 0.7rem; font-weight: 800; border-radius: 50%; width: 18px; height: 18px; display: flex; align-items: center; justify-content: center;">
            <?= $unreadNotifications ?>
          </span>
        <?php endif; ?>
      </a>
    </div>

    <div style="display: flex; align-items: center; gap: 12px;">
      <a href="<?= base_url('logout.php') ?>" class="btn btn-secondary btn-sm" title="Sign Out">
        <i class="fa-solid fa-right-from-bracket"></i> Sign Out
      </a>
    </div>
  </header>

  <main class="student-container">
    
    <!-- Gamification & Profile Banner -->
    <div class="student-reward-widget">
      <div class="reward-info">
        <h2>Welcome back, <?= e($_SESSION['username']) ?>! 👋</h2>
        <p>Marwadi University Asset Recovery Portal. Help locate lost campus belongings to earn recognition.</p>
        <div style="margin-top: 12px;">
          <span class="reward-badge-pill">
            <i class="fa-solid fa-award" style="color: #facc15;"></i> Rank: <?= e($userBadge) ?>
          </span>
        </div>
      </div>
      <div class="points-counter">
        <?= $rewardPoints ?>
        <span>Finder Points</span>
      </div>
    </div>

    <!-- Filter & Search Controls -->
    <div class="glass-card" style="border-radius: var(--radius-lg); padding: 20px; margin-bottom: 28px; box-shadow: var(--shadow-sm);">
      <form method="GET" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; align-items: end;">
        
        <div>
          <label style="font-size: 0.82rem; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 6px;">Search Items</label>
          <input type="text" name="search" class="form-control" placeholder="Search keywords..." value="<?= e($search) ?>">
        </div>

        <div>
          <label style="font-size: 0.82rem; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 6px;">Category</label>
          <select name="category" class="form-control">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= e($cat['category']) ?>" <?= $category === $cat['category'] ? 'selected' : '' ?>>
                <?= e($cat['category']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label style="font-size: 0.82rem; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 6px;">Type</label>
          <select name="type" class="form-control">
            <option value="">All Types (Lost & Found)</option>
            <option value="found" <?= $reportType === 'found' ? 'selected' : '' ?>>Found Items Only</option>
            <option value="lost" <?= $reportType === 'lost' ? 'selected' : '' ?>>Lost Reports Only</option>
          </select>
        </div>

        <div style="display: flex; gap: 8px;">
          <button type="submit" class="btn btn-primary" style="flex-grow: 1;">
            <i class="fa-solid fa-filter"></i> Filter
          </button>
          <a href="<?= base_url('student/dashboard.php') ?>" class="btn btn-secondary" title="Reset Filters">
            <i class="fa-solid fa-rotate-left"></i>
          </a>
        </div>
      </form>
    </div>

    <!-- Visual Community Feed Header -->
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
      <h2 style="font-size: 1.4rem;">
        <i class="fa-solid fa-layer-group" style="color: var(--primary); margin-right: 8px;"></i> Community Feed
      </h2>
      <span style="font-size: 0.9rem; color: var(--text-muted); font-weight: 600;">
        Showing <?= count($items) ?> items
      </span>
    </div>

    <!-- Feed Grid -->
    <?php if (empty($items)): ?>
      <div class="glass-card" style="text-align: center; padding: 60px 20px; border-radius: var(--radius-lg);">
        <i class="fa-solid fa-box-open" style="font-size: 3.5rem; color: var(--text-muted); margin-bottom: 16px;"></i>
        <h3>No matching items found</h3>
        <p style="color: var(--text-muted); max-width: 400px; margin: 8px auto 20px;">
          Adjust your search filters or report a new lost/found item to assist the campus community.
        </p>
        <a href="<?= base_url('student/report_item.php') ?>" class="btn btn-primary">
          <i class="fa-solid fa-plus"></i> Report Item Now
        </a>
      </div>
    <?php else: ?>
      <div class="item-feed-grid">
        <?php foreach ($items as $item): ?>
          <div class="item-card">
            
            <span class="type-chip <?= $item['report_type'] === 'found' ? 'found' : 'lost' ?>">
              <?= ucfirst($item['report_type']) ?>
            </span>

            <a href="<?= base_url('student/toggle_favorite.php?id=' . $item['id']) ?>" class="fav-btn <?= $item['is_fav'] ? 'active' : '' ?>" title="Bookmark Item">
              <i class="fa-solid fa-heart"></i>
            </a>

            <?php if (!empty($item['image_path']) && file_exists(__DIR__ . '/../' . $item['image_path'])): ?>
              <img src="<?= base_url($item['image_path']) ?>" alt="<?= e($item['title']) ?>" class="item-card-image">
            <?php else: ?>
              <div class="item-card-placeholder">
                <i class="<?= $item['report_type'] === 'found' ? 'fa-solid fa-hand-holding-hand' : 'fa-solid fa-magnifying-glass' ?>"></i>
                <span style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-top: 6px; color: rgba(255,255,255,0.7);">
                  <?= e($item['category']) ?>
                </span>
              </div>
            <?php endif; ?>

            <div class="item-card-body">
              <div class="item-card-category"><?= e($item['category']) ?> • Ref: <?= e($item['reference_number']) ?></div>
              <h3 class="item-card-title"><?= e($item['title']) ?></h3>
              <p class="item-card-desc"><?= e($item['description']) ?></p>
              
              <div class="item-card-meta">
                <div class="item-card-meta-item">
                  <i class="fa-solid fa-location-dot" style="color: var(--primary);"></i> <?= e($item['location']) ?>
                </div>
                <div class="item-card-meta-item">
                  <i class="fa-solid fa-calendar-day"></i> <?= date('M d, Y', strtotime($item['item_date'])) ?>
                </div>
              </div>
            </div>

            <div class="item-card-footer">
              <div style="font-size: 0.8rem; color: var(--text-muted);">
                By <strong><?= e($item['reporter_name']) ?></strong>
              </div>

              <?php if ($item['report_type'] === 'found' && (int)$item['user_id'] !== $userId): ?>
                <a href="<?= base_url('student/submit_claim.php?id=' . $item['id']) ?>" class="btn btn-primary btn-sm">
                  <i class="fa-solid fa-hand-holding"></i> Claim Item
                </a>
              <?php else: ?>
                <span class="badge badge-<?= $item['status'] ?>">
                  <?= ucfirst($item['status']) ?>
                </span>
              <?php endif; ?>
            </div>

          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </main>
</div>
<?php require_once __DIR__ . '/../footer.php'; ?>
