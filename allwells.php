<?php

// Load API credentials (not in Git)
require_once __DIR__ . '/credentials.php';

// API Endpoints
$friendlynames_endpoint = $base_url . '/sispec/friendlynames';
$locations_endpoint = $base_url . '/locations/list';

// Default to last 30 days
$default_days = 30;

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Debug mode toggle
$debug_mode = isset($_GET['debug']) && $_GET['debug'] === '1';

// Function to debug curl commands
function debugCurl($url, $headers, $postFields = null) {
    $curlCommand = 'curl -X ' . ($postFields ? 'POST' : 'GET') . ' "' . $url . '"';
    
    // Add headers
    foreach ($headers as $header) {
        $curlCommand .= ' -H "' . $header . '"';
    }
    
    // Add post data if this is a POST request
    if ($postFields) {
        $curlCommand .= ' -d "' . $postFields . '"';
    }
    
    return $curlCommand;
}

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
    
    // Debug the curl command if requested
    if ($debug) {
        echo '<div class="alert alert-secondary"><strong>Debug - OAuth cURL Command:</strong><br>';
        echo '<code>' . debugCurl($token_url, $headers, $postFields) . '</code>';
        echo '</div>';
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Disable SSL verification - use with caution
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        echo 'OAuth Error: ' . curl_error($ch);
        return false;
    }
    
    curl_close($ch);
    $data = json_decode($response, true);
    
    if (isset($data['access_token'])) {
        return $data['access_token'];
    } else {
        echo 'Failed to get access token. Response: ' . print_r($data, true);
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
    
    // Debug the curl command if requested
    if ($debug) {
        echo '<div class="alert alert-secondary"><strong>Debug - cURL Command:</strong><br>';
        echo '<code>' . debugCurl($url, $headers) . '</code>';
        echo '</div>';
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
    
    // Debug response headers if requested
    if ($debug) {
        echo '<div class="alert alert-secondary"><strong>Debug - Response Headers:</strong><br>';
        echo '<pre>' . htmlspecialchars($header_text) . '</pre>';
        echo '</div>';
    }
    
    // Get next page token from headers - improved parsing with more robust checks
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
    
    if ($debug && $nextPage) {
        echo '<div class="alert alert-info">Found next page token: ' . htmlspecialchars(substr($nextPage, 0, 20)) . '...</div>';
    }
    
    curl_close($ch);
    
    $data = json_decode($body, true);
    
    // If data is null but body exists, there might be a JSON parsing issue
    if (is_null($data) && !empty($body)) {
        if ($debug) {
            echo '<div class="alert alert-warning">Failed to parse JSON response. Response body:</div>';
            echo '<pre>' . htmlspecialchars(substr($body, 0, 1000)) . '...</pre>';
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
        
        // Add pagination info to debug
        $debug_info['pages_fetched'] = $currentPage;
    }
    
    if ($currentPage >= $maxPages && $nextPageToken) {
        $debug_info['max_pages_reached'] = true;
        $debug_info['more_data_available'] = true;
        
        if ($debug) {
            echo '<div class="alert alert-warning">Reached maximum page limit (' . $maxPages . '). There is more data available.</div>';
        }
    } else {
        if ($debug) {
            echo '<div class="alert alert-success">Retrieved all available data: ' . $currentPage . ' pages with ' . $totalReadings . ' total readings.</div>';
        }
    }
    
    $debug_info['total_readings'] = $totalReadings;
    $debug_info['total_pages'] = $currentPage;
    
    $combinedData['debug'] = $debug_info;
    return $combinedData;
}

// Get OAuth token first
$access_token = getOAuthToken($client_id, $client_secret, $token_url, $debug_mode);

// Initialize variables
$parameters = [];
$units = [];
$locations = [];

if (!$access_token) {
    echo '<div class="alert alert-danger">Authentication failed. Please check your client credentials.</div>';
} else {
    // Get friendly names for parameters and units
    $friendlynames = makeApiRequest($friendlynames_endpoint, $access_token, null, $debug_mode);
    $parameters = isset($friendlynames['parameters']) && is_array($friendlynames['parameters']) ? $friendlynames['parameters'] : [];
    $units = isset($friendlynames['units']) && is_array($friendlynames['units']) ? $friendlynames['units'] : [];

    // Get all locations with pagination support
    function getAllLocations($endpoint, $token, $debug = false, $maxPages = 20) {
        // Get the first page
        $firstPageData = makeApiRequest($endpoint, $token, null, $debug);
        
        if (!$firstPageData || !is_array($firstPageData)) {
            if ($debug) {
                echo '<div class="alert alert-danger">Error: Failed to retrieve locations.</div>';
                if ($firstPageData) {
                    echo '<pre>' . htmlspecialchars(print_r($firstPageData, true)) . '</pre>';
                }
            }
            return [];
        }
        
        // Check if we have more pages and need to paginate
        $currentPage = 1;
        $allLocations = $firstPageData;
        $nextPageToken = isset($firstPageData['_next_page']) ? $firstPageData['_next_page'] : null;
        
        if ($debug) {
            echo '<div class="alert alert-info">Locations - Page 1: Retrieved ' . count($firstPageData) . ' locations.</div>';
            
            // Explicit check for next page
            if ($nextPageToken) {
                echo '<div class="alert alert-warning">Next page token detected for locations. Will fetch more pages.</div>';
            }
        }
        
        // Fetch additional pages as long as there are more and we haven't hit the max
        while ($nextPageToken && $currentPage < $maxPages) {
            if ($debug) {
                echo '<div class="alert alert-secondary">Fetching locations page ' . ($currentPage + 1) . '...</div>';
            }
            
            // Add a small delay to avoid rate limiting
            usleep(250000); // 0.25 seconds
            
            $nextPageData = makeApiRequest($endpoint, $token, $nextPageToken, $debug);
            
            if (!$nextPageData || !is_array($nextPageData)) {
                // If there's an error with pagination, we still return what we have
                if ($debug) {
                    echo '<div class="alert alert-warning">Failed to retrieve locations page ' . ($currentPage + 1) . '. Using what we have so far.</div>';
                }
                break;
            }
            
            // Remove the internal _next_page token for the next page data
            $nextPageLocations = $nextPageData;
            if (isset($nextPageLocations['_next_page'])) {
                unset($nextPageLocations['_next_page']);
            }
            
            // Merge locations, handling both array and object formats
            if (is_array($nextPageLocations)) {
                // If they're simple arrays, just merge them
                if (isset($nextPageLocations[0])) {
                    $allLocations = array_merge($allLocations, $nextPageLocations);
                }
                // Otherwise, they might be objects with specific keys
                else {
                    foreach ($nextPageLocations as $key => $location) {
                        if ($key !== '_next_page') {
                            $allLocations[$key] = $location;
                        }
                    }
                }
            }
            
            $currentPage++;
            $nextPageToken = isset($nextPageData['_next_page']) ? $nextPageData['_next_page'] : null;
            
            if ($debug) {
                echo '<div class="alert alert-info">Locations - Page ' . $currentPage . ': Retrieved ' . count($nextPageLocations) . ' more locations. Total so far: ' . count($allLocations) . '</div>';
            }
        }
        
        if ($currentPage >= $maxPages && $nextPageToken && $debug) {
            echo '<div class="alert alert-warning">Reached maximum page limit for locations (' . $maxPages . '). There may be more locations available.</div>';
        }
        
        return $allLocations;
    }
    
    // Get all locations with pagination
    $locations = getAllLocations($locations_endpoint, $access_token, $debug_mode);
}

// Sort locations if possible
// Sort and filter locations if possible
if (is_array($locations) && !empty($locations)) {
    // First filter out unwanted locations
    $locations = array_filter($locations, function($location) {
        // Skip locations if not an array or missing name
        if (!is_array($location) || !isset($location['name'])) {
            return false;
        }
        
        $name = $location['name'];
        
        // Skip locations with "default-" or "Location" in the name
        if (stripos($name, 'default-') !== false || stripos($name, 'Location') !== false) {
            return false;
        }
        
        return true;
    });
    
    // Then sort the remaining locations
    usort($locations, function($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HydroVu Water Well Data</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .location-card {
            margin-bottom: 20px;
        }
        .chart-container {
            height: 300px;
            margin-top: 20px;
            margin-bottom: 30px;
        }
        .loading {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 0.2em solid currentColor;
            border-radius: 50%;
            border-right-color: transparent;
            animation: spinner-border .75s linear infinite;
        }
        /* Improved pagination styling */
        .pagination-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            margin-bottom: 20px;
        }
        .pagination-nav {
            width: 100%;
            overflow-x: auto;
            padding: 10px 0;
        }
        .pagination {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
        }
        .page-item {
            margin: 2px;
        }
        .page-link {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        .date-selector {
            margin-top: 10px;
        }
        /* Data table styles */
        .table-responsive {
            margin-top: 20px;
        }
        /* Style for pagination controls */
        .pagination-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .pagination-info {
            font-size: 0.9rem;
        }
        .pagination-size-selector {
            width: auto;
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1 class="mb-4">HydroVu Water Well Data</h1>
        
        <?php if (empty($locations)): ?>
            <div class="alert alert-warning">No locations found. Please check your API token.</div>
        <?php else: ?>
            <!-- Location and data range selector -->
            <form method="get" class="mb-4">
                <div class="row">
                    <div class="col-md-4">
                        <label for="location" class="form-label">Select a location:</label>
                        <select name="location" id="location" class="form-select" required>
                            <option value="">Select a location</option>
                            <?php foreach ($locations as $location): ?>
                                <?php if (is_array($location) && isset($location['id']) && isset($location['name'])): ?>
                                <option value="<?php echo htmlspecialchars($location['id']); ?>" 
                                    <?php echo isset($_GET['location']) && $_GET['location'] == $location['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($location['name']); ?>
                                </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="dateRange" class="form-label">Date range:</label>
                        <select name="dateRange" id="dateRange" class="form-select">
                            <option value="preset" <?php echo (!isset($_GET['dateRange']) || $_GET['dateRange'] == 'preset') ? 'selected' : ''; ?>>Preset</option>
                            <option value="custom" <?php echo (isset($_GET['dateRange']) && $_GET['dateRange'] == 'custom') ? 'selected' : ''; ?>>Custom dates</option>
                        </select>
                    </div>
                    <div class="col-md-3" id="presetSelector">
                        <label for="presetDays" class="form-label">Period:</label>
                        <select name="presetDays" id="presetDays" class="form-select">
                            <option value="7" <?php echo (isset($_GET['presetDays']) && $_GET['presetDays'] == '7') ? 'selected' : ''; ?>>Last 7 days</option>
                            <option value="30" <?php echo (!isset($_GET['presetDays']) || $_GET['presetDays'] == '30') ? 'selected' : ''; ?>>Last 30 days</option>
                            <option value="90" <?php echo (isset($_GET['presetDays']) && $_GET['presetDays'] == '90') ? 'selected' : ''; ?>>Last 90 days</option>
                            <option value="180" <?php echo (isset($_GET['presetDays']) && $_GET['presetDays'] == '180') ? 'selected' : ''; ?>>Last 180 days</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" value="1" id="debug" name="debug" <?php echo $debug_mode ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="debug">
                                Show Debug Info
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- Custom date range selector (hidden by default) -->
                <div class="row date-selector d-none" id="customDateSelector">
                    <div class="col-md-4">
                        <label for="startDate" class="form-label">Start date:</label>
                        <input type="date" name="startDate" id="startDate" class="form-control" 
                               value="<?php echo isset($_GET['startDate']) ? $_GET['startDate'] : date('Y-m-d', strtotime('-30 days')); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="endDate" class="form-label">End date:</label>
                        <input type="date" name="endDate" id="endDate" class="form-control"
                               value="<?php echo isset($_GET['endDate']) ? $_GET['endDate'] : date('Y-m-d'); ?>">
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary" id="loadButton">
                            <span id="loadingIndicator" class="loading d-none"></span>
                            Load Data
                        </button>
                    </div>
                </div>
            </form>
            
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Date range selectors
                    const dateRangeSelect = document.getElementById('dateRange');
                    const presetSelector = document.getElementById('presetSelector');
                    const customDateSelector = document.getElementById('customDateSelector');
                    
                    function updateDateSelectors() {
                        if (dateRangeSelect.value === 'custom') {
                            presetSelector.classList.add('d-none');
                            customDateSelector.classList.remove('d-none');
                        } else {
                            presetSelector.classList.remove('d-none');
                            customDateSelector.classList.add('d-none');
                        }
                    }
                    
                    // Initialize date selectors
                    updateDateSelectors();
                    
                    // Update when date range selection changes
                    dateRangeSelect.addEventListener('change', updateDateSelectors);
                    
                    // Show loading indicator when form is submitted
                    document.getElementById('loadButton').addEventListener('click', function() {
                        document.getElementById('loadingIndicator').classList.remove('d-none');
                        this.disabled = true;
                        this.form.submit();
                    });
                });
            </script>
            
            <?php
            // If a location is selected, display its data
            if (isset($_GET['location']) && !empty($_GET['location'])) {
                $locationId = $_GET['location'];
                
                // Determine the time range based on form input
                if (isset($_GET['dateRange']) && $_GET['dateRange'] == 'custom') {
                    // Custom date range
                    if (isset($_GET['startDate']) && !empty($_GET['startDate'])) {
                        $startTime = strtotime($_GET['startDate'] . ' 00:00:00');
                    } else {
                        $startTime = strtotime('-30 days');
                    }
                    
                    if (isset($_GET['endDate']) && !empty($_GET['endDate'])) {
                        $endTime = strtotime($_GET['endDate'] . ' 23:59:59');
                    } else {
                        $endTime = null;
                    }
                    
                    $dateRangeDescription = 'Custom: ' . date('Y-m-d', $startTime) . 
                                          (isset($endTime) ? ' to ' . date('Y-m-d', $endTime) : ' to now');
                } else {
                    // Preset range
                    $presetDays = isset($_GET['presetDays']) ? intval($_GET['presetDays']) : $default_days;
                    $startTime = time() - ($presetDays * 24 * 60 * 60);
                    $endTime = null;
                    $dateRangeDescription = 'Last ' . $presetDays . ' days';
                }
                
                // Fetch data with specified time range
                $locationData = getLocationData($locationId, $access_token, $base_url, $startTime, $endTime, 20, $debug_mode);
                
                // Find current location info with error handling
                $currentLocation = null;
                if (is_array($locations)) {
                    foreach ($locations as $location) {
                        if (is_array($location) && isset($location['id']) && $location['id'] == $locationId) {
                            $currentLocation = $location;
                            break;
                        }
                    }
                }
                
                // If we couldn't find the location, create a basic one with the ID
                if ($currentLocation === null) {
                    $currentLocation = [
                        'id' => $locationId,
                        'name' => 'Location ID: ' . $locationId,
                        'description' => 'Location details unavailable'
                    ];
                }
                
                // Check if we have a valid response with parameters
                $hasData = isset($locationData['parameters']) && 
                           is_array($locationData['parameters']) && 
                           !empty($locationData['parameters']);
                           
                // Check if we have an error
                $hasError = isset($locationData['error']);
                
                if ($hasData):
                    // Count total readings
                    $totalReadings = 0;
                    foreach ($locationData['parameters'] as $parameter) {
                        if (isset($parameter['readings'])) {
                            $totalReadings += count($parameter['readings']);
                        }
                    }
            ?>
                <div class="card location-card">
                    <div class="card-header">
                        <h3><?php echo htmlspecialchars($currentLocation['name']); ?></h3>
                        <?php if (!empty($currentLocation['description'])): ?>
                            <p class="text-muted"><?php echo htmlspecialchars($currentLocation['description']); ?></p>
                        <?php endif; ?>
                        <?php if (isset($currentLocation['gps'])): ?>
                            <p>
                                GPS: <?php echo $currentLocation['gps']['latitude']; ?>, 
                                <?php echo $currentLocation['gps']['longitude']; ?>
                            </p>
                        <?php endif; ?>
                        
                        <div class="alert alert-info">
                            <strong>Data summary:</strong> 
                            Showing <?php echo $totalReadings; ?> total readings 
                            for <?php echo $dateRangeDescription; ?>.
                            <?php if (isset($locationData['debug']['max_pages_reached'])): ?>
                                <br><strong>Note:</strong> There may be more data available. Some data may be truncated.
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <ul class="nav nav-tabs" id="myTab" role="tablist">
                            <?php 
                            $firstTab = true;
                            foreach ($locationData['parameters'] as $parameter): 
                                // Skip empty parameters
                                if (!isset($parameter['readings']) || empty($parameter['readings'])) {
                                    continue;
                                }
                                
                                $paramId = $parameter['parameterId'];
                                $paramName = isset($parameters[$paramId]) ? $parameters[$paramId] : 'Parameter ' . $paramId;
                                $unitId = $parameter['unitId'];
                                $unitName = isset($units[$unitId]) ? $units[$unitId] : '';
                                $tabId = 'param-' . $paramId;
                            ?>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link <?php echo $firstTab ? 'active' : ''; ?>" 
                                            id="<?php echo $tabId; ?>-tab" 
                                            data-bs-toggle="tab" 
                                            data-bs-target="#<?php echo $tabId; ?>-content" 
                                            type="button" 
                                            role="tab" 
                                            aria-controls="<?php echo $tabId; ?>-content" 
                                            aria-selected="<?php echo $firstTab ? 'true' : 'false'; ?>">
                                        <?php echo htmlspecialchars($paramName); ?>
                                        <span class="badge bg-secondary"><?php echo count($parameter['readings']); ?></span>
                                    </button>
                                </li>
                            <?php 
                                $firstTab = false;
                            endforeach; 
                            ?>
                        </ul>
                        
                        <div class="tab-content mt-3" id="myTabContent">
                            <?php 
                            $firstTab = true;
                            foreach ($locationData['parameters'] as $parameter): 
                                // Skip empty parameters
                                if (!isset($parameter['readings']) || empty($parameter['readings'])) {
                                    continue;
                                }
                                
                                $paramId = $parameter['parameterId'];
                                $paramName = isset($parameters[$paramId]) ? $parameters[$paramId] : 'Parameter ' . $paramId;
                                $unitId = $parameter['unitId'];
                                $unitName = isset($units[$unitId]) ? $units[$unitId] : '';
                                $tabId = 'param-' . $paramId;
                                
                                // Prepare chart data
                                $chartLabels = [];
                                $chartData = [];
                                
                                // Sort readings by timestamp (oldest first)
                                usort($parameter['readings'], function($a, $b) {
                                    return $a['timestamp'] - $b['timestamp'];
                                });
                                
                                // Down-sample data for charts if there are too many points
                                $maxPointsForChart = 500; // Maximum points to show in chart
                                $readings = $parameter['readings'];
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
                                        $chartData[] = $reading['value'];
                                    }
                                } else {
                                    // Use all readings for chart
                                    foreach ($readings as $reading) {
                                        $chartLabels[] = formatTimestamp($reading['timestamp']);
                                        $chartData[] = $reading['value'];
                                    }
                                }
                                
                                $chartLabelsJson = json_encode($chartLabels);
                                $chartDataJson = json_encode($chartData);
                                $canvasId = 'chart-' . $paramId;
                            ?>
                                <div class="tab-pane fade <?php echo $firstTab ? 'show active' : ''; ?>" 
                                     id="<?php echo $tabId; ?>-content" 
                                     role="tabpanel" 
                                     aria-labelledby="<?php echo $tabId; ?>-tab">
                                    
                                    <h4><?php echo htmlspecialchars($paramName); ?> <?php echo !empty($unitName) ? '(' . htmlspecialchars($unitName) . ')' : ''; ?></h4>
                                    
                                    <?php if (!empty($parameter['readings'])): ?>
                                        <!-- Display the most recent reading and summary info -->
                                        <?php 
                                            $latestReading = end($parameter['readings']); 
                                            $latestTimestamp = formatTimestamp($latestReading['timestamp']);
                                            $oldestReading = reset($parameter['readings']);
                                            $oldestTimestamp = formatTimestamp($oldestReading['timestamp']);
                                            
                                            // Calculate statistics
                                            $values = array_column($parameter['readings'], 'value');
                                            $min = min($values);
                                            $max = max($values);
                                            $avg = array_sum($values) / count($values);
                                        ?>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="card mb-3">
                                                    <div class="card-header bg-primary text-white">Latest Reading</div>
                                                    <div class="card-body">
                                                        <h5 class="card-title"><?php echo $latestReading['value']; ?> <?php echo htmlspecialchars($unitName); ?></h5>
                                                        <p class="card-text">Date: <?php echo $latestTimestamp; ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="card mb-3">
                                                    <div class="card-header bg-info text-white">Data Summary</div>
                                                    <div class="card-body">
                                                        <p class="mb-1">Data points: <?php echo count($parameter['readings']); ?></p>
                                                        <p class="mb-1">Date range: <?php echo $oldestTimestamp; ?> to <?php echo $latestTimestamp; ?></p>
                                                        <p class="mb-1">Min: <?php echo round($min, 4); ?> <?php echo htmlspecialchars($unitName); ?></p>
                                                        <p class="mb-1">Max: <?php echo round($max, 4); ?> <?php echo htmlspecialchars($unitName); ?></p>
                                                        <p class="mb-1">Avg: <?php echo round($avg, 4); ?> <?php echo htmlspecialchars($unitName); ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Chart for this parameter -->
                                        <div class="chart-container">
                                            <canvas id="<?php echo $canvasId; ?>"></canvas>
                                        </div>
                                        
                                        <script>
                                            document.addEventListener('DOMContentLoaded', function() {
                                                const ctx = document.getElementById('<?php echo $canvasId; ?>').getContext('2d');
                                                new Chart(ctx, {
                                                    type: 'line',
                                                    data: {
                                                        labels: <?php echo $chartLabelsJson; ?>,
                                                        datasets: [{
                                                            label: '<?php echo addslashes($paramName); ?> <?php echo !empty($unitName) ? '(' . addslashes($unitName) . ')' : ''; ?>',
                                                            data: <?php echo $chartDataJson; ?>,
                                                            borderColor: 'rgba(54, 162, 235, 1)',
                                                            backgroundColor: 'rgba(54, 162, 235, 0.2)',
                                                            pointRadius: <?php echo count($chartData) > 100 ? 0 : 3; ?>,
                                                            tension: 0.1
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
                                                                    maxTicksLimit: 20
                                                                }
                                                            }
                                                        },
                                                        plugins: {
                                                            tooltip: {
                                                                mode: 'index',
                                                                intersect: false
                                                            }
                                                        }
                                                    }
                                                });
                                            });
                                        </script>
                                        
                                        <!-- Export data options -->
                                        <div class="mb-4">
                                            <h5>Export Data</h5>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportTableToCSV('<?php echo htmlspecialchars($currentLocation['name']); ?>_<?php echo htmlspecialchars($paramName); ?>.csv')">
                                                Export to CSV
                                            </button>
                                        </div>
                                        
                                        <!-- Improved table pagination controls -->
                                        <div class="pagination-controls">
                                            <div class="pagination-info">
                                                <span id="pagination-info-<?php echo $paramId; ?>">
                                                    Showing <span id="showing-start-<?php echo $paramId; ?>">1</span> to
                                                    <span id="showing-end-<?php echo $paramId; ?>">20</span> of
                                                    <?php echo count($parameter['readings']); ?> entries
                                                </span>
                                            </div>
                                            <div class="pagination-size">
                                                <label for="page-size-<?php echo $paramId; ?>" class="form-label me-2">Rows per page:</label>
                                                <select id="page-size-<?php echo $paramId; ?>" class="form-select form-select-sm pagination-size-selector" 
                                                        onchange="changePageSize(<?php echo $paramId; ?>, this.value)">
                                                    <option value="10">10</option>
                                                    <option value="20" selected>20</option>
                                                    <option value="50">50</option>
                                                    <option value="100">100</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <!-- Data table with improved pagination -->
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover" id="dataTable-<?php echo $paramId; ?>">
                                                <thead>
                                                    <tr>
                                                        <th>Timestamp</th>
                                                        <th>Value (<?php echo htmlspecialchars($unitName); ?>)</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach (array_reverse($parameter['readings']) as $reading): ?>
                                                        <tr>
                                                            <td><?php echo formatTimestamp($reading['timestamp']); ?></td>
                                                            <td><?php echo $reading['value']; ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        
                                        <!-- Improved pagination with page numbers and next/prev buttons -->
                                        <div class="pagination-container">
                                            <nav class="pagination-nav" aria-label="Data table pagination">
                                                <ul class="pagination pagination-sm" id="pagination-<?php echo $paramId; ?>">
                                                    <!-- Pagination will be added by JavaScript -->
                                                </ul>
                                            </nav>
                                        </div>
                                    <?php else: ?>
                                        <p class="alert alert-info">No readings available for this parameter.</p>
                                    <?php endif; ?>
                                </div>
                            <?php 
                                $firstTab = false;
                            endforeach; 
                            ?>
                        </div>
                    </div>
                </div>
                
                <?php 
                else: 
                    if (isset($_GET['location'])):
                        if ($hasError):
                ?>
                <div class="alert alert-danger">
                    <h4>Error retrieving data</h4>
                    <p><?php echo htmlspecialchars($locationData['error'] ?? 'Unknown error occurred.'); ?></p>
                    
                    <?php if (isset($locationData['debug'])): ?>
                    <div class="mt-3">
                        <p><strong>API Response:</strong></p>
                        <pre class="bg-light p-3"><?php echo htmlspecialchars(print_r($locationData['debug'], true)); ?></pre>
                    </div>
                    <?php endif; ?>
                </div>
                <?php 
                        else:
                ?>
                <div class="alert alert-info">
                    <h4>No data available</h4>
                    <p>No data available for the selected location in the specified time period. This might be because:</p>
                    <ul>
                        <li>The location has no readings recorded during this time</li>
                        <li>The location doesn't have any sensors configured</li>
                        <li>There might be permission issues accessing this location's data</li>
                    </ul>
                    <p>Try selecting a different location or time period.</p>
                </div>
                <?php 
                        endif;
                    endif;
                endif;
            } 
            ?>
        <?php endif; ?>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- CSV Export and Pagination Scripts -->
    <script>
        // Function to export table data to CSV
        function exportTableToCSV(filename) {
            // Find the currently active tab
            const activeTab = document.querySelector('.tab-pane.active');
            if (!activeTab) return;
            
            const table = activeTab.querySelector('table');
            if (!table) return;
            
            const rows = table.querySelectorAll('tr');
            const csv = [];
            
            for (let i = 0; i < rows.length; i++) {
                const row = [], cols = rows[i].querySelectorAll('td, th');
                
                for (let j = 0; j < cols.length; j++) {
                    // Clean the text and wrap in quotes
                    let data = cols[j].innerText.replace(/"/g, '""');
                    row.push('"' + data + '"');
                }
                
                csv.push(row.join(','));
            }
            
            // Download the CSV file
            const csvFile = new Blob([csv.join('\n')], {type: 'text/csv'});
            const downloadLink = document.createElement('a');
            
            downloadLink.download = filename;
            downloadLink.href = window.URL.createObjectURL(csvFile);
            downloadLink.style.display = 'none';
            
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
        }
        
        // Improved pagination system
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize pagination for all tables
            const tables = document.querySelectorAll('.table');
            tables.forEach(function(table) {
                const tableId = table.id;
                if (!tableId) return;
                
                const paramId = tableId.replace('dataTable-', '');
                initPagination(paramId);
            });
        });
        
        // Initialize pagination
        function initPagination(paramId) {
            const table = document.getElementById('dataTable-' + paramId);
            if (!table) return;
            
            const tbody = table.querySelector('tbody');
            if (!tbody) return;
            
            const rows = tbody.querySelectorAll('tr');
            if (rows.length === 0) return;
            
            // Default page size
            const pageSize = 20;
            paginateTable(paramId, pageSize, 0);
        }
        
        // Function to handle page size change
        function changePageSize(paramId, newSize) {
            paginateTable(paramId, parseInt(newSize), 0);
        }
        
        // Main pagination function
        function paginateTable(paramId, pageSize, currentPage) {
            const table = document.getElementById('dataTable-' + paramId);
            const pagination = document.getElementById('pagination-' + paramId);
            const infoStart = document.getElementById('showing-start-' + paramId);
            const infoEnd = document.getElementById('showing-end-' + paramId);
            
            if (!table || !pagination) return;
            
            const tbody = table.querySelector('tbody');
            const rows = tbody.querySelectorAll('tr');
            const totalRows = rows.length;
            
            // Calculate total pages
            const pageCount = Math.ceil(totalRows / pageSize);
            
            // Validate current page
            if (currentPage < 0) currentPage = 0;
            if (currentPage >= pageCount) currentPage = pageCount - 1;
            
            // Hide all rows
            rows.forEach(row => row.style.display = 'none');
            
            // Show rows for current page
            const start = currentPage * pageSize;
            const end = Math.min(start + pageSize, totalRows);
            
            for (let i = start; i < end; i++) {
                rows[i].style.display = '';
            }
            
            // Update pagination info
            if (infoStart && infoEnd) {
                infoStart.textContent = totalRows > 0 ? start + 1 : 0;
                infoEnd.textContent = end;
            }
            
            // Clear existing pagination
            pagination.innerHTML = '';
            
            // Don't show pagination if not needed
            if (pageCount <= 1) return;
            
            // Create pagination controls
            // Previous button
            addPaginationButton(pagination, '«', currentPage > 0, () => {
                paginateTable(paramId, pageSize, currentPage - 1);
            });
            
            // Determine which page numbers to show
            const maxVisiblePages = 5;
            let startPage, endPage;
            
            if (pageCount <= maxVisiblePages) {
                // Show all pages
                startPage = 0;
                endPage = pageCount - 1;
            } else {
                // Calculate start and end pages
                if (currentPage <= Math.floor(maxVisiblePages / 2)) {
                    startPage = 0;
                    endPage = maxVisiblePages - 1;
                } else if (currentPage >= pageCount - Math.floor(maxVisiblePages / 2) - 1) {
                    startPage = pageCount - maxVisiblePages;
                    endPage = pageCount - 1;
                } else {
                    startPage = currentPage - Math.floor(maxVisiblePages / 2);
                    endPage = currentPage + Math.floor(maxVisiblePages / 2);
                }
            }
            
            // First page button (if not showing first page)
            if (startPage > 0) {
                addPaginationButton(pagination, '1', true, () => {
                    paginateTable(paramId, pageSize, 0);
                });
                
                // Ellipsis if needed
                if (startPage > 1) {
                    addEllipsis(pagination);
                }
            }
            
            // Page numbers
            for (let i = startPage; i <= endPage; i++) {
                addPaginationButton(pagination, i + 1, true, () => {
                    paginateTable(paramId, pageSize, i);
                }, i === currentPage);
            }
            
            // Last page button (if not showing last page)
            if (endPage < pageCount - 1) {
                // Ellipsis if needed
                if (endPage < pageCount - 2) {
                    addEllipsis(pagination);
                }
                
                addPaginationButton(pagination, pageCount, true, () => {
                    paginateTable(paramId, pageSize, pageCount - 1);
                });
            }
            
            // Next button
            addPaginationButton(pagination, '»', currentPage < pageCount - 1, () => {
                paginateTable(paramId, pageSize, currentPage + 1);
            });
        }
        
        // Helper function to add pagination buttons
        function addPaginationButton(container, text, enabled, onClick, isActive = false) {
            const li = document.createElement('li');
            li.className = 'page-item' + (isActive ? ' active' : '') + (!enabled ? ' disabled' : '');
            
            const a = document.createElement('a');
            a.className = 'page-link';
            a.href = '#';
            a.innerHTML = text;
            
            if (enabled) {
                a.addEventListener('click', function(e) {
                    e.preventDefault();
                    onClick();
                });
            }
            
            li.appendChild(a);
            container.appendChild(li);
        }
        
        // Helper function to add ellipsis
        function addEllipsis(container) {
            const li = document.createElement('li');
            li.className = 'page-item disabled';
            
            const span = document.createElement('span');
            span.className = 'page-link';
            span.innerHTML = '&hellip;';
            
            li.appendChild(span);
            container.appendChild(li);
        }
    </script>
</body>
</html>