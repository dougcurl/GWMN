// js/well-loader.js
const loadingMessages = {
    connecting: [
        'Waking up the sensors...',
        'Establishing secure connection...',
        'Initializing data pipeline...'
    ],
    latest: [
        'Reading latest sensor values...',
        'Checking current water levels...',
        'Retrieving real-time measurements...'
    ],
    location: [
        'Pinpointing well location...',
        'Loading satellite imagery...',
        'Mapping coordinates...'
    ],
    weekly: [
        'Analyzing weekly patterns...',
        'Crunching 7 days of data...',
        'Processing weekly trends...',
        'Downsampling readings for optimal display...'
    ],
    hourly: [
        'Examining hourly fluctuations...',
        'Loading high-resolution data...',
        'Processing 24-hour timeline...'
    ],
    finalizing: [
        'Polishing the charts...',
        'Enabling interactive features...',
        'Almost ready...'
    ]
};

// Helper to get random message
function getRandomMessage(category) {
    const messages = loadingMessages[category];
    return messages[Math.floor(Math.random() * messages.length)];
}


// Then update the function to use random messages:
async function loadWellData() {
    try {
        updateLoadingProgress(0, 'Connecting...', getRandomMessage('connecting'));
        
        // Stage 1: Load latest readings first (fastest)
        updateLoadingProgress(10, 'Loading latest readings...', getRandomMessage('latest'));
        await loadLatestReadings();
        updateLoadingProgress(25, 'Latest readings loaded ✓', 'Current values retrieved successfully');
        
        // Stage 2: Load location/map in parallel
        updateLoadingProgress(30, 'Loading well location...', getRandomMessage('location'));
        loadMap();
        updateLoadingProgress(40, 'Location loaded ✓', 'Map is ready');
        
        // Stage 3: Load charts
        updateLoadingProgress(45, 'Loading weekly data...', getRandomMessage('weekly'));
        await loadWeeklyCharts();
        updateLoadingProgress(70, 'Weekly charts loaded ✓', 'Processed weekly trends');
        
        updateLoadingProgress(75, 'Loading daily data...', getRandomMessage('hourly'));
        await loadHourlyCharts();
        updateLoadingProgress(95, 'Daily charts loaded ✓', 'Processed hourly trends');
        
        // Final touches
        updateLoadingProgress(100, 'All set! ✓', getRandomMessage('finalizing'));
        
        // Wait a tiny bit before hiding to show 100%
        setTimeout(() => {
            const loader = document.getElementById('page-loader');
            if (loader) {
                loader.classList.add('fade-out');
                setTimeout(() => {
                    loader.style.display = 'none';
                }, 300);
            }
            
            document.getElementById('last-updated').textContent = new Date().toLocaleString();
            initAnimationToggle();
        }, 400);
        
    } catch (error) {
        console.error('Error loading well data:', error);
        updateLoadingProgress(0, 'Error loading data', error.message);
        
        const loader = document.getElementById('page-loader');
        if (loader) {
            loader.innerHTML = `
                <div class="loading-content">
                    <div class="text-danger mb-3">
                        <i class="bi bi-exclamation-triangle" style="font-size: 3rem;"></i>
                    </div>
                    <h4 class="text-danger">Error Loading Data</h4>
                    <p class="text-muted">${error.message}</p>
                    <button class="btn btn-primary mt-3" onclick="location.reload()">
                        <i class="bi bi-arrow-clockwise"></i> Retry
                    </button>
                </div>
            `;
        }
    }
}

async function loadLatestReadings() {
    try {
        const response = await fetch(`api/get_well_data.php?id=${wellId}&type=latest`);
        if (!response.ok) throw new Error('Failed to fetch latest readings');
        
        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.error);
        }
        
        displayLatestReadings(data.latest);
    } catch (error) {
        console.error('Error in loadLatestReadings:', error);
        throw error;
    }
}

function displayLatestReadings(latest) {
    if (!latest) return;
    
    // Check if latest readings already exist
    let latestContainer = document.querySelector('#map-readings-container .col-lg-4');
    
    let html = '<div class="row" style="margin-top:50px;">';
    
    // Display depth/water level
    if (latest.depth) {
        const isHighLevel = latest.depth.value >= waterLevelWarning;
        html += `
            <div class="col-12 mb-3">
                <div class="latest-reading water-level ${isHighLevel ? 'high-level' : ''}">
                    <div class="latest-label">Latest Groundwater Level Elevation</div>
                    <div class="latest-value" id="latest-depth-value">
                        ${latest.depth.value.toFixed(2)} ${latest.depth.unit}
                    </div>
                    <div class="latest-time text-muted" id="latest-depth-time">
                        as of ${formatTimestampJS(latest.depth.timestamp)}
                    </div>
                    ${isHighLevel ? `
                        <div class="alert alert-danger mt-2 mb-0">
                            <strong>Warning:</strong> Groundwater Level Elevation is above warning threshold (${waterLevelWarning} ft)
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
    }
    
    // Display temperature
    if (latest.temperature) {
        html += `
            <div class="col-12 mb-3">
                <div class="latest-reading temperature">
                    <div class="latest-label">Latest Temperature</div>
                    <div class="latest-value" id="latest-temp-value">
                        ${latest.temperature.value.toFixed(2)} ${latest.temperature.unit}
                    </div>
                    <div class="latest-time text-muted" id="latest-temp-time">
                        as of ${formatTimestampJS(latest.temperature.timestamp)}
                    </div>
                </div>
            </div>
        `;
    }
    
    html += '</div>';
    
    const mapSection = document.getElementById('map-readings-container');
    
    if (mapSection) {
        if (latestContainer) {
            // UPDATE existing container
            latestContainer.innerHTML = html;
        } else {
            // CREATE new container
            latestContainer = document.createElement('div');
            latestContainer.className = 'col-lg-4 mb-4';
            latestContainer.innerHTML = html;
            mapSection.appendChild(latestContainer);
        }
    }
}

async function loadMap() {
    try {
        const response = await fetch(`api/get_well_data.php?id=${wellId}&type=location`);
        if (!response.ok) throw new Error('Failed to fetch location');
        
        const data = await response.json();
        
        if (data.error) {
            console.error('Location error:', data.error);
            return;
        }
        
        displayMap(data.location);
    } catch (error) {
        console.error('Error loading map:', error);
    }
}

function displayMap(location) {
    if (!location || !location.gps) return;
    
    const lat = location.gps.latitude;
    const lon = location.gps.longitude;
    
    const mapHtml = `
        <div class="card h-100">
            <div class="card-header">
                <strong>Well Location</strong>
                <small class="text-muted">(${lat.toFixed(6)}, ${lon.toFixed(6)})</small>
            </div>
            <div class="card-body p-0">
                <div style="height: 400px; position: relative;">
                    <iframe 
                        src="https://kygs.maps.arcgis.com/apps/instant/basic/index.html?appid=950d226696a14106938919d028b1944a&legend=false&level=16&siteid=${wellNumericId}"
                        style="width: 100%; height: 100%; border: none;"
                        title="${location.name || 'Well Location'}"
                        allowfullscreen>
                    </iframe>
                </div>
            </div>
        </div>
    `;
    
    const container = document.getElementById('map-readings-container');
    
    if (container) {
        const mapContainer = document.createElement('div');
        mapContainer.className = 'col-lg-8 mb-4';
        mapContainer.innerHTML = mapHtml;
        container.insertBefore(mapContainer, container.firstChild);
    }
}

async function loadWeeklyCharts() {
    try {
        const response = await fetch(`api/get_well_data.php?id=${wellId}&type=weekly`);
        if (!response.ok) throw new Error('Failed to fetch weekly data');
        
        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.error);
        }
        
        displayWeeklyCharts(data.weekly);
    } catch (error) {
        console.error('Error loading weekly charts:', error);
        throw error;
    }
}

function displayWeeklyCharts(weekly) {
    if (!weekly) return;
    
    const content = document.getElementById('well-content');
    const html = buildWeeklyChartsHTML(weekly);
    
    content.innerHTML = html;
    
    // Wait for DOM to update, then create charts
    setTimeout(() => {
        if (weekly.depth && weekly.depth.week_readings) {
            window.chartData = window.chartData || {};
            window.chartData.weekDepth = {
                readings: weekly.depth.week_readings,
                unit: weekly.depth.unit
            };
            createDepthChart('week-chart-depth', weekly.depth.week_readings, weekly.depth.unit, true);
        }
        if (weekly.temperature && weekly.temperature.week_readings) {
            window.chartData = window.chartData || {};
            window.chartData.weekTemp = {
                readings: weekly.temperature.week_readings,
                unit: weekly.temperature.unit
            };
            createTemperatureChart('week-chart-temperature', weekly.temperature.week_readings, weekly.temperature.unit);
        }
        
        // Reinitialize animation toggle after recreating the controls
        initAnimationToggle();
    }, 100);
}

async function loadHourlyCharts() {
    try {
        const response = await fetch(`api/get_well_data.php?id=${wellId}&type=hourly`);
        if (!response.ok) throw new Error('Failed to fetch hourly data');
        
        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.error);
        }
        
        displayHourlyCharts(data.hourly);
    } catch (error) {
        console.error('Error loading hourly charts:', error);
        throw error;
    }
}

function displayHourlyCharts(hourly) {
    if (!hourly) return;
    
    const content = document.getElementById('well-content');
    const html = buildHourlyChartsHTML(hourly);
    
    // Use insertAdjacentHTML to preserve existing charts
    content.insertAdjacentHTML('beforeend', html);
    
    // Wait for DOM to update, then create charts
    setTimeout(() => {
        if (hourly.depth && hourly.depth.hour_readings) {
            window.chartData = window.chartData || {};
            window.chartData.hourDepth = {
                readings: hourly.depth.hour_readings,
                unit: hourly.depth.unit
            };
            createDepthChart('hour-chart-depth', hourly.depth.hour_readings, hourly.depth.unit, false);
        }
        if (hourly.temperature && hourly.temperature.hour_readings) {
            window.chartData = window.chartData || {};
            window.chartData.hourTemp = {
                readings: hourly.temperature.hour_readings,
                unit: hourly.temperature.unit
            };
            createTemperatureChart('hour-chart-temperature', hourly.temperature.hour_readings, hourly.temperature.unit);
        }
    }, 100);
}

function createDepthChart(canvasId, readings, unit, downsample) {
    // Downsample for weekly view
    if (downsample && readings.length > 336) {
        readings = downsampleData(readings, 336);
    }
    
    const labels = readings.map(r => r.timestamp * 1000);
    const data = readings.map(r => parseFloat(r.value));
    
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: `Groundwater Level Elevation (${unit})`,
                data: data,
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.1)',
                borderWidth: 2,
                pointRadius: 0,
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        title: function(context) {
                            return formatTimestampJS(context[0].parsed.x / 1000);
                        },
                        label: function(context) {
                            return context.dataset.label + ': ' + context.parsed.y.toFixed(2);
                        }
                    }
                }
            },
            scales: {
                x: {
                    type: 'time',
                    time: {
                        unit: downsample ? 'day' : 'hour',
                        displayFormats: {
                            hour: 'MMM d, ha',
                            day: 'MMM d'
                        }
                    },
                    title: {
                        display: true,
                        text: 'Date/Time'
                    }
                },
                y: {
                    title: {
                        display: true,
                        text: `Water Level Elevation (${unit})`
                    }
                }
            }
        }
    });
}

function createTemperatureChart(canvasId, readings, unit) {
    const labels = readings.map(r => r.timestamp * 1000);
    const data = readings.map(r => parseFloat(r.value));
    
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: `Temperature (${unit})`,
                data: data,
                borderColor: 'rgb(255, 159, 64)',
                backgroundColor: 'rgba(255, 159, 64, 0.1)',
                borderWidth: 2,
                pointRadius: 0,
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        title: function(context) {
                            return formatTimestampJS(context[0].parsed.x / 1000);
                        },
                        label: function(context) {
                            return context.dataset.label + ': ' + context.parsed.y.toFixed(2);
                        }
                    }
                }
            },
            scales: {
                x: {
                    type: 'time',
                    time: {
                        unit: 'hour',
                        displayFormats: {
                            hour: 'MMM d, ha'
                        }
                    },
                    title: {
                        display: true,
                        text: 'Date/Time'
                    }
                },
                y: {
                    title: {
                        display: true,
                        text: `Temperature (${unit})`
                    }
                }
            }
        }
    });
}

function downsampleData(data, targetPoints) {
    if (data.length <= targetPoints) return data;
    
    const step = data.length / targetPoints;
    const result = [];
    
    for (let i = 0; i < targetPoints; i++) {
        const index = Math.floor(i * step);
        result.push(data[index]);
    }
    
    return result;
}

function formatTimestampJS(timestamp) {
    const date = new Date(timestamp * 1000);
    const month = date.getMonth() + 1;
    const day = date.getDate();
    const year = date.getFullYear() % 100;
    let hours = date.getHours();
    const minutes = date.getMinutes();
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12;
    hours = hours ? hours : 12;
    const minutesStr = minutes < 10 ? '0' + minutes : minutes;
    
    return `${month}/${day}/${year} ${hours}:${minutesStr} ${ampm}`;
}

function formatDateRange(startTimestamp, endTimestamp) {
    const startDate = new Date(startTimestamp * 1000);
    const endDate = new Date(endTimestamp * 1000);
    
    const startStr = startDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    const endStr = endDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    
    return `${startStr} to ${endStr}`;
}

function initAnimationToggle() {
    const toggle = document.getElementById('animation-toggle');
    if (toggle) {
        let animationInterval;
        let dataRefreshInterval;
        
        toggle.addEventListener('change', function() {
            if (this.checked) {
                // Animation every 30 seconds (just visual, no API call)
                animationInterval = setInterval(() => {
                    reanimateCharts();
                }, 30000);
                
                // Data refresh every 15 minutes (API call)
                dataRefreshInterval = setInterval(() => {
                    refreshAllData();
                }, 15 * 60 * 1000);
            } else {
                // Stop both intervals
                if (animationInterval) clearInterval(animationInterval);
                if (dataRefreshInterval) clearInterval(dataRefreshInterval);
            }
        });
        
        // Start intervals if checked by default
        if (toggle.checked) {
            animationInterval = setInterval(() => {
                reanimateCharts();
            }, 30000);
            
            dataRefreshInterval = setInterval(() => {
                refreshAllData();
            }, 15 * 60 * 1000);
        }
    }
}

function reanimateCharts() {
    // Destroy all existing charts
    Chart.helpers.each(Chart.instances, function(instance) {
        instance.destroy();
    });
    
    // Recreate charts with existing data
    if (window.chartData && window.chartData.weekDepth) {
        setTimeout(() => {
            createDepthChart('week-chart-depth', window.chartData.weekDepth.readings, window.chartData.weekDepth.unit, true);
        }, 100);
    }
    
    if (window.chartData && window.chartData.weekTemp) {
        setTimeout(() => {
            createTemperatureChart('week-chart-temperature', window.chartData.weekTemp.readings, window.chartData.weekTemp.unit);
        }, 200);
    }
    
    if (window.chartData && window.chartData.hourDepth) {
        setTimeout(() => {
            createDepthChart('hour-chart-depth', window.chartData.hourDepth.readings, window.chartData.hourDepth.unit, false);
        }, 300);
    }
    
    if (window.chartData && window.chartData.hourTemp) {
        setTimeout(() => {
            createTemperatureChart('hour-chart-temperature', window.chartData.hourTemp.readings, window.chartData.hourTemp.unit);
        }, 400);
    }
    
    // Update latest readings
    loadLatestReadings();
}

async function refreshAllData() {
    try {
        // Fetch fresh data
        await loadLatestReadings();
        
        const weeklyResponse = await fetch(`api/get_well_data.php?id=${wellId}&type=weekly`);
        const weeklyData = await weeklyResponse.json();
        
        const hourlyResponse = await fetch(`api/get_well_data.php?id=${wellId}&type=hourly`);
        const hourlyData = await hourlyResponse.json();
        
        // Store new data
        window.chartData = window.chartData || {};
        
        if (weeklyData.weekly) {
            if (weeklyData.weekly.depth) {
                window.chartData.weekDepth = {
                    readings: weeklyData.weekly.depth.week_readings,
                    unit: weeklyData.weekly.depth.unit
                };
            }
            if (weeklyData.weekly.temperature) {
                window.chartData.weekTemp = {
                    readings: weeklyData.weekly.temperature.week_readings,
                    unit: weeklyData.weekly.temperature.unit
                };
            }
        }
        
        if (hourlyData.hourly) {
            if (hourlyData.hourly.depth) {
                window.chartData.hourDepth = {
                    readings: hourlyData.hourly.depth.hour_readings,
                    unit: hourlyData.hourly.depth.unit
                };
            }
            if (hourlyData.hourly.temperature) {
                window.chartData.hourTemp = {
                    readings: hourlyData.hourly.temperature.hour_readings,
                    unit: hourlyData.hourly.temperature.unit
                };
            }
        }
        
        // Reanimate with fresh data
        reanimateCharts();
        
        // Update timestamp
        document.getElementById('last-updated').textContent = new Date().toLocaleString();
    } catch (error) {
        console.error('Error refreshing data:', error);
    }
}

// Date range control functions - AJAX version (no page reload)
function updateWeekRange(days) {
    const weekStartInput = document.getElementById('week_start');
    const currentStart = weekStartInput.value;
    let date;
    
    if (currentStart) {
        date = new Date(currentStart + 'T00:00:00');
    } else {
        date = new Date();
        date.setDate(date.getDate() - 7);
    }
    
    date.setDate(date.getDate() + days);
    
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const formattedDate = `${year}-${month}-${day}`;
    
    weekStartInput.value = formattedDate;
    
    // Update via AJAX instead of form submission
    loadWeeklyDataWithDate(formattedDate);
}

function updateHourRange(hours) {
    const hourStartInput = document.getElementById('hour_start');
    const currentStart = hourStartInput.value;
    let date;
    
    if (currentStart) {
        date = new Date(currentStart);
    } else {
        date = new Date();
        date.setHours(date.getHours() - 24);
    }
    
    date.setHours(date.getHours() + hours);
    
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hour = String(date.getHours()).padStart(2, '0');
    const minute = String(date.getMinutes()).padStart(2, '0');
    const formattedDate = `${year}-${month}-${day}T${hour}:${minute}`;
    
    hourStartInput.value = formattedDate;
    
    // Update via AJAX instead of form submission
    loadHourlyDataWithDate(formattedDate);
}

function resetWeekRange() {
    // Clear the input
    document.getElementById('week_start').value = '';
    
    // Reload weekly data without date parameter
    loadWeeklyDataWithDate(null);
}

function resetHourRange() {
    // Clear the input
    document.getElementById('hour_start').value = '';
    
    // Reload hourly data without date parameter
    loadHourlyDataWithDate(null);
}

// Load weekly data with specific date
async function loadWeeklyDataWithDate(weekStart) {
    try {
        // Show loading spinner
        showWeeklyLoadingSpinner();
        
        // Build URL with date parameter
        let url = `api/get_well_data.php?id=${wellId}&type=weekly`;
        if (weekStart) {
            url += `&week_start=${weekStart}`;
        }
        
        const response = await fetch(url);
        if (!response.ok) throw new Error('Failed to fetch weekly data');
        
        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.error);
        }
        
        // Find and remove old weekly content
        const wellContent = document.getElementById('well-content');
        const hourlyHeaderElement = document.getElementById('hourly-data-header');
        
        // Remove everything before the hourly section
        while (wellContent.firstChild && wellContent.firstChild !== hourlyHeaderElement) {
            wellContent.removeChild(wellContent.firstChild);
        }
        
        // Create a temporary container for new weekly content
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = buildWeeklyChartsHTML(data.weekly);
        
        // Insert all new weekly content before hourly section
        while (tempDiv.firstChild) {
            wellContent.insertBefore(tempDiv.firstChild, hourlyHeaderElement);
        }
        
        // Wait a bit then create the charts
        setTimeout(() => {
            if (data.weekly.depth && data.weekly.depth.week_readings) {
                window.chartData = window.chartData || {};
                window.chartData.weekDepth = {
                    readings: data.weekly.depth.week_readings,
                    unit: data.weekly.depth.unit
                };
                createDepthChart('week-chart-depth', data.weekly.depth.week_readings, data.weekly.depth.unit, true);
            }
            if (data.weekly.temperature && data.weekly.temperature.week_readings) {
                window.chartData = window.chartData || {};
                window.chartData.weekTemp = {
                    readings: data.weekly.temperature.week_readings,
                    unit: data.weekly.temperature.unit
                };
                createTemperatureChart('week-chart-temperature', data.weekly.temperature.week_readings, data.weekly.temperature.unit);
            }
            
            // Reinitialize animation toggle
            initAnimationToggle();
            
            // Hide loading spinner
            hideWeeklyLoadingSpinner();
        }, 100);
        
        // Update URL without page reload
        const newUrl = new URL(window.location);
        if (weekStart) {
            newUrl.searchParams.set('week_start', weekStart);
        } else {
            newUrl.searchParams.delete('week_start');
        }
        window.history.pushState({}, '', newUrl);
        
    } catch (error) {
        console.error('Error loading weekly data:', error);
        hideWeeklyLoadingSpinner();
        alert('Error loading weekly data: ' + error.message);
    }
}

// Load hourly data with specific date
async function loadHourlyDataWithDate(hourStart) {
    try {
        // Show loading spinner
        showHourlyLoadingSpinner();
        
        // Build URL with date parameter
        let url = `api/get_well_data.php?id=${wellId}&type=hourly`;
        if (hourStart) {
            url += `&hour_start=${hourStart}`;
        }
        
        const response = await fetch(url);
        if (!response.ok) throw new Error('Failed to fetch hourly data');
        
        const data = await response.json();
        
        if (data.error) {
            throw new Error(data.error);
        }
        
        // Remove old hourly content (everything from hourly header onwards)
        const hourlyHeaderElement = document.getElementById('hourly-data-header');
        const cardsToRemove = [];
        let elem = hourlyHeaderElement.nextElementSibling;
        
        while (elem) {
            if (elem.classList.contains('card')) {
                cardsToRemove.push(elem);
            }
            elem = elem.nextElementSibling;
        }
        
        hourlyHeaderElement.remove();
        cardsToRemove.forEach(card => card.remove());
        
        // Build and append new hourly content
        const hourlyHTML = buildHourlyChartsHTML(data.hourly);
        const wellContent = document.getElementById('well-content');
        wellContent.insertAdjacentHTML('beforeend', hourlyHTML);
        
        // Wait a bit then create the charts
        setTimeout(() => {
            if (data.hourly.depth && data.hourly.depth.hour_readings) {
                window.chartData = window.chartData || {};
                window.chartData.hourDepth = {
                    readings: data.hourly.depth.hour_readings,
                    unit: data.hourly.depth.unit
                };
                createDepthChart('hour-chart-depth', data.hourly.depth.hour_readings, data.hourly.depth.unit, false);
            }
            if (data.hourly.temperature && data.hourly.temperature.hour_readings) {
                window.chartData = window.chartData || {};
                window.chartData.hourTemp = {
                    readings: data.hourly.temperature.hour_readings,
                    unit: data.hourly.temperature.unit
                };
                createTemperatureChart('hour-chart-temperature', data.hourly.temperature.hour_readings, data.hourly.temperature.unit);
            }
            
            // Hide loading spinner and scroll
            hideHourlyLoadingSpinner();
            
            const newHourlyHeader = document.getElementById('hourly-data-header');
            if (newHourlyHeader) {
                newHourlyHeader.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }, 100);
        
        // Update URL without page reload
        const newUrl = new URL(window.location);
        if (hourStart) {
            newUrl.searchParams.set('hour_start', hourStart);
        } else {
            newUrl.searchParams.delete('hour_start');
        }
        window.history.pushState({}, '', newUrl);
        
    } catch (error) {
        console.error('Error loading hourly data:', error);
        hideHourlyLoadingSpinner();
        alert('Error loading hourly data: ' + error.message);
    }
}

// Helper functions for loading spinners
function showWeeklyLoadingSpinner() {
    const weeklyHeader = document.getElementById('weekly-data-header');
    if (!weeklyHeader) return;
    
    // Add loading class to header
    weeklyHeader.classList.add('section-loading');
    
    // Find all weekly cards and add loading overlay
    let elem = weeklyHeader.nextElementSibling;
    while (elem && elem.id !== 'hourly-data-header') {
        if (elem.classList.contains('card')) {
            const overlay = document.createElement('div');
            overlay.className = 'chart-loading-overlay';
            overlay.innerHTML = `
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p>Loading weekly data...</p>
            `;
            elem.style.position = 'relative';
            elem.appendChild(overlay);
        }
        elem = elem.nextElementSibling;
    }
}

function hideWeeklyLoadingSpinner() {
    const weeklyHeader = document.getElementById('weekly-data-header');
    if (weeklyHeader) {
        weeklyHeader.classList.remove('section-loading');
    }
    
    // Remove all loading overlays from weekly cards
    let elem = weeklyHeader ? weeklyHeader.nextElementSibling : null;
    while (elem && elem.id !== 'hourly-data-header') {
        if (elem.classList.contains('card')) {
            const overlay = elem.querySelector('.chart-loading-overlay');
            if (overlay) {
                overlay.remove();
            }
        }
        elem = elem.nextElementSibling;
    }
}

function showHourlyLoadingSpinner() {
    const hourlyHeader = document.getElementById('hourly-data-header');
    if (!hourlyHeader) return;
    
    // Add loading class to header
    hourlyHeader.classList.add('section-loading');
    
    // Find all hourly cards and add loading overlay
    let elem = hourlyHeader.nextElementSibling;
    while (elem) {
        if (elem.classList.contains('card')) {
            const overlay = document.createElement('div');
            overlay.className = 'chart-loading-overlay';
            overlay.innerHTML = `
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p>Loading daily data...</p>
            `;
            elem.style.position = 'relative';
            elem.appendChild(overlay);
        }
        elem = elem.nextElementSibling;
    }
}

function hideHourlyLoadingSpinner() {
    const hourlyHeader = document.getElementById('hourly-data-header');
    if (hourlyHeader) {
        hourlyHeader.classList.remove('section-loading');
    }
    
    // Remove all loading overlays from hourly cards
    let elem = hourlyHeader ? hourlyHeader.nextElementSibling : null;
    while (elem) {
        if (elem.classList.contains('card')) {
            const overlay = elem.querySelector('.chart-loading-overlay');
            if (overlay) {
                overlay.remove();
            }
        }
        elem = elem.nextElementSibling;
    }
}

// Helper function to build weekly charts HTML (extracted from displayWeeklyCharts)
function buildWeeklyChartsHTML(weekly) {
    if (!weekly) return '';
    
    let html = '';
    
    // Add animation controls and monthly button
    html += `
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="time-control">
                <div class="animation-toggle">
                    <span>Animation:</span>
                    <label class="toggle-switch">
                        <input type="checkbox" id="animation-toggle" checked>
                        <span class="toggle-slider"></span>
                    </label>
                    <span>Refresh graphs every 30s</span>
                </div>
                <small class="text-muted">(Data updates every 15 minutes)</small>
            </div>
            <div class="time-control">
                <a href="monthly.php?id=${wellId}" class="btn btn-outline-primary monthly-view-btn">View Monthly Data</a>
            </div>
        </div>
    `;
    
    // Calculate date range for weekly data
    let weekStart = null;
    let weekEnd = null;
    if (weekly.depth && weekly.depth.week_readings && weekly.depth.week_readings.length > 0) {
        const readings = weekly.depth.week_readings;
        weekStart = readings[0].timestamp;
        weekEnd = readings[readings.length - 1].timestamp;
    } else if (weekly.temperature && weekly.temperature.week_readings && weekly.temperature.week_readings.length > 0) {
        const readings = weekly.temperature.week_readings;
        weekStart = readings[0].timestamp;
        weekEnd = readings[readings.length - 1].timestamp;
    }
    
    // Get current time for determining if this is the current week
    const currentTime = Math.floor(Date.now() / 1000);
    const isCurrentWeek = weekEnd && (weekEnd >= currentTime - 3600);
    
    // Weekly data header with controls
    html += `
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" id="weekly-data-header">
            <div>
                <h2>Weekly Data</h2>
                <h4 class="section-title mb-0" id="weekly-date-range">${weekStart && weekEnd ? formatDateRange(weekStart, weekEnd) : ''}</h4>
            </div>
            <div class="time-control">
                <form onsubmit="event.preventDefault(); loadWeeklyDataWithDate(document.getElementById('week_start').value);" class="form-inline d-inline text-end" id="weekForm">
                    <input type="hidden" name="id" value="${wellId}">
                    <div class="d-flex justify-content-end align-items-center mb-2">
                        <label class="me-2 small">Week Start Date:</label>
                        <input type="date" 
                            name="week_start" 
                            id="week_start" 
                            class="form-control form-control-sm d-inline w-auto me-2" 
                            value="${weekStart ? new Date(weekStart * 1000).toISOString().split('T')[0] : ''}"
                            max="${new Date().toISOString().split('T')[0]}">
                        <button type="submit" class="btn btn-sm btn-primary me-2">Go</button>
                    </div>
                    <div style="padding-top:8px;padding-right:6px;">
                        <button type="button" class="btn btn-sm btn-outline-secondary me-1" onclick="updateWeekRange(-7)">« Previous Week</button>
                        ${!isCurrentWeek ? '<button type="button" class="btn btn-sm btn-outline-secondary me-1" onclick="updateWeekRange(7)">Next Week »</button>' : ''}
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetWeekRange()">Reset</button>
                    </div>
                </form>
            </div>
        </div>
    `;
    
    // Depth chart
    if (weekly.depth && weekly.depth.week_readings) {
        const readings = weekly.depth.week_readings;
        const values = readings.map(r => r.value);
        const min = Math.min(...values);
        const max = Math.max(...values);
        const avg = values.reduce((a, b) => a + b, 0) / values.length;
        const aboveThreshold = values.filter(v => v >= waterLevelWarning).length;
        
        html += `
            <div class="card mb-4">
                <div class="card-header">
                    <span>Groundwater Level Elevation - Weekly Trend (30 min intervals)</span>
                </div>
                <div class="card-body">
                    <div class="data-summary mb-3">
                        <div class="summary-item">
                            <div class="summary-label">Min</div>
                            <div class="summary-value">${min.toFixed(2)} ${weekly.depth.unit}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">Max</div>
                            <div class="summary-value">${max.toFixed(2)} ${weekly.depth.unit}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">Average</div>
                            <div class="summary-value">${avg.toFixed(2)} ${weekly.depth.unit}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">Readings</div>
                            <div class="summary-value">${readings.length}</div>
                        </div>
                        ${aboveThreshold > 0 ? `
                            <div class="summary-item">
                                <div class="summary-label">Above Warning</div>
                                <div class="summary-value">${aboveThreshold}</div>
                            </div>
                        ` : ''}
                    </div>
                    <div class="chart-container" style="position: relative; height: 400px;">
                        <canvas id="week-chart-depth"></canvas>
                    </div>
                    <div class="text-end mt-3">
                        <button class="btn btn-sm btn-outline-primary" onclick="exportToCSV('Depth', 'weekly')">
                            <i class="bi bi-download"></i> Export Weekly Groundwater Level Data (15 min intervals)
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        // Store data for export
        window.exportData = window.exportData || {};
        window.exportData.depth_weekly = {
            readings: readings,
            unit: weekly.depth.unit
        };
    }
    
    // Temperature chart
    if (weekly.temperature && weekly.temperature.week_readings) {
        const readings = weekly.temperature.week_readings;
        const values = readings.map(r => r.value);
        const min = Math.min(...values);
        const max = Math.max(...values);
        const avg = values.reduce((a, b) => a + b, 0) / values.length;
        
        html += `
            <div class="card mb-4">
                <div class="card-header">
                    <span>Temperature - Weekly Trend (30 min intervals)</span>
                </div>
                <div class="card-body">
                    <div class="data-summary mb-3">
                        <div class="summary-item">
                            <div class="summary-label">Min</div>
                            <div class="summary-value">${min.toFixed(2)} ${weekly.temperature.unit}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">Max</div>
                            <div class="summary-value">${max.toFixed(2)} ${weekly.temperature.unit}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">Average</div>
                            <div class="summary-value">${avg.toFixed(2)} ${weekly.temperature.unit}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">Readings</div>
                            <div class="summary-value">${readings.length}</div>
                        </div>
                    </div>
                    <div class="chart-container" style="position: relative; height: 400px;">
                        <canvas id="week-chart-temperature"></canvas>
                    </div>
                    <div class="text-end mt-3">
                        <button class="btn btn-sm btn-outline-primary" onclick="exportToCSV('Temperature', 'weekly')">
                            <i class="bi bi-download"></i> Export Weekly Temperature Data (15 min intervals)
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        window.exportData = window.exportData || {};
        window.exportData.temperature_weekly = {
            readings: readings,
            unit: weekly.temperature.unit
        };
    }
    
    return html;
}

// Helper function to build hourly charts HTML (extracted from displayHourlyCharts)
function buildHourlyChartsHTML(hourly) {
    if (!hourly) return '';
    
    // Calculate date range for hourly data
    let hourStart = null;
    let hourEnd = null;
    if (hourly.depth && hourly.depth.hour_readings && hourly.depth.hour_readings.length > 0) {
        const readings = hourly.depth.hour_readings;
        hourStart = readings[0].timestamp;
        hourEnd = readings[readings.length - 1].timestamp;
    } else if (hourly.temperature && hourly.temperature.hour_readings && hourly.temperature.hour_readings.length > 0) {
        const readings = hourly.temperature.hour_readings;
        hourStart = readings[0].timestamp;
        hourEnd = readings[readings.length - 1].timestamp;
    }
    
    // Get current time for determining if this is the current day
    const currentTime = Math.floor(Date.now() / 1000);
    const isCurrentDay = hourEnd && (hourEnd >= currentTime - 3600);
    
    // Format datetime for input (YYYY-MM-DDTHH:mm)
    const formatDateTimeForInput = (timestamp) => {
        const date = new Date(timestamp * 1000);
        return date.toISOString().slice(0, 16);
    };
    
    // Hourly data header with controls
    let html = `
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap" style="margin-top:80px;" id="hourly-data-header">
            <div>
                <h2>Daily Data</h2>
                <h4 class="section-title mb-0" id="hourly-date-range">${hourStart && hourEnd ? formatDateRange(hourStart, hourEnd) : ''}</h4>
            </div>
            <div class="time-control">
                <form onsubmit="event.preventDefault(); loadHourlyDataWithDate(document.getElementById('hour_start').value);" class="form-inline d-inline text-end" id="hourForm">
                    <input type="hidden" name="id" value="${wellId}">
                    <div class="mb-2">
                        <label class="me-2 small">Start Date/Time:</label>
                        <input type="datetime-local" 
                            name="hour_start" 
                            id="hour_start" 
                            class="form-control form-control-sm d-inline w-auto me-2" 
                            value="${hourStart ? formatDateTimeForInput(hourStart) : ''}"
                            max="${new Date().toISOString().slice(0, 16)}">
                        <button type="submit" class="btn btn-sm btn-primary me-2">Go</button>
                    </div>
                    <div style="padding-top:8px;padding-right:6px;">
                        <button type="button" class="btn btn-sm btn-outline-secondary me-1" onclick="updateHourRange(-24)">« Previous Day</button>
                        ${!isCurrentDay ? '<button type="button" class="btn btn-sm btn-outline-secondary me-1" onclick="updateHourRange(24)">Next Day »</button>' : ''}
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetHourRange()">Reset</button>
                    </div>
                </form>
            </div>
        </div>
    `;
    
    // Depth chart
    if (hourly.depth && hourly.depth.hour_readings) {
        const readings = hourly.depth.hour_readings;
        const values = readings.map(r => r.value);
        const min = Math.min(...values);
        const max = Math.max(...values);
        const avg = values.reduce((a, b) => a + b, 0) / values.length;
        const aboveThreshold = values.filter(v => v >= waterLevelWarning).length;
        
        html += `
            <div class="card mb-4">
                <div class="card-header">
                    <span>Groundwater Level Elevation - Hourly Trend (15 min intervals)</span>
                </div>
                <div class="card-body">
                    <div class="data-summary mb-3">
                        <div class="summary-item">
                            <div class="summary-label">Min</div>
                            <div class="summary-value">${min.toFixed(2)} ${hourly.depth.unit}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">Max</div>
                            <div class="summary-value">${max.toFixed(2)} ${hourly.depth.unit}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">Average</div>
                            <div class="summary-value">${avg.toFixed(2)} ${hourly.depth.unit}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">Readings</div>
                            <div class="summary-value">${readings.length}</div>
                        </div>
                        ${aboveThreshold > 0 ? `
                            <div class="summary-item">
                                <div class="summary-label">Above Warning</div>
                                <div class="summary-value">${aboveThreshold}</div>
                            </div>
                        ` : ''}
                    </div>
                    <div class="chart-container" style="position: relative; height: 400px;">
                        <canvas id="hour-chart-depth"></canvas>
                    </div>
                    <div class="text-end mt-3">
                        <button class="btn btn-sm btn-outline-primary" onclick="exportToCSV('Depth', 'hourly')">
                            <i class="bi bi-download"></i> Export Hourly Groundwater Level Data (15 min intervals)
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        window.exportData = window.exportData || {};
        window.exportData.depth_hourly = {
            readings: readings,
            unit: hourly.depth.unit
        };
    }
    
    // Temperature chart
    if (hourly.temperature && hourly.temperature.hour_readings) {
        const readings = hourly.temperature.hour_readings;
        const values = readings.map(r => r.value);
        const min = Math.min(...values);
        const max = Math.max(...values);
        const avg = values.reduce((a, b) => a + b, 0) / values.length;
        
        html += `
            <div class="card mb-4">
                <div class="card-header">
                    <span>Temperature - Hourly Trend (15 min intervals)</span>
                </div>
                <div class="card-body">
                    <div class="data-summary mb-3">
                        <div class="summary-item">
                            <div class="summary-label">Min</div>
                            <div class="summary-value">${min.toFixed(2)} ${hourly.temperature.unit}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">Max</div>
                            <div class="summary-value">${max.toFixed(2)} ${hourly.temperature.unit}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">Average</div>
                            <div class="summary-value">${avg.toFixed(2)} ${hourly.temperature.unit}</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">Readings</div>
                            <div class="summary-value">${readings.length}</div>
                        </div>
                    </div>
                    <div class="chart-container" style="position: relative; height: 400px;">
                        <canvas id="hour-chart-temperature"></canvas>
                    </div>
                    <div class="text-end mt-3">
                        <button class="btn btn-sm btn-outline-primary" onclick="exportToCSV('Temperature', 'hourly')">
                            <i class="bi bi-download"></i> Export Hourly Temperature Data (15 min intervals)
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        window.exportData = window.exportData || {};
        window.exportData.temperature_hourly = {
            readings: readings,
            unit: hourly.temperature.unit
        };
    }
    
    return html;
}

function updateLoadingProgress(percentage, message, detail) {
    const progressBar = document.getElementById('loading-progress');
    const messageEl = document.getElementById('loading-message');
    const detailEl = document.getElementById('loading-detail');
    
    if (progressBar) {
        progressBar.style.width = percentage + '%';
        progressBar.setAttribute('aria-valuenow', percentage);
    }
    
    if (messageEl && message) {
        messageEl.textContent = message;
    }
    
    if (detailEl && detail) {
        detailEl.textContent = detail;
    }
}

async function loadWellData() {
    try {
        updateLoadingProgress(0, 'Connecting...', 'Establishing connection to data source');
        
        // Stage 1: Load latest readings first (fastest)
        updateLoadingProgress(10, 'Loading latest readings...', 'Fetching current sensor values');
        await loadLatestReadings();
        updateLoadingProgress(25, 'Latest readings loaded', 'Current values retrieved successfully');
        
        // Stage 2: Load location/map in parallel
        updateLoadingProgress(30, 'Loading well location...', 'Retrieving GPS coordinates and map data');
        loadMap();
        updateLoadingProgress(40, 'Location loaded', 'Map is ready');
        
        // Stage 3: Load charts
        updateLoadingProgress(45, 'Loading weekly data...', 'Fetching 7 days of readings');
        await loadWeeklyCharts();
        updateLoadingProgress(70, 'Weekly charts loaded', 'Processed weekly trends');
        
        updateLoadingProgress(75, 'Loading daily data...', 'Fetching 24 hours of readings');
        await loadHourlyCharts();
        updateLoadingProgress(95, 'Daily charts loaded', 'Processed hourly trends');
        
        // Final touches
        updateLoadingProgress(100, 'Finalizing...', 'Setting up interactive features');
        
        // Wait a tiny bit before hiding to show 100%
        setTimeout(() => {
            // Hide loader with fade effect
            const loader = document.getElementById('page-loader');
            if (loader) {
                loader.classList.add('fade-out');
                setTimeout(() => {
                    loader.style.display = 'none';
                }, 300);
            }
            
            document.getElementById('last-updated').textContent = new Date().toLocaleString();
            
            // Initialize animation toggle
            initAnimationToggle();
        }, 200);
        
    } catch (error) {
        console.error('Error loading well data:', error);
        updateLoadingProgress(0, 'Error loading data', error.message);
        
        // Show error state
        const loader = document.getElementById('page-loader');
        if (loader) {
            loader.innerHTML = `
                <div class="loading-content">
                    <div class="text-danger mb-3">
                        <i class="bi bi-exclamation-triangle" style="font-size: 3rem;"></i>
                    </div>
                    <h4 class="text-danger">Error Loading Data</h4>
                    <p class="text-muted">${error.message}</p>
                    <button class="btn btn-primary mt-3" onclick="location.reload()">
                        <i class="bi bi-arrow-clockwise"></i> Retry
                    </button>
                </div>
            `;
        }
    }
}

// Export to CSV function
function exportToCSV(paramType, timeRange) {
    const key = `${paramType.toLowerCase()}_${timeRange}`;
    const exportDataItem = window.exportData[key];
    
    if (!exportDataItem) {
        alert('No data available to export');
        return;
    }
    
    let csv = 'Timestamp,Value,Unit\n';
    exportDataItem.readings.forEach(reading => {
        const dateStr = formatTimestampJS(reading.timestamp);
        csv += `${dateStr},${reading.value},${exportDataItem.unit}\n`;
    });
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${wellId}_${paramType}_${timeRange}_${Date.now()}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}