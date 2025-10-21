<?php
/**
 * Root Index Page - Lists all available wells
 * Place this in your root directory
 */

require_once 'wells_config.php';

$all_wells = getAllWells();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KGS Groundwater Monitoring Network - Well Selection</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px 0;
            margin-bottom: 40px;
        }
    </style>
</head>
<body>
    <div class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-2 text-center text-md-start mb-3 mb-md-0">
                    <img src="https://kgs.uky.edu/kygeode/img/UK-KGSlogos/UK-KGS-lockup/KGS.png" 
                         alt="KGS Logo" 
                         class="img-fluid" 
                         style="max-height: 100px; filter: brightness(0) invert(1);">
                </div>
                <div class="col-md-10">
                    <h1 class="display-4 mb-2">Groundwater Monitoring Network</h1>
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

        <div class="row">
            <?php foreach ($all_wells as $well_id => $well_config): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <a href="<?php echo $well_id; ?>/" class="text-decoration-none">
                        <div class="card well-card h-100 shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title text-primary mb-3">
                                    <?php echo htmlspecialchars($well_config['full_name']); ?>
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
                                        <strong>Common Name:</strong> <?php echo htmlspecialchars($well_config['common_name']); ?>
                                    </small>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent border-top-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-primary">View Data →</span>
                                    <small class="text-muted">Location ID: <?php echo substr($well_config['location_id'], 0, 8); ?>...</small>
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