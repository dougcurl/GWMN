<?php
// Caching functions
// Caching functions with consistent paths
function getCachedData($cacheKey, $ttl = 900) {
    global $cache_enabled;
    if (!$cache_enabled) return false;
    
    // Use consistent cache directory path
    $cacheDir = __DIR__ . '/cache';  // Changed from '/../cache'
    $cacheFile = $cacheDir . '/' . md5($cacheKey) . '.cache';
    
    // Create cache directory if it doesn't exist
    if (!is_dir($cacheDir)) {
        if (!mkdir($cacheDir, 0755, true)) {
            return false; // Can't create cache directory
        }
    }
   
    // Check if cache file exists and is not expired
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $ttl)) {
        $data = file_get_contents($cacheFile);
        if ($data === false) return false;
        
        $unserializedData = unserialize($data);
        if ($unserializedData === false) return false;
        
        return $unserializedData;
    }
    
    return false;
}

function setCachedData($cacheKey, $data) {
    global $cache_enabled;
    if (!$cache_enabled) return false;
    
    // Use consistent cache directory path
    $cacheDir = __DIR__ . '/cache';  // Changed from '/../cache'
    $cacheFile = $cacheDir . '/' . md5($cacheKey) . '.cache';
    
    // Create cache directory if it doesn't exist
    if (!is_dir($cacheDir)) {
        if (!mkdir($cacheDir, 0755, true)) {
            return false; // Can't create cache directory
        }
    }
    
    return file_put_contents($cacheFile, serialize($data)) !== false;
}

// Function to get OAuth2 token with caching
function getOAuthToken($client_id, $client_secret, $token_url, $debug = false) {
    $cacheKey = "oauth_token_{$client_id}";
    $cachedToken = getCachedData($cacheKey, 3600); // Cache for 1 hour
    
    if ($cachedToken !== false) {
        return $cachedToken;
    }
    
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
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        if ($debug) echo 'OAuth Error: ' . curl_error($ch);
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);
    $data = json_decode($response, true);
    
    if (isset($data['access_token'])) {
        setCachedData($cacheKey, $data['access_token']);
        return $data['access_token'];
    } else {
        if ($debug) echo 'Failed to get access token. Response: ' . print_r($data, true);
        return false;
    }
}

// Function to make API requests with improved error handling
function makeApiRequest($url, $token, $startPage = null, $debug = false, $retries = 2) {
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
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($retries > 0) {
            if ($debug) {
                echo '<div class="alert alert-warning">Request failed: ' . $error . '. Retrying (' . $retries . ' left)...</div>';
            }
            // Wait before retry
            sleep(1);
            return makeApiRequest($url, $token, $startPage, $debug, $retries - 1);
        }
        
        if ($debug) {
            echo '<div class="alert alert-danger">API Error: ' . $error . '</div>';
        }
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
        if (stripos($header, 'x-isi-next-page:') === 0) {
            $nextPage = trim(substr($header, 16));
            break;
        }
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
        return ['error' => 'Failed to parse JSON response', 'raw_response' => $body];
    }
    
    // Add next page token to the response
    if ($nextPage) {
        if (is_array($data)) {
            $data['_next_page'] = $nextPage;
        } else {
            $data = ['_next_page' => $nextPage];
        }
    }
    
    return $data;
}

// Function to format timestamp
function formatTimestamp($timestamp) {
    // Format date as MM/DD/YY
    $dateStr = date('n/j/y', $timestamp);
    
    // Format time as HH:MM AM/PM
    $timeStr = date('g:i A', $timestamp);
    
    // Return formatted date and time
    return $dateStr . ' ' . $timeStr;
}

// Function to downsample data for visualization
function downsampleReadings($readings, $maxPoints = 100) {
    $count = count($readings);
    
    if ($count <= $maxPoints) {
        return $readings; // No need to downsample
    }
    
    $interval = ceil($count / $maxPoints);
    $sampledReadings = [];
    
    // Always include first reading
    $sampledReadings[] = $readings[0];
    
    for ($i = $interval; $i < $count - $interval; $i += $interval) {
        $sampledReadings[] = $readings[$i];
    }
    
    // Always include last reading
    $sampledReadings[] = $readings[$count - 1];
    
    return $sampledReadings;
}

// Get data for a specific location with pagination support - does not return lat/lon details
function getLocationData($locationId, $token, $base_url, $startTime, $endTime = null, $maxPages = 10, $debug = false) {

    // Round timestamps to 15-minute intervals for consistent cache keys
    // This ensures that requests within the same 15-minute window use the same cache
    $rounded_start = floor($startTime / 900) * 900; // 900 seconds = 15 minutes
    $rounded_end = $endTime ? floor($endTime / 900) * 900 : 'now';
    
    // Generate cache key using rounded timestamps
    $cacheKey = "location_{$locationId}_{$rounded_start}_{$rounded_end}";
    $cachedData = getCachedData($cacheKey);
    
    if ($cachedData !== false) {
        if ($debug) {
            // Fixed cache file path here
            $cacheFile = __DIR__ . '/cache/' . md5($cacheKey) . '.cache';
            if (file_exists($cacheFile)) {
                echo '<div class="alert alert-info">Using cached data from: ' . date('Y-m-d H:i:s', filemtime($cacheFile)) . 
                     ' (cache key: ' . substr(md5($cacheKey), 0, 8) . '...)</div>';
            }
        }
        return $cachedData;
    }
    
    $url = $base_url . '/locations/' . $locationId . '/data?startTime=' . $startTime;
    
    // Add endTime if specified
    if ($endTime !== null) {
        $url .= '&endTime=' . $endTime;
    }
    
    $debug_info = [
        'endpoint' => $url,
        'start_time' => date('Y-m-d H:i:s', $startTime),
        'end_time' => $endTime ? date('Y-m-d H:i:s', $endTime) : 'Not specified'
    ];
    

if ($debug) {
    echo '<div class="alert alert-info">';
    echo "API URL: " . $url . "<br>";
    echo "Fetching data from: " . date('Y-m-d H:i:s', $startTime);
    if ($endTime) {
        echo ' to ' . date('Y-m-d H:i:s', $endTime);
    }
    echo '</div>';
}
    
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
    
    $debug_info['total_readings'] = $totalReadings;
    $debug_info['total_pages'] = $currentPage;
    
    $combinedData['debug'] = $debug_info;
    
    // Cache the data
    setCachedData($cacheKey, $combinedData);
    
    return $combinedData;
}

// Function to get location details - uses /locations/list endpoint
function getLocationDetails($locationId, $token, $base_url, $debug) {
    $cacheKey = "location_details_{$locationId}";
    $cachedData = getCachedData($cacheKey, 3600 * 24); // Cache for 24 hours
    
    if ($cachedData !== false) {
        return $cachedData;
    }
    
    // Use the /locations/list endpoint which includes GPS coordinates
    $url = $base_url . '/locations/list';
    
    if ($debug) {
        echo '<div class="alert alert-info">Fetching location details from: ' . $url . '</div>';
    }
    
    $locationsData = makeApiRequest($url, $token, null, $debug);
    
    if (!$locationsData || isset($locationsData['error'])) {
        if ($debug) {
            echo '<div class="alert alert-danger">Failed to get locations list</div>';
        }
        return false;
    }
    
    // The response is a direct array of locations (not wrapped in a 'locations' key)
    // Remove the _next_page token if it exists
    $nextPageToken = isset($locationsData['_next_page']) ? $locationsData['_next_page'] : null;
    unset($locationsData['_next_page']);
    
    if (!is_array($locationsData) || empty($locationsData)) {
        if ($debug) {
            echo '<div class="alert alert-danger">No locations in response</div>';
        }
        return false;
    }
    
    if ($debug) {
        echo '<div class="alert alert-info">Found ' . count($locationsData) . ' locations in first page</div>';
    }
    
    // Find the specific location by ID
    $targetLocation = null;
    foreach ($locationsData as $key => $location) {
        // Skip if this is the _next_page token
        if ($key === '_next_page') {
            continue;
        }
        
        // Check if this location matches our target ID
        if (isset($location['id']) && $location['id'] == $locationId) {
            $targetLocation = $location;
            break;
        }
    }
    
    // If not found in first page and there's a next page, paginate
    $currentPage = 1;
    $maxPages = 10;
    
    while (!$targetLocation && $nextPageToken && $currentPage < $maxPages) {
        if ($debug) {
            echo '<div class="alert alert-secondary">Searching page ' . ($currentPage + 1) . ' for location...</div>';
        }
        
        usleep(250000); // 0.25 second delay
        
        $nextPageData = makeApiRequest($url, $token, $nextPageToken, $debug);
        
        if (!$nextPageData || isset($nextPageData['error'])) {
            break;
        }
        
        $nextPageToken = isset($nextPageData['_next_page']) ? $nextPageData['_next_page'] : null;
        unset($nextPageData['_next_page']);
        
        // Search this page
        foreach ($nextPageData as $key => $location) {
            if ($key === '_next_page') {
                continue;
            }
            
            if (isset($location['id']) && $location['id'] == $locationId) {
                $targetLocation = $location;
                break;
            }
        }
        
        $currentPage++;
    }
    
    if (!$targetLocation) {
        if ($debug) {
            echo '<div class="alert alert-warning">Location ID ' . $locationId . ' not found in locations list</div>';
            echo '<div class="alert alert-info">Available location IDs in first page: ';
            $ids = [];
            foreach ($locationsData as $key => $loc) {
                if ($key !== '_next_page' && isset($loc['id'])) {
                    $ids[] = $loc['id'] . ' (' . ($loc['name'] ?? 'unnamed') . ')';
                }
            }
            echo implode(', ', $ids);
            echo '</div>';
        }
        return false;
    }
    
    if ($debug) {
        echo '<div class="alert alert-success">Found location: ' . ($targetLocation['name'] ?? 'Unknown') . '</div>';
        if (isset($targetLocation['gps'])) {
            echo '<div class="alert alert-info">GPS: ' . $targetLocation['gps']['latitude'] . ', ' . $targetLocation['gps']['longitude'] . '</div>';
        } else {
            echo '<div class="alert alert-warning">No GPS data in location details</div>';
        }
    }
    
    // Cache and return the location details
    setCachedData($cacheKey, $targetLocation);
    return $targetLocation;
}

// Function to get friendly names for parameters and units
function getFriendlyNames($token, $base_url, $debug = false) {
    $cacheKey = "friendlynames";
    $cachedData = getCachedData($cacheKey, 3600 * 24); // Cache for 24 hours
    
    if ($cachedData !== false) {
        return $cachedData;
    }
    
    $url = $base_url . '/sispec/friendlynames';
    
    $friendlynames = makeApiRequest($url, $token, null, $debug);
    
    $parameters = isset($friendlynames['parameters']) && is_array($friendlynames['parameters']) ? $friendlynames['parameters'] : [];
    $units = isset($friendlynames['units']) && is_array($friendlynames['units']) ? $friendlynames['units'] : [];
    
    $result = [
        'parameters' => $parameters,
        'units' => $units
    ];
    
    setCachedData($cacheKey, $result);
    return $result;
}

// transformWaterLevelData - converts data from meters to feet and then to water level with elevation
function transformWaterLevelData($readings, $method, $baseline, $water_well_elevation, $transducer_height) {
    $transformedReadings = [];
    $debugMessage = "Transforming water level data with baseline: $baseline\n";
    $debugMessage .= "Original readings sample: ";
    
    // Sample the first few readings for debugging
    $sampleCount = min(3, count($readings));
    for($i = 0; $i < $sampleCount; $i++) {
        $debugMessage .= "Reading #$i: " . $readings[$i]['value'] . ", ";
    }
    
    $debugMessage .= "\nTransformed readings: ". "\n". "method=$method, baseline=$baseline, water_well_elevation=$water_well_elevation, transducer_height=$transducer_height\n";
    
    if($method === 'Baseline_Elev') {
        $debugMessage .= "Using Baseline_Elev method\n";
        // Adjust well depth elevation using the baseline elevation
        $baseline = $baseline; // baseline is the elevation of the measuring point
        foreach ($readings as $reading) {
            $transformedReading = $reading;
            // Convert depth to water level by converting meters to feet and adding baseline
            $transformedReading['value'] = ($reading['value'] * 3.2084) + $baseline;
            $transformedReadings[] = $transformedReading;
            
            // Debug first few transformed readings
            if(count($transformedReadings) <= $sampleCount) {
                $debugMessage .= "Reading #" . (count($transformedReadings)-1) . 
                                ": Original=" . $reading['value'] . 
                                ", Transformed=" . $transformedReading['value'] . ", ";
            }
        }
    } else { //assume 'TD_Height'
        // Adjust well depth elevation using casing height and transducer height
        foreach ($readings as $reading) {
            $transformedReading = $reading;
            // Convert depth to water level by converting meters to feet and subtracting well elevation and transducer heights
            $transformedReading['value'] =  $water_well_elevation - ($reading['value'] * 3.2084) - $transducer_height;
            $transformedReadings[] = $transformedReading;

            // Debug first few transformed readings
            if(count($transformedReadings) <= $sampleCount) {
                $debugMessage .= "Reading #" . (count($transformedReadings)-1) . 
                                ": Original=" . $reading['value'] . 
                                ", Transformed=" . $transformedReading['value'] . ", ";
            }
        }
    }

    // Add debug message to a log or display on page
    if(isset($_GET['debug']) && $_GET['debug'] === '1') {
        echo "<pre class='alert alert-info'>$debugMessage</pre>";
    }
    
    return $transformedReadings;
}

//transform reading to F:
function transformWaterTempData($readings) {
    $transformedReadings = [];
    foreach ($readings as $reading) {
        $transformedReading = $reading;
        $transformedReading['value'] = ($reading['value'] * 1.8) + 32;
        $transformedReadings[] = $transformedReading;
    }

    return $transformedReadings;
}

// Process the data for web display - simplified for week and hour only
function processWellData($week_data, $hour_data, $parameters, $units, $paramNames, $paramUnits, $depth_method, $water_level_baseline, $water_well_elevation, $transducer_height) {
    $availableParams = [];
    
    // Process weekly data to get available parameters
    if (isset($week_data['parameters']) && is_array($week_data['parameters'])) {
        foreach ($week_data['parameters'] as $param) {
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
                    // Debug before transformation
                    if(isset($_GET['debug']) && $_GET['debug'] === '1') {
                        echo "<pre class='alert alert-info'>Before transformation (week data):<br>";
                        echo "Parameter: " . $paramKey . "<br>";
                        echo "Sample readings: ";
                        for($i = 0; $i < min(3, count($param['readings'])); $i++) {
                            echo "Reading #$i: " . $param['readings'][$i]['value'] . ", ";
                        }
                        echo "</pre>";
                    }
                    
                    $readings = transformWaterLevelData($param['readings'], $depth_method, $water_level_baseline, $water_well_elevation, $transducer_height);
                    
                    // Debug after transformation
                    if(isset($_GET['debug']) && $_GET['debug'] === '1') {
                        echo "<pre class='alert alert-info'>After transformation (week data):<br>";
                        echo "Sample transformed readings: ";
                        for($i = 0; $i < min(3, count($readings)); $i++) {
                            echo "Reading #$i: " . $readings[$i]['value'] . ", ";
                        }
                        echo "</pre>";
                    }
                } else { 
                    //get Temperature
                    $readings = transformWaterTempData($param['readings']);
                }
                
                $availableParams[$paramKey] = [
                    'id' => $paramId,
                    'name' => $displayName,
                    'unit' => $unitName,
                    'week_readings' => $readings
                ];
            }
        }
    }
    
    // Process hourly data
    if (isset($hour_data['parameters']) && is_array($hour_data['parameters'])) {
        foreach ($hour_data['parameters'] as $param) {
            if (isset($param['readings']) && !empty($param['readings'])) {
                $paramId = $param['parameterId'];
                $paramKey = '';
                
                // Try to match with existing parameters
                if (isset($availableParams['depth']) && $paramId === $availableParams['depth']['id']) {
                    $paramKey = 'depth';
                } else if (isset($availableParams['temperature']) && $paramId === $availableParams['temperature']['id']) {
                    $paramKey = 'temperature';
                } else {
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
                }
                
                // Skip if not water level or temperature
                if (!in_array($paramKey, ['depth', 'temperature'])) {
                    continue;
                }
                
                $readings = $param['readings'];
                
                // If depth parameter, transform to water level
                if ($paramKey === 'depth') {
                    $readings = transformWaterLevelData($readings, $depth_method, $water_level_baseline, $water_well_elevation, $transducer_height);
                } else {
                    $readings = transformWaterTempData($readings);
                }
                
                // If this parameter already exists, add hour readings
                if (isset($availableParams[$paramKey])) {
                    $availableParams[$paramKey]['hour_readings'] = $readings;
                } else {
                    // Get unit and display name
                    $unitId = $param['unitId'];
                    $unitName = isset($units[$unitId]) ? $units[$unitId] : '';
                    if (isset($paramUnits[$paramKey])) {
                        $unitName = $paramUnits[$paramKey];
                    }
                    
                    $displayName = isset($paramNames[$paramKey]) ? $paramNames[$paramKey] : 
                                  (isset($parameters[$paramId]) ? $parameters[$paramId] : 'Parameter ' . $paramId);
                    
                    // Create new parameter entry
                    $availableParams[$paramKey] = [
                        'id' => $paramId,
                        'name' => $displayName,
                        'unit' => $unitName,
                        'hour_readings' => $readings
                    ];
                }
            }
        }
    }
    
    return $availableParams;
}
?>