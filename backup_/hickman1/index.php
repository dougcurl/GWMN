<?php
/**
 * Main Well Page - index.php
 * Place this file in each well directory
 */

// Include configuration and shared API functions
require_once 'config.php';
require_once __DIR__ . '/../common/api.php';

// Get OAuth token
$access_token = getOAuthToken($client_id, $client_secret, $token_url, $debug_mode);

// Initialize variables
$parameters = [];
$units = [];
$location_details = null;
$availableParams = [];

if (!$access_token) {
    $error_message = 'Authentication failed. Please check your client credentials.';
} else {
    // Get friendly names for parameters and units
    $friendlynames = getFriendlyNames($access_token, $base_url, $debug_mode);
    $parameters = $friendlynames['parameters'];
    $units = $friendlynames['units'];
    
    // Get location details
    $location_details = getLocationDetails($well_location_id, $access_token, $base_url, $debug_mode);
    
    // Get GPS coordinates from location details
    $latitude = null;
    $longitude = null;
    if ($location_details && isset($location_details['gps'])) {
        $latitude = $location_details['gps']['latitude'];
        $longitude = $location_details['gps']['longitude'];
    }
    
    // Get data for week and hour periods using calculated end times
    $week_data = getLocationData($well_location_id, $access_token, $base_url, $custom_week_start, $week_end, 10, $debug_mode);
    $hour_data = getLocationData($well_location_id, $access_token, $base_url, $custom_hour_start, $hour_end, 5, $debug_mode);
    
    if (!$week_data || isset($week_data['error'])) {
        $error_message = isset($week_data['error']) ? $week_data['error'] : 'Failed to retrieve well data.';
    } else {
        // Process the data - simplified for week and hour only
        $availableParams = processWellData($week_data, $hour_data, $parameters, $units, $paramNames, $paramUnits, $depth_method, $water_level_baseline, $water_well_elevation, $transducer_height);
    }
}

if ($debug_mode) {
    echo '<div class="alert alert-info">';
    echo '<strong>Location Details Debug:</strong><br>';
    echo 'Location ID: ' . ($location_details['id'] ?? 'not set') . '<br>';
    echo 'Location Name: ' . ($location_details['name'] ?? 'not set') . '<br>';
    echo 'Has GPS: ' . (isset($location_details['gps']) ? 'YES' : 'NO') . '<br>';
    if (isset($location_details['gps'])) {
        echo 'Latitude: ' . ($location_details['gps']['latitude'] ?? 'not set') . '<br>';
        echo 'Longitude: ' . ($location_details['gps']['longitude'] ?? 'not set') . '<br>';
    }
    echo '<pre>' . print_r($location_details, true) . '</pre>';
    echo '</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KGS Groundwater Well Monitoring - <?php echo $page_title ?? 'Hickman 1 Deep Water Well (KGON-5)'; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <!-- Custom CSS -->
    <link href="../css/styles.css" rel="stylesheet">
</head>
<body>
    <!-- Page loading overlay -->
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
                    <h1 class="mb-1 fs-3">
                        <?php if ($page_title): ?>
                            <?php echo $page_title ?? 'Hickman 1 Deep Water Well (KGON-5)'; ?>
                        <?php else: ?>
                            Hickman 1 Deep Water Well (KGON-5)
                        <?php endif; ?>
                    </h1>
                    <h2 class="fs-5 text-muted"><a href="https://www.uky.edu/KGS/water/water-groundwater-monitoring.php">KGS Groundwater Monitoring Network</a></h2>
                </div>
                <div class="col-md-4 col-12 d-flex justify-content-md-end justify-content-center mt-3 mt-md-0">
                    <div class="d-flex flex-row gap-2">
                        <a href="https://kgs.uky.edu/kygeode/services/water/gwmn/" class="btn btn-outline-primary monthly-view-btn">View All Real-Time KGON Wells</a>
                    </div>
                </div>
            </div>
            <hr class="mt-3">
        </div>

        <!-- Animation toggle and monthly button controls -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <!-- Animation toggle control (left) -->
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
            <!-- Monthly button (right) -->
            <div class="time-control">
            <div class="d-flex flex-row gap-2">
                <a href="monthly.php" class="btn btn-outline-primary monthly-view-btn">View Monthly Data</a>
            </div>
            </div>
        </div>
            <!-- Map and Latest Readings Section -->
            <div class="row mt-4">
                <!-- Map on the left -->
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
                    <?php else: ?>
                        <div class="card h-100">
                            <div class="card-header">
                                <strong>Well Location</strong>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <p>Location not available for this well.</p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Latest readings on the right -->
                <div class="col-lg-4 mb-4">
                    <div class="row" style="margin-top:50px;">
                        <?php if (isset($availableParams['depth'])): 
                            // Get the latest reading
                            $readings = isset($availableParams['depth']['hour_readings']) ? 
                                       $availableParams['depth']['hour_readings'] : 
                                       $availableParams['depth']['week_readings'];
                            usort($readings, function($a, $b) {
                                return $b['timestamp'] - $a['timestamp']; // Sort by timestamp, newest first
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
                                        <strong>Warning:</strong> Groundwater Level Elevation is above the well head elevation (838 ft) (<?php echo $water_level_warning; ?> ft)
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (isset($availableParams['temperature'])): 
                            // Get the latest reading
                            $readings = isset($availableParams['temperature']['hour_readings']) ? 
                                       $availableParams['temperature']['hour_readings'] : 
                                       $availableParams['temperature']['week_readings'];
                            usort($readings, function($a, $b) {
                                return $b['timestamp'] - $a['timestamp']; // Sort by timestamp, newest first
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
            
            <?php if (isset($availableParams['depth']) && isset($availableParams['depth']['week_readings'])): ?>
                <div class="card">
                    <div class="card-header">
                        <span>Groundwater Level Elevation - Weekly Trend</span>
                    </div>
                    <div class="card-body">
                        <?php
                        $weeklyDepthReadings = $availableParams['depth']['week_readings'];

                        // Sort readings by timestamp (oldest first)
                        usort($weeklyDepthReadings, function($a, $b) {
                            return $a['timestamp'] - $b['timestamp'];
                        });

                        // Prepare chart data
                        $sampledDepthReadings = downsampleReadings($weeklyDepthReadings, 100);
                        // Prepare chart data specific to depth
                        $depthChartLabels = [];
                        $depthChartData = [];

                        foreach ($sampledDepthReadings as $depthReading) {
                            $depthChartLabels[] = $depthReading['timestamp'];
                            $depthChartData[] = number_format($depthReading['value'], 2, '.', '');
                        }

                        // More debugging after chart data is prepared
                        if($debug_mode) {
                            echo "<pre class='alert alert-info'>Chart data prepared:<br>";
                            echo "Sample of chart data values: ";
                            for($i = 0; $i < min(3, count($depthChartData)); $i++) {
                                echo "Value #$i: " . $depthChartData[$i] . ", ";
                            }
                            echo "</pre>";
                        }
                        
                        $weekDepthLabelsJson = json_encode($depthChartLabels);
                        $weekDepthDataJson = json_encode($depthChartData);
                        
                        // Calculate statistics
                        $values = array_column($weeklyDepthReadings, 'value');
                        $min = min($values);
                        $max = max($values);
                        $avg = array_sum($values) / count($values);
                        $aboveThreshold = count(array_filter($values, function($v) use ($water_level_warning) {
                            return $v >= $water_level_warning;
                        }));
                        ?>
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
            

            <?php if (isset($availableParams['temperature']) && isset($availableParams['temperature']['week_readings'])): ?>
                <div class="card">
                    <div class="card-header">
                        <span>Temperature - Weekly Trend</span>
                    </div>
                    <div class="card-body">
                        <?php
                        $tempReadings = $availableParams['temperature']['week_readings'];
                        
                        // Sort readings by timestamp (oldest first)
                        usort($tempReadings, function($a, $b) {
                            return $a['timestamp'] - $b['timestamp'];
                        });
                        
                        // Prepare chart data - Using downsample function to limit data points
                        $sampledTempReadings = downsampleReadings($tempReadings, 100);
                        $chartTempLabels = [];
                        $chartTempData = [];
                        
                        foreach ($sampledTempReadings as $reading) {
                            $chartTempLabels[] = $reading['timestamp'];
                            $chartTempData[] = number_format($reading['value'], 2, '.', '');
                        }
                        
                        $chartTempLabelsJson = json_encode($chartTempLabels);
                        $chartTempDataJson = json_encode($chartTempData);
                        
                        // Calculate statistics
                        $values = array_column($tempReadings, 'value');
                        $min = min($values);
                        $max = max($values);
                        $avg = array_sum($values) / count($values);
                        ?>
                        
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
            
            <!-- Hourly data section -->
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="margin-top:80px;" id="hourly-data-header">
                <div>
                    <h2>Daily Data</h2>
                    <h4 class="section-title mb-0"><?php echo date('M j, Y g:i A', $custom_hour_start); ?> to <?php echo date('M j, Y g:i A', $hour_end); ?></h4>
                </div>
                <div class="time-control">
                    <form method="get" class="form-inline d-inline text-end" id="hourForm">
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
            
            <?php if (isset($availableParams['depth']) && isset($availableParams['depth']['hour_readings'])): ?>
                <div class="card">
                    <div class="card-header">
                        <span>Groundwater Level Elevation - Hourly Trend</span>
                    </div>
                    <div class="card-body">
                        <?php
                        $depthHourReadings = $availableParams['depth']['hour_readings'];
                        
                        // Sort readings by timestamp (oldest first)
                        usort($depthHourReadings, function($a, $b) {
                            return $a['timestamp'] - $b['timestamp'];
                        });
                        
                        // Prepare chart data
                         $depthHourChartLabels = [];
                         $depthHourChartData = [];
                         
                         foreach ($depthHourReadings as $reading) {
                            $depthHourChartLabels[] = $reading['timestamp'];
                            $depthHourChartData[] = number_format($reading['value'], 2, '.', '');
                         }
                        
                         $hourDepthLabelsJson = json_encode($depthHourChartLabels);
                         $hourDepthDataJson = json_encode($depthHourChartData);
                        
                        // Calculate statistics
                        $values = array_column($depthHourReadings, 'value');
                        $min = min($values);
                        $max = max($values);
                        $avg = array_sum($values) / count($values);
                        $aboveThreshold = count(array_filter($values, function($v) use ($water_level_warning) {
                            return $v >= $water_level_warning;
                        }));
                        ?>
                        
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
            
            <?php if (isset($availableParams['temperature']) && isset($availableParams['temperature']['hour_readings'])): ?>
                <div class="card">
                    <div class="card-header">
                        <span>Temperature - Hourly Trend</span>
                    </div>
                    <div class="card-body">
                        <?php
                        $tempHourReadings = $availableParams['temperature']['hour_readings'];
    
                        // Sort readings by timestamp (oldest first)
                        usort($tempHourReadings, function($a, $b) {
                            return $a['timestamp'] - $b['timestamp'];
                        });
                        
                        // Create separate arrays for hour chart data
                        $tempHourChartLabels = [];
                        $tempHourChartData = [];
                        
                        foreach ($tempHourReadings as $reading) {
                            $tempHourChartLabels[] = $reading['timestamp'];
                            $tempHourChartData[] = number_format($reading['value'], 2, '.', '');
                        }
                        
                        $hourTempLabelsJson = json_encode($tempHourChartLabels);
                        $hourTempDataJson = json_encode($tempHourChartData);

                        // Calculate statistics
                        $values = array_column($tempHourReadings, 'value');
                        $min = min($values);
                        $max = max($values);
                        $avg = array_sum($values) / count($values);
                        ?>
                        
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
            
<?php if ($debug_mode): ?>
<div class="card mt-4">
    <div class="card-header">Hour Data Debug</div>
    <div class="card-body">
        <h5>Hour Data Structure:</h5>
        <pre>
<?php
// Check if hour readings exist
if (isset($availableParams['depth']) && isset($availableParams['depth']['hour_readings'])) {
    echo "Depth Hour Readings Exist: YES\n";
    echo "Count: " . count($availableParams['depth']['hour_readings']) . "\n";
    echo "Sample: \n";
    $sample = array_slice($availableParams['depth']['hour_readings'], 0, 3);
    print_r($sample);
} else {
    echo "Depth Hour Readings Exist: NO\n";
    if (isset($availableParams['depth'])) {
        echo "Available keys in depth parameter: \n";
        print_r(array_keys($availableParams['depth']));
    } else {
        echo "Depth parameter not found\n";
    }
}

// Check hour data from API
echo "\n\nHour Data from API:\n";
if (isset($hour_data['parameters']) && is_array($hour_data['parameters'])) {
    echo "Hour parameters count: " . count($hour_data['parameters']) . "\n";
    foreach ($hour_data['parameters'] as $param) {
        if (isset($param['parameterId'])) {
            echo "Parameter ID: " . $param['parameterId'] . "\n";
            if (isset($param['readings']) && !empty($param['readings'])) {
                echo "  Readings count: " . count($param['readings']) . "\n";
                echo "  First reading: \n";
                print_r($param['readings'][0]);
            } else {
                echo "  No readings found\n";
            }
        }
    }
} else {
    echo "No hour parameters found\n";
}
?>
        </pre>
    </div>
</div>
<?php endif; ?>

            <!-- JavaScript for charts and export data-->
            <script>
                // Set water level warning threshold for use in charts
                const waterLevelWarning = <?php echo $water_level_warning; ?>;
                
                // Chart.js data for Water Level - Week
                <?php if (isset($availableParams['depth']) && isset($availableParams['depth']['week_readings'])): 

                ?>
                    const weekDepthLabels = <?php echo isset($weekDepthLabelsJson) ? $weekDepthLabelsJson : '[]'; ?>;
                    const weekDepthData = <?php echo isset($weekDepthDataJson) ? $weekDepthDataJson : '[]'; ?>;
                <?php else: ?>
                    const weekDepthLabels = [];
                    const weekDepthData = [];
                <?php endif; ?>
                
                // Chart.js data for Temperature - Week
                <?php if (isset($availableParams['temperature']) && isset($availableParams['temperature']['week_readings'])): 
                    $weekTempLabels = $chartTempLabels;
                    $weekTempData = $chartTempData;
                    
                    $weekTempLabelsJson = json_encode($weekTempLabels);
                    $weekTempDataJson = json_encode($weekTempData);
                ?>
                    const weekTempLabels = <?php echo isset($chartTempLabelsJson) ? $chartTempLabelsJson : '[]'; ?>;
                    const weekTempData = <?php echo isset($chartTempDataJson) ? $chartTempDataJson : '[]'; ?>;
                <?php else: ?>
                    const weekTempLabels = [];
                    const weekTempData = [];
                <?php endif; ?>
                
                // Chart.js data for Water Level - Hour
                <?php if (isset($availableParams['depth']) && isset($availableParams['depth']['hour_readings'])): 
                    $hourDepthLabels = $depthHourChartLabels;
                    $hourDepthData = $depthHourChartData;
                    
                    $hourDepthLabelsJson = json_encode($hourDepthLabels);
                    $hourDepthDataJson = json_encode($hourDepthData);
                    
                ?>
                    const hourDepthLabels = <?php echo isset($hourDepthLabelsJson) ? $hourDepthLabelsJson : '[]'; ?>;
                    const hourDepthData = <?php echo isset($hourDepthDataJson) ? $hourDepthDataJson : '[]'; ?>;
                <?php else: ?>
                    const hourDepthLabels = [];
                    const hourDepthData = [];
                <?php endif; ?>
                
                // Chart.js data for Temperature - Hour
                <?php if (isset($availableParams['temperature']) && isset($availableParams['temperature']['hour_readings'])): 
                    $hourTempLabels = $tempHourChartLabels;
                    $hourTempData = $tempHourChartData;
                    
                    $hourTempLabelsJson = json_encode($hourTempLabels);
                    $hourTempDataJson = json_encode($hourTempData);
                ?>
                    const hourTempLabels = <?php echo isset($hourTempLabelsJson) ? $hourTempLabelsJson : '[]'; ?>;
                    const hourTempData = <?php echo isset($hourTempDataJson) ? $hourTempDataJson : '[]'; ?>;
                <?php else: ?>
                    const hourTempLabels = [];
                    const hourTempData = [];
                <?php endif; ?>
            </script>
        
        <footer>
            <div class="container">
                <p>Kentucky Geological Survey | Data provided by HydroVu API</p>
                <p>Last updated: <?php echo date('m/d/y h:i A', time()); ?></p>
                <?php if ($debug_mode): ?>
                <p><a href="?debug=0">Disable Debug Mode</a></p>
                <?php endif; ?>
            </div>
        </footer>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JavaScript -->
    <script src="../js/scripts.js"></script>
    <script>
        // Initialize export data
        <?php if (isset($availableParams['depth']) && isset($availableParams['depth']['week_readings'])): ?>
            initExportData('Depth', 'weekly', <?php echo json_encode($weeklyDepthReadings); ?>, '<?php echo htmlspecialchars($availableParams['depth']['unit']); ?>');
        <?php endif; ?>

        <?php if (isset($availableParams['depth']) && isset($availableParams['depth']['hour_readings'])): ?>
            initExportData('Depth', 'hourly', <?php echo json_encode($depthHourReadings); ?>, '<?php echo htmlspecialchars($availableParams['depth']['unit']); ?>');
        <?php endif; ?>

        <?php if (isset($availableParams['temperature']) && isset($availableParams['temperature']['week_readings'])): ?>
            initExportData('Temperature', 'weekly', <?php echo json_encode($tempReadings); ?>, '<?php echo htmlspecialchars($availableParams['temperature']['unit']); ?>');
        <?php endif; ?>

        <?php if (isset($availableParams['temperature']) && isset($availableParams['temperature']['hour_readings'])): ?>
            initExportData('Temperature', 'hourly', <?php echo json_encode($tempHourReadings); ?>, '<?php echo htmlspecialchars($availableParams['temperature']['unit']); ?>');
        <?php endif; ?>
    </script>
</body>
</html>