<?php
require_once __DIR__ . '/../config.php';
require_role(['admin', 'security']);

$userId = get_current_user_id();

log_activity($userId, 'Export CSV Download', 'Exported resolved item records CSV file');

// Set CSV headers
$filename = 'unifind_resolved_reports_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Header Row
fputcsv($output, [
    'Reference Number',
    'Title',
    'Category',
    'Report Type',
    'Campus Location',
    'Item Date',
    'Reported By',
    'Custody Location',
    'Report Status',
    'Verification Status',
    'Created At'
]);

// Fetch all resolved or all reports
$query = "SELECT r.*, u.username FROM reports r JOIN users u ON r.user_id = u.id ORDER BY r.id DESC";
$result = $conn->query($query);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['reference_number'],
            $row['title'],
            $row['category'],
            ucfirst($row['report_type']),
            $row['location'],
            $row['item_date'],
            $row['username'],
            $row['custody_location'] ?? 'N/A',
            ucfirst($row['status']),
            ucfirst($row['verification_status']),
            $row['created_at']
        ]);
    }
}

fclose($output);
exit();
