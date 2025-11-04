<?php
/**
 * Root Index Page - Lists all available wells
 * Updated for instant loading with async status checks
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
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

        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.875rem;
        }
        .well-card.status-offline {
            opacity: 0.85;
        }
        .well-card.status-stale {
            border-left: 3px solid #ffc107;
        }
        .well-card.status-active {
            border-left: 3px solid #28a745;
        }
        .status-loading {
            animation: pulse 1.5s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 0.6; }
            50% { opacity: 1; }
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
                    <a href="https://kygs.uky.edu/">
                        <img src="https://kgs.uky.edu/kygeode/img/UK-KGSlogos/UK-KGS-lockup/KGS.png" 
                             alt="KGS Logo" 
                             class="img-fluid" 
                             style="max-height: 250px; filter: brightness(0) invert(1);">
                    </a>
                </div>
                <div class="col-md-11 text-md-start">
                    <h1 class="display-4 mb-2">KY Groundwater Observation Network Wells</h1>
                    <p class="lead mb-0">
                        Real-time groundwater level and temperature monitoring across Kentucky<br>
                        <a href="https://www.uky.edu/KGS/water/water-groundwater-monitoring.php"
                           class="text-white hero-link">
                            Learn more about our network →
                        </a>
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
                    We will be updating this list as more KGON wells are brought online with live data.
                </p>
            </div>
        </div>

        <div class="row mb-4">
            <div style="height: 400px; position: relative;">
                <iframe 
                    src="https://kygs.maps.arcgis.com/apps/instant/basic/index.html?appid=a914432c6d6940268c9080859733a235&legend=off&level=7&center=-85.4576,37.8393"
                    style="width: 100%; height: 100%; border: none;"
                    title="KGON Well Locations"
                    allowfullscreen>
                </iframe>
                <img 
                    src="images/kgon-legend.png" 
                    alt="Map Attribution" 
                    style="position: absolute; bottom: 10px; left: 30px; width: 200px;">
            </div>
        </div>

        <div class="row" id="wells-container">
            <?php foreach ($all_wells as $well_id => $well_config): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <a href="well.php?id=<?php echo urlencode($well_id); ?>" class="text-decoration-none">
                        <div class="card well-card h-100 shadow-sm" data-well-id="<?php echo htmlspecialchars($well_id); ?>">
                            <div class="card-body">
                                <h5 class="card-title text-primary mb-3">
                                    <?php echo htmlspecialchars($well_config['full_name']); ?><br>
                                    (<?php echo htmlspecialchars($well_config['well_numeric_id']); ?>)
                                </h5>
                                
                                <p class="card-text text-muted">
                                    <?php echo htmlspecialchars($well_config['description']); ?>
                                </p>
                                
                                <div class="mt-3 status-badges">
                                    <span class="badge bg-info me-2">Real-time Data</span>
                                    <span class="badge bg-secondary status-loading">
                                        <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                        Checking...
                                    </span>
                                </div>
                                
                                <div class="mt-2 status-message">
                                    <small class="text-muted status-indicator">
                                        <i class="bi bi-clock"></i>
                                        <span class="status-text">Loading status...</span>
                                    </small>
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
        
        <!-- Status Legend -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title mb-3">Status Legend</h6>
                        <div class="row">
                            <div class="col-md-3 col-6 mb-2">
                                <span class="badge bg-success">🟢 Active</span>
                                <small class="d-block text-muted">Data within 24 hours</small>
                            </div>
                            <div class="col-md-3 col-6 mb-2">
                                <span class="badge bg-warning text-dark">🟡 Stale</span>
                                <small class="d-block text-muted">1-7 days old</small>
                            </div>
                            <div class="col-md-3 col-6 mb-2">
                                <span class="badge bg-danger">🔴 Offline</span>
                                <small class="d-block text-muted">No recent data (7+ days)</small>
                            </div>
                            <div class="col-md-3 col-6 mb-2">
                                <span class="badge bg-secondary">❓ Unknown</span>
                                <small class="d-block text-muted">Unable to verify</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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
    <script>
        // Load well statuses asynchronously after page load
        document.addEventListener('DOMContentLoaded', function() {
            loadWellStatuses();
        });
        
        function loadWellStatuses() {
            fetch('api/get_well_statuses.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.statuses) {
                        updateWellStatuses(data.statuses);
                    } else {
                        console.error('Failed to load well statuses');
                        showStatusError();
                    }
                })
                .catch(error => {
                    console.error('Error fetching well statuses:', error);
                    showStatusError();
                });
        }
        
        function updateWellStatuses(statuses) {
            for (const wellId in statuses) {
                const status = statuses[wellId];
                const card = document.querySelector(`[data-well-id="${wellId}"]`);
                
                if (!card) continue;
                
                // Update status class
                card.classList.remove('status-loading');
                card.classList.add('status-' + status.status);
                
                // Update status badge
                const statusBadge = card.querySelector('.status-badges .status-loading');
                if (statusBadge) {
                    statusBadge.className = 'badge ' + status.badge_class;
                    statusBadge.innerHTML = status.icon + ' ' + capitalizeFirst(status.status);
                }
                
                // Update status message
                const statusText = card.querySelector('.status-text');
                if (statusText) {
                    statusText.textContent = status.message;
                }
            }
        }
        
        function showStatusError() {
            document.querySelectorAll('.status-loading').forEach(badge => {
                badge.className = 'badge bg-secondary';
                badge.innerHTML = '❓ Unknown';
            });
            
            document.querySelectorAll('.status-text').forEach(text => {
                text.textContent = 'Unable to verify status';
            });
        }
        
        function capitalizeFirst(str) {
            return str.charAt(0).toUpperCase() + str.slice(1);
        }
    </script>
</body>
</html>