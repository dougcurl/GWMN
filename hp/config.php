<?php
// API Configuration
$client_id = 'KGSStream1';
$client_secret = '93ccab41ad394e668295313b6e8e1ef1';
$base_url = 'https://www.hydrovu.com/public-api/v1';
$token_url = 'https://hydrovu.com/public-api/oauth/token';
$well_location_id = '5515870852612096';

// Water level calculation baseline
$water_level_baseline = 804.25;

// Water level warning threshold  (ground surface elevation from new lidar elevation)
$water_level_warning = 838;

// Debug mode toggle
$debug_mode = isset($_GET['debug']) && $_GET['debug'] === '1';

// Time ranges - just week and hour
$current_time = time();
$week_start = $current_time - (7 * 24 * 60 * 60);
$hour_start = $current_time - (24 * 60 * 60);

// Allow custom date ranges
$custom_week_start = isset($_GET['week_start']) ? strtotime($_GET['week_start']) : $week_start;
$custom_hour_start = isset($_GET['hour_start']) ? strtotime($_GET['hour_start']) : $hour_start;


if ($debug_mode) {
    echo "<div class='alert alert-info'>";
    echo "Current time: " . date('Y-m-d H:i:s', $current_time) . "<br>";
    echo "Hour start time: " . date('Y-m-d H:i:s', $custom_hour_start) . "<br>";
    echo "Time range for hour data: " . (($current_time - $custom_hour_start) / 3600) . " hours";
    echo "</div>";
}

// Parameter mapping
$paramNames = [
    'depth' => 'Groundwater Level Elevation',
    'temperature' => 'Temperature'
];

$paramUnits = [
    'depth' => 'ft',
    'temperature' => '°F'
];

// Cache settings
$cache_enabled = true;
$cache_ttl = 900; // 15 minutes
?>