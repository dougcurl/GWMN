<?php
/**
 * Universal Monthly Data Page - monthly.php
 * Place this in the root directory
 * Usage: monthly.php?id=hp or monthly.php?id=hickman1
 * 
 * This version uses AJAX to load data for fast, responsive page display
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
$reading_interval = isset($well_config['reading_interval']) ? $well_config['reading_interval'] : 15;

// Page display variables
$page_title = $well_config['full_name'];
$well_numeric_id = isset($well_config['well_numeric_id']) ? $well_config['well_numeric_id'] : '';
$water_level_warning = $well_config['water_level_warning'];

// Get current time rounded to reading interval
$current_time = roundToInterval(time(), $reading_interval);

// Calculate default month start
$month_start = $current_time - (30 * 24 * 60 * 60);

// Get custom month start from URL if provided
$custom_month_start = null;
if (isset($_GET['month_start'])) {
    $custom_month_start = $_GET['month_start'];
    $custom_month_start_timestamp = roundToInterval(strtotime($custom_month_start), $reading_interval);
    $month_end = $custom_month_start_timestamp + (30 * 24 * 60 * 60);
    if ($month_end > $current_time) {
        $month_end = $current_time;
    }
} else {
    $custom_month_start_timestamp = $month_start;
    $month_end = $current_time;
}

// Check if this is the current month
$is_current_month = ($month_end >= $current_time - 60);

// Get location details for map (using minimal OAuth call)
$access_token = getOAuthToken($client_id, $client_secret, $token_url, false);
$location_details = null;
$latitude = null;
$longitude = null;

if ($access_token) {
    $location_details = getLocationDetails($well_location_id, $access_token, $base_url, false);
    if ($location_details && isset($location_details['gps'])) {
        $latitude = $location_details['gps']['latitude'];
        $longitude = $location_details['gps']['longitude'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Well Monitoring Data - <?php echo htmlspecialchars($page_title); ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="css/styles.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Page loading overlay -->
    <div class="page-loading-overlay" id="pageLoader">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-3">Loading monthly data...</p>
    </div>

    <div class="container mt-4">
        <div class="header-section">
            <div class="row align-items-center">
                <div class="col-md-2 col-sm-3 text-center text-md-start mb-3 mb-md-0">
                    <a href="https://kygs.uky.edu">
                        <img src="https://kgs.uky.edu/kygeode/img/UK-KGSlogos/UK-KGS-lockup/KGS.png" alt="KGS Logo" class="img-fluid" style="max-height: 100px;">
                    </a>
                </div>
                <div class="col-md-6 col-sm-9 text-center text-md-start">
                    <div class="mb-2">
                        <span class="badge bg-light text-primary border border-primary" style="font-size: 0.75rem; font-weight: 500;">
                            <i class="bi bi-droplet-fill"></i> KY Groundwater Observation Network (KGON)
                        </span>
                    </div>
                    <!-- Breadcrumb Navigation -->
                    <nav aria-label="breadcrumb" class="mb-2">
                        <ol class="breadcrumb mb-0" style="font-size: 0.875rem;">
                            <li class="breadcrumb-item">
                                <a href="https://www.uky.edu/KGS/water/water-groundwater-monitoring.php" target="_blank">
                                    <i class="bi bi-droplet-fill"></i> KGON
                                </a>
                            </li>
                            <li class="breadcrumb-item">
                                <a href="index.php">All Wells</a>
                            </li>
                            <li class="breadcrumb-item">
                                <a href="well.php?id=<?php echo urlencode($well_id); ?>">
                                    <?php echo htmlspecialchars($well_numeric_id); ?>
                                </a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">Monthly Data</li>
                        </ol>
                    </nav>
                    <h1 class="mb-1 fs-3"><?php echo htmlspecialchars($page_title); ?><br>(<?php echo htmlspecialchars($well_numeric_id); ?>)</h1>
                </div>
                <div class="col-md-4 col-12 d-flex justify-content-md-end justify-content-center mt-3 mt-md-0">
                    <div class="d-flex flex-column gap-2">
                        <a href="well.php?id=<?php echo urlencode($well_id); ?>" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-arrow-left"> </i>Back to <?php echo htmlspecialchars($well_numeric_id); ?> Details
                        </a>
                        <a href="index.php" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-grid-3x3-gap"></i> All KGON Wells
                        </a>
                    </div>
                </div>
            </div>
            <hr class="mt-3">
        </div>

        <!-- Monthly Data Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
            <div>
                <h2>Monthly Data (30 Days)</h2>
                <h4 class="section-title mb-0">
                    <?php echo date('M j, Y', $custom_month_start_timestamp); ?> to <?php echo date('M j, Y', $month_end); ?>
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
                               value="<?php echo date('Y-m-d', $custom_month_start_timestamp); ?>"
                               max="<?php echo date('Y-m-d', $current_time); ?>">
                        <button type="submit" class="btn btn-sm btn-primary me-2">Go</button>
                    </div>
                    <div style="padding-top:8px;padding-right:6px;">
                        <button type="button" class="btn btn-sm btn-outline-secondary me-1" onclick="updateMonthRange(-30)">« Previous 30 Days</button>
                        <?php if (!$is_current_month): ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary me-1" onclick="updateMonthRange(30)">Next 30 Days »</button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="resetMonthRange()">Current Month</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="row">
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-header">
                        <strong>Groundwater Level Elevation - Monthly Trend</strong>
                    </div>
                    <div class="card-body">
                        <!-- Statistics Summary -->
                        <div class="data-summary mb-3" id="depth-summary">
                            <div class="summary-item">
                                <div class="summary-label">Current</div>
                                <div class="summary-value" id="depth-current">--</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Min</div>
                                <div class="summary-value" id="depth-min">--</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Max</div>
                                <div class="summary-value" id="depth-max">--</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Average</div>
                                <div class="summary-value" id="depth-avg">--</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Readings</div>
                                <div class="summary-value" id="depth-count">--</div>
                            </div>
                        </div>
                        
                        <!-- Chart -->
                        <div class="chart-container position-relative" style="height: 500px;">
                            <div class="loading-overlay active">
                                <div class="spinner"></div>
                            </div>
                            <canvas id="month-chart-depth"></canvas>
                        </div>
                        
                        <div class="text-end mt-3">
                            <button class="btn btn-sm btn-outline-primary" onclick="exportToCSV('depth')">
                                Export Monthly Data to CSV
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-header">
                        <strong>Temperature - Monthly Trend</strong>
                    </div>
                    <div class="card-body">
                        <!-- Statistics Summary -->
                        <div class="data-summary mb-3" id="temp-summary">
                            <div class="summary-item">
                                <div class="summary-label">Current</div>
                                <div class="summary-value" id="temp-current">--</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Min</div>
                                <div class="summary-value" id="temp-min">--</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Max</div>
                                <div class="summary-value" id="temp-max">--</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Average</div>
                                <div class="summary-value" id="temp-avg">--</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Readings</div>
                                <div class="summary-value" id="temp-count">--</div>
                            </div>
                        </div>
                        
                        <!-- Chart -->
                        <div class="chart-container position-relative" style="height: 500px;">
                            <div class="loading-overlay active">
                                <div class="spinner"></div>
                            </div>
                            <canvas id="month-chart-temperature"></canvas>
                        </div>
                        
                        <div class="text-end mt-3">
                            <button class="btn btn-sm btn-outline-primary" onclick="exportToCSV('temperature')">
                                Export Monthly Data to CSV
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Map Section -->
        <?php if ($latitude && $longitude): ?>
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <strong>Well Location</strong>
                        <small class="text-muted">(<?php echo number_format($latitude, 6); ?>, <?php echo number_format($longitude, 6); ?>)</small>
                    </div>
                    <div class="card-body p-0">
                        <div style="height: 400px; position: relative;">
                            <iframe 
                                src="https://kygs.maps.arcgis.com/apps/instant/basic/index.html?appid=950d226696a14106938919d028b1944a&legend=false&level=16&siteid=<?php echo urlencode($well_numeric_id); ?>"
                                style="width: 100%; height: 100%; border: none;"
                                title="Well Location Map"
                                allowfullscreen>
                            </iframe>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <footer>
        <div class="container">
            <p>Kentucky Geological Survey | Data provided by HydroVu API</p>
            <p>Last updated: <?php echo date('m/d/y h:i A', time()); ?></p>
        </div>
    </footer>

    <script src="js/monthly-scripts.js"></script>
    <script>
        // Initialize page with AJAX loading
        document.addEventListener('DOMContentLoaded', function() {
            const wellId = '<?php echo addslashes($well_id); ?>';
            const monthStart = '<?php echo $custom_month_start ? addslashes($custom_month_start) : ''; ?>';
            
            // Initialize monthly page with AJAX
            initMonthlyPage(wellId, monthStart);
            
            // Hide page loader after a short delay
            setTimeout(function() {
                document.getElementById('pageLoader').style.display = 'none';
            }, 500);
        });
    </script>
</body>
</html>