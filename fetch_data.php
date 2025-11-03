<?php
/**
 * Universal AJAX Data Fetching Endpoint
 * Handles data fetching for any well dynamically based on URL parameter
 * Usage: 
 *   - fetch_data.php?well=hp (weekly/daily data)
 *   - fetch_data.php?well=hp&type=monthly&month_start=YYYY-MM-DD (monthly data)
 */

header('Content-Type: application/json');

// Load API credentials
require_once __DIR__ . '/credentials.php';

// Load the shared wells configuration
require_once __DIR__ . '/wells_config.php';

// Load common API functions
require_once __DIR__ . '/common/api.php';

/**
 * Round timestamp to the nearest reading interval
 * This ensures cache consistency and proper data retrieval
 */
function roundToInterval($timestamp, $interval_minutes = 15) {
    $interval_seconds = $interval_minutes * 60;
    return floor($timestamp / $interval_seconds) * $interval_seconds;
}

// Get well ID from URL parameter
$current_well_id = isset($_GET['well']) ? $_GET['well'] : null;

// Check if this is a monthly data request
$is_monthly_request = isset($_GET['type']) && $_GET['type'] === 'monthly';

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

// Get current time rounded to reading interval
$current_time = roundToInterval(time(), $reading_interval);

if ($is_monthly_request) {
    // Handle monthly data request
    $month_start = $current_time - (30 * 24 * 60 * 60);
    
    if (isset($_GET['month_start'])) {
        $custom_month_start = roundToInterval(strtotime($_GET['month_start']), $reading_interval);
        $month_end = $custom_month_start + (30 * 24 * 60 * 60);
        if ($month_end > $current_time) {
            $month_end = $current_time;
        }
    } else {
        $custom_month_start = $month_start;
        $month_end = $current_time;
    }
    
    // Get data for month period
    $month_data = getLocationData($well_location_id, $access_token, $base_url, $custom_month_start, $month_end, 20, false);
    
    if (!$month_data || isset($month_data['error'])) {
        $response_data['message'] = isset($month_data['error']) ? $month_data['error'] : 'Failed to retrieve well data.';
        echo json_encode($response_data);
        exit;
    }
    
    // Process the monthly data
    $availableParams = [];
    
    if (isset($month_data['parameters']) && is_array($month_data['parameters'])) {
        foreach ($month_data['parameters'] as $param) {
            if (isset($param['readings']) && !empty($param['readings'])) {
                $paramId = $param['parameterId'];
                $paramKey = '';
                
                // Try to identify parameter type
                foreach ($parameters as $key => $name) {
                    if ($key == $paramId) {
                        $paramName = $name;
                        if (stripos($paramName, 'depth') !== false || stripos($paramName, 'level') !== false) {
                            $paramKey = 'depth';
                        } elseif (stripos($paramName, 'temp') !== false) {
                            $paramKey = 'temperature';
                        } else {
                            $paramKey = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $paramName));
                        }
                        break;
                    }
                }
                
                // Skip if not water level or temperature
                if (!in_array($paramKey, ['depth', 'temperature'])) {
                    continue;
                }
                
                if (empty($paramKey)) {
                    $paramKey = 'param_' . $paramId;
                }
                
                // Get unit
                $unitId = $param['unitId'];
                $unitName = isset($units[$unitId]) ? $units[$unitId] : '';
                
                if (isset($paramUnits[$paramKey])) {
                    $unitName = $paramUnits[$paramKey];
                }
                
                // Get display name
                $displayName = isset($paramNames[$paramKey]) ? $paramNames[$paramKey] : 
                              (isset($parameters[$paramId]) ? $parameters[$paramId] : 'Parameter ' . $paramId);
                
                $readings = $param['readings'];
                
                // Transform data based on parameter type
                if ($paramKey === 'depth') {
                    $readings = transformWaterLevelData($readings, $depth_method, $water_level_baseline, $water_well_elevation, $transducer_height);
                } else {
                    $readings = transformWaterTempData($readings);
                }
                
                $availableParams[$paramKey] = [
                    'id' => $paramId,
                    'name' => $displayName,
                    'unit' => $unitName,
                    'readings' => $readings
                ];
            }
        }
    }
    
    // Build response for monthly data
    $response_data['success'] = true;
    $response_data['month_start'] = $custom_month_start;
    $response_data['month_end'] = $month_end;
    
    // Add depth data if available
    if (isset($availableParams['depth'])) {
        $depthData = [];
        $depthData['name'] = $availableParams['depth']['name'];
        $depthData['unit'] = $availableParams['depth']['unit'];
        $depthData['readings'] = $availableParams['depth']['readings'];
        $response_data['depth'] = $depthData;
    }
    
    // Add temperature data if available
    if (isset($availableParams['temperature'])) {
        $tempData = [];
        $tempData['name'] = $availableParams['temperature']['name'];
        $tempData['unit'] = $availableParams['temperature']['unit'];
        $tempData['readings'] = $availableParams['temperature']['readings'];
        $response_data['temperature'] = $tempData;
    }
    
} else {
    // Handle weekly/daily data request (existing functionality)
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
        $response_data['message'] = isset($week_data['error']) ? $week_data['error'] : 'Failed to retrieve week data.';
        echo json_encode($response_data);
        exit;
    }
    
    if (!$day_data || isset($day_data['error'])) {
        $response_data['message'] = isset($day_data['error']) ? $day_data['error'] : 'Failed to retrieve day data.';
        echo json_encode($response_data);
        exit;
    }
    
    // Process the data
    $availableParams = processWellData($week_data, $day_data, $parameters, $units, $paramNames, $paramUnits, $depth_method, $water_level_baseline, $water_well_elevation, $transducer_height);
    
    // Build response
    $response_data['success'] = true;
    $response_data['week_start'] = $custom_week_start;
    $response_data['week_end'] = $week_end;
    $response_data['day_start'] = $custom_day_start;
    $response_data['day_end'] = $day_end;
    
    // Add depth data if available
    if (isset($availableParams['depth'])) {
        $depthData = [];
        $depthData['name'] = $availableParams['depth']['name'];
        $depthData['unit'] = $availableParams['depth']['unit'];
        $depthData['week_readings'] = isset($availableParams['depth']['week_readings']) ? 
                                       $availableParams['depth']['week_readings'] : [];
        $depthData['hour_readings'] = isset($availableParams['depth']['hour_readings']) ? 
                                       $availableParams['depth']['hour_readings'] : [];
        
        $response_data['depth'] = $depthData;
    }
    
    // Add temperature data if available
    if (isset($availableParams['temperature'])) {
        $tempData = [];
        $tempData['name'] = $availableParams['temperature']['name'];
        $tempData['unit'] = $availableParams['temperature']['unit'];
        $tempData['week_readings'] = isset($availableParams['temperature']['week_readings']) ? 
                                      $availableParams['temperature']['week_readings'] : [];
        $tempData['hour_readings'] = isset($availableParams['temperature']['hour_readings']) ? 
                                      $availableParams['temperature']['hour_readings'] : [];
        
        $response_data['temperature'] = $tempData;
    }
}

// Output JSON
echo json_encode($response_data);
?>