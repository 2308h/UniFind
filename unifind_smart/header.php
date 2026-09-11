<?php
require_once __DIR__ . '/config.php';
$pageTitle = $pageTitle ?? 'Marwadi University Lost & Found System';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?> | UniFind Smart</title>
  <link rel="stylesheet" href="<?= base_url('style.css') ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script>
    // Apply saved theme early before render to prevent flash
    (function() {
      const savedTheme = localStorage.getItem('unifind_theme') || 'light';
      if (savedTheme === 'dark') {
        document.documentElement.classList.add('dark-theme');
        document.documentElement.setAttribute('data-theme', 'dark');
      }
    })();
  </script>
</head>
<body class="<?= (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark') ? 'dark-theme' : '' ?>">
