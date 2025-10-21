<?php
// fetch_data.php - Returns updated water well data in JSON format
header('Content-Type: application/json');

// Include the configuration and API functions
require_once 'config.php';
require_once 'api.php';

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
    'timestamp' => time()
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
    
    usort($readings, function($a, $b) {
        return $b['timestamp'] - $a['timestamp']; // Sort by timestamp, newest first
    });
    
    $latestReading = $readings[0];
    $depthData['latest'] = $latestReading;
    
    // Add weekly readings
    if (isset($availableParams['depth']['week_readings'])) {
        $weekReadings = $availableParams['depth']['week_readings'];
        // Sort by timestamp (oldest first)
        usort($weekReadings, function($a, $b) {
            return $a['timestamp'] - $b['timestamp'];
        });
        
        // Down-sample data for the response to reduce payload size
        $sampledWeekReadings = downsampleReadings($weekReadings, 100);
        $depthData['week'] = $sampledWeekReadings;
    }
    
    // Add hourly/daily readings
    if (isset($availableParams['depth']['hour_readings'])) {
        $hourReadings = $availableParams['depth']['hour_readings'];
        // Sort by timestamp (oldest first)
        usort($hourReadings, function($a, $b) {
            return $a['timestamp'] - $b['timestamp'];
        });
        
        // Down-sample data for the response
        $sampledHourReadings = downsampleReadings($hourReadings, 100);
        $depthData['hour'] = $sampledHourReadings;
    }
    
    // Add statistics
    if (isset($availableParams['depth']['week_readings'])) {
        $values = array_column($availableParams['depth']['week_readings'], 'value');
        $depthData['stats'] = [
            'min' => min($values),
            'max' => max($values),
            'avg' => array_sum($values) / count($values),
            'count' => count($values),
            'above_warning' => count(array_filter($values, function($v) use ($water_level_warning) {
                return $v >= $water_level_warning;
            }))
        ];
    }
    
    $responseData['depth'] = $depthData;
}

// Process temperature data
if (isset($availableParams['temperature'])) {
    $tempData = [
        'name' => $availableParams['temperature']['name'],
        'unit' => $availableParams['temperature']['unit']
    ];
    
    // Get latest reading
    $readings = isset($availableParams['temperature']['hour_readings']) ? 
               $availableParams['temperature']['hour_readings'] : 
               $availableParams['temperature']['week_readings'];
    
    usort($readings, function($a, $b) {
        return $b['timestamp'] - $a['timestamp']; // Sort by timestamp, newest first
    });
    
    $latestReading = $readings[0];
    $tempData['latest'] = $latestReading;
    
    // Add weekly readings
    if (isset($availableParams['temperature']['week_readings'])) {
        $weekReadings = $availableParams['temperature']['week_readings'];
        // Sort by timestamp (oldest first)
        usort($weekReadings, function($a, $b) {
            return $a['timestamp'] - $b['timestamp'];
        });
        
        // Down-sample data for the response
        $sampledWeekReadings = downsampleReadings($weekReadings, 100);
        $tempData['week'] = $sampledWeekReadings;
    }
    
    // Add hourly/daily readings
    if (isset($availableParams['temperature']['hour_readings'])) {
        $hourReadings = $availableParams['temperature']['hour_readings'];
        // Sort by timestamp (oldest first)
        usort($hourReadings, function($a, $b) {
            return $a['timestamp'] - $b['timestamp'];
        });
        
        // Down-sample data for the response
        $sampledHourReadings = downsampleReadings($hourReadings, 100);
        $tempData['hour'] = $sampledHourReadings;
    }
    
    // Add statistics
    if (isset($availableParams['temperature']['week_readings'])) {
        $values = array_column($availableParams['temperature']['week_readings'], 'value');
        $tempData['stats'] = [
            'min' => min($values),
            'max' => max($values),
            'avg' => array_sum($values) / count($values),
            'count' => count($values)
        ];
    }
    
    $responseData['temperature'] = $tempData;
}

// Add some metadata
$responseData['meta'] = [
    'refresh_time' => date('Y-m-d H:i:s'),
    'week_start' => date('Y-m-d H:i:s', $custom_week_start),
    'day_start' => date('Y-m-d H:i:s', $custom_day_start),
    'warning_threshold' => $water_level_warning
];

// Return JSON response
echo json_encode($responseData);
?>