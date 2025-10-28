<?php
/**
 * Universal Configuration File for Individual Wells
 * Place this SAME file in each well directory (e.g., /hp/config.php, /hickman1/config.php)
 * 
 * This file automatically detects which well it's serving based on the directory name
 * and loads the appropriate configuration from wells_config.php
 */

// Load API credentials (not in Git)
require_once __DIR__ . '/../credentials.php';

// Load the shared wells configuration
require_once __DIR__ . '/../wells_config.php';

// Detect which well we're viewing from the URL path
$current_well_id = detectWellFromPath();

// If no well detected, show error
if ($current_well_id === null) {
    die('Error: Could not determine which well to display. Please check the URL.');
}

// Load well-specific configuration from wells_config.php
$well_config = getWellConfig($current_well_id);

if ($well_config === null) {
    die('Error: Configuration not found for well: ' . htmlspecialchars($current_well_id));
}

// Extract well-specific variables for use in pages
$well_location_id = $well_config['location_id'];
$depth_method = $well_config['depth_method'];
$water_well_elevation = isset($well_config['water_well_elevation']) ? $well_config['water_well_elevation'] : 0;
$casing_height = isset($well_config['casing_height']) ? $well_config['casing_height'] : 0;
$transducer_height = isset($well_config['transducer_height']) ? $well_config['transducer_height'] : 0;
$water_level_baseline = isset($well_config['water_level_baseline']) ? $well_config['water_level_baseline'] : 0;
$water_level_warning = $well_config['water_level_warning'];
$paramNames = $well_config['param_names'];
$paramUnits = $well_config['param_units'];
$reading_interval = isset($well_config['reading_interval']) ? $well_config['reading_interval'] : 15; // Default to 15 minutes

// Page display variables
$page_title = $well_config['full_name'];
$well_common_name = $well_config['common_name'];
$well_description = isset($well_config['description']) ? $well_config['description'] : '';
$aquifer_name = isset($well_config['aquifer_name']) ? $well_config['aquifer_name'] : '';
$well_numeric_id = isset($well_config['well_numeric_id']) ? $well_config['well_numeric_id'] : '';

// Debug mode toggle (add ?debug=1 to URL to see debug info)
$debug_mode = isset($_GET['debug']) && $_GET['debug'] === '1';

/**
 * Round timestamp to the nearest reading interval
 * This ensures cache consistency and proper data retrieval
 */
function roundToInterval($timestamp, $interval_minutes = 15) {
    $interval_seconds = $interval_minutes * 60;
    return floor($timestamp / $interval_seconds) * $interval_seconds;
}

// Get current time rounded to the reading interval
$current_time = roundToInterval(time(), $reading_interval);

// Time ranges - week and hour (default values)
$week_start = $current_time - (7 * 24 * 60 * 60);
$hour_start = $current_time - (24 * 60 * 60);

// Allow custom date ranges via URL parameters
if (isset($_GET['week_start'])) {
    $custom_week_start = roundToInterval(strtotime($_GET['week_start']), $reading_interval);
    // Calculate end as 7 days from the custom start
    $week_end = $custom_week_start + (7 * 24 * 60 * 60);
    // Don't let end time go beyond current time
    if ($week_end > $current_time) {
        $week_end = $current_time;
    }
} else {
    $custom_week_start = $week_start;
    $week_end = $current_time;
}

if (isset($_GET['hour_start'])) {
    $custom_hour_start = roundToInterval(strtotime($_GET['hour_start']), $reading_interval);
    // Calculate end as 24 hours from the custom start
    $hour_end = $custom_hour_start + (24 * 60 * 60);
    // Don't let end time go beyond current time
    if ($hour_end > $current_time) {
        $hour_end = $current_time;
    }
} else {
    $custom_hour_start = $hour_start;
    $hour_end = $current_time;
}

// Check if we're viewing current data (to hide Next buttons if needed)
$is_current_week = ($week_end >= $current_time - 3600); // Within 1 hour of current
$is_current_day = ($hour_end >= $current_time - 3600); // Within 1 hour of current

// Debug output
if ($debug_mode) {
    echo "<div class='alert alert-info'>";
    echo "<strong>Debug Information:</strong><br>";
    echo "Current Well: " . htmlspecialchars($well_common_name) . " (ID: " . htmlspecialchars($current_well_id) . ")<br>";
    echo "Location ID: " . htmlspecialchars($well_location_id) . "<br>";
    echo "Reading Interval: " . $reading_interval . " minutes<br>";
    echo "Baseline: " . $water_level_baseline . " ft<br>";
    echo "Warning Threshold: " . $water_level_warning . " ft<br>";
    echo "Current time (rounded): " . date('Y-m-d H:i:s', $current_time) . "<br>";
    echo "<strong>Week Range:</strong><br>";
    echo "&nbsp;&nbsp;Start: " . date('Y-m-d H:i:s', $custom_week_start) . "<br>";
    echo "&nbsp;&nbsp;End: " . date('Y-m-d H:i:s', $week_end) . "<br>";
    echo "&nbsp;&nbsp;Duration: " . round(($week_end - $custom_week_start) / (24 * 3600), 2) . " days<br>";
    echo "&nbsp;&nbsp;Is Current: " . ($is_current_week ? 'YES' : 'NO') . "<br>";
    echo "<strong>Hour Range:</strong><br>";
    echo "&nbsp;&nbsp;Start: " . date('Y-m-d H:i:s', $custom_hour_start) . "<br>";
    echo "&nbsp;&nbsp;End: " . date('Y-m-d H:i:s', $hour_end) . "<br>";
    echo "&nbsp;&nbsp;Duration: " . round(($hour_end - $custom_hour_start) / 3600, 2) . " hours<br>";
    echo "&nbsp;&nbsp;Is Current: " . ($is_current_day ? 'YES' : 'NO');
    echo "</div>";
}

// Cache settings
$cache_enabled = true;
$cache_ttl = 900; // 15 minutes (900 seconds)
?>