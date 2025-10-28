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
 * - reading_interval: How often data is recorded (in minutes) - used for cache consistency
 */

$wells_config = [
    'hp' => [
        'well_id' => 'hp',
        'location_id' => '5515870852612096',
        'common_name' => 'Horse Park',
        'well_numeric_id' => 'KGON-1',
        'full_name' => 'Kentucky Horse Park Water Well',
        'property_owner' => 'Kentucky Horse Park', //name of property owner
        'water_well_elevation' => 838, //ground surface elevation at well location
        'water_well_depth' => 80, //depth of the well
        'depth_method' => 'Baseline_Elev',  //method used to measure depth to water from raw data
        // Baseline_Elev Well Elevation: Water Level Baseline Elevation + Raw Well Depth
        'casing_height' => 1.25, //height of well casing above ground surface
        //'transducer_height' => -33.75, //height of measuring point above ground surface - may be negative if below ground surface
        'water_level_baseline' => 804.25, //NAVD88 elevation of the measuring point
        'water_level_warning' => 838, //top of the well head casing - elevation at top of the well casing
        'reading_interval' => 15, // Data recording interval in minutes
        'aquifer_name' => 'Lexington Limestone',
        'description' => 'Horse Park groundwater monitoring well (KGON 1) located at the Kentucky Horse Park in Lexington, KY. Depth of well is 80 ft.',
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
        'well_numeric_id' => 'KGON-5',
        'full_name' => 'Hickman 1 Deep Water Well',
        'property_owner' => 'Naranjo Family',
        'depth_method' => 'TD_Height', //method used to measure depth to water from raw data 
        // TD_Height Well Elevation: Water Well Surface Elevation -  Transducer Height - Raw Well Depth = Water Level Elevation
        'water_well_elevation' => 421, //ground surface elevation at well location
        'water_well_depth' => 380, //depth of the well
        'casing_height' => 1.5, //height of well casing above ground surface
        'transducer_height' => 1.17, //height of measuring point above ground surface
        //'water_level_baseline' => 421, // NAVD88 elevation of the measuring point
        'water_level_warning' =>  422.5, // elevation at top of the well casing
        'reading_interval' => 15, // Data recording interval in minutes
        'aquifer_name' => 'Middle Claiborne Aquifer',
        'description' => 'Hickman 1 deep groundwater monitoring well (KGON 5) located in Hickman County, KY. Depth of well is 380 ft.',
        'param_names' => [
            'depth' => 'Groundwater Level Elevation',
            'temperature' => 'Temperature'
        ],
        'param_units' => [
            'depth' => 'ft',
            'temperature' => '°F'
        ]
    ]
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