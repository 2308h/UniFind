<?php
require_once __DIR__ . '/../config.php';
require_role('student');

$userId = get_current_user_id();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_valid_csrf_token($token)) {
        $error = 'Security validation failed.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $itemDate = $_POST['item_date'] ?? date('Y-m-d');
        $reportType = $_POST['report_type'] ?? 'lost';

        if (empty($title) || empty($category) || empty($location)) {
            $error = 'Title, category, and location are required fields.';
        } else {
            // Handle file upload with strict 2MB validation
            $imagePath = handle_file_upload('image');
            if ($imagePath === false) {
                $error = 'Failed to upload photo. Please verify image format (JPG/PNG/WEBP) and ensure file size is under 2MB.';
            } else {
                $verStatus = ($reportType === 'found') ? 'pending' : 'verified';

                $stmt = $conn->prepare('INSERT INTO reports (user_id, title, description, category, location, item_date, report_type, status, verification_status, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, "open", ?, ?)');
                $stmt->bind_param('issssssss', $userId, $title, $description, $category, $location, $itemDate, $reportType, $verStatus, $imagePath);
                
                if ($stmt->execute()) {
                    $newReportId = $stmt->insert_id;
                    $stmt->close();

                    log_activity($userId, 'Item Reported', "Reported {$reportType} item: {$title}");
                    add_reward_points($userId, 10);

                    if ($reportType === 'found') {
                        $success = 'Found item report submitted successfully! It is queued for security verification and will be published once approved.';
                    } else {
                        $success = 'Lost item report created successfully! You earned +10 Finder Points.';
                    }
                } else {
                    $error = 'Database error while saving report. Please try again.';
                }
            }
        }
    }
}

$unreadNotifications = get_unread_notifications_count($userId);
$pageTitle = 'Report Lost or Found Item';
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
      <a href="<?= base_url('student/report_item.php') ?>" class="student-nav-item active">
        <i class="fa-solid fa-plus-circle"></i> Report Item
      </a>
      <a href="<?= base_url('student/my_activity.php') ?>" class="student-nav-item">
        <i class="fa-solid fa-clock-rotate-left"></i> My Activity
      </a>
      <a href="<?= base_url('student/notifications.php') ?>" class="student-nav-item">
        <i class="fa-solid fa-bell"></i> Notifications
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
      <h1 style="font-size: 1.8rem; margin-bottom: 4px;">Campus Report Form</h1>
      <p style="color: var(--text-muted); font-size: 0.95rem;">
        Did you lose an item or find something on Marwadi University campus? Submit the report details below.
      </p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i> <?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert alert-success">
        <i class="fa-solid fa-circle-check"></i> <?= e($success) ?>
      </div>
    <?php endif; ?>

    <div class="glass-card" style="border-radius: var(--radius-lg); padding: 30px; box-shadow: var(--shadow-md);">
      <form method="POST" action="" enctype="multipart/form-data">
        <?= csrf_input() ?>

        <div class="form-group">
          <label style="font-size: 0.9rem; font-weight: 700; margin-bottom: 8px;">Report Type *</label>
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
            <label style="border: 2px solid var(--border-color); padding: 14px; border-radius: var(--radius-md); text-align: center; cursor: pointer; transition: var(--transition);" id="label-lost">
              <input type="radio" name="report_type" value="lost" checked onclick="updateTypeUI('lost')" style="margin-right: 6px;">
              <strong style="color: #ef4444;"><i class="fa-solid fa-circle-question"></i> I Lost Something</strong>
            </label>
            <label style="border: 2px solid var(--border-color); padding: 14px; border-radius: var(--radius-md); text-align: center; cursor: pointer; transition: var(--transition);" id="label-found">
              <input type="radio" name="report_type" value="found" onclick="updateTypeUI('found')" style="margin-right: 6px;">
              <strong style="color: #10b981;"><i class="fa-solid fa-hand-holding-heart"></i> I Found Something</strong>
            </label>
          </div>
        </div>

        <div class="form-group">
          <label for="title">Item Title / Name *</label>
          <input type="text" id="title" name="title" class="form-control" placeholder="e.g. Blue Hydroflask Water Bottle" required>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
          <div class="form-group">
            <label for="category">Category *</label>
            <select id="category" name="category" class="form-control" required>
              <option value="">Select Category...</option>
              <option value="Electronics">Electronics (Laptops, Phones, Airpods)</option>
              <option value="Bags & Accessories">Bags & Accessories (Backpacks, Pouches)</option>
              <option value="IDs & Wallets">IDs & Wallets (Student IDs, Wallets)</option>
              <option value="Books & Stationery">Books & Stationery (Notes, Textbooks)</option>
              <option value="Clothing">Clothing & Eyewear (Jackets, Glasses)</option>
              <option value="Keys & Cards">Keys & Cards (Room Keys, Metro Cards)</option>
              <option value="Other">Other Miscellaneous</option>
            </select>
          </div>

          <div class="form-group">
            <label for="item_date">Date Lost / Found *</label>
            <input type="date" id="item_date" name="item_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
        </div>

        <div class="form-group">
          <label for="location">Campus Location *</label>
          <input type="text" id="location" name="location" class="form-control" list="campus-locations" placeholder="e.g. Main Library 2nd Floor" required>
          <datalist id="campus-locations">
            <option value="Main Library 2nd Floor">
            <option value="Student Union Cafe">
            <option value="Engineering Quad Bench">
            <option value="Main Canteen">
            <option value="Science Building Room 204">
            <option value="Block A Main Entrance">
            <option value="Sports Complex Gymnasium">
            <option value="Hostel Block B Lobby">
          </datalist>
        </div>

        <div class="form-group">
          <label for="description">Detailed Description</label>
          <textarea id="description" name="description" class="form-control" rows="3" placeholder="Describe color, size, unique stickers, or distinctive marks..."></textarea>
        </div>

        <!-- Built-in Image Upload Tool with Strict 2MB Limit -->
        <div class="form-group">
          <label>Item Photo (Upload / Mobile Camera - Max 2MB)</label>
          <div style="border: 2px dashed var(--border-color); border-radius: var(--radius-md); padding: 24px; text-align: center; background: var(--input-bg); cursor: pointer;" onclick="document.getElementById('image').click()">
            <i class="fa-solid fa-cloud-arrow-up" style="font-size: 2.2rem; color: var(--primary); margin-bottom: 8px;"></i>
            <p style="font-weight: 700; font-size: 0.95rem;">Click to upload or capture photo</p>
            <p style="color: var(--text-muted); font-size: 0.8rem;">Supports JPG, PNG, WEBP up to 2MB</p>
            <input type="file" id="image" name="image" accept="image/*" style="display: none;" onchange="previewImage(this)">
          </div>
          <div id="image-preview-container" style="display: none; margin-top: 14px; text-align: center;">
            <img id="image-preview" src="#" alt="Preview" style="max-height: 180px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
          </div>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 14px; font-size: 1rem; margin-top: 10px;">
          Submit Campus Report <i class="fa-solid fa-paper-plane"></i>
        </button>

      </form>
    </div>

  </main>
</div>

<script>
  function updateTypeUI(type) {
    document.getElementById('label-lost').style.borderColor = (type === 'lost') ? '#ef4444' : 'var(--border-color)';
    document.getElementById('label-found').style.borderColor = (type === 'found') ? '#10b981' : 'var(--border-color)';
  }

  function previewImage(input) {
    if (input.files && input.files[0]) {
      const reader = new FileReader();
      reader.onload = function(e) {
        document.getElementById('image-preview').src = e.target.result;
        document.getElementById('image-preview-container').style.display = 'block';
      }
      reader.readAsDataURL(input.files[0]);
    }
  }
  updateTypeUI('lost');
</script>
<?php require_once __DIR__ . '/../footer.php'; ?>
