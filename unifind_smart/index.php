<?php
require_once __DIR__ . '/config.php';

// Auto-redirect if already logged in
if (is_logged_in()) {
    if (get_current_role() === 'admin' || get_current_role() === 'security') {
        header('Location: ' . base_url('admin/dashboard.php'));
    } else {
        header('Location: ' . base_url('student/dashboard.php'));
    }
    exit();
}

$error = '';
$success = isset($_GET['registered']) ? 'Account registration successful! Welcome to Marwadi University Lost & Found System.' : '';

// Process Login Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_valid_csrf_token($token)) {
        $error = 'Security validation failed. Please refresh and try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please enter both your email address and password.';
        } else {
            $stmt = $conn->prepare('SELECT id, username, email, password, role, reward_points, badge FROM users WHERE email = ?');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($user && password_verify($password, $user['password'])) {
                // Initialize session state
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['reward_points'] = $user['reward_points'];
                $_SESSION['badge'] = $user['badge'];

                log_activity($user['id'], 'User Authentication', 'Logged into portal as ' . $user['role']);

                if ($user['role'] === 'admin' || $user['role'] === 'security') {
                    header('Location: ' . base_url('admin/dashboard.php'));
                } else {
                    header('Location: ' . base_url('student/dashboard.php'));
                }
                exit();
            } else {
                $error = 'Invalid email address or password. Please verify your credentials.';
            }
        }
    }
}

$pageTitle = 'Campus Portal Authentication';
require_once __DIR__ . '/header.php';
?>
  <div style="min-height: 90vh; display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 20px;">

    <div style="max-width: 440px; width: 100%; background: var(--bg-card); border-radius: var(--radius-lg); border: 1px solid var(--border-color); padding: 36px; box-shadow: var(--shadow-lg);">
      
      <!-- Campus Brand Header -->
      <div style="text-align: center; margin-bottom: 28px;">
        <div style="display: inline-flex; align-items: center; justify-content: center; width: 68px; height: 68px; border-radius: 50%; background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); color: #fff; font-size: 2rem; margin-bottom: 14px; box-shadow: 0 10px 20px rgba(79,70,229,0.35);">
          <i class="fa-solid fa-graduation-cap"></i>
        </div>
        <h1 style="font-size: 1.75rem; margin-bottom: 4px;">UniFind <span class="gradient-text">Smart</span></h1>
        <p style="color: var(--text-muted); font-size: 0.9rem; font-weight: 600;">
          Marwadi University Lost & Found System
        </p>
      </div>

      <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation"></i> <?= e($error) ?></div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= e($success) ?></div>
      <?php endif; ?>

      <!-- Login Form -->
      <form method="POST" action="">
        <?= csrf_input() ?>
        
        <div class="form-group">
          <label for="email"><i class="fa-solid fa-envelope" style="margin-right: 6px; color: var(--primary);"></i> University Email</label>
          <input type="email" id="email" name="email" class="form-control" placeholder="user@unifind.edu" required autofocus>
        </div>

        <div class="form-group">
          <label for="password"><i class="fa-solid fa-lock" style="margin-right: 6px; color: var(--primary);"></i> Password</label>
          <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 13px; font-size: 1rem; margin-top: 8px;">
          Sign In to Portal <i class="fa-solid fa-arrow-right"></i>
        </button>
      </form>

      <!-- Account Creation Link -->
      <div style="text-align: center; margin-top: 24px; padding-top: 20px; border-top: 1px dashed var(--border-color); font-size: 0.9rem; color: var(--text-muted);">
        Don't have a student or staff account? <br>
        <a href="<?= base_url('register.php') ?>" style="font-weight: 700; color: var(--primary); display: inline-block; margin-top: 6px;">
          Register New Campus Account &rarr;
        </a>
      </div>

    </div>
  </div>

<?php require_once __DIR__ . '/footer.php'; ?>
