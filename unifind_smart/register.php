<?php
require_once __DIR__ . '/config.php';

define('ADMIN_SECRET_KEY', 'admin123');

// If already logged in, redirect straight to dashboard
if (is_logged_in()) {
    header('Location: ' . base_url(get_current_role() === 'admin' ? 'admin/dashboard.php' : 'student/dashboard.php'));
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_valid_csrf_token($token)) {
        $error = 'Security validation failed. Please refresh and try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $desiredRole = $_POST['role'] ?? 'student';
        $adminKey = trim($_POST['admin_key'] ?? '');

        if (empty($username) || empty($email) || empty($password)) {
            $error = 'All required fields must be completed.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid university email address.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters in length.';
        } else {
            // Role Determination
            $finalRole = 'student';
            $badge = 'Novice Finder';
            $points = 0;

            if ($desiredRole === 'admin' || $desiredRole === 'security') {
                if ($adminKey !== ADMIN_SECRET_KEY) {
                    $error = 'Invalid Admin Security Key. Please verify key or select Student Account.';
                } else {
                    $finalRole = $desiredRole;
                    $badge = ($finalRole === 'admin') ? 'Master Admin' : 'Security Guardian';
                    $points = ($finalRole === 'admin') ? 500 : 250;
                }
            }

            if (empty($error)) {
                // Check if username or email exists
                $check = $conn->prepare('SELECT id FROM users WHERE email = ? OR username = ?');
                $check->bind_param('ss', $email, $username);
                $check->execute();
                if ($check->get_result()->num_rows > 0) {
                    $error = 'An account with that email address or username already exists.';
                    $check->close();
                } else {
                    $check->close();
                    $hashed = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $conn->prepare('INSERT INTO users (username, email, password, role, reward_points, badge) VALUES (?, ?, ?, ?, ?, ?)');
                    $stmt->bind_param('ssssis', $username, $email, $hashed, $finalRole, $points, $badge);
                    
                    if ($stmt->execute()) {
                        $newUserId = $stmt->insert_id;
                        $stmt->close();
                        
                        log_activity($newUserId, 'Account Registration', "Registered new {$finalRole} account: {$username}");

                        // ==========================================
                        // CRITICAL FEATURE: SEAMLESS AUTO-LOGIN
                        // Set session state immediately upon registration
                        // ==========================================
                        $_SESSION['user_id'] = $newUserId;
                        $_SESSION['username'] = $username;
                        $_SESSION['email'] = $email;
                        $_SESSION['role'] = $finalRole;
                        $_SESSION['reward_points'] = $points;
                        $_SESSION['badge'] = $badge;

                        // Direct redirect to relevant dashboard without manual re-login
                        if ($finalRole === 'admin' || $finalRole === 'security') {
                            header('Location: ' . base_url('admin/dashboard.php'));
                        } else {
                            header('Location: ' . base_url('student/dashboard.php'));
                        }
                        exit();
                    } else {
                        $error = 'System error creating account. Please try again.';
                    }
                }
            }
        }
    }
}

$pageTitle = 'Account Registration';
require_once __DIR__ . '/header.php';
?>
  <div style="min-height: 90vh; display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 20px;">

    <div style="max-width: 480px; width: 100%; background: var(--bg-card); border-radius: var(--radius-lg); border: 1px solid var(--border-color); padding: 36px; box-shadow: var(--shadow-lg);">
      
      <div style="text-align: center; margin-bottom: 24px;">
        <h1 style="font-size: 1.7rem; margin-bottom: 4px;">Join <span class="gradient-text">UniFind Smart</span></h1>
        <p style="color: var(--text-muted); font-size: 0.9rem; font-weight: 600;">
          Marwadi University Student & Staff Portal
        </p>
      </div>

      <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i> <?= e($error) ?></div>
      <?php endif; ?>

      <form method="POST" action="">
        <?= csrf_input() ?>
        
        <div class="form-group">
          <label for="username"><i class="fa-solid fa-user" style="margin-right: 6px; color: var(--primary);"></i> Full Username *</label>
          <input type="text" id="username" name="username" class="form-control" placeholder="e.g. alex_student" required>
        </div>

        <div class="form-group">
          <label for="email"><i class="fa-solid fa-envelope" style="margin-right: 6px; color: var(--primary);"></i> University Email *</label>
          <input type="email" id="email" name="email" class="form-control" placeholder="student@unifind.edu" required>
        </div>

        <div class="form-group">
          <label for="role"><i class="fa-solid fa-user-shield" style="margin-right: 6px; color: var(--primary);"></i> Account Type *</label>
          <select id="role" name="role" class="form-control" onchange="toggleAdminKeyField(this.value)">
            <option value="student">🎓 Student Account</option>
            <option value="admin">🛡️ Administrator Account</option>
            <option value="security">👮 Security Staff Account</option>
          </select>
        </div>

        <div class="form-group" id="admin_key_group" style="display: none; background: rgba(79, 70, 229, 0.08); padding: 14px; border-radius: var(--radius-md); border: 1px solid rgba(79, 70, 229, 0.2);">
          <label for="admin_key" style="color: var(--primary); font-weight: 700;">
            <i class="fa-solid fa-key"></i> Administrative Security Key *
          </label>
          <input type="password" id="admin_key" name="admin_key" class="form-control" placeholder="Enter Administrative Key">
        </div>

        <div class="form-group">
          <label for="password"><i class="fa-solid fa-lock" style="margin-right: 6px; color: var(--primary);"></i> Password *</label>
          <input type="password" id="password" name="password" class="form-control" placeholder="Minimum 6 characters" required>
        </div>

        <div class="form-group">
          <label for="confirm_password"><i class="fa-solid fa-check-double" style="margin-right: 6px; color: var(--primary);"></i> Confirm Password *</label>
          <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Confirm your password" required>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 13px; font-size: 1rem; margin-top: 10px;">
          Create Account & Log In <i class="fa-solid fa-user-check"></i>
        </button>
      </form>

      <div style="text-align: center; margin-top: 24px; font-size: 0.9rem; color: var(--text-muted);">
        Already registered? <a href="<?= base_url('index.php') ?>" style="font-weight: 700;">Sign In Here</a>
      </div>

    </div>
  </div>

  <script>
    function toggleAdminKeyField(roleVal) {
      const group = document.getElementById('admin_key_group');
      if (roleVal === 'admin' || roleVal === 'security') {
        group.style.display = 'block';
        document.getElementById('admin_key').required = true;
      } else {
        group.style.display = 'none';
        document.getElementById('admin_key').required = false;
      }
    }
  </script>
<?php require_once __DIR__ . '/footer.php'; ?>
