<?php
require_once __DIR__ . '/../config.php';
require_role(['admin', 'security']);

// Query 30-Day Trend Data
$trendQuery = $conn->query("
    SELECT 
        DATE(created_at) AS log_date,
        SUM(CASE WHEN report_type = 'lost' THEN 1 ELSE 0 END) AS lost_count,
        SUM(CASE WHEN report_type = 'found' THEN 1 ELSE 0 END) AS found_count,
        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) AS resolved_count
    FROM reports
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY log_date ASC
");
$trendRows = $trendQuery ? $trendQuery->fetch_all(MYSQLI_ASSOC) : [];

$dates = [];
$lostSeries = [];
$foundSeries = [];
$resolvedSeries = [];

foreach ($trendRows as $row) {
    $dates[] = date('M d', strtotime($row['log_date']));
    $lostSeries[] = (int)$row['lost_count'];
    $foundSeries[] = (int)$row['found_count'];
    $resolvedSeries[] = (int)$row['resolved_count'];
}

// Query Category Distribution
$catQuery = $conn->query("
    SELECT category, COUNT(*) AS count 
    FROM reports 
    GROUP BY category 
    ORDER BY count DESC
");
$catRows = $catQuery ? $catQuery->fetch_all(MYSQLI_ASSOC) : [];

$catLabels = [];
$catCounts = [];
foreach ($catRows as $cat) {
    $catLabels[] = $cat['category'];
    $catCounts[] = (int)$cat['count'];
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Analytics & Trends | UniFind Admin</title>
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
        <a href="<?= base_url('admin/dashboard.php') ?>" class="admin-nav-item">
          <i class="fa-solid fa-chart-pie"></i> Executive Overview
        </a>
        <a href="<?= base_url('admin/verification_queue.php') ?>" class="admin-nav-item">
          <i class="fa-solid fa-clipboard-check"></i> Verification Queue
        </a>
        <a href="<?= base_url('admin/claims_center.php') ?>" class="admin-nav-item">
          <i class="fa-solid fa-scale-balanced"></i> Claims Resolution
        </a>
        <a href="<?= base_url('admin/user_management.php') ?>" class="admin-nav-item">
          <i class="fa-solid fa-users-gear"></i> User Management
        </a>
        <a href="<?= base_url('admin/audit_logs.php') ?>" class="admin-nav-item">
          <i class="fa-solid fa-clock-rotate-left"></i> Security Audit Logs
        </a>
        <a href="<?= base_url('admin/analytics.php') ?>" class="admin-nav-item active">
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
          <div style="font-weight: 700; font-size: 0.88rem; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= e($_SESSION['username']) ?></div>
          <div style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase;"><?= e($_SESSION['role']) ?></div>
        </div>
        <a href="<?= base_url('logout.php') ?>" style="color: #ef4444;" title="Sign Out"><i class="fa-solid fa-right-from-bracket"></i></a>
      </div>
    </aside>

    <main class="admin-main">
      
      <div class="admin-header">
        <div>
          <h1 style="font-size: 1.8rem; margin-bottom: 4px;">Analytics Overview</h1>
          <p style="color: var(--text-muted); font-size: 0.92rem;">Visual trend metrics, resolution rates, and lost item category distributions.</p>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
        
        <!-- 30-Day Trend Line Chart -->
        <div class="glass-card" style="border-radius: var(--radius-lg); padding: 26px;">
          <h3 style="font-size: 1.2rem; margin-bottom: 16px;">
            <i class="fa-solid fa-chart-line" style="color: var(--primary); margin-right: 8px;"></i> 30-Day Report Trends (Lost vs. Found vs. Resolved)
          </h3>
          <canvas id="trendChart" height="150"></canvas>
        </div>

        <!-- Category Doughnut Chart -->
        <div class="glass-card" style="border-radius: var(--radius-lg); padding: 26px;">
          <h3 style="font-size: 1.2rem; margin-bottom: 16px;">
            <i class="fa-solid fa-chart-pie" style="color: var(--accent); margin-right: 8px;"></i> Top Item Categories
          </h3>
          <canvas id="categoryChart" height="220"></canvas>
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

    // 1. Line Chart Data
    const trendCtx = document.getElementById('trendChart').getContext('2d');
    new Chart(trendCtx, {
      type: 'line',
      data: {
        labels: <?= json_encode($dates) ?>,
        datasets: [
          {
            label: 'Lost Reports',
            data: <?= json_encode($lostSeries) ?>,
            borderColor: '#ef4444',
            backgroundColor: 'rgba(239, 68, 68, 0.1)',
            tension: 0.3,
            fill: true
          },
          {
            label: 'Found Reports',
            data: <?= json_encode($foundSeries) ?>,
            borderColor: '#10b981',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            tension: 0.3,
            fill: true
          },
          {
            label: 'Resolved Handovers',
            data: <?= json_encode($resolvedSeries) ?>,
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            tension: 0.3,
            fill: true
          }
        ]
      },
      options: {
        responsive: true,
        plugins: { legend: { position: 'top' } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
      }
    });

    // 2. Category Doughnut Chart Data
    const catCtx = document.getElementById('categoryChart').getContext('2d');
    new Chart(catCtx, {
      type: 'doughnut',
      data: {
        labels: <?= json_encode($catLabels) ?>,
        datasets: [{
          data: <?= json_encode($catCounts) ?>,
          backgroundColor: ['#4f46e5', '#06b6d4', '#f59e0b', '#10b981', '#ec4899', '#8b5cf6']
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } }
      }
    });
  </script>
</body>
</html>
