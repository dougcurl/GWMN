<?php
/**
 * Shared API Functions
 * Place in /common/api.php
 * Used by all wells
 */

// Cache directory
$cache_dir = __DIR__ . '/../cache/';
if (!file_exists($cache_dir)) {
    mkdir($cache_dir, 0755, true);
}

/**
 * Get OAuth2 token
 */
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
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
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

/**
 * Make API requests
 */
function makeApiRequest($url, $token, $startPage = null, $debug = false) {
    $headers = [
        'accept: application/json',
        'authorization: Bearer ' . $token
    ];
    
    if ($startPage) {
        $headers[] = 'X-ISI-Start-Page: ' . $startPage;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_HEADER, true);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        if ($debug) {
            echo '<div class="alert alert-danger">API Error: ' . curl_error($ch) . '</div>';
        }
        return ['error' => curl_error($ch)];
    }
    
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header_text = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    
    curl_close($ch);
    
    $data = json_decode($body, true);
    
    if ($data === null) {
        return ['error' => 'Invalid JSON response'];
    }
    
    // Extract next page from headers
    if (preg_match('/X-ISI-Next-Page:\s*(\d+)/i', $header_text, $matches)) {
        $data['_next_page'] = $matches[1];
    }
    
    return $data;
}

/**
 * Cache functions
 */
function getCacheKey($prefix, $params = []) {
    return $prefix . '_' . md5(serialize($params));
}

function getCachedData($key, $ttl = 900) {
    global $cache_dir, $cache_enabled;
    
    if (!$cache_enabled) {
        return false;
    }
    
    $cache_file = $cache_dir . $key . '.cache';
    
    if (file_exists($cache_file)) {
        $cache_data = unserialize(file_get_contents($cache_file));
        if (time() - $cache_data['time'] < $ttl) {
            return $cache_data['data'];
        }
    }
    
    return false;
}

function setCachedData($key, $data) {
    global $cache_dir, $cache_enabled;
    
    if (!$cache_enabled) {
        return;
    }
    
    $cache_file = $cache_dir . $key . '.cache';
    $cache_data = [
        'time' => time(),
        'data' => $data
    ];
    
    file_put_contents($cache_file, serialize($cache_data));
}

/**
 * Get location data
 */
function getLocationData($locationId, $token, $base_url, $startTime, $endTime, $maxPages = 10, $debug = false) {
    $cacheKey = getCacheKey('location_data', [$locationId, $startTime, $endTime]);
    $cachedData = getCachedData($cacheKey, 900); // 15 minutes
    
    if ($cachedData !== false) {
        if ($debug) {
            echo '<div class="alert alert-info">Using cached data for location ' . $locationId . '</div>';
        }
        return $cachedData;
    }
    
    $url = $base_url . '/locations/' . $locationId . '/data?startTime=' . $startTime . '&endTime=' . $endTime;
    
    $combinedData = null;
    $currentPage = 1;
    $totalReadings = 0;
    $debug_info = ['pages_fetched' => 0, 'api_calls' => []];
    
    while ($currentPage <= $maxPages) {
        $pageData = makeApiRequest($url, $token, ($currentPage > 1 ? $currentPage : null), $debug);
        
        if (isset($pageData['error'])) {
            return $pageData;
        }
        
        $debug_info['api_calls'][] = [
            'page' => $currentPage,
            'url' => $url,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        if ($combinedData === null) {
            $combinedData = $pageData;
        } else {
            if (isset($pageData['parameters']) && is_array($pageData['parameters'])) {
                foreach ($pageData['parameters'] as $paramIndex => $param) {
                    if (isset($param['readings']) && is_array($param['readings'])) {
                        $combinedData['parameters'][$paramIndex]['readings'] = array_merge(
                            $combinedData['parameters'][$paramIndex]['readings'],
                            $param['readings']
                        );
                    }
                }
            }
        }
        
        $pageReadings = 0;
        if (isset($pageData['parameters'])) {
            foreach ($pageData['parameters'] as $param) {
                if (isset($param['readings'])) {
                    $pageReadings += count($param['readings']);
                }
            }
        }
        
        $totalReadings += $pageReadings;
        $debug_info['pages_fetched'] = $currentPage;
        
        $nextPage = isset($pageData['_next_page']) ? $pageData['_next_page'] : null;
        
        if (!$nextPage || $currentPage >= $maxPages) {
            break;
        }
        
        $currentPage = $nextPage;
        
        if ($debug) {
            echo '<div class="alert alert-info">Page ' . $currentPage . ': Retrieved ' . $pageReadings . ' more readings. Total so far: ' . $totalReadings . '</div>';
        }
    }
    
    $debug_info['total_readings'] = $totalReadings;
    $debug_info['total_pages'] = $currentPage;
    
    $combinedData['debug'] = $debug_info;
    
    setCachedData($cacheKey, $combinedData);
    
    return $combinedData;
}

/**
 * Get location details
 */
function getLocationDetails($locationId, $token, $base_url, $debug = false) {
    $cacheKey = "location_details_{$locationId}";
    $cachedData = getCachedData($cacheKey, 3600 * 24); // Cache for 24 hours
    
    if ($cachedData !== false) {
        return $cachedData;
    }
    
    $url = $base_url . '/locations/' . $locationId;
    
    $locationData = makeApiRequest($url, $token, null, $debug);
    
    if (!$locationData || isset($locationData['error'])) {
        return false;
    }
    
    setCachedData($cacheKey, $locationData);
    return $locationData;
}

/**
 * Get friendly names
 */
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

/**
 * Transform water level data
 */
function transformWaterLevelData($readings, $baseline) {
    $transformedReadings = [];
    
    foreach ($readings as $reading) {
        $depthInMeters = $reading['value'];
        $depthInFeet = $depthInMeters * 3.28084;
        $waterLevel = $baseline + $depthInFeet;
        
        $transformedReadings[] = [
            'timestamp' => $reading['timestamp'],
            'value' => round($waterLevel, 2)
        ];
    }
    
    return $transformedReadings;
}

/**
 * Transform water temperature data (Celsius to Fahrenheit)
 */
function transformWaterTempData($readings) {
    $transformedReadings = [];
    
    foreach ($readings as $reading) {
        $tempC = $reading['value'];
        $tempF = ($tempC * 9/5) + 32;
        
        $transformedReadings[] = [
            'timestamp' => $reading['timestamp'],
            'value' => round($tempF, 1)
        ];
    }
    
    return $transformedReadings;
}

/**
 * Process well data
 */
function processWellData($week_data, $hour_data, $parameters, $units, $paramNames, $paramUnits, $water_level_baseline) {
    $availableParams = [];
    
    // Process week data
    if (isset($week_data['parameters']) && is_array($week_data['parameters'])) {
        foreach ($week_data['parameters'] as $param) {
            if (isset($param['readings']) && !empty($param['readings'])) {
                $paramId = $param['parameterId'];
                $paramKey = '';
                
                // Identify parameter type
                foreach ($parameters as $key => $name) {
                    if ($key == $paramId) {
                        $paramName = $name;
                        if (stripos($paramName, 'depth') !== false || stripos($paramName, 'level') !== false) {
                            $paramKey = 'depth';
                        } elseif (stripos($paramName, 'temp') !== false) {
                            $paramKey = 'temperature';
                        }
                        break;
                    }
                }
                
                if (!in_array($paramKey, ['depth', 'temperature'])) {
                    continue;
                }
                
                if (empty($paramKey)) {
                    $paramKey = 'param_' . $paramId;
                }
                
                $unitId = $param['unitId'];
                $unitName = isset($units[$unitId]) ? $units[$unitId] : '';
                
                if (isset($paramUnits[$paramKey])) {
                    $unitName = $paramUnits[$paramKey];
                }
                
                $displayName = isset($paramNames[$paramKey]) ? $paramNames[$paramKey] : 
                              (isset($parameters[$paramId]) ? $parameters[$paramId] : 'Parameter ' . $paramId);
                
                $readings = $param['readings'];
                
                if ($paramKey === 'depth') {
                    $readings = transformWaterLevelData($readings, $water_level_baseline);
                } else {
                    $readings = transformWaterTempData($readings);
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
    
    // Process hour data
    if (isset($hour_data['parameters']) && is_array($hour_data['parameters'])) {
        foreach ($hour_data['parameters'] as $param) {
            if (isset($param['readings']) && !empty($param['readings'])) {
                $paramId = $param['parameterId'];
                $paramKey = '';
                
                foreach ($parameters as $key => $name) {
                    if ($key == $paramId) {
                        $paramName = $name;
                        if (stripos($paramName, 'depth') !== false || stripos($paramName, 'level') !== false) {
                            $paramKey = 'depth';
                        } elseif (stripos($paramName, 'temp') !== false) {
                            $paramKey = 'temperature';
                        }
                        break;
                    }
                }
                
                if (!empty($paramKey) && isset($availableParams[$paramKey])) {
                    $readings = $param['readings'];
                    
                    if ($paramKey === 'depth') {
                        $readings = transformWaterLevelData($readings, $water_level_baseline);
                    } else {
                        $readings = transformWaterTempData($readings);
                    }
                    
                    $availableParams[$paramKey]['hour_readings'] = $readings;
                }
            }
        }
    }
    
    return $availableParams;
}
?>