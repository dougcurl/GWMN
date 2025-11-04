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
    <title>Kentucky Groundwater Observation Network- <?php echo htmlspecialchars($page_title); ?> (<?php echo htmlspecialchars($well_numeric_id); ?>)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="css/styles.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@2.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
</head>
<body>
    <!-- GA4 updated Jan 3, 2023 - Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-GHBYG6LVJQ"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', 'G-GHBYG6LVJQ');
      gtag('config', 'UA-3514165-12');
    </script>
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
                            <li class="breadcrumb-item active" aria-current="page">
                                    <?php echo htmlspecialchars($well_numeric_id); ?>
                            </li>
                        </ol>
                    </nav>
                    <h1 class="mb-1 fs-3"><?php echo htmlspecialchars($page_title); ?><br>(<?php echo htmlspecialchars($well_numeric_id); ?>)</h1>
                </div>
                <div class="col-md-4 col-12 d-flex justify-content-md-end justify-content-center mt-3 mt-md-0">
                    <div class="d-flex flex-column gap-2">
                        <a href="index.php" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-grid-3x3-gap"></i> All KGON Wells
                        </a>
                        <a href="https://www.uky.edu/KGS/water/water-groundwater-monitoring.php" 
                        class="btn btn-outline-secondary btn-sm"
                        target="_blank">
                            <i class="bi bi-info-circle"></i> About KGON
                        </a>
                    </div>
                </div>
            </div>
            <hr class="mt-3">
        </div>

        <!-- Well Information Card -->
        <div id="well-info-section"></div>

        <!-- Map and Latest Readings container -->
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
        
        // Additional config data
        const wellConfig = {
            propertyOwner: <?php echo json_encode($well_config['property_owner'] ?? ''); ?>,
            propertyLogo: <?php echo json_encode($well_config['property_logo'] ?? ''); ?>,
            description: <?php echo json_encode($well_config['description'] ?? ''); ?>,
            aquiferName: <?php echo json_encode($well_config['aquifer_name'] ?? ''); ?>,
            akgwaNumber: <?php echo json_encode($well_config['akgwa_number'] ?? ''); ?>,
            wellDepth: <?php echo json_encode($well_config['water_well_depth'] ?? ''); ?>
        };
        
        // Load data immediately after page structure is visible
        document.addEventListener('DOMContentLoaded', function() {
            displayWellInfo();
            loadWellData();
        });
        // ============================================================================
        // WELL INFORMATION DISPLAY
        // ============================================================================

        /**
         * Display well information card

         */
        // Display well information
        function displayWellInfo() {
            const infoSection = document.getElementById('well-info-section');
            let html = '<div class="well-info-card">';
            
            // Property owner and logo
            if (wellConfig.propertyOwner || wellConfig.propertyLogo) {
                html += '<div class="property-info">';
                if (wellConfig.propertyLogo) {
                    html += `<div class="property-owner"><img src="${wellConfig.propertyLogo}" alt="Property Logo" class="property-logo"></div>`;
                }
                if (wellConfig.propertyOwner) {
                    html += `<div class="property-owner">Property Owner: ${wellConfig.propertyOwner}</div>`;
                }
                html += '</div>';
            }
            
            // Description
            if (wellConfig.description) {
                html += '<div class="info-item">';
                html += '<div class="info-label">Description</div>';
                html += `<div class="info-value">${wellConfig.description}</div>`;
                html += '</div>';
            }
            
            // Aquifer name
            if (wellConfig.aquiferName) {
                html += '<div class="info-item mt-3">';
                html += '<div class="info-label">Aquifer</div>';
                html += `<div class="info-value mt-2"><span class="aquifer-badge">${wellConfig.aquiferName}</span></div>`;
                html += '</div>';
            }
            
            // KGS Well Information Link
            if (wellConfig.akgwaNumber) {
                html += '<div class="info-item mt-3">';
                html += '<div class="info-label">Additional Information</div>';
                html += '<div class="info-value mt-2">';
                html += `<a href="https://kgs.uky.edu/kgsweb/DataSearching/Water/wellinfo.asp?id=${wellConfig.akgwaNumber}" 
                         target="_blank" class="kgs-link">
                         <i class="bi bi-box-arrow-up-right"></i>
                         View Well Information in KY Groundwater Data Repository (KGS)
                         </a>`;
                html += '</div>';
                html += '</div>';
            }
            
            html += '</div>';
            infoSection.innerHTML = html;
        }
    </script>
    <script src="js/well-loader.js"></script>
</body>
</html>