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
$water_level_baseline = $well_config['water_level_baseline'];
$water_level_warning = $well_config['water_level_warning'];
$paramNames = $well_config['param_names'];
$paramUnits = $well_config['param_units'];

// Page display variables
$page_title = $well_config['full_name'];
$well_common_name = $well_config['common_name'];
$well_description = isset($well_config['description']) ? $well_config['description'] : '';

// Debug mode toggle (add ?debug=1 to URL to see debug info)
$debug_mode = isset($_GET['debug']) && $_GET['debug'] === '1';

// Time ranges - week and hour
$current_time = time();
$week_start = $current_time - (7 * 24 * 60 * 60);
$hour_start = $current_time - (24 * 60 * 60);

// Allow custom date ranges via URL parameters
$custom_week_start = isset($_GET['week_start']) ? strtotime($_GET['week_start']) : $week_start;
$custom_hour_start = isset($_GET['hour_start']) ? strtotime($_GET['hour_start']) : $hour_start;

// Debug output
if ($debug_mode) {
    echo "<div class='alert alert-info'>";
    echo "<strong>Debug Information:</strong><br>";
    echo "Current Well: " . htmlspecialchars($well_common_name) . " (ID: " . htmlspecialchars($current_well_id) . ")<br>";
    echo "Location ID: " . htmlspecialchars($well_location_id) . "<br>";
    echo "Baseline: " . $water_level_baseline . " ft<br>";
    echo "Warning Threshold: " . $water_level_warning . " ft<br>";
    echo "Current time: " . date('Y-m-d H:i:s', $current_time) . "<br>";
    echo "Hour start time: " . date('Y-m-d H:i:s', $custom_hour_start) . "<br>";
    echo "Week start time: " . date('Y-m-d H:i:s', $custom_week_start) . "<br>";
    echo "Time range for hour data: " . round(($current_time - $custom_hour_start) / 3600, 2) . " hours<br>";
    echo "Time range for week data: " . round(($current_time - $custom_week_start) / (24 * 3600), 2) . " days";
    echo "</div>";
}

// Cache settings
$cache_enabled = true;
$cache_ttl = 900; // 15 minutes (900 seconds)
?>