<?php
/**
 * AJAX Data Fetching Endpoint - fetch_data.php
 * Place this file in each well directory
 * Returns updated water well data in JSON format
 */

header('Content-Type: application/json');

// Include the configuration and shared API functions
require_once 'config.php';
require_once __DIR__ . '/../common/api.php';

// Get OAuth token
$access_token = getOAuthToken($client_id, $client_secret, $token_url, false);

// Initialize response structure
$response_data = [
    'success' => false,
    'message' => '',
    'timestamp' => time()
];

if (!$access_token) {
    $response_data['message'] = 'Authentication failed';
    echo json_encode($response_data);
    exit;
}

// Get friendly names
$friendlynames = getFriendlyNames($access_token, $base_url, false);
$parameters = $friendlynames['parameters'];
$units = $friendlynames['units'];

// Get current time
$current_time = time();

// Define time ranges
$week_start = $current_time - (7 * 24 * 60 * 60);
$day_start = $current_time - (24 * 60 * 60);

// Allow custom date ranges via URL parameters
$custom_week_start = isset($_GET['week_start']) ? strtotime($_GET['week_start']) : $week_start;
$custom_day_start = isset($_GET['day_start']) ? strtotime($_GET['day_start']) : $day_start;

// Get data for week and day periods only
$week_data = getLocationData($well_location_id, $access_token, $base_url, $custom_week_start, $current_time, 10, false);
$day_data = getLocationData($well_location_id, $access_token, $base_url, $custom_day_start, $current_time, 5, false);

if (!$week_data || isset($week_data['error'])) {
    $response_data['message'] = isset($week_data['error']) ? $week_data['error'] : 'Failed to retrieve well data';
    echo json_encode($response_data);
    exit;
}

// Process the data
$availableParams = processWellData($week_data, $day_data, $parameters, $units, $paramNames, $paramUnits, $water_level_baseline);

// Structure the data for JSON response
$responseData = [
    'success' => true,
    'timestamp' => time(),
    'well_id' => $current_well_id,
    'well_name' => $well_common_name
];

// Process water level data
if (isset($availableParams['depth'])) {
    $depthData = [
        'name' => $availableParams['depth']['name'],
        'unit' => $availableParams['depth']['unit']
    ];
    
    // Get latest reading
    $readings = isset($availableParams['depth']['hour_readings']) ?
                $availableParams['depth']['hour_readings'] : 
                $availableParams['depth']['week_readings'];
    
    if (!empty($readings)) {
        $latest = end($readings);
        $depthData['current'] = [
            'value' => $latest['value'],
            'timestamp' => $latest['timestamp'],
            'formatted_time' => date('Y-m-d H:i:s', $latest['timestamp'])
        ];
        
        // Determine status
        $status = 'normal';
        if ($latest['value'] >= $water_level_warning) {
            $status = 'warning';
        }
        $depthData['status'] = $status;
    }
    
    // Add all readings for charts
    $depthData['week_readings'] = isset($availableParams['depth']['week_readings']) ? 
                                   $availableParams['depth']['week_readings'] : [];
    $depthData['hour_readings'] = isset($availableParams['depth']['hour_readings']) ? 
                                   $availableParams['depth']['hour_readings'] : [];
    
    $responseData['depth'] = $depthData;
}

// Process temperature data
if (isset($availableParams['temperature'])) {
    $tempData = [
        'name' => $availableParams['temperature']['name'],
        'unit' => $availableParams['temperature']['unit']
    ];
    
    // Get latest reading
    $temp_readings = isset($availableParams['temperature']['hour_readings']) ?
                     $availableParams['temperature']['hour_readings'] : 
                     $availableParams['temperature']['week_readings'];
    
    if (!empty($temp_readings)) {
        $temp_latest = end($temp_readings);
        $tempData['current'] = [
            'value' => $temp_latest['value'],
            'timestamp' => $temp_latest['timestamp'],
            'formatted_time' => date('Y-m-d H:i:s', $temp_latest['timestamp'])
        ];
    }
    
    // Add all readings for charts
    $tempData['week_readings'] = isset($availableParams['temperature']['week_readings']) ? 
                                  $availableParams['temperature']['week_readings'] : [];
    $tempData['hour_readings'] = isset($availableParams['temperature']['hour_readings']) ? 
                                  $availableParams['temperature']['hour_readings'] : [];
    
    $responseData['temperature'] = $tempData;
}

// Output JSON
echo json_encode($responseData, JSON_PRETTY_PRINT);
?>