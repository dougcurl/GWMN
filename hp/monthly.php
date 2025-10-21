<?php
// Include configuration and API functions
require_once 'config.php';
require_once 'api.php';

// Add month-specific configuration
$month_start = $current_time - (30 * 24 * 60 * 60);
$custom_month_start = isset($_GET['month_start']) ? strtotime($_GET['month_start']) : $month_start;

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
    
    // Get data for month period only
    $month_data = getLocationData($well_location_id, $access_token, $base_url, $custom_month_start, $current_time, 20, $debug_mode);
    
    if (!$month_data || isset($month_data['error'])) {
        $error_message = isset($month_data['error']) ? $month_data['error'] : 'Failed to retrieve well data.';
    } else {
        // Process the data - simplified for month only
        $availableParams = [];
        
        // Process monthly data to get available parameters
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
                    
                    // If we couldn't identify it, use the parameter ID
                    if (empty($paramKey)) {
                        $paramKey = 'param_' . $paramId;
                    }
                    
                    // Get unit for this parameter
                    $unitId = $param['unitId'];
                    $unitName = isset($units[$unitId]) ? $units[$unitId] : '';
                    
                    // Override unit based on parameter type if we have a mapping
                    if (isset($paramUnits[$paramKey])) {
                        $unitName = $paramUnits[$paramKey];
                    }
                    
                    // Get display name
                    $displayName = isset($paramNames[$paramKey]) ? $paramNames[$paramKey] : 
                                  (isset($parameters[$paramId]) ? $parameters[$paramId] : 'Parameter ' . $paramId);
                    
                    // Store parameter info
                    $readings = $param['readings'];
                    
                    // If depth parameter, transform to water level
                    if ($paramKey === 'depth') {
                        $readings = transformWaterLevelData($readings, $water_level_baseline);
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
    <title>Monthly Water Well Data - <?php echo htmlspecialchars($location_details['name'] ?? 'Well #' . $well_location_id); ?></title>
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
            <p class="mt-3">Loading monthly data, please wait...</p>
        </div>
    </div>

    <div class="container mt-4">
        <!-- New Header with Logo -->
        <div class="header-container mb-4">
            <div class="row align-items-center">
                <div class="col-md-2 col-sm-3 text-center text-md-start mb-3 mb-md-0">
                    <img src="https://kgs.uky.edu/kygeode/img/UK-KGSlogos/UK-KGS-lockup/KGS.png" alt="KGS Logo" class="img-fluid" style="max-height: 80px;">
                </div>
                <div class="col-md-6 col-sm-9">
                    <h1 class="mb-1 fs-3">
                        Monthly Data: 
                        <?php if ($location_details && isset($location_details['name'])): ?>
                            KGS <?php echo htmlspecialchars($location_details['name']); ?>
                        <?php else: ?>
                            Kentucky Horse Park Water Well
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
            <h2 class="section-title">Monthly Data (Last 30 Days)</h2>
            
            <?php if (isset($availableParams['depth'])): ?>
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Groundwater Level Elevation - Monthly Trend</span>
                            <div class="time-control">
                                <form method="get" class="form-inline d-inline">
                                    <input type="hidden" name="month_start" id="month_start" value="">
                                    <button type="button" class="btn btn-sm btn-outline-secondary me-1" onclick="updateMonthRange(-30)">« Previous</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetMonthRange()">Reset</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php
                        $depthReadings = $availableParams['depth']['readings'];
                        
                        // Sort readings by timestamp (oldest first)
                        usort($depthReadings, function($a, $b) {
                            return $a['timestamp'] - $b['timestamp'];
                        });
                        
                        // Prepare chart data - Using downsample function to limit data points
                        $sampledDepthReadings = downsampleReadings($depthReadings, 100);
                        $depthChartLabels = [];
                        $depthChartData = [];
                        
                        foreach ($sampledDepthReadings as $reading) {
                            $depthChartLabels[] = $reading['timestamp']; // Send raw timestamp instead of formatted string
                            $depthChartData[] = number_format($reading['value'], 2, '.', '');
                        }
                        
                        $depthChartLabelsJson = json_encode($depthChartLabels);
                        $depthChartDataJson = json_encode($depthChartData);
                        
                        // Calculate statistics
                        $values = array_column($depthReadings, 'value');
                        $min = min($values);
                        $max = max($values);
                        $avg = array_sum($values) / count($values);
                        $aboveThreshold = count(array_filter($values, function($v) use ($water_level_warning) {
                            return $v >= $water_level_warning;
                        }));
                        
                        // Only show above warning if there are actually readings above the threshold
                        $showAboveWarning = ($aboveThreshold > 0);
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
                                <div class="summary-value"><?php echo count($depthReadings); ?></div>
                            </div>
                            <?php if ($showAboveWarning): ?>
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
                            <canvas id="month-chart-depth"></canvas>
                        </div>
                        <div class="text-end">
                            <button class="btn btn-sm btn-outline-primary" onclick="exportToCSV('Depth', 'monthly')">
                                <i class="bi bi-download"></i> Export Monthly Groundwater Level Data
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (isset($availableParams['temperature'])): ?>
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Temperature - Monthly Trend</span>
                            <div class="time-control">
                                <form method="get" class="form-inline d-inline">
                                    <input type="hidden" name="month_start" id="month_start_temp" value="">
                                    <button type="button" class="btn btn-sm btn-outline-secondary me-1" onclick="updateMonthRange(-30)">« Previous</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetMonthRange()">Reset</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php
                        $tempReadings = $availableParams['temperature']['readings'];
                        
                        // Sort readings by timestamp (oldest first)
                        usort($tempReadings, function($a, $b) {
                            return $a['timestamp'] - $b['timestamp'];
                        });
                        
                        // Prepare chart data - Using downsample function to limit data points
                        $sampledTempReadings = downsampleReadings($tempReadings, 100);
                        $tempChartLabels = [];
                        $tempChartData = [];
                        
                        foreach ($sampledTempReadings as $reading) {
                            $tempChartLabels[] = $reading['timestamp']; // Send raw timestamp instead of formatted string
                            $tempChartData[] = number_format($reading['value'], 2, '.', '');
                        }
                        
                        $tempChartLabelsJson = json_encode($tempChartLabels);
                        $tempChartDataJson = json_encode($tempChartData);
                        
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
                            <canvas id="month-chart-temperature"></canvas>
                        </div>
                        
                        <div class="text-end">
                            <button class="btn btn-sm btn-outline-primary" onclick="exportToCSV('Temperature', 'monthly')">
                                <i class="bi bi-download"></i> Export Monthly Temperature Data
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- JavaScript for charts and downloading data-->
            <script>
                // Global variables to store data
                let exportData = {};

                // Set water level warning threshold for use in charts
                const waterLevelWarning = <?php echo $water_level_warning; ?>;
                
                // Chart.js data for Water Level - Month - Now with separate variables
                <?php if (isset($availableParams['depth'])): ?>
                const monthDepthLabels = <?php echo $depthChartLabelsJson; ?>;
                const monthDepthData = <?php echo $depthChartDataJson; ?>;
                <?php else: ?>
                const monthDepthLabels = [];
                const monthDepthData = [];
                <?php endif; ?>
                
                // Chart.js data for Temperature - Month - Now with separate variables
                <?php if (isset($availableParams['temperature'])): ?>
                const monthTempLabels = <?php echo $tempChartLabelsJson; ?>;
                const monthTempData = <?php echo $tempChartDataJson; ?>;
                <?php else: ?>
                const monthTempLabels = [];
                const monthTempData = [];
                <?php endif; ?>
                
                // Create charts when document is ready
                document.addEventListener('DOMContentLoaded', function() {
                    // Create depth chart
                    if (document.getElementById('month-chart-depth')) {
                        createChart('month-chart-depth', monthDepthLabels, monthDepthData, 'Groundwater Level Elevation (ft)', 'rgba(54, 162, 235, 1)', 'rgba(54, 162, 235, 0.1)');
                    }
                    
                    // Create temperature chart
                    if (document.getElementById('month-chart-temperature')) {
                        createChart('month-chart-temperature', monthTempLabels, monthTempData, 'Temperature (°F)', 'rgba(255, 159, 64, 1)', 'rgba(255, 159, 64, 0.1)');
                    }
                });
                
                // Function to create a chart
                function createChart(canvasId, labels, data, label, borderColor, backgroundColor) {
                    const ctx = document.getElementById(canvasId).getContext('2d');
                    
                    // Check if the data is for water level to add threshold line
                    const isWaterLevel = label.includes('Water Level');
                    
                    // Format the timestamps in the labels array
                    const formattedLabels = labels.map(timestamp => {
                        // Check if timestamp is a number or string
                        if (typeof timestamp === 'string' && timestamp.match(/^\d+$/)) {
                            timestamp = parseInt(timestamp);
                        }
                        
                        // Format the timestamp
                        try {
                            const date = new Date(timestamp * 1000);
                            
                            // Format date as MM/DD/YY
                            const dateStr = date.toLocaleDateString('en-US', {
                                month: 'numeric',
                                day: 'numeric',
                                year: '2-digit'
                            });
                            
                            // Format time as HH:MM AM/PM
                            const timeStr = date.toLocaleTimeString('en-US', {
                                hour: '2-digit',
                                minute: '2-digit',
                                hour12: true
                            });
                            
                            return dateStr + '\n' + timeStr;
                        } catch (e) {
                            console.error("Error formatting timestamp:", timestamp, e);
                            return timestamp; // Return original if formatting fails
                        }
                    });
                    
                    // Calculate min and max values for better scaling
                    let minValue = Math.min(...data);
                    let maxValue = Math.max(...data);
                    
                    // Add padding to min/max (5% of the data range)
                    const range = maxValue - minValue;
                    const padding = range * 0.05;
                    minValue = Math.max(0, minValue - padding); // Don't go below 0 if data is all positive
                    maxValue = maxValue + padding;
                    
                    // Only include warning threshold in scale if data is close to it
                    let includeWarningInScale = false;
                    if (isWaterLevel) {
                        // If data is within 30% of the warning threshold, or exceeds it, include it in scale
                        if (maxValue >= waterLevelWarning) {
                            includeWarningInScale = true;
                            // If max is less than warning, extend max to include warning with some padding
                            if (maxValue < waterLevelWarning) {
                                maxValue = waterLevelWarning + padding;
                            }
                        }
                    }
                    
                    // Create chart configuration
                    const chartConfig = {
                        type: 'line',
                        data: {
                            labels: formattedLabels,
                            datasets: [{
                                label: label,
                                data: data,
                                borderColor: borderColor,
                                backgroundColor: backgroundColor,
                                fill: true,
                                pointRadius: data.length > 50 ? 0 : 2,
                                tension: 0.2,
                                borderWidth: 2,
                                pointBackgroundColor: function(context) {
                                    // Mark points above warning threshold for water level
                                    if (isWaterLevel && context.raw >= waterLevelWarning) {
                                        return 'rgba(255, 0, 0, 1)';
                                    }
                                    return borderColor;
                                },
                                segment: {
                                    borderColor: function(context) {
                                        // Change line color above warning threshold for water level
                                        if (isWaterLevel) {
                                            const valueAfter = context.p1.parsed.y;
                                            if (valueAfter >= waterLevelWarning) {
                                                return 'rgba(255, 0, 0, 1)';
                                            }
                                        }
                                        return borderColor;
                                    }
                                }
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            animation: {
                                duration: 0  // Disable initial animation
                            },
                            scales: {
                                x: {
                                    ticks: {
                                        maxRotation: 0,         // Keep labels horizontal
                                        minRotation: 0,         // Keep labels horizontal
                                        autoSkip: true,
                                        maxTicksLimit: 8,       // Limit number of ticks to prevent overcrowding
                                        padding: 10,            // Add padding for multi-line labels
                                        font: {
                                            size: 12            // Smaller font for better fit
                                        }
                                    }
                                },
                                y: {
                                    min: minValue,
                                    max: maxValue,
                                    ticks: {
                                        callback: function(value) {
                                            return value.toFixed(2);
                                        }
                                    }
                                }
                            },
                            plugins: {
                                tooltip: {
                                    mode: 'index',
                                    intersect: false,
                                    callbacks: {
                                        label: function(context) {
                                            return context.dataset.label + ': ' + parseFloat(context.raw).toFixed(2);
                                        }
                                    }
                                }
                            }
                        }
                    };

                    // Add warning line only if it's relevant to the data
                    if (isWaterLevel && includeWarningInScale) {
                        // Add a horizontal line dataset for the warning threshold
                        chartConfig.data.datasets.push({
                            label: 'Warning Threshold',
                            data: Array(labels.length).fill(waterLevelWarning),
                            borderColor: 'rgba(255, 0, 0, 0.5)',
                            borderWidth: 2,
                            borderDash: [5, 5],
                            fill: false,
                            pointRadius: 0
                        });
                    }

                    // Create and return the chart
                    return new Chart(ctx, chartConfig);
                }
                
                // Date range control functions
                function updateMonthRange(days) {
                    const date = new Date();
                    date.setDate(date.getDate() + days);
                    document.getElementById('month_start').value = date.toISOString().split('T')[0];
                    document.getElementById('month_start').form.submit();
                }
                
                function resetMonthRange() {
                    document.getElementById('month_start').value = '';
                    document.getElementById('month_start').form.submit();
                }
                
                // Initialize export data
                function initExportData(dataType, period, data, unit) {
                    // Initialize the exportData object structure if it doesn't exist yet
                    if (!exportData[period + dataType]) {
                        exportData[period + dataType] = {
                            data: data,
                            unit: unit
                        };
                    } else {
                        // Update existing data
                        exportData[period + dataType].data = data;
                        exportData[period + dataType].unit = unit;
                    }
                    
                    // Log successful initialization for debugging
                    console.log(`Initialized ${period} ${dataType} data with ${data.length} readings`);
                }


                // Main export function
                function exportToCSV(dataType, period) {
                    try {
                        // Get the appropriate data
                        const key = period + dataType;
                        
                        // Check if data exists
                        if (!exportData[key] || !exportData[key].data) {
                            console.error(`Error: No data found for ${key}`);
                            alert(`Export failed: No data available for ${dataType} (${period})`);
                            return;
                        }
                        
                        const readings = exportData[key].data;
                        const unitName = exportData[key].unit;
                        
                        // Validate data
                        if (!Array.isArray(readings) || readings.length === 0) {
                            console.error(`Error: Invalid or empty data for export: ${key}`);
                            alert(`Export failed: No valid data available for ${dataType} (${period})`);
                            return;
                        }
                        
                        // Create filename
                        const filename = `${dataType}_${period}.csv`;
                        
                        // Create CSV content
                        let csvContent = `Timestamp,Value (${unitName})\n`;
                        
                        // Sort readings by timestamp (oldest first)
                        const sortedReadings = [...readings].sort((a, b) => a.timestamp - b.timestamp);
                        
                        // Add data rows
                        sortedReadings.forEach(reading => {
                            const date = new Date(reading.timestamp * 1000);
                            const timestamp = date.toISOString().replace('T', ' ').substring(0, 19);
                            const value = parseFloat(reading.value).toFixed(2);
                            csvContent += `${timestamp},${value}\n`;
                        });
                        
                        // Create a download link
                        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                        const link = document.createElement('a');
                        const url = URL.createObjectURL(blob);
                        
                        link.setAttribute('href', url);
                        link.setAttribute('download', filename);
                        link.style.visibility = 'hidden';
                        
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                        
                        // Clean up
                        setTimeout(() => {
                            URL.revokeObjectURL(url);
                        }, 100);
                        
                        console.log(`Successfully exported ${sortedReadings.length} readings to ${filename}`);
                        
                    } catch (error) {
                        console.error("CSV Export error:", error);
                        alert("Export failed: " + error.message);
                    }
                }

                // Initialize export data
                <?php if (isset($availableParams['depth']) && isset($availableParams['depth']['readings'])): ?>
                    initExportData('Depth', 'monthly', <?php echo json_encode($depthReadings); ?>, '<?php echo htmlspecialchars($availableParams['depth']['unit']); ?>');
                <?php endif; ?>

                <?php if (isset($availableParams['temperature']) && isset($availableParams['temperature']['readings'])): ?>
                    initExportData('Temperature', 'monthly', <?php echo json_encode($tempReadings); ?>, '<?php echo htmlspecialchars($availableParams['temperature']['unit']); ?>');
                <?php endif; ?>
            </script>
        <?php endif; ?>
        
        <footer>
            <div class="container">
                <p>Kentucky Geological Survey | Data provided by HydroVu API</p>
                <p>Last updated: <?php echo date('Y-m-d H:i:s'); ?></p>
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
</body>
</html>