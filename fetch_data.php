<?php
/**
 * Universal AJAX Data Fetching Endpoint
 * Handles data fetching for any well dynamically based on URL parameter
 * Usage: fetch_data.php?well=hp or fetch_data.php?well=hickman1
 */

header('Content-Type: application/json');

// Load API credentials
require_once __DIR__ . '/credentials.php';

// Load the shared wells configuration
require_once __DIR__ . '/wells_config.php';

// Load common API functions
require_once __DIR__ . '/common/api.php';

// Get well ID from URL parameter
$current_well_id = isset($_GET['well']) ? $_GET['well'] : null;

// Initialize response structure
$response_data = [
    'success' => false,
    'message' => '',
    'timestamp' => time()
];

// Validate well ID
if ($current_well_id === null) {
    $response_data['message'] = 'No well ID provided';
    echo json_encode($response_data);
    exit;
}

// Load well-specific configuration
$well_config = getWellConfig($current_well_id);

if ($well_config === null) {
    $response_data['message'] = 'Configuration not found for well: ' . htmlspecialchars($current_well_id);
    echo json_encode($response_data);
    exit;
}

// Extract well-specific variables
$well_location_id = $well_config['location_id'];
$depth_method = $well_config['depth_method'];
$water_well_elevation = isset($well_config['water_well_elevation']) ? $well_config['water_well_elevation'] : 0;
$casing_height = isset($well_config['casing_height']) ? $well_config['casing_height'] : 0;
$transducer_height = isset($well_config['transducer_height']) ? $well_config['transducer_height'] : 0;
$water_level_baseline = isset($well_config['water_level_baseline']) ? $well_config['water_level_baseline'] : 0;
$water_level_warning = $well_config['water_level_warning'];
$paramNames = $well_config['param_names'];
$paramUnits = $well_config['param_units'];
$reading_interval = isset($well_config['reading_interval']) ? $well_config['reading_interval'] : 15;

// Get OAuth token
$access_token = getOAuthToken($client_id, $client_secret, $token_url, false);

if (!$access_token) {
    $response_data['message'] = 'Authentication failed';
    echo json_encode($response_data);
    exit;
}

// Get friendly names
$friendlynames = getFriendlyNames($access_token, $base_url, false);
$parameters = $friendlynames['parameters'];
$units = $friendlynames['units'];

// Helper function to round to interval
function roundToInterval($timestamp, $interval_minutes = 15) {
    $interval_seconds = $interval_minutes * 60;
    return floor($timestamp / $interval_seconds) * $interval_seconds;
}

// Get current time rounded to reading interval
$current_time = roundToInterval(time(), $reading_interval);

// Define default time ranges
$week_start = $current_time - (7 * 24 * 60 * 60);
$day_start = $current_time - (24 * 60 * 60);

// Allow custom date ranges via URL parameters and calculate proper end times
if (isset($_GET['week_start'])) {
    $custom_week_start = roundToInterval(strtotime($_GET['week_start']), $reading_interval);
    $week_end = $custom_week_start + (7 * 24 * 60 * 60);
    if ($week_end > $current_time) {
        $week_end = $current_time;
    }
} else {
    $custom_week_start = $week_start;
    $week_end = $current_time;
}

if (isset($_GET['day_start'])) {
    $custom_day_start = roundToInterval(strtotime($_GET['day_start']), $reading_interval);
    $day_end = $custom_day_start + (24 * 60 * 60);
    if ($day_end > $current_time) {
        $day_end = $current_time;
    }
} else {
    $custom_day_start = $day_start;
    $day_end = $current_time;
}

// Get data for week and day periods using calculated end times
$week_data = getLocationData($well_location_id, $access_token, $base_url, $custom_week_start, $week_end, 10, false);
$day_data = getLocationData($well_location_id, $access_token, $base_url, $custom_day_start, $day_end, 5, false);

if (!$week_data || isset($week_data['error'])) {
    $response_data['message'] = isset($week_data['error']) ? $week_data['error'] : 'Failed to retrieve well data';
    echo json_encode($response_data);
    exit;
}

// Process the data
$availableParams = processWellData($week_data, $day_data, $parameters, $units, $paramNames, $paramUnits, $depth_method, $water_level_baseline, $water_well_elevation, $transducer_height);

// Prepare successful response
$response_data['success'] = true;
$response_data['message'] = 'Data retrieved successfully';
$response_data['data'] = $availableParams;
$response_data['well_id'] = $current_well_id;
$response_data['well_name'] = $well_config['full_name'];

echo json_encode($response_data);
?>