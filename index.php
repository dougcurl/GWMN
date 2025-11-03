<?php
/**
 * Root Index Page - Lists all available wells
 * Updated to use consolidated well.php page
 */

require_once 'wells_config.php';

$all_wells = getAllWells();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kentucky Groundwater Observation Network - Real-Time Data Wells</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/styles.css" rel="stylesheet">
    <style>
        .well-card {
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
        }
        .well-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }
        .hero-section {
            background: linear-gradient(135deg, #06196eff 0%, #a4cbe2ff 100%);
            color: white;
            padding: 60px 0;
            margin-bottom: 40px;
        }
    </style>
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
    <div class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-3 text-center text-md-start mb-3 mb-md-0">
                    <a href="https://kygs.uky.edu/"><img src="https://kgs.uky.edu/kygeode/img/UK-KGSlogos/UK-KGS-lockup/KGS.png" 
                         alt="KGS Logo" 
                         class="img-fluid" 
                         style="max-height: 250px; filter: brightness(0) invert(1);">
                    </a>
                </div>
                <div class="col-md-11 text-md-start">
                    <h1 class="display-4 mb-2">Kentucky Groundwater Observation Network</h1>
                    <p class="lead mb-0">
                        Real-time groundwater level and temperature monitoring across Kentucky
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="container mb-5">
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="mb-3">Select a Monitoring Well</h2>
                <p class="text-muted">
                    Choose a well below to view real-time data, historical trends, and monitoring statistics.
                </p>
            </div>
        </div>

        <div class="row mb-4">
            <div style="height: 400px; position: relative;">
                <iframe 
                    src="https://kygs.maps.arcgis.com/apps/instant/basic/index.html?appid=a914432c6d6940268c9080859733a235&level=7&center=-85.4576,37.8393"
                    style="width: 100%; height: 100%; border: none;"
                    title="KGON Well Locations"
                    allowfullscreen>
                </iframe>
            </div>
        </div>

        <div class="row">
            <?php foreach ($all_wells as $well_id => $well_config): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <!-- Updated to use well.php?id= instead of directory structure -->
                    <a href="well.php?id=<?php echo urlencode($well_id); ?>" class="text-decoration-none">
                        <!-- If .htaccess is enabled, you can use: href="well/<?php echo urlencode($well_id); ?>" -->
                        <div class="card well-card h-100 shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title text-primary mb-3">
                                    <?php echo htmlspecialchars($well_config['full_name']); ?><br>
                                    (<?php echo htmlspecialchars($well_config['well_numeric_id']); ?>)
                                </h5>
                                
                                <p class="card-text text-muted">
                                    <?php echo htmlspecialchars($well_config['description']); ?>
                                </p>
                                
                                <div class="mt-3">
                                    <span class="badge bg-info me-2">Real-time Data</span>
                                    <span class="badge bg-success">Active</span>
                                </div>
                                
                                <div class="mt-3 pt-3 border-top">
                                    <small class="text-muted">
                                        <strong>Name:</strong> <?php echo htmlspecialchars($well_config['full_name']); ?>
                                        <br>
                                        <strong>Depth:</strong> <?php echo htmlspecialchars($well_config['water_well_depth']); ?> ft
                                        <br>
                                        <strong>Elevation:</strong> <?php echo htmlspecialchars($well_config['water_well_elevation']); ?> ft
                                        <br>
                                        <strong>Aquifer:</strong> <?php echo htmlspecialchars($well_config['aquifer_name']); ?>
                                    </small>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent border-top-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-primary">View Data →</span>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($all_wells)): ?>
            <div class="alert alert-warning">
                <h4>No Wells Configured</h4>
                <p>Please configure wells in the <code>wells_config.php</code> file.</p>
            </div>
        <?php endif; ?>
    </div>

    <footer class="bg-light py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-0">
                        <strong>Kentucky Geological Survey</strong><br>
                        University of Kentucky
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="https://www.uky.edu/KGS/water/water-groundwater-monitoring.php" 
                       class="btn btn-outline-primary btn-sm">
                        Learn More About Our Network
                    </a>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>