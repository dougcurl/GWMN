# Kentucky Groundwater Observation Network (KGON) Monitoring System

A real-time groundwater monitoring web application built for the Kentucky Geological Survey to display live water level and temperature data from monitoring wells across Kentucky.

## 🌊 Overview

This system provides public access to real-time groundwater data from the Kentucky Groundwater Observation Network (KGON). It features interactive charts, map integration, historical data views, and CSV export capabilities for researchers, water managers, and the general public.

**Live Demo:** [Your Production URL]

## ✨ Key Features

- **Real-Time Data Display**: Live groundwater level elevation and temperature readings
- **Interactive Charts**: Weekly, daily, and monthly data visualization using Chart.js
- **Progressive Loading**: Instant page display with background data fetching
- **Responsive Design**: Mobile-friendly interface built with Bootstrap 5
- **Multiple Time Views**:
  - Weekly trends (past 7 days)
  - Daily detail (past 24 hours)
  - Monthly overview (past 30 days)
- **Date Range Navigation**: Custom date range selection with intuitive controls
- **CSV Export**: Download raw data with automatic interval detection
- **Map Integration**: ArcGIS Online mapping showing well locations
- **Automatic Interval Detection**: Adapts to different wells' recording frequencies (5, 10, 15, 30, 60 minutes)
- **Smart Caching**: API rate limit compliance with timestamp-rounded cache keys
- **Status Monitoring**: Async well status checking on the index page

## 📋 System Requirements

### Server Requirements
- **PHP**: 7.4 or higher
- **Web Server**: IIS (configured) or Apache with mod_rewrite
- **PHP Extensions**:
  - cURL (for API requests)
  - JSON
  - OpenSSL (for HTTPS API calls)

### Client Requirements
- Modern web browser with JavaScript enabled
- Chart.js 3.9.1+ (loaded via CDN)
- Bootstrap 5.3+ (loaded via CDN)

## 🚀 Installation

### 1. Clone or Download

```bash
git clone https://github.com/dougcurl/GWMN
cd gwmn
```

### 2. Configure API Credentials

Copy the example credentials file and add your HydroVu API credentials:

```bash
cp credentials-example.php credentials.php
```

Edit `credentials.php`:

```php
<?php
// HydroVu API Credentials
$client_id = 'YOUR_CLIENT_ID_HERE';
$client_secret = 'YOUR_CLIENT_SECRET_HERE';

// API Endpoints
$base_url = 'https://www.hydrovu.com/public-api/v1';
$token_url = 'https://hydrovu.com/public-api/oauth/token';
?>
```

**⚠️ Important:** Never commit `credentials.php` to version control. It's already in `.gitignore`.

### 3. Configure Wells

Wells are configured in `wells_config.php`. Each well entry requires:

```php
'kgon1' => [
    'well_id' => 'hp',                    // Internal identifier - make this up
    'location_id' => '5515870852612096',  // HydroVu API location ID (NO SPACES!) - takes a little sleuthing to get. Get this via the API call: https://www.hydrovu.com/public-api/docs/index.html#/Locations/getLocationsListUsingGET - probably need to page through the records by finding the X-ISI-Next-Page in the response header JSON after running and pasting into the "string" text field under X-ISI-Start-Page.
    'common_name' => 'Horse Park',        // Short display name
    'well_numeric_id' => 'KGON-1',       // Public well identifier
    'full_name' => 'Kentucky Horse Park Water Well',
    'property_owner' => 'Kentucky Horse Park',
    'property_logo' => 'images/khp-logo.png', // Optional
    'water_well_elevation' => 838,        // Ground surface elevation (ft)
    'water_well_depth' => 80,            // Well depth (ft)
    'depth_method' => 'Baseline_Elev',   // or 'TD_Height'
    'water_level_baseline' => 804.25,    // NAVD88 measuring point elevation
    'water_level_warning' => 838,        // Warning threshold (ft)
    'reading_interval' => 15,            // Data interval (minutes)
    'aquifer_name' => 'Lexington Limestone',
    'description' => 'Well description...',
    'param_names' => [
        'depth' => 'Groundwater Level Elevation',
        'temperature' => 'Temperature'
    ],
    'param_units' => [
        'depth' => 'ft',
        'temperature' => '°F'
    ]
]
```

### 4. Set Up Web Server

#### For IIS (Included Configuration)
The included `web.config` provides:
- URL rewriting rules for clean URLs
- Static content caching
- Compression settings
- Security settings (hides credentials files)

No additional IIS configuration needed.

#### For Apache
Create `.htaccess` in the root directory:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /

    # Redirect /hp to /well/kgon1
    RewriteRule ^hp/?$ well.php?id=kgon1 [L,R=301]
    
    # Support /?id=wellid at root
    RewriteCond %{QUERY_STRING} ^id=([a-zA-Z0-9_-]+)$
    RewriteRule ^$ well.php?id=%1 [L]
    
    # Redirect /wellid/ to /well/wellid
    RewriteRule ^(kgon1|kgon2|kgon3|hickman1)/?$ well.php?id=$1 [L,R=301]
    
    # Rewrite /well/wellid to /well.php?id=wellid
    RewriteRule ^well/([a-zA-Z0-9_-]+)/?$ well.php?id=$1 [L]
</IfModule>
```

### 5. Create Cache Directory

The cache directory is automatically created by the application, but you can create it manually:

```bash
mkdir -p common/cache
chmod 755 common/cache
```

### 6. Test Installation

1. Visit your installation URL
2. You should see the index page listing all configured wells
3. Click on a well to view its data
4. Check browser console for any errors

## 📁 Directory Structure

```
gwmn/
├── api/                          # API endpoints
│   ├── get_well_data.php        # Main data fetching endpoint
│   └── get_well_statuses.php    # Async status checking
├── common/                       # Shared utilities
│   ├── api.php                  # API functions and caching
│   └── cache/                   # Cache directory (auto-created)
├── css/
│   └── styles.css               # Custom styles
├── images/                       # Well logos and assets
├── js/
│   ├── well-loader.js           # Main well page AJAX loader
│   ├── monthly-scripts.js       # Monthly view AJAX loader
│   └── scripts.js               # Legacy (not currently used)
├── hp/                          # Legacy directory (redirects to kgon1)
│   └── index.php
├── credentials-example.php       # Example API credentials
├── credentials.php              # Your actual credentials (NEVER COMMIT)
├── fetch_data.php               # Legacy data endpoint
├── wells_config.php             # Well configurations
├── well.php                     # Main well display page
├── monthly.php                  # Monthly data view
├── index.php                    # Landing page
├── web.config                   # IIS configuration
├── .gitignore                   # Git ignore rules
└── README.md                    # This file
```

## 🔧 Configuration Details

### Depth Measurement Methods

The system supports two methods for calculating water level elevation from raw sensor data:

#### 1. Baseline_Elev (Baseline Elevation Method)
Used when the sensor measures **height** of water above the transducer.

**Formula:** `Water Level Elevation = Baseline Elevation + (Raw Reading × 3.2084)`

**Use when:**
- Transducer is at a known fixed elevation
- Sensor measures water height above transducer
- Most common method

**Example Configuration:**
```php
'depth_method' => 'Baseline_Elev',
'water_level_baseline' => 804.25,  // Elevation of measuring point (NAVD88)
```

#### 2. TD_Height (Transducer Height Method)
Used when the sensor measures **depth** to water from a reference point.

**Formula:** `Water Level Elevation = Transducer Height - (Raw Reading × 3.2084)`

**Use when:**
- Measuring depth from ground surface or casing top
- Transducer position is above the measurement point

**Example Configuration:**
```php
'depth_method' => 'TD_Height',
'transducer_height' => 1.17,       // Height above ground (ft)
'water_well_elevation' => 421,     // Ground surface elevation (ft)
```

### Reading Intervals

The system automatically detects recording intervals but you should set the expected interval for optimal caching:

- `5` - 5-minute intervals
- `10` - 10-minute intervals
- `15` - 15-minute intervals (most common)
- `30` - 30-minute intervals
- `60` - 1-hour intervals

**Cache Consistency:** The system rounds timestamps to the nearest interval, ensuring that requests within the same interval window use the same cached data.

### Caching Strategy

**Cache Location:** `common/cache/`

**Cache Behavior:**
- OAuth tokens: 1 hour TTL
- Location data: 15 minutes TTL (default)
- Location details: 24 hours TTL
- Friendly names: 24 hours TTL

**Cache Keys:** MD5 hash of rounded timestamps and parameters

**Rate Limiting:** Automatic 250ms delays between paginated API requests

## 🎨 Customization

### Adding a New Well

1. Get the HydroVu location ID for your well from the HydroVu API
2. Determine the well's physical parameters (elevation, depth, etc.)
3. Add a new entry to `wells_config.php`:

```php
'kgon999' => [
    'well_id' => 'mywell',
    'location_id' => 'YOUR_HYDROVU_LOCATION_ID',  // ⚠️ NO LEADING/TRAILING SPACES!
    'common_name' => 'My Well',
    'well_numeric_id' => 'KGON-999',
    // ... other required fields
]
```

4. The well will automatically appear on the index page
5. URL will be: `well.php?id=kgon999`

**⚠️ CRITICAL:** Ensure `location_id` has NO leading or trailing spaces! This is the most common configuration error.

### Styling

Main styles are in `css/styles.css`. Key classes:

- `.well-card` - Individual well cards on index
- `.chart-container` - Chart wrapper
- `.latest-reading` - Latest value display boxes
- `.data-summary` - Statistics summary row
- `.hero-section` - Landing page header

### Logos and Branding

Add property owner logos to the `images/` directory and reference in well config:

```php
'property_logo' => 'images/your-logo.png'
```

## 🔌 API Integration

### HydroVu API

The application uses the HydroVu Public API v1:
- **Base URL:** `https://www.hydrovu.com/public-api/v1`
- **Authentication:** OAuth 2.0 Client Credentials
- **Documentation:** https://www.hydrovu.com/public-api/docs/

### Key API Functions (in common/api.php)

```php
// Get OAuth token (cached for 1 hour)
getOAuthToken($client_id, $client_secret, $token_url, $debug)

// Get location data for time range
getLocationData($locationId, $token, $base_url, $startTime, $endTime, $maxPages, $debug)

// Get location details (GPS coordinates, etc.)
getLocationDetails($locationId, $token, $base_url, $debug)

// Get parameter friendly names
getFriendlyNames($token, $base_url, $debug)

// Transform water level data
transformWaterLevelData($readings, $method, $baseline, $water_well_elevation, $transducer_height)

// Transform temperature data (C to F)
transformWaterTempData($readings)
```

## 📊 Chart System

### Chart Library: Chart.js 3.9.1

**Time Axis:** Uses `chartjs-adapter-date-fns` for time-based x-axis

### Chart Types by View

1. **Weekly View**
   - Downsampled to ~336 points
   - Time unit: days
   - Shows 7-day trends

2. **Daily View**
   - Full resolution
   - Time unit: hours
   - Shows 24-hour detail

3. **Monthly View**
   - Downsampled to ~200 points
   - Time unit: days
   - Shows 30-day overview

### Customizing Charts

Edit `js/well-loader.js` functions:
- `createDepthChart()` - Water level charts
- `createTemperatureChart()` - Temperature charts

## 🐛 Troubleshooting

### Common Issues

#### 1. "Failed to parse JSON response"
**Cause:** Usually a leading/trailing space in `location_id`

**Fix:** Check `wells_config.php` for spaces around location_id:
```php
// WRONG:
'location_id' => ' 5326751413305344',

// CORRECT:
'location_id' => '5326751413305344',
```

#### 2. "No Data Available" for all time ranges
**Possible Causes:**
- Wrong location_id
- API credentials invalid
- Well has no data in the time range
- Network connectivity issues

**Debug:**
Add `&debug=1` to URL: `well.php?id=kgon7&debug=1`

This will show:
- API endpoint URLs
- Timestamp ranges
- Raw API responses
- Transformation steps

#### 3. Slow Page Loading
**Causes:**
- API timeout issues
- Missing cache directory
- Too many API pagination requests

**Solutions:**
1. Check cache directory exists and is writable
2. Verify `reading_interval` matches actual well interval
3. Check API response times in browser Network tab

#### 4. Cache Not Working
**Check:**
```bash
# Verify cache directory exists
ls -la common/cache/

# Check permissions
chmod 755 common/cache/
```

#### 5. Charts Not Displaying
**Check browser console for:**
- Chart.js loading errors
- JavaScript errors
- Network failures for chart data

**Verify:**
```html
<!-- These must load in well.php -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@2.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
```

### Debug Mode

Enable debug output by adding to any page URL:
```
well.php?id=kgon1&debug=1
monthly.php?id=kgon1&debug=1
```

Debug mode shows:
- API endpoint URLs
- Timestamp conversions
- Raw API responses
- Data transformation steps
- Cache hit/miss information

### Error Logs

**IIS:** Check IIS error logs in `C:\inetpub\logs\LogFiles\`

**Apache:** Check Apache error log (location varies by system)

**PHP Errors:** Enable in `php.ini`:
```ini
display_errors = On
error_reporting = E_ALL
```

## 🔒 Security Considerations

### Credentials Protection
- `credentials.php` is in `.gitignore`
- `web.config` blocks direct access to credentials files
- Never commit API credentials to version control

### API Rate Limiting
- OAuth tokens cached for 1 hour
- Data requests cached for 15 minutes
- 250ms delay between pagination requests
- Timestamp rounding ensures cache reuse

### Input Validation
- Well IDs validated against `wells_config.php`
- Date inputs validated and sanitized
- SQL injection not applicable (no database)

## 📈 Performance Optimization

### Current Optimizations

1. **Progressive Loading**: HTML shell loads immediately, data fetches asynchronously
2. **Smart Caching**: Rounded timestamps ensure cache hits for concurrent requests
3. **Downsampling**: Large datasets reduced to optimal chart points
4. **CDN Resources**: Bootstrap and Chart.js loaded from CDN
5. **Async Status Checks**: Well statuses load independently on index page

### Recommended Optimizations

1. **Enable Gzip Compression** (already in web.config for IIS)
2. **Browser Caching**: Static assets cached for 7 days
3. **Minify CSS/JS**: Consider minification for production
4. **CDN for Images**: Consider CDN for well logos

## 🔄 Maintenance

### Regular Tasks

#### Daily
- Monitor well statuses on index page
- Check for API connectivity issues

#### Weekly
- Review cache directory size: `du -sh common/cache/`
- Clear old cache if needed: `find common/cache/ -mtime +7 -delete`

#### Monthly
- Verify all wells are reporting data
- Check API rate limit usage
- Review error logs

### Updating Wells

To update well configuration:
1. Edit `wells_config.php`
2. Update well parameters
3. Clear cache for that well: `rm common/cache/*location_{locationId}*`
4. Test on staging before production

### Backup Recommendations

**Essential Files:**
- `credentials.php` (API credentials)
- `wells_config.php` (well configurations)
- `images/` (well logos)

**Optional:**
- Cache directory (regenerates automatically)

## 🚦 Deployment Checklist

- [ ] Copy `credentials-example.php` to `credentials.php`
- [ ] Add HydroVu API credentials to `credentials.php`
- [ ] Configure all wells in `wells_config.php`
- [ ] Verify NO spaces in location_id fields
- [ ] Test cache directory creation/permissions
- [ ] Test well pages load correctly
- [ ] Verify charts display data
- [ ] Test CSV exports
- [ ] Check mobile responsiveness
- [ ] Enable error logging
- [ ] Set up monitoring/alerts
- [ ] Document custom configuration

## 📚 Additional Resources

### External Documentation
- [HydroVu API Docs](https://www.hydrovu.com/public-api/docs/)
- [Chart.js Documentation](https://www.chartjs.org/docs/latest/)
- [Bootstrap 5 Documentation](https://getbootstrap.com/docs/5.3/)

### KGS Resources
- [KGON Information](https://www.uky.edu/KGS/water/water-groundwater-monitoring.php)
- [KY Groundwater Data Repository](https://kgs.uky.edu/kgsweb/DataSearching/WaterWellSearch.asp)

## 🤝 Contributing

### Development Workflow
1. Create feature branch from main
2. Make changes
3. Test thoroughly with multiple wells
4. Update README if needed
5. Submit pull request

### Code Style
- PHP: PSR-12 coding standard
- JavaScript: ES6+ features
- Comments: Explain "why", not "what"
- Functions: Single responsibility principle

## 📝 License

[Your License Here]

## 👥 Credits

**Developed for:** Kentucky Geological Survey, University of Kentucky

**Data Provider:** HydroVu API

**Built with:**
- PHP
- Chart.js
- Bootstrap 5
- ArcGIS Online

## 📞 Support

For issues or questions:
- Create an issue in the repository
- KGS Website: https://kygs.uky.edu

---

**Last Updated:** December 2025
