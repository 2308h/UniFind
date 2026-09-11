<?php
require_once __DIR__ . '/../config.php';
$id = (int)($_GET['id'] ?? 0);
header('Location: ' . base_url('student/submit_claim.php?id=' . $id));
exit();
