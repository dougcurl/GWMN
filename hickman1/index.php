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
    <title>KGS Groundwater Well Monitoring - <?php echo htmlspecialchars($page_title); ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <!-- Custom CSS -->
    <link href="../css/styles.css" rel="stylesheet">
    
    <style>
        /* Loading skeleton styles */
        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s ease-in-out infinite;
            border-radius: 4px;
        }
        
        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        
        .skeleton-header {
            height: 60px;
            margin-bottom: 20px;
        }
        
        .skeleton-card {
            height: 150px;
            margin-bottom: 20px;
        }
        
        .skeleton-chart {
            height: 400px;
            margin-bottom: 20px;
        }
        
        .skeleton-text {
            height: 20px;
            margin-bottom: 10px;
        }
        
        #loading-skeleton {
            display: block;
        }
        
        #actual-content {
            display: none;
        }
        
        .page-ready #loading-skeleton {
            display: none;
        }
        
        .page-ready #actual-content {
            display: block;
        }
    </style>
</head>
<body>
    <!-- Loading Skeleton -->
    <div id="loading-skeleton" class="container mt-4">
        <div class="skeleton skeleton-header"></div>
        <div class="row">
            <div class="col-md-6 col-lg-4">
                <div class="skeleton skeleton-card"></div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="skeleton skeleton-card"></div>
            </div>
        </div>
        <div class="skeleton skeleton-chart"></div>
        <div class="skeleton skeleton-chart"></div>
    </div>

    <!-- Actual Content -->
    <div id="actual-content">
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
                                <?php echo htmlspecialchars($page_title); ?>
                            <?php endif; ?>
                        </h1>
                        <h2 class="fs-5 text-muted">
                            <a href="https://www.uky.edu/KGS/water/water-groundwater-monitoring.php">KGS Groundwater Monitoring Network</a>
                        </h2>
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
                        $readings = isset($availableParams['depth']['hour_readings']) ?
                                    $availableParams['depth']['hour_readings'] : 
                                    $availableParams['depth']['readings'];
                        
                        if (!empty($readings)):
                            $latest = end($readings);
                            $latest_value = $latest['value'];
                            $latest_time = date('Y-m-d H:i:s', $latest['timestamp']);
                            
                            // Determine status
                            $status_class = 'success';
                            $status_text = 'Normal';
                            if ($latest_value >= $water_level_warning) {
                                $status_class = 'warning';
                                $status_text = 'Near Surface';
                            }
                    ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100 shadow-sm">
                                <div class="card-body">
                                    <h5 class="card-title text-primary">
                                        <?php echo htmlspecialchars($availableParams['depth']['name']); ?>
                                    </h5>
                                    <div class="display-4 mb-2">
                                        <?php echo number_format($latest_value, 2); ?>
                                        <small class="fs-5 text-muted">
                                            <?php echo htmlspecialchars($availableParams['depth']['unit']); ?>
                                        </small>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="badge bg-<?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                        <small class="text-muted">
                                            Updated: <?php echo $latest_time; ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php 
                        endif;
                    endif; 
                    
                    // Temperature card
                    if (isset($availableParams['temperature'])): 
                        $temp_readings = isset($availableParams['temperature']['hour_readings']) ?
                                         $availableParams['temperature']['hour_readings'] : 
                                         $availableParams['temperature']['readings'];
                        
                        if (!empty($temp_readings)):
                            $temp_latest = end($temp_readings);
                            $temp_value = $temp_latest['value'];
                            $temp_time = date('Y-m-d H:i:s', $temp_latest['timestamp']);
                    ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100 shadow-sm">
                                <div class="card-body">
                                    <h5 class="card-title text-info">
                                        <?php echo htmlspecialchars($availableParams['temperature']['name']); ?>
                                    </h5>
                                    <div class="display-4 mb-2">
                                        <?php echo number_format($temp_value, 1); ?>
                                        <small class="fs-5 text-muted">
                                            <?php echo htmlspecialchars($availableParams['temperature']['unit']); ?>
                                        </small>
                                    </div>
                                    <div class="d-flex justify-content-end">
                                        <small class="text-muted">
                                            Updated: <?php echo $temp_time; ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php 
                        endif;
                    endif; 
                    ?>
                </div>

                <!-- Charts would continue here with your existing chart code -->
                <!-- Week Chart for Water Level -->
                <?php if (isset($availableParams['depth']) && !empty($availableParams['depth']['week_readings'])): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <span>Groundwater Level Elevation - Week View</span>
                    </div>
                    <div class="card-body">
                        <canvas id="weekDepthChart" style="max-height: 400px;"></canvas>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Hour Chart for Water Level -->
                <?php if (isset($availableParams['depth']) && !empty($availableParams['depth']['hour_readings'])): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <span>Groundwater Level Elevation - 24 Hour View</span>
                    </div>
                    <div class="card-body">
                        <canvas id="hourDepthChart" style="max-height: 400px;"></canvas>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Temperature Charts -->
                <?php if (isset($availableParams['temperature'])): ?>
                    <?php if (!empty($availableParams['temperature']['week_readings'])): ?>
                    <div class="card mt-4">
                        <div class="card-header">
                            <span>Temperature - Week View</span>
                        </div>
                        <div class="card-body">
                            <canvas id="weekTempChart" style="max-height: 400px;"></canvas>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($availableParams['temperature']['hour_readings'])): ?>
                    <div class="card mt-4">
                        <div class="card-header">
                            <span>Temperature - 24 Hour View</span>
                        </div>
                        <div class="card-body">
                            <canvas id="hourTempChart" style="max-height: 400px;"></canvas>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
                
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Hide skeleton and show content when page is ready
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                document.body.classList.add('page-ready');
            }, 100);
        });
        
        // Chart rendering code
        <?php if (isset($availableParams['depth']) && !empty($availableParams['depth']['week_readings'])): ?>
        const weekDepthData = {
            labels: [<?php 
                foreach ($availableParams['depth']['week_readings'] as $reading) {
                    echo "'" . date('M j, g:i A', $reading['timestamp']) . "',";
                }
            ?>],
            datasets: [{
                label: '<?php echo htmlspecialchars($availableParams['depth']['name']); ?> (<?php echo htmlspecialchars($availableParams['depth']['unit']); ?>)',
                data: [<?php 
                    foreach ($availableParams['depth']['week_readings'] as $reading) {
                        echo $reading['value'] . ',';
                    }
                ?>],
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.1)',
                tension: 0.1,
                fill: true
            }]
        };

        new Chart(document.getElementById('weekDepthChart'), {
            type: 'line',
            data: weekDepthData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: true, position: 'top' },
                    tooltip: { mode: 'index', intersect: false }
                },
                scales: {
                    x: { display: true, title: { display: true, text: 'Date/Time' } },
                    y: { display: true, title: { display: true, text: '<?php echo htmlspecialchars($availableParams['depth']['unit']); ?>' } }
                }
            }
        });
        <?php endif; ?>
    </script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>