<?php
/**
 * Universal Well Configuration
 * 
 * Define all wells in one place. Add new wells by adding entries to this array.
 * Each well needs:
 * - well_id: Unique identifier for URL routing
 * - location_id: API location ID from HydroVu
 * - common_name: Display name for the well
 * - full_name: Full title for the page
 * - water_level_baseline: Elevation baseline for water level calculations
 * - water_level_warning: Ground surface elevation threshold
 */

$wells_config = [
    'hp' => [
        'well_id' => 'hp',
        'location_id' => '5515870852612096',
        'common_name' => 'Horse Park',
        'full_name' => 'Kentucky Horse Park Water Well',
        'water_level_baseline' => 804.25,
        'water_level_warning' => 838,
        'description' => 'Horse Park groundwater monitoring well',
        'param_names' => [
            'depth' => 'Groundwater Level Elevation',
            'temperature' => 'Temperature'
        ],
        'param_units' => [
            'depth' => 'ft',
            'temperature' => '°F'
        ]
    ],
    'hickman1' => [
        'well_id' => 'hickman1',
        'location_id' => '5898229225619456', // Replace with actual location ID
        'common_name' => 'Hickman1',
        'full_name' => 'Hickman 1 Water Well',
        'water_level_baseline' => 0, // Replace with actual baseline
        'water_level_warning' => 0, // Replace with actual warning level
        'description' => 'Hickman 1 groundwater monitoring well',
        'param_names' => [
            'depth' => 'Groundwater Level Elevation',
            'temperature' => 'Temperature'
        ],
        'param_units' => [
            'depth' => 'ft',
            'temperature' => '°F'
        ]
    ]
    // Add more wells here as needed
];

/**
 * Get configuration for a specific well
 * 
 * @param string $well_id The well identifier (e.g., 'hp', 'hickman1')
 * @return array|null Well configuration or null if not found
 */
function getWellConfig($well_id) {
    global $wells_config;
    return isset($wells_config[$well_id]) ? $wells_config[$well_id] : null;
}

/**
 * Get all available wells
 * 
 * @return array All well configurations
 */
function getAllWells() {
    global $wells_config;
    return $wells_config;
}

/**
 * Detect current well from URL path
 * Supports both /well_id/ and /well_id/index.php formats
 * 
 * @return string|null The well_id or null if not found
 */
function detectWellFromPath() {
    $script_path = $_SERVER['SCRIPT_NAME'];
    $request_uri = $_SERVER['REQUEST_URI'];
    
    // Extract the directory name from the script path
    $path_parts = explode('/', trim($script_path, '/'));
    
    // The well_id should be the directory name (second to last element typically)
    if (count($path_parts) >= 2) {
        $well_id = $path_parts[count($path_parts) - 2];
        
        // Verify this is a valid well
        if (getWellConfig($well_id) !== null) {
            return $well_id;
        }
    }
    
    // Fallback: check REQUEST_URI
    $uri_parts = explode('/', trim($request_uri, '/'));
    foreach ($uri_parts as $part) {
        if (getWellConfig($part) !== null) {
            return $part;
        }
    }
    
    return null;
}
?>