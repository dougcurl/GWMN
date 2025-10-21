<?php
// Include configuration and API functions
require_once 'config.php';
require_once 'api.php';

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
    
    // Get data for week and hour periods only
    $week_data = getLocationData($well_location_id, $access_token, $base_url, $custom_week_start, $current_time, 10, $debug_mode);
    $hour_data = getLocationData($well_location_id, $access_token, $base_url, $custom_hour_start, $current_time, 5, $debug_mode);
    
    if (!$week_data || isset($week_data['error'])) {
        $error_message = isset($week_data['error']) ? $week_data['error'] : 'Failed to retrieve well data.';
    } else {
        // Process the data - simplified for week and hour only
        $availableParams = processWellData($week_data, $hour_data, $parameters, $units, $paramNames, $paramUnits, $water_level_baseline);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KGS Groundwater Well Monitoring - KGS Horse Park Water Well</title>
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
                <img src="https://kgs.uky.edu/kygeode/img/UK-KGSlogos/UK-KGS-lockup/KGS.png" alt="KGS Logo" class="img-fluid" style="max-height: 80px;">
            </div>
            <div class="col-md-6 col-sm-9">
                <h1 class="mb-1 fs-3">
                    <?php if ($location_details && isset($location_details['name'])): ?>
                        KGS <?php echo htmlspecialchars($location_details['name']); ?>
                    <?php else: ?>
                        Kentucky Horse Park Water Well
                    <?php endif; ?>
                </h1>
                <h2 class="fs-5 text-muted"><a href="https://www.uky.edu/KGS/water/water-groundwater-monitoring.php">KGS Groundwater Monitoring Network</a></h2>
            </div>
            <div class="col-md-4 col-12 d-flex justify-content-md-end justify-content-center mt-3 mt-md-0">
                <a href="monthly.php" class="btn btn-outline-primary monthly-view-btn">View Monthly Data</a>
            </div>
        </div>
        <hr class="mt-3">
    </div>
        
        <?php if ($location_details && isset($location_details['description'])): ?>
            <p class="lead"><?php echo htmlspecialchars($location_details['description']); ?></p>
        <?php endif; ?>
        
        <?php if ($location_details && isset($location_details['gps'])): ?>
            <p>
                <strong>Location:</strong> 
                <?php echo $location_details['gps']['latitude']; ?>, 
                <?php echo $location_details['gps']['longitude']; ?>
            </p>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <h4>Error</h4>
                <p><?php echo htmlspecialchars($error_message); ?></p>
            </div>
        <?php else: ?>
            
            <!-- Animation toggle control -->
            <div class="time-control mb-4">
                <div class="animation-toggle">
                    <span>Animation:</span>
                    <label class="toggle-switch">
                        <input type="checkbox" id="animation-toggle" checked>
                        <span class="toggle-slider"></span>
                    </label>
                    <span>Refreshing every 30s</span>
                </div>
                <small class="text-muted">(Data updates from API every 15 minutes)</small>
            </div>
            
            <!-- Latest readings section -->
            <div class="row mt-4">
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
                <div class="col-md-6">
                    <div class="latest-reading water-level <?php echo $isHighLevel ? 'high-level' : ''; ?>">
                        <div class="latest-label">Latest Groundwater Level Elevation</div>
                        <div class="latest-value" id="latest-depth-value">
                            <?php echo $formattedValue; ?> <?php echo htmlspecialchars($availableParams['depth']['unit']); ?>
                        </div>
                        <div class="latest-time text-muted" id="latest-depth-time">
                            as of <?php echo formatTimestamp($latestReading['timestamp']); ?>
                        </div>
                        <?php if ($isHighLevel): ?>
                            <div class="alert alert-danger mt-2">
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
                <div class="col-md-6">
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
            
            <!-- Weekly data section -->
            <h2 class="section-title">Weekly Data (Last 7 Days)</h2>
            
            <?php if (isset($availableParams['depth']) && isset($availableParams['depth']['week_readings'])): ?>
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Groundwater Level Elevation - Weekly Trend</span>
                            <div class="time-control">
                                <form method="get" class="form-inline d-inline">
                                    <input type="hidden" name="week_start" id="week_start" value="">
                                    <button type="button" class="btn btn-sm btn-outline-secondary me-1" onclick="updateWeekRange(-7)">« Previous</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetWeekRange()">Reset</button>
                                </form>
                            </div>
                        </div>
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
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Temperature - Weekly Trend</span>
                            <div class="time-control">
                                <form method="get" class="form-inline d-inline">
                                    <input type="hidden" name="week_start" id="week_start_temp" value="">
                                    <button type="button" class="btn btn-sm btn-outline-secondary me-1" onclick="updateWeekRange(-7)">« Previous</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetWeekRange()">Reset</button>
                                </form>
                            </div>
                        </div>
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
            <h2 class="section-title">Daily Data (Last 24 Hours)</h2>
            
            <?php if (isset($availableParams['depth']) && isset($availableParams['depth']['hour_readings'])): ?>
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Groundwater Level Elevation - Hourly Trend</span>
                            <div class="time-control">
                                <form method="get" class="form-inline d-inline">
                                    <input type="hidden" name="hour_start" id="hour_start" value="">
                                    <button type="button" class="btn btn-sm btn-outline-secondary me-1" onclick="updateHourRange(-1)">« Previous</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetHourRange()">Reset</button>
                                </form>
                            </div>
                        </div>
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
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Temperature - Hourly Trend</span>
                            <div class="time-control">
                                <form method="get" class="form-inline d-inline">
                                    <input type="hidden" name="hour_start" id="hour_start_temp" value="">
                                    <button type="button" class="btn btn-sm btn-outline-secondary me-1" onclick="updateHourRange(-1)">« Previous</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetHourRange()">Reset</button>
                                </form>
                            </div>
                        </div>
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
        <?php endif; ?>
        
        <footer>
            <div class="container">
                <p>Kentucky Geological Survey | Data provided by HydroVu API</p>
                <p>Last updated: <?php echo date('m/d/y h:i A', time()); ?></p>
                <?php if ($debug_mode): ?>
                <p><a href="?debug=0">Disable Debug Mode</a></p>
                <?php else: ?>
                <!--<p><small><a href="?debug=1" class="text-muted">Enable Debug Mode</a></small></p>-->
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