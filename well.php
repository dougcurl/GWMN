<?php
/**
 * Universal Well Display Page - well.php
 * Complete version with all features from old pages
 * Usage: well.php?id=hp or well.php?id=hickman1
 */

// Load configuration
require_once __DIR__ . '/credentials.php';
require_once __DIR__ . '/wells_config.php';
require_once __DIR__ . '/common/api.php';

// Get well ID from URL
$current_well_id = isset($_GET['id']) ? $_GET['id'] : null;

if ($current_well_id === null) {
    header('Location: index.php');
    exit;
}

$well_config = getWellConfig($current_well_id);
if ($well_config === null) {
    die('Error: Configuration not found for well: ' . htmlspecialchars($current_well_id));
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
$water_well_depth = isset($well_config['water_well_depth']) ? $well_config['water_well_depth'] : 0;
$page_title = $well_config['full_name'];
$well_common_name = $well_config['common_name'];
$well_description = isset($well_config['description']) ? $well_config['description'] : '';
$aquifer_name = isset($well_config['aquifer_name']) ? $well_config['aquifer_name'] : '';
$well_numeric_id = isset($well_config['well_numeric_id']) ? $well_config['well_numeric_id'] : '';

$debug_mode = isset($_GET['debug']) && $_GET['debug'] === '1';

function roundToInterval($timestamp, $interval_minutes = 15) {
    $interval_seconds = $interval_minutes * 60;
    return floor($timestamp / $interval_seconds) * $interval_seconds;
}

$current_time = roundToInterval(time(), $reading_interval);
$week_start = $current_time - (7 * 24 * 60 * 60);
$hour_start = $current_time - (24 * 60 * 60); // Changed to 24 hours for daily data

// Allow custom date ranges
if (isset($_GET['week_start'])) {
    $custom_week_start = roundToInterval(strtotime($_GET['week_start']), $reading_interval);
    $week_end = $custom_week_start + (7 * 24 * 60 * 60);
    if ($week_end > $current_time) $week_end = $current_time;
} else {
    $custom_week_start = $week_start;
    $week_end = $current_time;
}

if (isset($_GET['hour_start'])) {
    $custom_hour_start = roundToInterval(strtotime($_GET['hour_start']), $reading_interval);
    $hour_end = $custom_hour_start + (24 * 60 * 60); // 24 hours
    if ($hour_end > $current_time) $hour_end = $current_time;
} else {
    $custom_hour_start = $hour_start;
    $hour_end = $current_time;
}

$is_current_week = ($week_end >= $current_time - 3600);
$is_current_day = ($hour_end >= $current_time - 3600);

$access_token = getOAuthToken($client_id, $client_secret, $token_url, $debug_mode);
$parameters = [];
$units = [];
$location_details = null;
$availableParams = [];

if (!$access_token) {
    $error_message = 'Authentication failed. Please check your client credentials.';
} else {
    $friendlynames = getFriendlyNames($access_token, $base_url, $debug_mode);
    $parameters = $friendlynames['parameters'];
    $units = $friendlynames['units'];
    
    $location_details = getLocationDetails($well_location_id, $access_token, $base_url, $debug_mode);
    $latitude = null;
    $longitude = null;
    if ($location_details && isset($location_details['gps'])) {
        $latitude = $location_details['gps']['latitude'];
        $longitude = $location_details['gps']['longitude'];
    }
    
    $week_data = getLocationData($well_location_id, $access_token, $base_url, $custom_week_start, $week_end, 10, $debug_mode);
    $hour_data = getLocationData($well_location_id, $access_token, $base_url, $custom_hour_start, $hour_end, 5, $debug_mode);
    
    if (!$week_data || isset($week_data['error'])) {
        $error_message = isset($week_data['error']) ? $week_data['error'] : 'Failed to retrieve well data.';
    } else {
        $availableParams = processWellData($week_data, $hour_data, $parameters, $units, $paramNames, $paramUnits, $depth_method, $water_level_baseline, $water_well_elevation, $transducer_height);
    }
}

// Prepare chart data arrays for depth week
if (isset($availableParams['depth']['week_readings'])) {
    $weeklyDepthReadings = $availableParams['depth']['week_readings'];
    usort($weeklyDepthReadings, function($a, $b) {
        return $a['timestamp'] - $b['timestamp'];
    });
    $sampledDepthReadings = downsampleReadings($weeklyDepthReadings, 100);
    $weekDepthLabels = [];
    $weekDepthData = [];
    foreach ($sampledDepthReadings as $reading) {
        $weekDepthLabels[] = $reading['timestamp'];
        $weekDepthData[] = number_format($reading['value'], 3, '.', '');
    }
}

// Prepare chart data arrays for temperature week
if (isset($availableParams['temperature']['week_readings'])) {
    $tempReadings = $availableParams['temperature']['week_readings'];
    usort($tempReadings, function($a, $b) {
        return $a['timestamp'] - $b['timestamp'];
    });
    $sampledTempReadings = downsampleReadings($tempReadings, 100);
    $weekTempLabels = [];
    $weekTempData = [];
    foreach ($sampledTempReadings as $reading) {
        $weekTempLabels[] = $reading['timestamp'];
        $weekTempData[] = number_format($reading['value'], 3, '.', '');
    }
}

// Prepare chart data arrays for depth hour (daily - 24 hours)
if (isset($availableParams['depth']['hour_readings'])) {
    $depthHourReadings = $availableParams['depth']['hour_readings'];
    usort($depthHourReadings, function($a, $b) {
        return $a['timestamp'] - $b['timestamp'];
    });
    $hourDepthLabels = [];
    $hourDepthData = [];
    foreach ($depthHourReadings as $reading) {
        $hourDepthLabels[] = $reading['timestamp'];
        $hourDepthData[] = number_format($reading['value'], 3, '.', '');
    }
}

// Prepare chart data arrays for temperature hour (daily - 24 hours)
if (isset($availableParams['temperature']['hour_readings'])) {
    $tempHourReadings = $availableParams['temperature']['hour_readings'];
    usort($tempHourReadings, function($a, $b) {
        return $a['timestamp'] - $b['timestamp'];
    });
    $hourTempLabels = [];
    $hourTempData = [];
    foreach ($tempHourReadings as $reading) {
        $hourTempLabels[] = $reading['timestamp'];
        $hourTempData[] = number_format($reading['value'], 3, '.', '');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KGS Groundwater Well Monitoring - <?php echo htmlspecialchars($page_title); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="css/styles.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
</head>
<body>
    <div id="page-loader" class="page-loading-overlay" style="display: none;">
        <div class="loading-content">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3">Loading data, please wait...</p>
        </div>
    </div>

    <div class="container mt-4">
        <div class="header-container mb-4">
            <div class="row align-items-center">
                <div class="col-md-2 col-sm-3 text-center text-md-start mb-3 mb-md-0">
                    <a href="https://kygs.uky.edu">
                        <img src="https://kgs.uky.edu/kygeode/img/UK-KGSlogos/UK-KGS-lockup/KGS.png" alt="KGS Logo" class="img-fluid" style="max-height: 100px;">
                    </a>
                </div>
                <div class="col-md-6 col-sm-9">
                    <h1 class="mb-1 fs-3"><?php echo htmlspecialchars($page_title); ?></h1>
                    <h2 class="fs-5 text-muted"><a href="https://www.uky.edu/KGS/water/water-groundwater-monitoring.php">KGS Groundwater Monitoring Network</a></h2>
                </div>
                <div class="col-md-4 col-12 d-flex justify-content-md-end justify-content-center mt-3 mt-md-0">
                    <div class="d-flex flex-row gap-2">
                        <a href="index.php" class="btn btn-outline-primary">← All Real-Time KGON Wells</a>
                    </div>
                </div>
            </div>
            <hr class="mt-3">
        </div>

        <!-- Animation toggle and monthly button controls -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="time-control">
                <div class="animation-toggle">
                    <span>Animation:</span>
                    <label class="toggle-switch">
                        <input type="checkbox" id="animation-toggle" checked>
                        <span class="toggle-slider"></span>
                    </label>
                    <span>Refresh graphs every 30s</span>
                </div>
                <small class="text-muted">(Data updates every 15 minutes)</small>
            </div>
            <div class="time-control">
                <div class="d-flex flex-row gap-2">
                    <a href="monthly.php?id=<?php echo urlencode($current_well_id); ?>" class="btn btn-outline-primary monthly-view-btn">View Monthly Data</a>
                </div>
            </div>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php else: ?>
            
            <!-- Map and Latest Readings Section -->
            <div class="row mt-4">
                <div class="col-lg-8 mb-4">
                    <?php if ($latitude && $longitude): ?>
                        <div class="card h-100">
                            <div class="card-header">
                                <strong>Well Location</strong>
                                <small class="text-muted">(<?php echo number_format($latitude, 6); ?>, <?php echo number_format($longitude, 6); ?>)</small>
                            </div>
                            <div class="card-body p-0">
                                <div style="height: 400px; position: relative;">
                                    <iframe 
                                        src="https://kygs.maps.arcgis.com/apps/instant/basic/index.html?appid=950d226696a14106938919d028b1944a&legend=false&level=16&siteid=<?php echo urlencode($well_numeric_id); ?>"
                                        style="width: 100%; height: 100%; border: none;"
                                        title="<?php echo htmlspecialchars($location_details['name'] ?? 'Well Location'); ?>"
                                        allowfullscreen>
                                    </iframe>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Latest readings on the right -->
                <div class="col-lg-4 mb-4">
                    <div class="row" style="margin-top:50px;">
                        <?php if (isset($availableParams['depth'])): 
                            $readings = isset($availableParams['depth']['hour_readings']) ? 
                                       $availableParams['depth']['hour_readings'] : 
                                       $availableParams['depth']['week_readings'];
                            usort($readings, function($a, $b) {
                                return $b['timestamp'] - $a['timestamp'];
                            });
                            $latestReading = $readings[0];
                            $formattedValue = number_format($latestReading['value'], 2);
                            $isHighLevel = $latestReading['value'] >= $water_level_warning;
                        ?>
                        <div class="col-12 mb-3">
                            <div class="latest-reading water-level <?php echo $isHighLevel ? 'high-level' : ''; ?>">
                                <div class="latest-label">Latest Groundwater Level Elevation</div>
                                <div class="latest-value" id="latest-depth-value">
                                    <?php echo $formattedValue; ?> <?php echo htmlspecialchars($availableParams['depth']['unit']); ?>
                                </div>
                                <div class="latest-time text-muted" id="latest-depth-time">
                                    as of <?php echo formatTimestamp($latestReading['timestamp']); ?>
                                </div>
                                <?php if ($isHighLevel): ?>
                                    <div class="alert alert-danger mt-2 mb-0">
                                        <strong>Warning:</strong> Groundwater Level Elevation is above warning threshold (<?php echo $water_level_warning; ?> ft)
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (isset($availableParams['temperature'])): 
                            $readings = isset($availableParams['temperature']['hour_readings']) ? 
                                       $availableParams['temperature']['hour_readings'] : 
                                       $availableParams['temperature']['week_readings'];
                            usort($readings, function($a, $b) {
                                return $b['timestamp'] - $a['timestamp'];
                            });
                            $latestReading = $readings[0];
                            $formattedValue = number_format($latestReading['value'], 2);
                        ?>
                        <div class="col-12 mb-3">
                            <div class="latest-reading temperature">
                                <div class="latest-label">Latest Temperature</div>
                                <div class="latest-value" id="latest-temp-value">
                                    <?php echo $formattedValue; ?> <?php echo htmlspecialchars($availableParams['temperature']['unit']); ?>
                                </div>
                                <div class="latest-time text-muted" id="latest-temp-time">
                                    as of <?php echo formatTimestamp($latestReading['timestamp']); ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Weekly data section -->
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" id="weekly-data-header">
                <div>
                    <h2>Weekly Data</h2>
                    <h4 class="section-title mb-0"><?php echo date('M j, Y', $custom_week_start); ?> to <?php echo date('M j, Y', $week_end); ?></h4>
                </div>
                <div class="time-control">
                    <form method="get" class="form-inline d-inline text-end" id="weekForm">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($current_well_id); ?>">
                        <div class="d-flex justify-content-end align-items-center">
                            <label class="me-2 small">Week Start Date:</label>
                            <input type="date" 
                                   name="week_start" 
                                   id="week_start" 
                                   class="form-control form-control-sm d-inline w-auto me-2" 
                                   value="<?php echo date('Y-m-d', $custom_week_start); ?>"
                                   max="<?php echo date('Y-m-d', $current_time); ?>">
                            <button type="submit" class="btn btn-sm btn-primary me-2">Go</button>
                        </div>
                        <div style="padding-top:8px;padding-right:6px;">
                            <button type="button" class="btn btn-sm btn-outline-secondary me-1" onclick="updateWeekRange(-7)">« Previous Week</button>
                            <?php if (!$is_current_week): ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary me-1" onclick="updateWeekRange(7)">Next Week »</button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetWeekRange()">Reset</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if (isset($availableParams['depth']) && isset($weeklyDepthReadings)): 
                $values = array_column($weeklyDepthReadings, 'value');
                $min = min($values);
                $max = max($values);
                $avg = array_sum($values) / count($values);
                $aboveThreshold = count(array_filter($values, function($v) use ($water_level_warning) {
                    return $v >= $water_level_warning;
                }));
            ?>
                <div class="card">
                    <div class="card-header">
                        <span>Groundwater Level Elevation - Weekly Trend</span>
                    </div>
                    <div class="card-body">
                        <div class="data-summary mb-3">
                            <div class="summary-item">
                                <div class="summary-label">Min</div>
                                <div class="summary-value"><?php echo number_format($min, 2); ?> <?php echo htmlspecialchars($availableParams['depth']['unit']); ?></div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Max</div>
                                <div class="summary-value"><?php echo number_format($max, 2); ?> <?php echo htmlspecialchars($availableParams['depth']['unit']); ?></div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Average</div>
                                <div class="summary-value"><?php echo number_format($avg, 2); ?> <?php echo htmlspecialchars($availableParams['depth']['unit']); ?></div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Readings</div>
                                <div class="summary-value"><?php echo count($weeklyDepthReadings); ?></div>
                            </div>
                            <?php if ($aboveThreshold): ?>
                                <div class="summary-item">
                                    <div class="summary-label">Above Warning</div>
                                    <div class="summary-value"><?php echo $aboveThreshold; ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="chart-container position-relative">
                            <div class="loading-overlay">
                                <div class="spinner"></div>
                            </div>
                            <canvas id="week-chart-depth"></canvas>
                        </div>
                        
                        <div class="text-end">
                            <button class="btn btn-sm btn-outline-primary" onclick="exportToCSV('Depth', 'weekly')">
                                <i class="bi bi-download"></i> Export Weekly Groundwater Level Data
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($availableParams['temperature']) && isset($tempReadings)): 
                $values = array_column($tempReadings, 'value');
                $min = min($values);
                $max = max($values);
                $avg = array_sum($values) / count($values);
            ?>
                <div class="card">
                    <div class="card-header">
                        <span>Temperature - Weekly Trend</span>
                    </div>
                    <div class="card-body">
                        <div class="data-summary mb-3">
                            <div class="summary-item">
                                <div class="summary-label">Min</div>
                                <div class="summary-value"><?php echo number_format($min, 2); ?> <?php echo htmlspecialchars($availableParams['temperature']['unit']); ?></div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Max</div>
                                <div class="summary-value"><?php echo number_format($max, 2); ?> <?php echo htmlspecialchars($availableParams['temperature']['unit']); ?></div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Average</div>
                                <div class="summary-value"><?php echo number_format($avg, 2); ?> <?php echo htmlspecialchars($availableParams['temperature']['unit']); ?></div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Readings</div>
                                <div class="summary-value"><?php echo count($tempReadings); ?></div>
                            </div>
                        </div>
                        
                        <div class="chart-container position-relative">
                            <div class="loading-overlay">
                                <div class="spinner"></div>
                            </div>
                            <canvas id="week-chart-temperature"></canvas>
                        </div>
                        
                        <div class="text-end">
                            <button class="btn btn-sm btn-outline-primary" onclick="exportToCSV('Temperature', 'weekly')">
                                <i class="bi bi-download"></i> Export Weekly Temperature Data
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Hourly (Daily - 24 hour) data section -->
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="margin-top:80px;" id="hourly-data-header">
                <div>
                    <h2>Daily Data</h2>
                    <h4 class="section-title mb-0"><?php echo date('M j, Y g:i A', $custom_hour_start); ?> to <?php echo date('M j, Y g:i A', $hour_end); ?></h4>
                </div>
                <div class="time-control">
                    <form method="get" class="form-inline d-inline text-end" id="hourForm">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($current_well_id); ?>">
                        <div>
                            <label class="me-2 small">Start Date/Time:</label>
                            <input type="datetime-local" 
                                name="hour_start" 
                                id="hour_start" 
                                class="form-control form-control-sm d-inline w-auto me-2" 
                                value="<?php echo date('Y-m-d\TH:i', $custom_hour_start); ?>"
                                max="<?php echo date('Y-m-d\TH:i', $current_time); ?>">
                            <button type="submit" class="btn btn-sm btn-primary me-2">Go</button>
                        </div>
                        <div style="padding-top:8px;padding-right:6px;">
                            <button type="button" class="btn btn-sm btn-outline-secondary me-1" onclick="updateHourRange(-24)">« Previous Day</button>
                            <?php if (!$is_current_day): ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary me-1" onclick="updateHourRange(24)">Next Day »</button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetHourRange()">Reset</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if (isset($availableParams['depth']) && isset($depthHourReadings)): 
                $values = array_column($depthHourReadings, 'value');
                $min = min($values);
                $max = max($values);
                $avg = array_sum($values) / count($values);
                $aboveThreshold = count(array_filter($values, function($v) use ($water_level_warning) {
                    return $v >= $water_level_warning;
                }));
            ?>
                <div class="card">
                    <div class="card-header">
                        <span>Groundwater Level Elevation - Hourly Trend</span>
                    </div>
                    <div class="card-body">
                        <div class="data-summary mb-3">
                            <div class="summary-item">
                                <div class="summary-label">Min</div>
                                <div class="summary-value"><?php echo number_format($min, 2); ?> <?php echo htmlspecialchars($availableParams['depth']['unit']); ?></div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Max</div>
                                <div class="summary-value"><?php echo number_format($max, 2); ?> <?php echo htmlspecialchars($availableParams['depth']['unit']); ?></div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Average</div>
                                <div class="summary-value"><?php echo number_format($avg, 2); ?> <?php echo htmlspecialchars($availableParams['depth']['unit']); ?></div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Readings</div>
                                <div class="summary-value"><?php echo count($depthHourReadings); ?></div>
                            </div>
                            <?php if ($aboveThreshold): ?>
                            <div class="summary-item">
                                <div class="summary-label">Above Warning</div>
                                <div class="summary-value"><?php echo $aboveThreshold; ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="chart-container position-relative">
                            <div class="loading-overlay">
                                <div class="spinner"></div>
                            </div>
                            <canvas id="hour-chart-depth"></canvas>
                        </div>
                        
                        <div class="text-end">
                            <button class="btn btn-sm btn-outline-primary" onclick="exportToCSV('Depth', 'hourly')">
                                <i class="bi bi-download"></i> Export Hourly Groundwater Level Data
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($availableParams['temperature']) && isset($tempHourReadings)): 
                $values = array_column($tempHourReadings, 'value');
                $min = min($values);
                $max = max($values);
                $avg = array_sum($values) / count($values);
            ?>
                <div class="card">
                    <div class="card-header">
                        <span>Temperature - Hourly Trend</span>
                    </div>
                    <div class="card-body">
                        <div class="data-summary mb-3">
                            <div class="summary-item">
                                <div class="summary-label">Min</div>
                                <div class="summary-value"><?php echo number_format($min, 2); ?> <?php echo htmlspecialchars($availableParams['temperature']['unit']); ?></div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Max</div>
                                <div class="summary-value"><?php echo number_format($max, 2); ?> <?php echo htmlspecialchars($availableParams['temperature']['unit']); ?></div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Average</div>
                                <div class="summary-value"><?php echo number_format($avg, 2); ?> <?php echo htmlspecialchars($availableParams['temperature']['unit']); ?></div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Readings</div>
                                <div class="summary-value"><?php echo count($tempHourReadings); ?></div>
                            </div>
                        </div>
                        
                        <div class="chart-container position-relative">
                            <div class="loading-overlay">
                                <div class="spinner"></div>
                            </div>
                            <canvas id="hour-chart-temperature"></canvas>
                        </div>
                        
                        <div class="text-end">
                            <button class="btn btn-sm btn-outline-primary" onclick="exportToCSV('Temperature', 'hourly')">
                                <i class="bi bi-download"></i> Export Hourly Temperature Data
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>

    <footer>
        <div class="container">
            <p>Kentucky Geological Survey | Data provided by HydroVu API</p>
            <p>Last updated: <?php echo date('m/d/y h:i A', time()); ?></p>
        </div>
    </footer>

    <script>
        const waterLevelWarning = <?php echo $water_level_warning; ?>;
        
        // Data arrays
        const weekDepthLabels = <?php echo isset($weekDepthLabels) ? json_encode($weekDepthLabels) : '[]'; ?>;
        const weekDepthData = <?php echo isset($weekDepthData) ? json_encode($weekDepthData) : '[]'; ?>;
        const weekTempLabels = <?php echo isset($weekTempLabels) ? json_encode($weekTempLabels) : '[]'; ?>;
        const weekTempData = <?php echo isset($weekTempData) ? json_encode($weekTempData) : '[]'; ?>;
        const hourDepthLabels = <?php echo isset($hourDepthLabels) ? json_encode($hourDepthLabels) : '[]'; ?>;
        const hourDepthData = <?php echo isset($hourDepthData) ? json_encode($hourDepthData) : '[]'; ?>;
        const hourTempLabels = <?php echo isset($hourTempLabels) ? json_encode($hourTempLabels) : '[]'; ?>;
        const hourTempData = <?php echo isset($hourTempData) ? json_encode($hourTempData) : '[]'; ?>;
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/scripts.js"></script>
    <script>
        // Initialize export data
        <?php if (isset($weeklyDepthReadings)): ?>
            initExportData('Depth', 'weekly', <?php echo json_encode($weeklyDepthReadings); ?>, '<?php echo htmlspecialchars($availableParams['depth']['unit']); ?>');
        <?php endif; ?>

        <?php if (isset($depthHourReadings)): ?>
            initExportData('Depth', 'hourly', <?php echo json_encode($depthHourReadings); ?>, '<?php echo htmlspecialchars($availableParams['depth']['unit']); ?>');
        <?php endif; ?>

        <?php if (isset($tempReadings)): ?>
            initExportData('Temperature', 'weekly', <?php echo json_encode($tempReadings); ?>, '<?php echo htmlspecialchars($availableParams['temperature']['unit']); ?>');
        <?php endif; ?>

        <?php if (isset($tempHourReadings)): ?>
            initExportData('Temperature', 'hourly', <?php echo json_encode($tempHourReadings); ?>, '<?php echo htmlspecialchars($availableParams['temperature']['unit']); ?>');
        <?php endif; ?>
    </script>
</body>
</html>