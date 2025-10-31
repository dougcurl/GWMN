<?php
//api/get_well_data.php
header('Content-Type: application/json');

require_once __DIR__ . '/../credentials.php';
require_once __DIR__ . '/../wells_config.php';
require_once __DIR__ . '/../common/api.php';

$current_well_id = isset($_GET['id']) ? $_GET['id'] : null;
$data_type = isset($_GET['type']) ? $_GET['type'] : 'all';
$debug = isset($_GET['debug']) ? true : false;

if (!$current_well_id) {
    echo json_encode(['error' => 'Well ID required']);
    exit;
}

$well_config = getWellConfig($current_well_id);
if (!$well_config) {
    echo json_encode(['error' => 'Well not found']);
    exit;
}

// Extract config
$well_location_id = $well_config['location_id'];
$depth_method = $well_config['depth_method'];
$water_well_elevation = $well_config['water_well_elevation'] ?? 0;
$transducer_height = $well_config['transducer_height'] ?? 0;
$water_level_baseline = $well_config['water_level_baseline'] ?? 0;
$reading_interval = $well_config['reading_interval'] ?? 15;
$paramNames = $well_config['param_names'];
$paramUnits = $well_config['param_units'];

// Get OAuth token (your existing function already has caching)
$access_token = getOAuthToken($client_id, $client_secret, $token_url, false);

if (!$access_token) {
    echo json_encode(['error' => 'Authentication failed']);
    exit;
}

// Get friendly names
$friendlynames = getFriendlyNames($access_token, $base_url, false);
$parameters = $friendlynames['parameters'];
$units = $friendlynames['units'];

// Helper function for rounding timestamps
function roundToInterval($timestamp, $interval_minutes = 15) {
    $interval_seconds = $interval_minutes * 60;
    return floor($timestamp / $interval_seconds) * $interval_seconds;
}

// Prepare time ranges
$current_time = roundToInterval(time(), $reading_interval);

// Get date parameters from URL
$week_start_param = isset($_GET['week_start']) ? $_GET['week_start'] : null;
$hour_start_param = isset($_GET['hour_start']) ? $_GET['hour_start'] : null;

// Set time ranges based on parameters or defaults
// FIXED: When a custom start is provided, calculate end as start + duration
if ($week_start_param) {
    $week_start = strtotime($week_start_param);
    $week_end = $week_start + (7 * 24 * 60 * 60); // 7 days from start
    // Don't let it go into the future
    if ($week_end > $current_time) {
        $week_end = $current_time;
    }
} else {
    // Default: last 7 days
    $week_start = $current_time - (7 * 24 * 60 * 60);
    $week_end = $current_time;
}

if ($hour_start_param) {
    $hour_start = strtotime($hour_start_param);
    $hour_end = $hour_start + (24 * 60 * 60); // 24 hours from start
    // Don't let it go into the future
    if ($hour_end > $current_time) {
        $hour_end = $current_time;
    }
} else {
    // Default: last 24 hours
    $hour_start = $current_time - (24 * 60 * 60);
    $hour_end = $current_time;
}

$response = [];

// Load different data based on type
switch ($data_type) {
    case 'latest':
        // Get last hour of data for latest readings
        $latest_data = getLocationData($well_location_id, $access_token, $base_url, 
                                       $current_time - 3600, $current_time, 5, $debug);
        
        if ($debug) {
            $response['debug'] = ['raw_data' => $latest_data];
        }
        
        // Process using your existing function
        $processed = processWellData(
            $latest_data,
            null,
            $parameters,
            $units,
            $paramNames,
            $paramUnits,
            $depth_method,
            $water_level_baseline,
            $water_well_elevation,
            $transducer_height
        );
        
        // Extract just the latest values
        $response['latest'] = [];
        foreach ($processed as $key => $param) {
            $readings = isset($param['week_readings']) ? $param['week_readings'] : [];
            if (!empty($readings)) {
                // Get the most recent reading
                usort($readings, function($a, $b) {
                    return $b['timestamp'] - $a['timestamp'];
                });
                $response['latest'][$key] = [
                    'value' => $readings[0]['value'],
                    'timestamp' => $readings[0]['timestamp'],
                    'unit' => $param['unit'],
                    'name' => $param['name']
                ];
            }
        }
        break;
        
    case 'weekly':
        // Use week_end instead of current_time
        $week_data = getLocationData($well_location_id, $access_token, $base_url, 
                                     $week_start, $week_end, 10, $debug);
        
        if ($debug) {
            $response['debug'] = ['raw_data' => $week_data, 'week_start' => $week_start, 'week_end' => $week_end];
        }
        
        // Process using your existing function
        $processed = processWellData(
            $week_data,
            null,
            $parameters,
            $units,
            $paramNames,
            $paramUnits,
            $depth_method,
            $water_level_baseline,
            $water_well_elevation,
            $transducer_height
        );
        
        $response['weekly'] = $processed;
        break;
        
    case 'hourly':
        // Use hour_end instead of current_time
        $hour_data = getLocationData($well_location_id, $access_token, $base_url, 
                                     $hour_start, $hour_end, 5, $debug);
        
        if ($debug) {
            $response['debug'] = ['raw_data' => $hour_data, 'hour_start' => $hour_start, 'hour_end' => $hour_end];
        }
        
        // Process using your existing function
        $processed = processWellData(
            null,
            $hour_data,
            $parameters,
            $units,
            $paramNames,
            $paramUnits,
            $depth_method,
            $water_level_baseline,
            $water_well_elevation,
            $transducer_height
        );
        
        $response['hourly'] = $processed;
        break;
        
    case 'location':
        $location_details = getLocationDetails($well_location_id, $access_token, $base_url, false);
        $response['location'] = $location_details;
        break;
}

echo json_encode($response);