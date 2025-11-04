<?php
/**
 * API endpoint to check well statuses asynchronously
 * Returns status information for all configured wells
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../wells_config.php';
require_once __DIR__ . '/../credentials.php';
require_once __DIR__ . '/../common/api.php';

/**
 * Check if a well has recent data
 */
function checkWellStatus($well_id, $well_config) {
    global $client_id, $client_secret, $token_url, $base_url;
    
    $current_time = time();
    $one_day_ago = $current_time - (24 * 60 * 60);
    $one_week_ago = $current_time - (7 * 24 * 60 * 60);
    
    try {
        $access_token = getOAuthToken($client_id, $client_secret, $token_url, false);
        
        if (!$access_token) {
            return [
                'status' => 'unknown',
                'last_reading' => null,
                'message' => 'Unable to verify status',
                'badge_class' => 'bg-secondary',
                'icon' => '❓'
            ];
        }
        
        $two_hours_ago = $current_time - (2 * 60 * 60);
        $location_id = $well_config['location_id'];
        
        $data = getLocationData($location_id, $access_token, $base_url, $two_hours_ago, $current_time, 1, false);
        
        if (!$data || isset($data['error'])) {
            return [
                'status' => 'unknown',
                'last_reading' => null,
                'message' => 'Unable to verify status',
                'badge_class' => 'bg-secondary',
                'icon' => '❓'
            ];
        }
        
        $latest_timestamp = null;
        
        if (isset($data['parameters']) && is_array($data['parameters'])) {
            foreach ($data['parameters'] as $param) {
                if (isset($param['readings']) && !empty($param['readings'])) {
                    foreach ($param['readings'] as $reading) {
                        if (isset($reading['timestamp'])) {
                            $reading_time = $reading['timestamp'];
                            if ($latest_timestamp === null || $reading_time > $latest_timestamp) {
                                $latest_timestamp = $reading_time;
                            }
                        }
                    }
                }
            }
        }
        
        if ($latest_timestamp === null) {
            return [
                'status' => 'offline',
                'last_reading' => null,
                'message' => 'No recent data',
                'badge_class' => 'bg-danger',
                'icon' => '🔴'
            ];
        } else if ($latest_timestamp >= $one_day_ago) {
            $hours_ago = round(($current_time - $latest_timestamp) / 3600, 1);
            return [
                'status' => 'active',
                'last_reading' => $latest_timestamp,
                'message' => $hours_ago < 1 ? 'Updated recently' : "Updated {$hours_ago}h ago",
                'badge_class' => 'bg-success',
                'icon' => '🟢'
            ];
        } else if ($latest_timestamp >= $one_week_ago) {
            $days_ago = round(($current_time - $latest_timestamp) / 86400, 1);
            return [
                'status' => 'stale',
                'last_reading' => $latest_timestamp,
                'message' => "Updated {$days_ago} days ago",
                'badge_class' => 'bg-warning text-dark',
                'icon' => '🟡'
            ];
        } else {
            $days_ago = round(($current_time - $latest_timestamp) / 86400, 0);
            return [
                'status' => 'offline',
                'last_reading' => $latest_timestamp,
                'message' => "Last update {$days_ago} days ago",
                'badge_class' => 'bg-danger',
                'icon' => '🔴'
            ];
        }
        
    } catch (Exception $e) {
        return [
            'status' => 'unknown',
            'last_reading' => null,
            'message' => 'Unable to verify status',
            'badge_class' => 'bg-secondary',
            'icon' => '❓'
        ];
    }
}

// Get all wells
$all_wells = getAllWells();

// Check status for all wells
$statuses = [];
foreach ($all_wells as $well_id => $well_config) {
    $statuses[$well_id] = checkWellStatus($well_id, $well_config);
}

// Return JSON response
echo json_encode([
    'success' => true,
    'statuses' => $statuses,
    'timestamp' => time()
]);