<?php
/**
 * Root Index Page - Lists all available wells with status checks
 * Updated to show real-time status of each well
 */

require_once 'wells_config.php';
require_once 'credentials.php';
require_once __DIR__ . '/common/api.php';

$all_wells = getAllWells();

/**
 * Check if a well has recent data
 * @param string $well_id - Well identifier
 * @param array $well_config - Well configuration
 * @return array - Status information ['status' => 'active'|'stale'|'offline', 'last_reading' => timestamp, 'message' => string]
 */
function checkWellStatus($well_id, $well_config) {
    global $client_id, $client_secret, $token_url, $base_url;
    
    // Define time thresholds
    $current_time = time();
    $one_day_ago = $current_time - (24 * 60 * 60);
    $one_week_ago = $current_time - (7 * 24 * 60 * 60);
    
    try {
        // Get OAuth token (with caching)
        $access_token = getOAuthToken($client_id, $client_secret, $token_url, false);
        
        if (!$access_token) {
            return [
                'status' => 'unknown',
                'last_reading' => null,
                'message' => 'Unable to verify status',
                'badge_class' => 'bg-secondary',
                'icon' => '❓'
            ];
        }
        
        // Get the most recent data (last 2 hours to be safe)
        $two_hours_ago = $current_time - (2 * 60 * 60);
        $location_id = $well_config['location_id'];
        
        $data = getLocationData($location_id, $access_token, $base_url, $two_hours_ago, $current_time, 1, false);
        
        if (!$data || isset($data['error'])) {
            return [
                'status' => 'unknown',
                'last_reading' => null,
                'message' => 'Unable to verify status',
                'badge_class' => 'bg-secondary',
                'icon' => '❓'
            ];
        }
        
        // Find the most recent reading timestamp
        $latest_timestamp = null;
        
        if (isset($data['parameters']) && is_array($data['parameters'])) {
            foreach ($data['parameters'] as $param) {
                if (isset($param['readings']) && !empty($param['readings'])) {
                    foreach ($param['readings'] as $reading) {
                        if (isset($reading['timestamp'])) {
                            $reading_time = $reading['timestamp'];
                            if ($latest_timestamp === null || $reading_time > $latest_timestamp) {
                                $latest_timestamp = $reading_time;
                            }
                        }
                    }
                }
            }
        }
        
        // Determine status based on latest reading
        if ($latest_timestamp === null) {
            // No data found at all
            return [
                'status' => 'offline',
                'last_reading' => null,
                'message' => 'No recent data',
                'badge_class' => 'bg-danger',
                'icon' => '🔴'
            ];
        } else if ($latest_timestamp >= $one_day_ago) {
            // Data within last 24 hours - Active
            $hours_ago = round(($current_time - $latest_timestamp) / 3600, 1);
            return [
                'status' => 'active',
                'last_reading' => $latest_timestamp,
                'message' => $hours_ago < 1 ? 'Updated recently' : "Updated {$hours_ago}h ago",
                'badge_class' => 'bg-success',
                'icon' => '🟢'
            ];
        } else if ($latest_timestamp >= $one_week_ago) {
            // Data between 1-7 days old - Stale
            $days_ago = round(($current_time - $latest_timestamp) / 86400, 1);
            return [
                'status' => 'stale',
                'last_reading' => $latest_timestamp,
                'message' => "Updated {$days_ago} days ago",
                'badge_class' => 'bg-warning text-dark',
                'icon' => '🟡'
            ];
        } else {
            // Data older than 7 days - Offline
            $days_ago = round(($current_time - $latest_timestamp) / 86400, 0);
            return [
                'status' => 'offline',
                'last_reading' => $latest_timestamp,
                'message' => "Last update {$days_ago} days ago",
                'badge_class' => 'bg-danger',
                'icon' => '🔴'
            ];
        }
        
    } catch (Exception $e) {
        return [
            'status' => 'unknown',
            'last_reading' => null,
            'message' => 'Unable to verify status',
            'badge_class' => 'bg-secondary',
            'icon' => '❓'
        ];
    }
}

// Check status for all wells (with simple caching to avoid repeated API calls)
$well_statuses = [];
foreach ($all_wells as $well_id => $well_config) {
    $well_statuses[$well_id] = checkWellStatus($well_id, $well_config);
}
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

        <div class="row">
            <?php foreach ($all_wells as $well_id => $well_config): 
                $status = $well_statuses[$well_id];
            ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <a href="well.php?id=<?php echo urlencode($well_id); ?>" class="text-decoration-none">
                        <div class="card well-card h-100 shadow-sm status-<?php echo $status['status']; ?>">
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
                                    <span class="badge <?php echo $status['badge_class']; ?>">
                                        <?php echo $status['icon']; ?> <?php echo ucfirst($status['status']); ?>
                                    </span>
                                </div>
                                
                                <?php if ($status['last_reading']): ?>
                                <div class="mt-2">
                                    <small class="text-muted status-indicator">
                                        <i class="bi bi-clock"></i>
                                        <?php echo $status['message']; ?>
                                    </small>
                                </div>
                                <?php endif; ?>
                                
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
</body>
</html>