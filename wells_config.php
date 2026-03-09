<?php
/**
 * Universal Well Configuration
 * 
 * Define all wells in one place. Add new wells by adding entries to this array.
 * Each well needs:
 * - well_id: Unique identifier for URL routing (use HydroVu well ID or similar - https://www.hydrovu.com/public-api/docs/index.html))
 * - location_id: API location ID from HydroVu
 * - common_name: Display name for the well
 * - full_name: Full title for the page
 * - water_level_baseline: Elevation baseline for water level calculations
 * - water_level_warning: Ground surface elevation threshold
 * - reading_interval: How often data is recorded (in minutes) - used for cache consistency
 */

$wells_config = [
    'kgon1' => [
        'well_id' => 'hp',
        'location_id' => '5515870852612096',
        'common_name' => 'Horse Park',
        'well_numeric_id' => 'KGON-1',
        'akgwa_number' => '00060905',
        'full_name' => 'Kentucky Horse Park Water Well',
        'property_owner' => 'Kentucky Horse Park', //name of property owner
        'property_logo' => 'images/khp-logo.png', //relative path to logo image
        'water_well_elevation' => 838, //ground surface elevation at well location
        'water_well_depth' => 80, //depth of the well
        'depth_method' => 'Baseline_Elev',  //method used to measure depth to water from raw data
        // Baseline_Elev Well Elevation: Water Level Baseline Elevation + Raw Well Depth
        'casing_height' => 1.25, //height of well casing above ground surface
        //'transducer_height' => , //height of measuring point above ground surface - may be negative if below ground surface
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
        'kgon3' => [
        'well_id' => 'wko1',
        'location_id' => '6391112108408832',
        'common_name' => 'Henderson WKO 1 Well',
        'well_numeric_id' => 'KGON-3',
        'akgwa_number' => '80019466',
        'full_name' => 'Henderson WKO 1 Water Well',
        'property_owner' => 'Kentucky Geological Survey', //name of property owner
        'property_logo' => 'images/kgs-logo-final.png', //relative path to logo image
        'water_well_elevation' => 383.37, //ground surface elevation at well location
        'water_well_depth' => 47.9, //depth of the well
        'depth_method' => 'Baseline_Elev',  //method used to measure depth to water from raw data
        // Baseline_Elev Well Elevation: Water Level Baseline Elevation + Raw Well Depth
        'casing_height' => 2.19, //height of well casing above ground surface
        'water_level_baseline' => 350.56, //NAVD88 elevation of the measuring point - transducer height in well subtracted from ground surface elevation
        'water_level_warning' => 385.56, //top of the well head casing - elevation at top of the well casing
        'reading_interval' => 15, // Data recording interval in minutes
        'aquifer_name' => '	Ohio River Alluvium',
        'description' => 'Henderson WKO 1 groundwater monitoring well (KGON 3) located in Henderson County, KY. Depth of well is 47.9 ft.',
        'param_names' => [
            'depth' => 'Groundwater Level Elevation',
            'temperature' => 'Temperature'
        ],
        'param_units' => [
            'depth' => 'ft',
            'temperature' => '°F'
        ]
    ],
        'kgon4' => [
        'well_id' => 'benton',
        'location_id' => '4985108709507072',
        'common_name' => 'Benton-Rose Well',
        'well_numeric_id' => 'KGON-4',
        'akgwa_number' => '00057315',
        'full_name' => 'Benton-Rose Well',
        'property_owner' => 'Jodi and Wendy Rose', //name of property owner
        'water_well_elevation' => 462, //ground surface elevation at well location
        'water_well_depth' => 221, //depth of the well
        'depth_method' => 'Baseline_Elev',  //method used to measure depth to water from raw data
        // Baseline_Elev Well Elevation: Water Level Baseline Elevation + Raw Well Depth
        'casing_height' => 1.42, //height of well casing above ground surface
        //'transducer_height' => , //height of measuring point above ground surface - may be negative if below ground surface
        'water_level_baseline' => 368.39, //NAVD88 elevation of the measuring point
        'water_level_warning' => 463.42, //top of the well head casing - elevation at top of the well casing
        'reading_interval' => 15, // Data recording interval in minutes
        'aquifer_name' => 'McNairy Formation',
        'description' => 'Benton-Rose groundwater monitoring well (KGON 4) located in Marshall County, KY. Depth of well is 221 ft.',
        'param_names' => [
            'depth' => 'Groundwater Level Elevation',
            'temperature' => 'Temperature'
        ],
        'param_units' => [
            'depth' => 'ft',
            'temperature' => '°F'
        ]
    ],
    'kgon5' => [
        'well_id' => 'hickman1',
        'location_id' => '5898229225619456', // Replace with actual location ID
        'common_name' => 'Hickman1',
        'well_numeric_id' => 'KGON-5',
        'akgwa_number' => '80019466',        
        'full_name' => 'Hickman Deep Water Well',
        'property_owner' => 'Naranjo Family',
        'depth_method' => 'Baseline_Elev', //method used to measure depth to water from raw data - will only show the height
        // TD_Height Well Elevation: Transducer Height - Raw Well Depth = Water Level Elevation
        // Baseline_Elev Well Elevation: Water Level Baseline Elevation + Raw Well Depth
        'water_well_elevation' => 421, //ground surface elevation at well location
        'water_well_depth' => 380, //depth of the well
        'casing_height' => 1.5, //height of well casing above ground surface
        'transducer_height' => 1.17, //height of measuring point above ground surface
        'water_level_baseline' => 325.17, // NAVD88 elevation of the measuring point - transdcuer height in well subtracted from ground surface elevation
        'water_level_warning' =>  422.5, // elevation at top of the well casing
        'reading_interval' => 15, // Data recording interval in minutes
        'aquifer_name' => 'Middle Claiborne Aquifer',
        'description' => 'Hickman deep groundwater monitoring well (KGON 5) located in Hickman County, KY. Depth of well is 380 ft.',
        'param_names' => [
            'depth' => 'Groundwater Level Elevation',
            'temperature' => 'Temperature'
        ],
        'param_units' => [
            'depth' => 'ft',
            'temperature' => '°F'
        ]
     ],
    'kgon6' => [
        'well_id' => 'hickman2',
        'location_id' => '6070239209717760', // Replace with actual location ID
        'common_name' => 'Hickman2',
        'well_numeric_id' => 'KGON-6',
        'akgwa_number' => '80046534', 
        'full_name' => 'Hickman Shallow Water Well',
        'property_owner' => 'Naranjo Family',
        'depth_method' => 'Baseline_Elev', //method used to measure depth to water from raw data - will only show the height
        // TD_Height Well Elevation: Transducer Height - Raw Well Depth = Water Level Elevation
        // Baseline_Elev Well Elevation: Water Level Baseline Elevation + Raw Well Depth
        'water_well_elevation' => 424, //ground surface elevation at well location
        'water_well_depth' => 180, //depth of the well
        'casing_height' => 1.29, //height of well casing above ground surface
        //'transducer_height' => 1.17, //height of measuring point above ground surface
        'water_level_baseline' => 324.04, // NAVD88 elevation of the measuring point - transdcuer height in well subtracted from ground surface elevation
        'water_level_warning' =>  425.29, // elevation at top of the well casing
        'reading_interval' => 15, // Data recording interval in minutes
        'aquifer_name' => 'Upper Claiborne Aquifer',
        'description' => 'Hickman shallow groundwater monitoring well (KGON 6) located in Hickman County, KY. Depth of well is 180 ft.',
        'param_names' => [
            'depth' => 'Groundwater Level Elevation',
            'temperature' => 'Temperature'
        ],
        'param_units' => [
            'depth' => 'ft',
            'temperature' => '°F'
        ]
    ],
    'kgon7' => [
        'well_id' => 'msu1',
        'location_id' => '5326751413305344', // Replace with actual location ID
        'common_name' => 'MSU1',
        'well_numeric_id' => 'KGON-7',
        'akgwa_number' => '80046531',
        'full_name' => 'Murray State University Deep Water Well',
        'property_owner' => 'Murray State University',
        'property_logo' => 'images/MSUHutsonSchool.png',
        'depth_method' => 'Baseline_Elev', //method used to measure depth to water from raw data - will only show the height
        // TD_Height Well Elevation: Transducer Height - Raw Well Depth = Water Level Elevation
        // Baseline_Elev Well Elevation: Water Level Baseline Elevation + Raw Well Depth
        'water_well_elevation' => 576, //ground surface elevation at well location
        'water_well_depth' => 350, //depth of the well
        'casing_height' => 3.17, //height of well casing above ground surface
        //'transducer_height' => 1.17, //height of measuring point above ground surface
        'water_level_baseline' => 402.08, // NAVD88 elevation of the measuring point - transdcuer height in well subtracted from ground surface elevation
        'water_level_warning' =>  579.17, // elevation at top of the well casing
        'reading_interval' => 15, // Data recording interval in minutes
        'aquifer_name' => 'McNairy Formation',
        'description' => 'Murray State University deep groundwater monitoring well (KGON 7) located in Calloway County, KY. Depth of well is 350 ft.',
        'param_names' => [
            'depth' => 'Groundwater Level Elevation',
            'temperature' => 'Temperature'
        ],
        'param_units' => [
            'depth' => 'ft',
            'temperature' => '°F'
        ]
    ],
    'kgon8' => [
        'well_id' => 'msu2',
        'location_id' => '6051285955248128', // Replace with actual location ID
        'common_name' => 'MSU2',
        'well_numeric_id' => 'KGON-8',
        'akgwa_number' => '80046532',
        'full_name' => 'Murray State University Shallow Water Well',
        'property_owner' => 'Murray State University',
        'property_logo' => 'images/MSUHutsonSchool.png',
        'depth_method' => 'Baseline_Elev', //method used to measure depth to water from raw data - will only show the height
        // TD_Height Well Elevation: Transducer Height - Raw Well Depth = Water Level Elevation
        // Baseline_Elev Well Elevation: Water Level Baseline Elevation + Raw Well Depth
        'water_well_elevation' => 576, //ground surface elevation at well location
        'water_well_depth' => 150, //depth of the well
        'casing_height' => 3.24, //height of well casing above ground surface
        //'transducer_height' => 1.17, //height of measuring point above ground surface
        'water_level_baseline' => 502.08, // NAVD88 elevation of the measuring point - transdcuer height in well subtracted from ground surface elevation
        'water_level_warning' =>  579.24, // elevation at top of the well casing
        'reading_interval' => 15, // Data recording interval in minutes
        'aquifer_name' => 'Lower Wilcox Formation',
        'description' => 'Murray State University shallow groundwater monitoring well (KGON 8) located in Calloway County, KY. Depth of well is 150 ft.',
        'param_names' => [
            'depth' => 'Groundwater Level Elevation',
            'temperature' => 'Temperature'
        ],
        'param_units' => [
            'depth' => 'ft',
            'temperature' => '°F'
        ]
    ],
    'kgon9' => [
        'well_id' => 'princeton',
        'location_id' => '5127989815738368', // Replace with actual location ID
        'common_name' => 'Princeton-UKREC',
        'well_numeric_id' => 'KGON-9',
        'akgwa_number' => '00070087',
        'full_name' => 'University of Kentucky Research and Education Center at Princeton Water Well',
        'property_owner' => 'University of Kentucky Research and Education Center at Princeton',
        'property_logo' => 'images/UKREC-logo.jpg',
        'depth_method' => 'Baseline_Elev', //method used to measure depth to water from raw data - will only show the height
        // TD_Height Well Elevation: Transducer Height - Raw Well Depth = Water Level Elevation
        // Baseline_Elev Well Elevation: Water Level Baseline Elevation + Raw Well Depth
        'water_well_elevation' => 485, //ground surface elevation at well location
        'water_well_depth' => 59.3, //depth of the well
        'casing_height' => 2.48, //height of well casing above ground surface
        //'transducer_height' => 1.17, //height of measuring point above ground surface
        'water_level_baseline' => 403.30, // NAVD88 elevation of the measuring point - transdcuer height in well subtracted from ground surface elevation
        'water_level_warning' =>  487.48, // elevation at top of the well casing
        'reading_interval' => 15, // Data recording interval in minutes
        'aquifer_name' => 'Ste. Genevieve Limestone',
        'description' => 'University of Kentucky Research and Education Center at Princeton shallow groundwater monitoring well (KGON 9) located in Caldwell County, KY. Depth of well is 59.3 ft.',
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