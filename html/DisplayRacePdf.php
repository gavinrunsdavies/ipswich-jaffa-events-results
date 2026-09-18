<?php
if (!defined('ABSPATH')) {
    die('restricted access');
}

$eventId = isset($_GET['eventId']) ? intval($_GET['eventId']) : 0;
$meetingId = isset($_GET['meetingId']) ? intval($_GET['meetingId']) : 0;
$raceId = isset($_GET['raceId']) ? intval($_GET['raceId']) : 0;

if ($raceId <= 0) {
    status_header(404);
    exit;
}

require_once plugin_dir_path(__FILE__) . '../api/v1/class-ipswich-events-results-data-access.php';

$dataAccess = new \IpswichEventResultsAPI\V1\Ipswich_Events_Results_Data_Access();
$response = $dataAccess->get_race_results($raceId);

if (empty($response) || !isset($response[0]->results)) {
    status_header(404);
    exit;
}

// validate relationship if provided
if ($meetingId && isset($response[0]->meeting_id) && (int)$response[0]->meeting_id !== $meetingId) {
    status_header(404);
    exit;
}
if ($eventId && isset($response[0]->event_id) && (int)$response[0]->event_id !== $eventId) {
    status_header(404);
    exit;
}

if (strtoupper((string)$response[0]->type) !== 'PDF') {
    status_header(400);
    exit;
}

$pdf = $response[0]->results;
if (is_resource($pdf)) {
    $pdf = stream_get_contents($pdf);
}

$filename = preg_replace('/[^a-zA-Z0-9_.-]/', '-', (string)$response[0]->name) . '-' . preg_replace('/[^a-zA-Z0-9_.-]/', '-', (string)$response[0]->date) . '.pdf';

// Discard any buffered output (whitespace, stray warnings, etc.) that would
// otherwise get prepended to the binary PDF and corrupt it.
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . strlen((string)$pdf));

echo $pdf;
exit;
