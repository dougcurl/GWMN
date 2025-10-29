<?php
/**
 * Universal Monthly Data Page - monthly.php
 * Place this in the root directory
 * Usage: monthly.php?id=hp or monthly.php?id=hickman1
 */

// Load configuration files
require_once 'credentials.php';
require_once 'wells_config.php';
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
$well_id = isset($_GET['id']) ? $_GET['id'] : (isset($_GET['well']) ? $_GET['well'] : null);

// If no well specified, show well selection page
if (!$well_id) {
    $all_wells = getAllWells();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Monthly Data - Select Well</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="css/styles.css" rel="stylesheet">
    </head>
    <body>
        <div class="container mt-5">
            <h1 class="mb-4">Select a Well to View Monthly Data</h1>
            <div class="row">
                <?php foreach ($all_wells as $id => $config): ?>
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($config['full_name']); ?></h5>
                                <p class="card-text"><?php echo htmlspecialchars($config['description']); ?></p>
                                <a href="monthly.php?id=<?php echo urlencode($id); ?>" class="btn btn-primary">View Monthly Data</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Load well configuration
$well_config = getWellConfig($well_id);

if ($well_config === null) {
    die('<div class="alert alert-danger">Error: Well "' . htmlspecialchars($well_id) . '" not found in configuration.</div>');
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

// Page display variables
$page_title = $well_config['full_name'];
$well_common_name = $well_config['common_name'];
$well_description = isset($well_config['description']) ? $well_config['description'] : '';
$aquifer_name = isset($well_config['aquifer_name']) ? $well_config['aquifer_name'] : '';
$well_numeric_id = isset($well_config['well_numeric_id']) ? $well_config['well_numeric_id'] : '';

// Debug mode
$debug_mode = isset($_GET['debug']) && $_GET['debug'] == '1';

// Get current time rounded to reading interval
$current_time = roundToInterval(time(), $reading_interval);

// Add month-specific configuration (30 days)
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

// Check if this is the current month
$is_current_month = ($month_end >= $current_time - 60);

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
                    
                    // Process readings
                    $processedReadings = [];
                    
                    foreach ($param['readings'] as $reading) {
                        $timestamp = $reading['timestamp'];
                        $rawValue = $reading['value'];
                        
                        // Apply depth calculation if this is a depth parameter
                        if ($paramKey === 'depth') {
                            if ($depth_method === 'Baseline_Elev') {
                                $processedValue = $water_level_baseline + $rawValue;
                            } elseif ($depth_method === 'TD_Height') {
                                $processedValue = $water_well_elevation - $transducer_height - $rawValue;
                            } else {
                                $processedValue = $rawValue;
                            }
                        } else {
                            $processedValue = $rawValue;
                        }
                        
                        $processedReadings[] = [
                            'timestamp' => $timestamp,
                            'value' => $processedValue,
                            'raw_value' => $rawValue
                        ];
                    }
                    
                    // Sort readings by timestamp
                    usort($processedReadings, function($a, $b) {
                        return $a['timestamp'] - $b['timestamp'];
                    });
                    
                    // Store parameter info
                    $availableParams[$paramKey] = [
                        'id' => $paramId,
                        'name' => $displayName,
                        'unit' => $unitName,
                        'readings' => $processedReadings
                    ];
                }
            }
        }
    }
}

// Prepare chart data for each parameter
$monthlyChartData = [];

foreach ($availableParams as $paramKey => $paramInfo) {
    $readings = $paramInfo['readings'];
    
    // Down-sample data for charts if there are too many points
    // For 30 days with 15-minute intervals: 720 points = hourly data
    $maxPointsForChart = 720;
    
    if (count($readings) > $maxPointsForChart) {
        $samplingInterval = ceil(count($readings) / $maxPointsForChart);
        $sampledReadings = [];
        
        for ($i = 0; $i < count($readings); $i += $samplingInterval) {
            $sampledReadings[] = $readings[$i];
        }
        
        if (end($sampledReadings)['timestamp'] !== end($readings)['timestamp']) {
            $sampledReadings[] = end($readings);
        }
        
        $chartReadings = $sampledReadings;
    } else {
        $chartReadings = $readings;
    }
    
    $labels = [];
    $data = [];
    
    foreach ($chartReadings as $reading) {
        $labels[] = date('Y-m-d H:i', $reading['timestamp']);
        $data[] = round($reading['value'], 3);
    }
    
    $monthlyChartData[$paramKey] = [
        'labels' => $labels,
        'data' => $data
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Data - <?php echo htmlspecialchars($page_title); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/styles.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="row align-items-center">
                    <div class="col-md-2 text-center text-md-start mb-3 mb-md-0">
                        <a href="https://kygs.uky.edu"><img src="https://kgs.uky.edu/kygeode/img/UK-KGSlogos/UK-KGS-lockup/KGS.png" 
                             alt="KGS Logo" 
                             class="img-fluid" 
                             style="max-height: 80px;"></a>
                    </div>
                    <div class="col-md-6">
                        <h1 class="mb-0">
                            <?php echo htmlspecialchars($page_title); ?>
                            <?php if ($well_numeric_id): ?>
                                (<?php echo htmlspecialchars($well_numeric_id); ?>)
                            <?php endif; ?>
                        </h1>
                        <h2 class="fs-5 text-muted">
                            <a href="https://www.uky.edu/KGS/water/water-groundwater-monitoring.php">
                                KGS Groundwater Monitoring Network
                            </a>
                        </h2>
                    </div>
                    <div class="col-md-4 d-flex justify-content-md-end justify-content-center">
                        <div class="d-flex flex-column gap-2">
                            <a href="well.php?id=<?php echo $well_id; ?>" class="btn btn-outline-primary">← Back to
                                <?php if ($well_numeric_id): ?>
                                    <?php echo htmlspecialchars($well_numeric_id); ?>
                                <?php endif; ?> Well Dashboard</a>
                            <a href="index.php" class="btn btn-outline-secondary">View All Wells</a>
                        </div>
                    </div>
                </div>
                <hr class="mt-3">
            </div>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <!-- Monthly Data Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
            <div>
                <h2>Monthly Data (30 Days)</h2>
                <h4 class="section-title mb-0">
                    <?php echo date('M j, Y', $custom_month_start); ?> to <?php echo date('M j, Y', $month_end); ?>
                </h4>
            </div>
            <div class="time-control">
                <form method="get" class="form-inline d-inline text-end" id="monthForm">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($well_id); ?>">
                    <div class="d-flex justify-content-end align-items-center">
                        <label class="me-2 small">Month Start Date:</label>
                        <input type="date" 
                               name="month_start" 
                               id="month_start" 
                               class="form-control form-control-sm d-inline w-auto me-2" 
                               value="<?php echo date('Y-m-d', $custom_month_start); ?>"
                               max="<?php echo date('Y-m-d', $current_time); ?>">
                        <button type="submit" class="btn btn-sm btn-primary me-2">Go</button>
                    </div>
                    <div style="padding-top:8px;padding-right:6px;">
                        <button type="button" class="btn btn-sm btn-outline-secondary me-1" onclick="updateMonthRange(-30)">« Previous 30 Days</button>
                        <?php if (!$is_current_month): ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary me-1" onclick="updateMonthRange(30)">Next 30 Days »</button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetMonthRange()">Current 30 Days</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if (!empty($availableParams)): ?>
            
            <!-- Water Level Data -->
            <?php if (isset($availableParams['depth'])): 
                $depthReadings = $availableParams['depth']['readings'];
                $values = array_map(function($r) { return $r['value']; }, $depthReadings);
                $min = min($values);
                $max = max($values);
                $avg = array_sum($values) / count($values);
                $depthLabelsJson = json_encode($monthlyChartData['depth']['labels']);
                $depthDataJson = json_encode($monthlyChartData['depth']['data']);
            ?>
            <div class="card mb-4">
                <div class="card-header">
                    <strong><?php echo htmlspecialchars($availableParams['depth']['name']); ?> - Monthly Trend (1 hour intervals)</strong>
                </div>
                <div class="card-body">
                    <div class="data-summary mb-3">
                        <div class="summary-item">
                            <div class="summary-label">Current</div>
                            <div class="summary-value">
                                <?php echo number_format(end($depthReadings)['value'], 3); ?> 
                                <?php echo htmlspecialchars($availableParams['depth']['unit']); ?>
                            </div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">Min</div>
                            <div class="summary-value">
                                <?php echo number_format($min, 3); ?> 
                                <?php echo htmlspecialchars($availableParams['depth']['unit']); ?>
                            </div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">Max</div>
                            <div class="summary-value">
                                <?php echo number_format($max, 3); ?> 
                                <?php echo htmlspecialchars($availableParams['depth']['unit']); ?>
                            </div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">Average</div>
                            <div class="summary-value">
                                <?php echo number_format($avg, 3); ?> 
                                <?php echo htmlspecialchars($availableParams['depth']['unit']); ?>
                            </div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">Readings</div>
                            <div class="summary-value"><?php echo count($depthReadings); ?></div>
                        </div>
                    </div>
                    
                    <div class="chart-container position-relative" style="height: 400px;">
                        <canvas id="month-chart-depth"></canvas>
                    </div>
                    
                    <div class="text-end mt-3">
                        <button class="btn btn-sm btn-outline-primary" onclick="exportToCSV('Depth', 'monthly')">
                            Export Monthly Data (15 min intervals)
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Temperature Data -->
            <?php if (isset($availableParams['temperature'])): 
                $tempReadings = $availableParams['temperature']['readings'];
                $tempValues = array_map(function($r) { return $r['value']; }, $tempReadings);
                $tempMin = min($tempValues);
                $tempMax = max($tempValues);
                $tempAvg = array_sum($tempValues) / count($tempValues);
                $tempLabelsJson = json_encode($monthlyChartData['temperature']['labels']);
                $tempDataJson = json_encode($monthlyChartData['temperature']['data']);
            ?>
            <div class="card mb-4">
                <div class="card-header">
                    <strong><?php echo htmlspecialchars($availableParams['temperature']['name']); ?> - Monthly Trend (1 hour intervals)</strong>
                </div>
                <div class="card-body">
                    <div class="data-summary mb-3">
                        <div class="summary-item">
                            <div class="summary-label">Current</div>
                            <div class="summary-value">
                                <?php echo number_format(end($tempReadings)['value'], 3); ?> 
                                <?php echo htmlspecialchars($availableParams['temperature']['unit']); ?>
                            </div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">Min</div>
                            <div class="summary-value">
                                <?php echo number_format($tempMin, 3); ?> 
                                <?php echo htmlspecialchars($availableParams['temperature']['unit']); ?>
                            </div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">Max</div>
                            <div class="summary-value">
                                <?php echo number_format($tempMax, 3); ?> 
                                <?php echo htmlspecialchars($availableParams['temperature']['unit']); ?>
                            </div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">Average</div>
                            <div class="summary-value">
                                <?php echo number_format($tempAvg, 3); ?> 
                                <?php echo htmlspecialchars($availableParams['temperature']['unit']); ?>
                            </div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">Readings</div>
                            <div class="summary-value"><?php echo count($tempReadings); ?></div>
                        </div>
                    </div>
                    
                    <div class="chart-container position-relative" style="height: 400px;">
                        <canvas id="month-chart-temperature"></canvas>
                    </div>
                    
                    <div class="text-end mt-3">
                        <button class="btn btn-sm btn-outline-primary" onclick="exportToCSV('Temperature', 'monthly')">
                            Export Monthly Temperature Data (15 min intervals)
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="alert alert-info">No data available for the selected time period.</div>
        <?php endif; ?>

        <footer>
            <div class="container">
                <p>Kentucky Geological Survey | Data provided by HydroVu API</p>
                <p>Last updated: <?php echo date('m/d/y h:i A', time()); ?></p>
            </div>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const exportData = {};
        
        function initExportData(dataType, period, data, unit) {
            if (!exportData[period + dataType]) {
                exportData[period + dataType] = { data: data, unit: unit };
            }
        }
        
        function exportToCSV(dataType, period) {
            try {
                const key = period + dataType;
                if (!exportData[key] || !exportData[key].data) {
                    alert(`Export failed: No data available`);
                    return;
                }
                
                const readings = exportData[key].data;
                const unitName = exportData[key].unit;
                let csvContent = 'Timestamp,Value (' + unitName + ')\n';
                
                readings.forEach(reading => {
                    const timestamp = new Date(reading.timestamp * 1000).toISOString().replace('T', ' ').substring(0, 19);
                    const value = parseFloat(reading.value).toFixed(3);
                    csvContent += timestamp + ',' + value + '\n';
                });
                
                const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = `${dataType}_${period}_data.csv`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            } catch (error) {
                alert('Failed to export: ' + error.message);
            }
        }
        
        function updateMonthRange(days) {
            const date = new Date('<?php echo date('Y-m-d', $custom_month_start); ?>');
            date.setDate(date.getDate() + days);
            document.getElementById('month_start').value = date.toISOString().split('T')[0];
            document.getElementById('monthForm').submit();
        }
        
        function resetMonthRange() {
            const url = new URL(window.location.href);
            url.searchParams.delete('month_start');
            window.location.href = url.toString();
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($availableParams['depth'])): ?>
            const depthLabels = <?php echo $depthLabelsJson; ?>;
            const depthData = <?php echo $depthDataJson; ?>;
            initExportData('Depth', 'monthly', <?php echo json_encode($depthReadings); ?>, '<?php echo htmlspecialchars($availableParams['depth']['unit']); ?>');
            
            new Chart(document.getElementById('month-chart-depth'), {
                type: 'line',
                data: {
                    labels: depthLabels,
                    datasets: [{
                        label: '<?php echo addslashes($availableParams['depth']['name']); ?>',
                        data: depthData,
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.1)',
                        tension: 0.1,
                        pointRadius: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: true, position: 'top' },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => ctx.dataset.label + ': ' + ctx.raw.toFixed(3) + ' <?php echo htmlspecialchars($availableParams['depth']['unit']); ?>'
                            }
                        }
                    },
                    scales: {
                        x: {
                            title: { display: true, text: 'Date/Time' },
                            ticks: { maxRotation: 45, minRotation: 45, maxTicksLimit: 30 }
                        },
                        y: {
                            title: { display: true, text: '<?php echo addslashes($availableParams['depth']['name']); ?> (<?php echo htmlspecialchars($availableParams['depth']['unit']); ?>)' },
                            ticks: {
                                callback: function(value) {
                                    return value.toFixed(3);
                                }
                            }
                        }
                    }
                }
            });
            <?php endif; ?>
            
            <?php if (isset($availableParams['temperature'])): ?>
            const tempLabels = <?php echo $tempLabelsJson; ?>;
            const tempData = <?php echo $tempDataJson; ?>;
            initExportData('Temperature', 'monthly', <?php echo json_encode($tempReadings); ?>, '<?php echo htmlspecialchars($availableParams['temperature']['unit']); ?>');
            
            new Chart(document.getElementById('month-chart-temperature'), {
                type: 'line',
                data: {
                    labels: tempLabels,
                    datasets: [{
                        label: '<?php echo addslashes($availableParams['temperature']['name']); ?>',
                        data: tempData,
                        borderColor: 'rgb(255, 99, 132)',
                        backgroundColor: 'rgba(255, 99, 132, 0.1)',
                        tension: 0.1,
                        pointRadius: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: true, position: 'top' },
                        tooltip: {
                            callbacks: {
                                label: (ctx) => ctx.dataset.label + ': ' + ctx.raw.toFixed(3) + ' <?php echo htmlspecialchars($availableParams['temperature']['unit']); ?>'
                            }
                        }
                    },
                    scales: {
                        x: {
                            title: { display: true, text: 'Date/Time' },
                            ticks: { maxRotation: 45, minRotation: 45, maxTicksLimit: 30 }
                        },
                        y: {
                            title: { display: true, text: '<?php echo addslashes($availableParams['temperature']['name']); ?> (<?php echo htmlspecialchars($availableParams['temperature']['unit']); ?>)' },
                            ticks: {
                                callback: function(value) {
                                    return value.toFixed(3);
                                }
                            }
                        }
                    }
                }
            });
            <?php endif; ?>
        });
    </script>
</body>
</html>