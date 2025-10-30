<?php
//well.php
// Redirect anyone who lands on hp to kgon1
if (isset($_GET['id']) && $_GET['id'] === 'hp') {
    header("Location: well.php?id=kgon1", true, 301);
    exit;
}
// Minimal processing - just get config
require_once __DIR__ . '/credentials.php';
require_once __DIR__ . '/wells_config.php';

$current_well_id = isset($_GET['id']) ? $_GET['id'] : null;
if ($current_well_id === null) {
    header('Location: index.php');
    exit;
}

$well_config = getWellConfig($current_well_id);
if ($well_config === null) {
    die('Error: Configuration not found');
}

// Extract only essential variables
$page_title = $well_config['full_name'];
$water_level_warning = $well_config['water_level_warning'];
$well_numeric_id = isset($well_config['well_numeric_id']) ? $well_config['well_numeric_id'] : '';

// IMMEDIATELY send HTML shell - no API calls yet
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KGS Groundwater Well Monitoring - <?php echo htmlspecialchars($page_title); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="css/styles.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@2.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
</head>
<body>
    <!-- Page shows immediately with loading indicator -->
    <div id="page-loader" class="page-loading-overlay">
        <div class="loading-content">
            <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Loading...</span>
            </div>
            <h4 id="loading-message">Loading well data...</h4>
            <div class="progress mt-3" style="width: 300px; height: 8px;">
                <div id="loading-progress" class="progress-bar progress-bar-striped progress-bar-animated" 
                    role="progressbar" 
                    style="width: 0%" 
                    aria-valuenow="0" 
                    aria-valuemin="0" 
                    aria-valuemax="100"></div>
            </div>
            <p class="text-muted mt-2 small" id="loading-detail">Initializing...</p>
        </div>
    </div>

    <div class="container mt-4">
        <div class="header-container mb-4">
            <!-- Header content -->
            <div class="row align-items-center">
                <div class="col-md-2 col-sm-3 text-center text-md-start mb-3 mb-md-0">
                    <a href="https://kygs.uky.edu">
                        <img src="https://kgs.uky.edu/kygeode/img/UK-KGSlogos/UK-KGS-lockup/KGS.png" alt="KGS Logo" class="img-fluid" style="max-height: 100px;">
                    </a>
                </div>
                <div class="col-md-6 col-sm-9">
                    <h1 class="mb-1 fs-3"><?php echo htmlspecialchars($page_title); ?></h1>
                    <h2 class="fs-5 text-muted"><a href="https://www.uky.edu/KGS/water/water-groundwater-monitoring.php">KGS Groundwater Monitoring Network</a></h2>
                </div>
                <div class="col-md-4 col-12 d-flex justify-content-md-end justify-content-center mt-3 mt-md-0">
                    <div class="d-flex flex-row gap-2">
                        <a href="index.php" class="btn btn-outline-primary">← All Real-Time KGON Wells</a>
                    </div>
                </div>
            </div>
            <hr class="mt-3">
        </div>

        <!-- ADD THIS: Map and Latest Readings container -->
        <div class="row mt-4" id="map-readings-container">
            <!-- Map and readings will be inserted here by JavaScript -->
        </div>

        <!-- Content will be populated via AJAX -->
        <div id="well-content"></div>
    </div>

    <footer>
        <div class="container">
            <p>Kentucky Geological Survey | Data provided by HydroVu API</p>
            <p>Last updated: <span id="last-updated"></span></p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const waterLevelWarning = <?php echo $water_level_warning; ?>;
        const wellId = '<?php echo htmlspecialchars($current_well_id); ?>';
        const wellNumericId = '<?php echo htmlspecialchars($well_numeric_id); ?>';
        
        // Load data immediately after page structure is visible
        document.addEventListener('DOMContentLoaded', function() {
            loadWellData();
        });
    </script>
    <script src="js/well-loader.js"></script>
</body>
</html>