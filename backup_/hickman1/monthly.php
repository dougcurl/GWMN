<?php
/**
 * Monthly Data Page - monthly.php
 * Place this file in each well directory
 */

// Include configuration and shared API functions
require_once 'config.php';
require_once __DIR__ . '/../common/api.php';

// Add month-specific configuration
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

    // Get data for month period using calculated end time
    $month_data = getLocationData($well_location_id, $access_token, $base_url, $custom_month_start, $month_end, 20, $debug_mode);
    
    if (!$month_data || isset($month_data['error'])) {
        $error_message = isset($month_data['error']) ? $month_data['error'] : 'Failed to retrieve well data.';
    } else {
        // Process the data
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
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Well Monitoring Data - <?php echo $page_title ?? 'Kentucky Horse Park Water Well (KGON-1)'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
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
        
        .skeleton-header { height: 60px; margin-bottom: 20px; }
        .skeleton-chart { height: 400px; margin-bottom: 20px; }
        
        #loading-skeleton { display: block; }
        #actual-content { display: none; }
        
        .page-ready #loading-skeleton { display: none; }
        .page-ready #actual-content { display: block; }
    </style>
</head>
<body>
    <!-- Loading Skeleton -->
    <div id="loading-skeleton" class="container mt-4">
        <div class="skeleton skeleton-header"></div>
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
                            <?php if ($page_title): ?>
                                <?php echo $page_title ?? 'Kentucky Horse Park Water Well'; ?>
                            <?php else: ?>
                                Kentucky Horse Park Water Well (KGON-1)
                            <?php endif; ?>
                        </h1>
                        <h2 class="fs-5 text-muted"><a href="https://www.uky.edu/KGS/water/water-groundwater-monitoring.php">KGS Groundwater Monitoring Network</a></h2>
                    </div>
                    <div class="col-md-4 col-12 d-flex justify-content-md-end justify-content-center mt-3 mt-md-0">
                        <a href="index.php" class="btn btn-outline-primary">Back to Recent Data</a>
                    </div>
                </div>
                <hr class="mt-3">
            </div>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <h4>Error</h4>
                    <p><?php echo htmlspecialchars($error_message); ?></p>
                </div>
            <?php else: ?>
                <!-- Monthly data section -->
                <h2 class="section-title">Monthly Data</h2>
                
            <!-- Map Section -->
            <div>
                    <?php if ($latitude && $longitude): ?>
                    <div class="card h-100">
                        <div class="card-header">
                            <strong>Well Location</strong>
                            <small class="text-muted">(<?php echo number_format($latitude, 6); ?>, <?php echo number_format($longitude, 6); ?>)</small>
                        </div>
                        <div class="card-body p-0">
                            <div style="height: 400px; position: relative;">
                                <iframe 
                                    src="https://kygs.maps.arcgis.com/apps/instant/basic/index.html?appid=950d226696a14106938919d028b1944a&level=16&siteid=<?php echo urlencode($well_numeric_id); ?>"
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


                <?php if (isset($availableParams['depth'])): ?>
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Groundwater Level Elevation - Monthly Trend</span>
                                <div class="time-control">
                                    <form method="get" class="form-inline d-inline">
                                        <input type="date" 
                                               name="month_start" 
                                               class="form-control form-control-sm d-inline w-auto" 
                                               value="<?php echo date('Y-m-d', $custom_month_start); ?>">
                                        <button type="submit" class="btn btn-sm btn-primary ms-2">Update Start Date</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <canvas id="monthDepthChart" style="max-height: 400px;"></canvas>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($availableParams['temperature'])): ?>
                    <div class="card mt-4">
                        <div class="card-header">
                            <span>Temperature - Monthly Trend</span>
                        </div>
                        <div class="card-body">
                            <canvas id="monthTempChart" style="max-height: 400px;"></canvas>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Show content when ready
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                document.body.classList.add('page-ready');
            }, 100);
        });

        // Chart rendering code continues here...
        <?php if (isset($availableParams['depth']) && !empty($availableParams['depth']['readings'])): ?>
        const monthDepthData = {
            labels: [<?php 
                foreach ($availableParams['depth']['readings'] as $reading) {
                    echo "'" . date('M j, g:i A', $reading['timestamp']) . "',";
                }
            ?>],
            datasets: [{
                label: '<?php echo htmlspecialchars($availableParams['depth']['name']); ?> (<?php echo htmlspecialchars($availableParams['depth']['unit']); ?>)',
                data: [<?php 
                    foreach ($availableParams['depth']['readings'] as $reading) {
                        echo $reading['value'] . ',';
                    }
                ?>],
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.1)',
                tension: 0.1,
                fill: true
            }]
        };

        new Chart(document.getElementById('monthDepthChart'), {
            type: 'line',
            data: monthDepthData,
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

        <?php if (isset($availableParams['temperature']) && !empty($availableParams['temperature']['readings'])): ?>
        const monthTempData = {
            labels: [<?php 
                foreach ($availableParams['temperature']['readings'] as $reading) {
                    echo "'" . date('M j, g:i A', $reading['timestamp']) . "',";
                }
            ?>],
            datasets: [{
                label: '<?php echo htmlspecialchars($availableParams['temperature']['name']); ?> (<?php echo htmlspecialchars($availableParams['temperature']['unit']); ?>)',
                data: [<?php 
                    foreach ($availableParams['temperature']['readings'] as $reading) {
                        echo $reading['value'] . ',';
                    }
                ?>],
                borderColor: 'rgb(255, 99, 132)',
                backgroundColor: 'rgba(255, 99, 132, 0.1)',
                tension: 0.1,
                fill: true
            }]
        };

        new Chart(document.getElementById('monthTempChart'), {
            type: 'line',
            data: monthTempData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: true, position: 'top' },
                    tooltip: { mode: 'index', intersect: false }
                },
                scales: {
                    x: { display: true, title: { display: true, text: 'Date/Time' } },
                    y: { display: true, title: { display: true, text: '<?php echo htmlspecialchars($availableParams['temperature']['unit']); ?>' } }
                }
            }
        });
        <?php endif; ?>
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>