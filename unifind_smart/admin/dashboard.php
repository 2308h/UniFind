<?php
require_once __DIR__ . '/../config.php';
require_role(['admin', 'security']);

$userId = get_current_user_id();

// Fetch Executive Stat Counts
$totalReports = $conn->query("SELECT COUNT(*) AS total FROM reports")->fetch_assoc()['total'] ?? 0;
$pendingVerifications = $conn->query("SELECT COUNT(*) AS total FROM reports WHERE verification_status = 'pending'")->fetch_assoc()['total'] ?? 0;
$pendingClaims = $conn->query("SELECT COUNT(*) AS total FROM item_claims WHERE status = 'pending'")->fetch_assoc()['total'] ?? 0;
$resolvedItems = $conn->query("SELECT COUNT(*) AS total FROM reports WHERE status = 'resolved'")->fetch_assoc()['total'] ?? 0;

// Fetch Recent Audit Logs
$logsResult = $conn->query("SELECT l.*, u.username FROM activity_logs l LEFT JOIN users u ON l.user_id = u.id ORDER BY l.created_at DESC LIMIT 6");
$recentLogs = $logsResult ? $logsResult->fetch_all(MYSQLI_ASSOC) : [];

// Fetch Items Pending Verification
$pendingVerStmt = $conn->query("SELECT r.*, u.username FROM reports r JOIN users u ON r.user_id = u.id WHERE r.verification_status = 'pending' ORDER BY r.created_at DESC LIMIT 5");
$pendingVerItems = $pendingVerStmt ? $pendingVerStmt->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard | UniFind Smart</title>
  <link rel="stylesheet" href="<?= base_url('style.css') ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        <a href="<?= base_url('admin/dashboard.php') ?>" class="admin-nav-item active">
          <i class="fa-solid fa-chart-pie"></i> Executive Overview
        </a>
        <a href="<?= base_url('admin/verification_queue.php') ?>" class="admin-nav-item" style="position: relative;">
          <i class="fa-solid fa-clipboard-check"></i> Verification Queue
          <?php if ($pendingVerifications > 0): ?>
            <span class="badge badge-pending" style="margin-left: auto; padding: 2px 8px; font-size: 0.7rem;">
              <?= $pendingVerifications ?>
            </span>
          <?php endif; ?>
        </a>
        <a href="<?= base_url('admin/claims_center.php') ?>" class="admin-nav-item" style="position: relative;">
          <i class="fa-solid fa-scale-balanced"></i> Claims Resolution
          <?php if ($pendingClaims > 0): ?>
            <span class="badge badge-claimed" style="margin-left: auto; padding: 2px 8px; font-size: 0.7rem;">
              <?= $pendingClaims ?>
            </span>
          <?php endif; ?>
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
          <div style="font-weight: 700; font-size: 0.88rem; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
            <?= e($_SESSION['username']) ?>
          </div>
          <div style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase;">
            <?= e($_SESSION['role']) ?>
          </div>
        </div>
        <a href="<?= base_url('logout.php') ?>" style="color: #ef4444;" title="Sign Out">
          <i class="fa-solid fa-right-from-bracket"></i>
        </a>
      </div>
    </aside>

    <!-- Main Content Area -->
    <main class="admin-main">
      
      <div class="admin-header">
        <div>
          <h1 style="font-size: 1.8rem; margin-bottom: 4px;">Executive Dashboard</h1>
          <p style="color: var(--text-muted); font-size: 0.92rem;">Marwadi University Lost & Found System Administration</p>
        </div>

        <div style="display: flex; align-items: center; gap: 14px;">
          <a href="<?= base_url('student/dashboard.php') ?>" class="btn btn-secondary btn-sm" target="_blank">
            <i class="fa-solid fa-arrow-up-right-from-square"></i> Student Portal
          </a>
        </div>
      </div>

      <!-- Stat Cards Grid -->
      <div class="stats-grid">
        
        <div class="stat-card">
          <div>
            <div class="stat-value"><?= $totalReports ?></div>
            <div class="stat-label">Total Reports</div>
          </div>
          <div class="stat-icon" style="background: rgba(79, 70, 229, 0.12); color: var(--primary);">
            <i class="fa-solid fa-boxes-stacked"></i>
          </div>
        </div>

        <div class="stat-card">
          <div>
            <div class="stat-value" style="color: <?= $pendingVerifications > 0 ? '#ef4444' : 'inherit' ?>;"><?= $pendingVerifications ?></div>
            <div class="stat-label">Verification Queue</div>
          </div>
          <div class="stat-icon" style="background: rgba(239, 68, 68, 0.12); color: var(--danger);">
            <i class="fa-solid fa-clipboard-check"></i>
          </div>
        </div>

        <div class="stat-card">
          <div>
            <div class="stat-value"><?= $pendingClaims ?></div>
            <div class="stat-label">Pending Claims</div>
          </div>
          <div class="stat-icon" style="background: rgba(245, 158, 11, 0.12); color: var(--warning);">
            <i class="fa-solid fa-hand-holding-heart"></i>
          </div>
        </div>

        <div class="stat-card">
          <div>
            <div class="stat-value"><?= $resolvedItems ?></div>
            <div class="stat-label">Resolved Items</div>
          </div>
          <div class="stat-icon" style="background: rgba(16, 185, 129, 0.12); color: var(--success);">
            <i class="fa-solid fa-circle-check"></i>
          </div>
        </div>

      </div>

      <!-- Main Section Split -->
      <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
        
        <!-- Left: Quick Verification Alert & Recent Pending Queue -->
        <div>
          <div class="data-table-card">
            <div class="data-table-header">
              <h3><i class="fa-solid fa-hourglass-half" style="color: var(--warning); margin-right: 8px;"></i> Verification Queue (Requires Review)</h3>
              <a href="<?= base_url('admin/verification_queue.php') ?>" class="btn btn-secondary btn-sm">View All</a>
            </div>
            <?php if (empty($pendingVerItems)): ?>
              <div style="padding: 30px; text-align: center; color: var(--text-muted);">
                <i class="fa-solid fa-circle-check" style="color: #10b981; margin-right: 6px;"></i> All clear! No pending items waiting for verification.
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table">
                  <thead>
                    <tr>
                      <th>Ref #</th>
                      <th>Title</th>
                      <th>Category</th>
                      <th>Found By</th>
                      <th>Date</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($pendingVerItems as $pItem): ?>
                      <tr>
                        <td><strong><?= e($pItem['reference_number']) ?></strong></td>
                        <td><?= e($pItem['title']) ?></td>
                        <td><span class="badge badge-claimed"><?= e($pItem['category']) ?></span></td>
                        <td><?= e($pItem['username']) ?></td>
                        <td><?= date('M d', strtotime($pItem['item_date'])) ?></td>
                        <td>
                          <a href="<?= base_url('admin/verification_queue.php?id=' . $pItem['id']) ?>" class="btn btn-primary btn-sm">Review</a>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>

          <!-- Quick Chart Summary -->
          <div class="glass-card" style="border-radius: var(--radius-lg); padding: 24px;">
            <h3 style="font-size: 1.1rem; margin-bottom: 16px;">
              <i class="fa-solid fa-chart-area" style="color: var(--primary); margin-right: 8px;"></i> 30-Day Activity Overview
            </h3>
            <canvas id="quickChart" height="130"></canvas>
          </div>
        </div>

        <!-- Right Sidebar: System Audit Activity Stream -->
        <div>
          <div class="glass-card" style="border-radius: var(--radius-lg); padding: 24px;">
            <h3 style="font-size: 1.1rem; margin-bottom: 16px;">
              <i class="fa-solid fa-clock-rotate-left" style="color: var(--primary); margin-right: 8px;"></i> Live Audit Stream
            </h3>
            
            <div style="display: flex; flex-direction: column; gap: 16px;">
              <?php foreach ($recentLogs as $log): ?>
                <div style="padding-bottom: 12px; border-bottom: 1px dashed var(--border-color);">
                  <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px;">
                    <strong style="font-size: 0.88rem; color: var(--text-main);"><?= e($log['action']) ?></strong>
                    <span style="font-size: 0.72rem; color: var(--text-muted);"><?= date('H:i', strtotime($log['created_at'])) ?></span>
                  </div>
                  <p style="font-size: 0.82rem; color: var(--text-muted); margin-bottom: 4px;"><?= e($log['details']) ?></p>
                  <div style="font-size: 0.75rem; color: var(--primary);">By: <?= e($log['username'] ?? 'System') ?> (<?= e($log['ip_address']) ?>)</div>
                </div>
              <?php endforeach; ?>
            </div>

            <div style="margin-top: 16px; text-align: center;">
              <a href="<?= base_url('admin/audit_logs.php') ?>" style="font-size: 0.85rem; font-weight: 700;">View Full Audit Trail &rarr;</a>
            </div>

          </div>
        </div>

      </div>

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

    // Render Quick Overview Chart
    const ctx = document.getElementById('quickChart').getContext('2d');
    new Chart(ctx, {
      type: 'bar',
      data: {
        labels: ['Total Reports', 'Verification Queue', 'Pending Claims', 'Resolved Items'],
        datasets: [{
          label: 'Count',
          data: [<?= $totalReports ?>, <?= $pendingVerifications ?>, <?= $pendingClaims ?>, <?= $resolvedItems ?>],
          backgroundColor: ['#4f46e5', '#ef4444', '#f59e0b', '#10b981'],
          borderRadius: 8
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
      }
    });
  </script>
</body>
</html>
