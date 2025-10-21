<?php
// Configuration
$client_id = 'KGSStream1'; // client ID
$client_secret = '93ccab41ad394e668295313b6e8e1ef1'; // client secret
$base_url = 'https://www.hydrovu.com/public-api/v1';
$token_url = 'https://hydrovu.com/public-api/oauth/token';
$well_location_id = '5515870852612096'; // Fixed well location ID

// API Endpoints
$friendlynames_endpoint = $base_url . '/sispec/friendlynames';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Debug mode toggle (hidden by default for public display)
$debug_mode = isset($_GET['debug']) && $_GET['debug'] === '1';

// Function to get OAuth2 token
function getOAuthToken($client_id, $client_secret, $token_url, $debug = false) {
    $postFields = http_build_query([
        'grant_type' => 'client_credentials',
        'client_id' => $client_id,
        'client_secret' => $client_secret
    ]);
    
    $headers = [
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Disable SSL verification - use with caution
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        if ($debug) echo 'OAuth Error: ' . curl_error($ch);
        return false;
    }
    
    curl_close($ch);
    $data = json_decode($response, true);
    
    if (isset($data['access_token'])) {
        return $data['access_token'];
    } else {
        if ($debug) echo 'Failed to get access token. Response: ' . print_r($data, true);
        return false;
    }
}

// Function to make API requests
function makeApiRequest($url, $token, $startPage = null, $debug = false) {
    $headers = [
        'accept: application/json',
        'authorization: Bearer ' . $token
    ];
    
    // Add pagination header if provided
    if ($startPage) {
        $headers[] = 'X-ISI-Start-Page: ' . $startPage;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Disable SSL verification - use with caution
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_HEADER, true); // Get headers in response
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        if ($debug) {
            echo '<div class="alert alert-danger">API Error: ' . curl_error($ch) . '</div>';
        }
        curl_close($ch);
        return false;
    }
    
    // Parse headers and body
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header_text = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    
    // Get next page token from headers
    $nextPage = null;
    $headerLines = explode("\n", $header_text);
    foreach ($headerLines as $header) {
        $header = trim($header);
        // Case-insensitive check for the header
        if (stripos($header, 'x-isi-next-page:') === 0) {
            $nextPage = trim(substr($header, 16)); // 16 is the length of 'x-isi-next-page:'
            break;
        }
        // Alternative format check (some servers format headers differently)
        else if (stripos($header, 'X-ISI-Next-Page:') === 0) {
            $nextPage = trim(substr($header, 16));
            break;
        }
    }
    
    curl_close($ch);
    
    $data = json_decode($body, true);
    
    // If data is null but body exists, there might be a JSON parsing issue
    if (is_null($data) && !empty($body)) {
        if ($debug) {
            echo '<div class="alert alert-warning">Failed to parse JSON response.</div>';
        }
        // Return empty array with error indicator
        return ['error' => 'Failed to parse JSON response', 'raw_response' => $body];
    }
    
    // Add next page token to the response
    if ($nextPage) {
        if (is_array($data)) {
            $data['_next_page'] = $nextPage;
        } else {
            // If data is not an array (e.g., null or other type), create an array
            $data = ['_next_page' => $nextPage];
        }
    }
    
    return $data;
}

// Function to format timestamp
function formatTimestamp($timestamp) {
    return date('Y-m-d H:i:s', $timestamp);
}

// Get data for a specific location with pagination support
function getLocationData($locationId, $token, $base_url, $startTime, $endTime = null, $maxPages = 20, $debug = false) {
    $url = $base_url . '/locations/' . $locationId . '/data?startTime=' . $startTime;
    
    // Add endTime if specified
    if ($endTime !== null) {
        $url .= '&endTime=' . $endTime;
    }
    
    // For debugging
    $debug_info = [
        'endpoint' => $url,
        'start_time' => date('Y-m-d H:i:s', $startTime),
        'end_time' => $endTime ? date('Y-m-d H:i:s', $endTime) : 'Not specified',
        'start_timestamp' => $startTime,
        'end_timestamp' => $endTime
    ];
    
    if ($debug) {
        echo '<div class="alert alert-info">Fetching data from: ' . date('Y-m-d H:i:s', $startTime);
        if ($endTime) {
            echo ' to ' . date('Y-m-d H:i:s', $endTime);
        }
        echo '</div>';
    }
    
    // Get the first page
    $firstPageData = makeApiRequest($url, $token, null, $debug);
    
    if (!$firstPageData || isset($firstPageData['error'])) {
        return isset($firstPageData['error']) 
            ? array_merge($firstPageData, ['debug' => $debug_info]) 
            : ['error' => 'Failed to retrieve data', 'debug' => $debug_info];
    }
    
    // Check if we have more pages and need to paginate
    $currentPage = 1;
    $combinedData = $firstPageData;
    $nextPageToken = isset($firstPageData['_next_page']) ? $firstPageData['_next_page'] : null;
    
    // Remove the internal _next_page token from returned data
    unset($combinedData['_next_page']);
    
    $totalReadings = 0;
    
    // Count initial readings
    if (isset($combinedData['parameters']) && is_array($combinedData['parameters'])) {
        foreach ($combinedData['parameters'] as $param) {
            if (isset($param['readings']) && is_array($param['readings'])) {
                $totalReadings += count($param['readings']);
            }
        }
    }
    
    if ($debug) {
        echo '<div class="alert alert-info">Page 1: Retrieved ' . $totalReadings . ' readings.</div>';
    }
    
    // Fetch additional pages as long as there are more and we haven't hit the max
    while ($nextPageToken && $currentPage < $maxPages) {
        if ($debug) {
            echo '<div class="alert alert-secondary">Fetching page ' . ($currentPage + 1) . '...</div>';
        }
        
        // Add a small delay to avoid rate limiting
        usleep(250000); // 0.25 seconds
        
        $nextPageData = makeApiRequest($url, $token, $nextPageToken, $debug);
        
        if (!$nextPageData || isset($nextPageData['error'])) {
            // If there's an error with pagination, we still return what we have
            $debug_info['pagination_error'] = isset($nextPageData['error']) 
                ? $nextPageData['error'] 
                : 'Failed to retrieve next page';
            $debug_info['pages_fetched'] = $currentPage;
            break;
        }
        
        $pageReadings = 0;
        
        // Merge the readings for each parameter
        if (isset($nextPageData['parameters']) && is_array($nextPageData['parameters'])) {
            foreach ($nextPageData['parameters'] as $paramKey => $paramData) {
                if (isset($paramData['readings']) && is_array($paramData['readings'])) {
                    $pageReadings += count($paramData['readings']);
                    
                    // Find matching parameter in combined data
                    foreach ($combinedData['parameters'] as $combinedParamKey => $combinedParam) {
                        if ($combinedParam['parameterId'] === $paramData['parameterId']) {
                            // Append the new readings
                            $combinedData['parameters'][$combinedParamKey]['readings'] = array_merge(
                                $combinedData['parameters'][$combinedParamKey]['readings'],
                                $paramData['readings']
                            );
                            break;
                        }
                    }
                }
            }
        }
        
        $totalReadings += $pageReadings;
        $currentPage++;
        $nextPageToken = isset($nextPageData['_next_page']) ? $nextPageData['_next_page'] : null;
        
        if ($debug) {
            echo '<div class="alert alert-info">Page ' . $currentPage . ': Retrieved ' . $pageReadings . ' more readings. Total so far: ' . $totalReadings . '</div>';
        }
    }
    
    if ($currentPage >= $maxPages && $nextPageToken) {
        $debug_info['max_pages_reached'] = true;
        $debug_info['more_data_available'] = true;
        
        if ($debug) {
            echo '<div class="alert alert-warning">Reached maximum page limit (' . $maxPages . '). There is more data available.</div>';
        }
    }
    
    $debug_info['total_readings'] = $totalReadings;
    $debug_info['total_pages'] = $currentPage;
    
    $combinedData['debug'] = $debug_info;
    return $combinedData;
}

// Function to get location details
function getLocationDetails($locationId, $token, $base_url, $debug = false) {
    $url = $base_url . '/locations/' . $locationId;
    
    $locationData = makeApiRequest($url, $token, null, $debug);
    
    if (!$locationData || isset($locationData['error'])) {
        return false;
    }
    
    return $locationData;
}

// Get OAuth token first
$access_token = getOAuthToken($client_id, $client_secret, $token_url, $debug_mode);

// Initialize variables
$parameters = [];
$units = [];
$location_details = null;

if (!$access_token) {
    echo '<div class="alert alert-danger">Authentication failed. Please check your client credentials.</div>';
} else {
    // Get friendly names for parameters and units
    $friendlynames = makeApiRequest($friendlynames_endpoint, $access_token, null, $debug_mode);
    $parameters = isset($friendlynames['parameters']) && is_array($friendlynames['parameters']) ? $friendlynames['parameters'] : [];
    $units = isset($friendlynames['units']) && is_array($friendlynames['units']) ? $friendlynames['units'] : [];
    
    // Get location details
    $location_details = getLocationDetails($well_location_id, $access_token, $base_url, $debug_mode);
}

// Time ranges for the three views (month, week, day)
$current_time = time();
$month_start = $current_time - (30 * 24 * 60 * 60);
$week_start = $current_time - (7 * 24 * 60 * 60);
$day_start = $current_time - (24 * 60 * 60);

// Allow custom date ranges via URL parameters
$custom_month_start = isset($_GET['month_start']) ? strtotime($_GET['month_start']) : $month_start;
$custom_week_start = isset($_GET['week_start']) ? strtotime($_GET['week_start']) : $week_start;
$custom_day_start = isset($_GET['day_start']) ? strtotime($_GET['day_start']) : $day_start;

// Get data for all three time periods
$month_data = getLocationData($well_location_id, $access_token, $base_url, $custom_month_start, $current_time, 20, $debug_mode);
$week_data = getLocationData($well_location_id, $access_token, $base_url, $custom_week_start, $current_time, 20, $debug_mode);
$day_data = getLocationData($well_location_id, $access_token, $base_url, $custom_day_start, $current_time, 20, $debug_mode);

// Parameter ID to unit name mapping (based on your description)
$paramUnits = [
    'depth' => 'ft',
    'temperature' => '°C',
    'pressure' => 'PSI'
];

// Parameter ID to display name mapping
$paramNames = [
    'depth' => 'Depth',
    'temperature' => 'Temperature',
    'pressure' => 'Pressure'
];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Water Well Data - <?php echo htmlspecialchars($location_details['name'] ?? 'Well #' . $well_location_id); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .chart-container {
            height: 300px;
            margin-top: 20px;
            margin-bottom: 30px;
        }
        .card {
            margin-bottom: 25px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .card-header {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .data-summary {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
        }
        .summary-item {
            flex: 1;
            min-width: 120px;
            padding: 10px;
            text-align: center;
            border-right: 1px solid #eee;
        }
        .summary-item:last-child {
            border-right: none;
        }
        .summary-label {
            font-size: 0.9rem;
            color: #666;
        }
        .summary-value {
            font-size: 1.2rem;
            font-weight: bold;
        }
        .latest-reading {
            background-color: #e9f7fe;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .latest-value {
            font-size: 1.8rem;
            color: #0d6efd;
            font-weight: bold;
        }
        .date-controls {
            font-size: 0.85rem;
            padding: 10px 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
            margin-top: 5px;
        }
        .section-title {
            margin: 30px 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        .export-button {
            font-size: 0.85rem;
        }
        footer {
            margin-top: 50px;
            padding: 20px 0;
            background-color: #f8f9fa;
            text-align: center;
            font-size: 0.9rem;
            color: #666;
        }
        .card-title-with-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .time-control {
            font-size: 0.8rem;
            opacity: 0.7;
            transition: opacity 0.3s;
        }
        .time-control:hover {
            opacity: 1;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1 class="mb-4">
            <?php if ($location_details && isset($location_details['name'])): ?>
                <?php echo htmlspecialchars($location_details['name']); ?>
            <?php else: ?>
                Water Well #<?php echo $well_location_id; ?>
            <?php endif; ?>
        </h1>
        
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
        
        <?php if (!$access_token): ?>
            <div class="alert alert-danger">Authentication failed. Please check your API credentials.</div>
        <?php elseif (!$month_data || isset($month_data['error'])): ?>
            <div class="alert alert-danger">
                <h4>Error retrieving data</h4>
                <p><?php echo htmlspecialchars($month_data['error'] ?? 'Failed to retrieve well data.'); ?></p>
            </div>
        <?php else: ?>
            
            <!-- Latest readings section -->
            <div class="row mt-4">
                <?php 
                // Determine available parameters from the month data
                $availableParams = [];
                if (isset($month_data['parameters']) && is_array($month_data['parameters'])) {
                    foreach ($month_data['parameters'] as $param) {
                        if (isset($param['readings']) && !empty($param['readings'])) {
                            $paramId = $param['parameterId'];
                            $paramKey = '';
                            
                            // Attempt to identify parameter type by name or ID
                            foreach ($parameters as $key => $name) {
                                if ($key == $paramId) {
                                    $paramName = $name;
                                    // Try to determine parameter type (depth, temp, pressure)
                                    if (stripos($paramName, 'depth') !== false || stripos($paramName, 'level') !== false) {
                                        $paramKey = 'depth';
                                    } elseif (stripos($paramName, 'temp') !== false) {
                                        $paramKey = 'temperature';
                                    } elseif (stripos($paramName, 'pressure') !== false || stripos($paramName, 'psi') !== false) {
                                        $paramKey = 'pressure';
                                    } else {
                                        $paramKey = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $paramName));
                                    }
                                    break;
                                }
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
                            $availableParams[$paramKey] = [
                                'id' => $paramId,
                                'name' => $displayName,
                                'unit' => $unitName,
                                'readings' => $param['readings']
                            ];
                        }
                    }
                }
                
                // Display latest readings for each parameter
                foreach ($availableParams as $paramKey => $paramInfo):
                    // Get the latest reading
                    $readings = $paramInfo['readings'];
                    usort($readings, function($a, $b) {
                        return $b['timestamp'] - $a['timestamp']; // Sort by timestamp, newest first
                    });
                    $latestReading = $readings[0];
                    
                    // Format the value to 2 decimal places
                    $formattedValue = number_format($latestReading['value'], 2);
                ?>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header"><?php echo htmlspecialchars($paramInfo['name']); ?></div>
                        <div class="card-body">
                            <div class="latest-reading">
                                <div class="latest-value">
                                    <?php echo $formattedValue; ?> <?php echo htmlspecialchars($paramInfo['unit']); ?>
                                </div>
                                <div class="text-muted">
                                    as of <?php echo formatTimestamp($latestReading['timestamp']); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Monthly data section -->
            <h2 class="section-title">Monthly Data (Last 30 Days)</h2>
            
            <?php foreach ($availableParams as $paramKey => $paramInfo): ?>
                <div class="card">
                    <div class="card-header">
                        <div class="card-title-with-controls">
                            <span><?php echo htmlspecialchars($paramInfo['name']); ?> - Monthly Trend</span>
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
                        // Find the parameter data in the month dataset
                        $paramData = null;
                        foreach ($month_data['parameters'] as $param) {
                            if ($param['parameterId'] == $paramInfo['id']) {
                                $paramData = $param;
                                break;
                            }
                        }
                        
                        if ($paramData && !empty($paramData['readings'])):
                            // Prepare chart data
                            $readings = $paramData['readings'];
                            
                            // Sort readings by timestamp (oldest first)
                            usort($readings, function($a, $b) {
                                return $a['timestamp'] - $b['timestamp'];
                            });
                            
                            // Prepare chart data
                            $chartLabels = [];
                            $chartData = [];
                            
                            // Down-sample data for charts if there are too many points
                            $maxPointsForChart = 100; // Reduced for cleaner display
                            $readingCount = count($readings);
                            
                            if ($readingCount > $maxPointsForChart) {
                                // Determine sampling interval
                                $interval = ceil($readingCount / $maxPointsForChart);
                                
                                // Sample data points
                                $sampledReadings = [];
                                for ($i = 0; $i < $readingCount; $i += $interval) {
                                    $sampledReadings[] = $readings[$i];
                                }
                                
                                // Ensure we always include the first and last reading
                                if (!in_array($readings[0], $sampledReadings)) {
                                    array_unshift($sampledReadings, $readings[0]);
                                }
                                if (!in_array($readings[$readingCount - 1], $sampledReadings)) {
                                    $sampledReadings[] = $readings[$readingCount - 1];
                                }
                                
                                // Use sampled readings for chart
                                foreach ($sampledReadings as $reading) {
                                    $chartLabels[] = formatTimestamp($reading['timestamp']);
                                    $chartData[] = number_format($reading['value'], 2, '.', '');
                                }
                            } else {
                                // Use all readings for chart
                                foreach ($readings as $reading) {
                                    $chartLabels[] = formatTimestamp($reading['timestamp']);
                                    $chartData[] = number_format($reading['value'], 2, '.', '');
                                }
                            }
                            
                            $chartLabelsJson = json_encode($chartLabels);
                            $chartDataJson = json_encode($chartData);
                            $canvasId = 'month-chart-' . $paramKey;
                            
                            // Calculate statistics
                            $values = array_column($readings, 'value');
                            $min = min($values);
                            $max = max($values);
                            $avg = array_sum($values) / count($values);
                        ?>
                            <div class="data-summary mb-3">
                                <div class="summary-item">
                                    <div class="summary-label">Min</div>
                                    <div class="summary-value"><?php echo number_format($min, 2); ?> <?php echo htmlspecialchars($paramInfo['unit']); ?></div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-label">Max</div>
                                    <div class="summary-value"><?php echo number_format($max, 2); ?> <?php echo htmlspecialchars($paramInfo['unit']); ?></div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-label">Average</div>
                                    <div class="summary-value"><?php echo number_format($avg, 2); ?> <?php echo htmlspecialchars($paramInfo['unit']); ?></div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-label">Readings</div>
                                    <div class="summary-value"><?php echo count($readings); ?></div>
                                </div>
                            </div>
                            
                            <div class="chart-container">
                                <canvas id="<?php echo $canvasId; ?>"></canvas>
                            </div>
                            
                            <div class="text-end">
                                <button class="btn btn-sm btn-outline-primary export-button" 
                                        onclick="exportToCSV('<?php echo htmlspecialchars($paramInfo['name']); ?>_monthly.csv', <?php echo json_encode($readings); ?>, '<?php echo htmlspecialchars($paramInfo['unit']); ?>')">
                                    <i class="bi bi-download"></i> Export Data
                                </button>
                            </div>
                            
                            <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    const ctx = document.getElementById('<?php echo $canvasId; ?>').getContext('2d');
                                    new Chart(ctx, {
                                        type: 'line',
                                        data: {
                                            labels: <?php echo $chartLabelsJson; ?>,
                                            datasets: [{
                                                label: '<?php echo addslashes($paramInfo['name']); ?> (<?php echo addslashes($paramInfo['unit']); ?>)',
                                                data: <?php echo $chartDataJson; ?>,
                                                borderColor: 'rgba(54, 162, 235, 1)',
                                                backgroundColor: 'rgba(54, 162, 235, 0.1)',
                                                fill: true,
                                                pointRadius: <?php echo count($chartData) > 50 ? 0 : 2; ?>,
                                                tension: 0.2
                                            }]
                                        },
                                        options: {
                                            responsive: true,
                                            maintainAspectRatio: false,
                                            scales: {
                                                x: {
                                                    ticks: {
                                                        maxRotation: 45,
                                                        minRotation: 45,
                                                        autoSkip: true,
                                                        maxTicksLimit: 12
                                                    }
                                                },
                                                y: {
                                                    ticks: {
                                                        callback: function(value) {
                                                            return value.toFixed(2) + ' <?php echo addslashes($paramInfo['unit']); ?>';
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
                                    });
                                });
                            </script>
                        <?php else: ?>
                            <div class="alert alert-info">No monthly data available for <?php echo htmlspecialchars($paramInfo['name']); ?>.</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <!-- Weekly data section -->
            <h2 class="section-title">Weekly Data (Last 7 Days)</h2>
            
            <?php foreach ($availableParams as $paramKey => $paramInfo): ?>
                <div class="card">
                    <div class="card-header">
                        <div class="card-title-with-controls">
                            <span><?php echo htmlspecialchars($paramInfo['name']); ?> - Weekly Trend</span>
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
                        // Find the parameter data in the week dataset
                        $paramData = null;
                        foreach ($week_data['parameters'] as $param) {
                            if ($param['parameterId'] == $paramInfo['id']) {
                                $paramData = $param;
                                break;
                            }
                        }
                        
                        if ($paramData && !empty($paramData['readings'])):
                            // Prepare chart data
                            $readings = $paramData['readings'];
                            
                            // Sort readings by timestamp (oldest first)
                            usort($readings, function($a, $b) {
                                return $a['timestamp'] - $b['timestamp'];
                            });
                            
                            // Prepare chart data
                            $chartLabels = [];
                            $chartData = [];
                            
                            foreach ($readings as $reading) {
                                $chartLabels[] = formatTimestamp($reading['timestamp']);
                                $chartData[] = number_format($reading['value'], 2, '.', '');
                            }
                            
                            $chartLabelsJson = json_encode($chartLabels);
                            $chartDataJson = json_encode($chartData);
                            $canvasId = 'week-chart-' . $paramKey;
                            
                            // Calculate statistics
                            $values = array_column($readings, 'value');
                            $min = min($values);
                            $max = max($values);
                            $avg = array_sum($values) / count($values);
                        ?>
                            <div class="data-summary mb-3">
                                <div class="summary-item">
                                    <div class="summary-label">Min</div>
                                    <div class="summary-value"><?php echo number_format($min, 2); ?> <?php echo htmlspecialchars($paramInfo['unit']); ?></div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-label">Max</div>
                                    <div class="summary-value"><?php echo number_format($max, 2); ?> <?php echo htmlspecialchars($paramInfo['unit']); ?></div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-label">Average</div>
                                    <div class="summary-value"><?php echo number_format($avg, 2); ?> <?php echo htmlspecialchars($paramInfo['unit']); ?></div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-label">Readings</div>
                                    <div class="summary-value"><?php echo count($readings); ?></div>
                                </div>
                            </div>
                            
                            <div class="chart-container">
                                <canvas id="<?php echo $canvasId; ?>"></canvas>
                            </div>
                            
                            <div class="text-end">
                                <button class="btn btn-sm btn-outline-primary export-button" 
                                        onclick="exportToCSV('<?php echo htmlspecialchars($paramInfo['name']); ?>_weekly.csv', <?php echo json_encode($readings); ?>, '<?php echo htmlspecialchars($paramInfo['unit']); ?>')">
                                    <i class="bi bi-download"></i> Export Data
                                </button>
                            </div>
                            
                            <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    const ctx = document.getElementById('<?php echo $canvasId; ?>').getContext('2d');
                                    new Chart(ctx, {
                                        type: 'line',
                                        data: {
                                            labels: <?php echo $chartLabelsJson; ?>,
                                            datasets: [{
                                                label: '<?php echo addslashes($paramInfo['name']); ?> (<?php echo addslashes($paramInfo['unit']); ?>)',
                                                data: <?php echo $chartDataJson; ?>,
                                                borderColor: 'rgba(75, 192, 192, 1)',
                                                backgroundColor: 'rgba(75, 192, 192, 0.1)',
                                                fill: true,
                                                pointRadius: <?php echo count($chartData) > 50 ? 0 : 2; ?>,
                                                tension: 0.2
                                            }]
                                        },
                                        options: {
                                            responsive: true,
                                            maintainAspectRatio: false,
                                            scales: {
                                                x: {
                                                    ticks: {
                                                        maxRotation: 45,
                                                        minRotation: 45,
                                                        autoSkip: true,
                                                        maxTicksLimit: 12
                                                    }
                                                },
                                                y: {
                                                    ticks: {
                                                        callback: function(value) {
                                                            return value.toFixed(2) + ' <?php echo addslashes($paramInfo['unit']); ?>';
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
                                    });
                                });
                            </script>
                        <?php else: ?>
                            <div class="alert alert-info">No weekly data available for <?php echo htmlspecialchars($paramInfo['name']); ?>.</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <!-- Daily data section -->
            <h2 class="section-title">Daily Data (Last 24 Hours)</h2>
            
            <?php foreach ($availableParams as $paramKey => $paramInfo): ?>
                <div class="card">
                    <div class="card-header">
                        <div class="card-title-with-controls">
                            <span><?php echo htmlspecialchars($paramInfo['name']); ?> - Daily Trend</span>
                            <div class="time-control">
                                <form method="get" class="form-inline d-inline">
                                    <input type="hidden" name="day_start" id="day_start" value="">
                                    <button type="button" class="btn btn-sm btn-outline-secondary me-1" onclick="updateDayRange(-1)">« Previous</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetDayRange()">Reset</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php
                        // Find the parameter data in the day dataset
                        $paramData = null;
                        foreach ($day_data['parameters'] as $param) {
                            if ($param['parameterId'] == $paramInfo['id']) {
                                $paramData = $param;
                                break;
                            }
                        }
                        
                        if ($paramData && !empty($paramData['readings'])):
                            // Prepare chart data
                            $readings = $paramData['readings'];
                            
                            // Sort readings by timestamp (oldest first)
                            usort($readings, function($a, $b) {
                                return $a['timestamp'] - $b['timestamp'];
                            });
                            
                            // Prepare chart data
                            $chartLabels = [];
                            $chartData = [];
                            
                            foreach ($readings as $reading) {
                                $chartLabels[] = formatTimestamp($reading['timestamp']);
                                $chartData[] = number_format($reading['value'], 2, '.', '');
                            }
                            
                            $chartLabelsJson = json_encode($chartLabels);
                            $chartDataJson = json_encode($chartData);
                            $canvasId = 'day-chart-' . $paramKey;
                            
                            // Calculate statistics
                            $values = array_column($readings, 'value');
                            $min = min($values);
                            $max = max($values);
                            $avg = array_sum($values) / count($values);
                        ?>
                            <div class="data-summary mb-3">
                                <div class="summary-item">
                                    <div class="summary-label">Min</div>
                                    <div class="summary-value"><?php echo number_format($min, 2); ?> <?php echo htmlspecialchars($paramInfo['unit']); ?></div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-label">Max</div>
                                    <div class="summary-value"><?php echo number_format($max, 2); ?> <?php echo htmlspecialchars($paramInfo['unit']); ?></div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-label">Average</div>
                                    <div class="summary-value"><?php echo number_format($avg, 2); ?> <?php echo htmlspecialchars($paramInfo['unit']); ?></div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-label">Readings</div>
                                    <div class="summary-value"><?php echo count($readings); ?></div>
                                </div>
                            </div>
                            
                            <div class="chart-container">
                                <canvas id="<?php echo $canvasId; ?>"></canvas>
                            </div>
                            
                            <div class="text-end">
                                <button class="btn btn-sm btn-outline-primary export-button" 
                                        onclick="exportToCSV('<?php echo htmlspecialchars($paramInfo['name']); ?>_daily.csv', <?php echo json_encode($readings); ?>, '<?php echo htmlspecialchars($paramInfo['unit']); ?>')">
                                    <i class="bi bi-download"></i> Export Data
                                </button>
                            </div>
                            
                            <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    const ctx = document.getElementById('<?php echo $canvasId; ?>').getContext('2d');
                                    new Chart(ctx, {
                                        type: 'line',
                                        data: {
                                            labels: <?php echo $chartLabelsJson; ?>,
                                            datasets: [{
                                                label: '<?php echo addslashes($paramInfo['name']); ?> (<?php echo addslashes($paramInfo['unit']); ?>)',
                                                data: <?php echo $chartDataJson; ?>,
                                                borderColor: 'rgba(255, 99, 132, 1)',
                                                backgroundColor: 'rgba(255, 99, 132, 0.1)',
                                                fill: true,
                                                pointRadius: <?php echo count($chartData) > 50 ? 0 : 2; ?>,
                                                tension: 0.2
                                            }]
                                        },
                                        options: {
                                            responsive: true,
                                            maintainAspectRatio: false,
                                            scales: {
                                                x: {
                                                    ticks: {
                                                        maxRotation: 45,
                                                        minRotation: 45,
                                                        autoSkip: true,
                                                        maxTicksLimit: 12
                                                    }
                                                },
                                                y: {
                                                    ticks: {
                                                        callback: function(value) {
                                                            return value.toFixed(2) + ' <?php echo addslashes($paramInfo['unit']); ?>';
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
                                    });
                                });
                            </script>
                        <?php else: ?>
                            <div class="alert alert-info">No daily data available for <?php echo htmlspecialchars($paramInfo['name']); ?>.</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <!-- JavaScript for CSV Export and date range controls -->
            <script>
                // Function to export data to CSV
                function exportToCSV(filename, readings, unitName) {
                    // Create CSV content
                    let csvContent = 'Timestamp,Value (' + unitName + ')\n';
                    
                    // Sort readings by timestamp (oldest first)
                    readings.sort((a, b) => a.timestamp - b.timestamp);
                    
                    // Add data rows
                    readings.forEach(reading => {
                        const timestamp = new Date(reading.timestamp * 1000).toISOString().replace('T', ' ').substring(0, 19);
                        const value = parseFloat(reading.value).toFixed(2);
                        csvContent += timestamp + ',' + value + '\n';
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
                }
                
                // Functions for date range controls
                function updateMonthRange(days) {
                    const date = new Date();
                    date.setDate(date.getDate() + days);
                    document.getElementById('month_start').value = date.toISOString().split('T')[0];
                    document.getElementById('month_start').form.submit();
                }
                
                function updateWeekRange(days) {
                    const date = new Date();
                    date.setDate(date.getDate() + days);
                    document.getElementById('week_start').value = date.toISOString().split('T')[0];
                    document.getElementById('week_start').form.submit();
                }
                
                function updateDayRange(days) {
                    const date = new Date();
                    date.setDate(date.getDate() + days);
                    document.getElementById('day_start').value = date.toISOString().split('T')[0];
                    document.getElementById('day_start').form.submit();
                }
                
                function resetMonthRange() {
                    document.getElementById('month_start').value = '';
                    document.getElementById('month_start').form.submit();
                }
                
                function resetWeekRange() {
                    document.getElementById('week_start').value = '';
                    document.getElementById('week_start').form.submit();
                }
                
                function resetDayRange() {
                    document.getElementById('day_start').value = '';
                    document.getElementById('day_start').form.submit();
                }
            </script>
        <?php endif; ?>
        
        <footer>
            <div class="container">
                <p>Water Well Monitoring System | Data provided by HydroVu API</p>
                <p>Last updated: <?php echo date('Y-m-d H:i:s'); ?></p>
                <?php if ($debug_mode): ?>
                <p><a href="?debug=0">Disable Debug Mode</a></p>
                <?php else: ?>
                <p><small><a href="?debug=1" class="text-muted">Enable Debug Mode</a></small></p>
                <?php endif; ?>
            </div>
        </footer>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>