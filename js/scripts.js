// Global variables
let animationEnabled = false;
let autoRefreshTimer = null;
let animationTimer = null;
let charts = {};
let latestData = {};
// Global variables to store data
let exportData = {};


// Initialize when document is ready
document.addEventListener('DOMContentLoaded', function() {
    // Show page loader
    const pageLoader = document.getElementById('page-loader');
    if (pageLoader) {
        pageLoader.style.display = 'flex';
    }
    
    // Initialize all charts
    initializeCharts();
    
    // Set up animation toggle
    setupAnimationToggle();
    
    // Set up auto-refresh (15 minutes)
    startAutoRefresh();
    
    // Hide loader after initialization
    if (pageLoader) {
        setTimeout(() => {
            pageLoader.style.opacity = 0;
            setTimeout(() => {
                pageLoader.style.display = 'none';
            }, 300);
        }, 500);
    }
});

// Initialize all charts
function initializeCharts() {
    // Week charts
    if (document.getElementById('week-chart-depth')) {
        charts.weekDepth = createChart('week-chart-depth', weekDepthLabels, weekDepthData, 'Groundwater Level Elevation (ft)', 'rgba(75, 192, 192, 1)', 'rgba(75, 192, 192, 0.1)');
    }
    
    if (document.getElementById('week-chart-temperature')) {
        charts.weekTemp = createChart('week-chart-temperature', weekTempLabels, weekTempData, 'Temperature (°F)', 'rgba(255, 205, 86, 1)', 'rgba(255, 205, 86, 0.1)');
    }
    
    // Hour charts
    if (document.getElementById('hour-chart-depth')) {
        charts.hourDepth = createChart('hour-chart-depth', hourDepthLabels, hourDepthData, 'Groundwater Level Elevation (ft)', 'rgba(153, 102, 255, 1)', 'rgba(153, 102, 255, 0.1)');
    }
    
    if (document.getElementById('hour-chart-temperature')) {
        charts.hourTemp = createChart('hour-chart-temperature', hourTempLabels, hourTempData, 'Temperature (°F)', 'rgba(255, 99, 132, 1)', 'rgba(255, 99, 132, 0.1)');
    }
    
    // Store latest data for animations
    latestData = {
        weekDepth: {
            labels: weekDepthLabels.slice(),
            data: weekDepthData.slice()
        },
        weekTemp: {
            labels: weekTempLabels.slice(),
            data: weekTempData.slice()
        },
        hourDepth: {
            labels: hourDepthLabels.slice(),
            data: hourDepthData.slice()
        },
        hourTemp: {
            labels: hourTempLabels.slice(),
            data: hourTempData.slice()
        }
    };
}

// Create a chart
function createChart(canvasId, labels, data, label, borderColor, backgroundColor) {
    const ctx = document.getElementById(canvasId).getContext('2d');
    
    // Check if the data is for water level to add threshold line
    const isWaterLevel = label.includes('Groundwater Level Elevation');
    
    // Format the timestamps in the labels array
    const formattedLabels = labels.map(timestamp => {
        // Check if timestamp is a number or string
        if (typeof timestamp === 'string' && timestamp.match(/^\d+$/)) {
            timestamp = parseInt(timestamp);
        }
        
        // Format the timestamp
        try {
            const date = new Date(timestamp * 1000);
            
            // Format date as MM/DD/YY
            const dateStr = date.toLocaleDateString('en-US', {
                month: 'numeric',
                day: 'numeric',
                year: '2-digit'
            });
            
            // Format time as HH:MM AM/PM
            const timeStr = date.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            });
            return dateStr + '\n' + timeStr;
        } catch (e) {
            console.error("Error formatting timestamp:", timestamp, e);
            return timestamp; // Return original if formatting fails
        }
    });


    // Calculate min and max values for better scaling
    let minValue = Math.min(...data);
    let maxValue = Math.max(...data);
    
    // Add padding to min/max (5% of the data range)
    const range = maxValue - minValue;
    const padding = range * 0.05;
    minValue = Math.max(0, minValue - padding); // Don't go below 0 if data is all positive
    maxValue = maxValue + padding;
    
    // Only include warning threshold in scale if data is close to it
    let includeWarningInScale = false;
    if (isWaterLevel) {
        // If data is within 30% of the warning threshold, or exceeds it, include it in scale
        if (maxValue >= waterLevelWarning) {
            includeWarningInScale = true;
            // If max is less than warning, extend max to include warning with some padding
            if (maxValue < waterLevelWarning) {
                maxValue = waterLevelWarning + padding;
            }
        }
    }
    
    // Create chart configuration
    const chartConfig = {
        type: 'line',
        data: {
            labels: formattedLabels,
            datasets: [{
                label: label,
                data: data,
                borderColor: borderColor,
                backgroundColor: backgroundColor,
                fill: true,
                pointRadius: data.length > 50 ? 0 : 2,
                tension: 0.2,
                borderWidth: 2,
                pointBackgroundColor: function(context) {
                    // Mark points above warning threshold for water level
                    if (isWaterLevel && context.raw >= waterLevelWarning) {
                        return 'rgba(255, 0, 0, 1)';
                    }else {
                        return 'rgba(113, 113, 233, 0.5)';
                    }
                    return borderColor;
                },
                segment: {
                    borderColor: function(context) {
                        // Change line color above warning threshold for water level
                        if (isWaterLevel) {
                            const valueAfter = context.p1.parsed.y;
                            if (valueAfter >= waterLevelWarning) {
                                return 'rgba(255, 0, 0, 1)';
                            }
                        }
                        return borderColor;
                    }
                }
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 0  // Disable initial animation
            },
            scales: {
                x: {
                    ticks: {
                        maxRotation: 0,         // Keep labels horizontal
                        minRotation: 0,         // Keep labels horizontal
                        autoSkip: true,
                        maxTicksLimit: 8,       // Limit number of ticks to prevent overcrowding
                        padding: 10,            // Add padding for multi-line labels
                        font: {
                            size: 12            // Smaller font for better fit
                        }
                    }
                },
                y: {
                    min: minValue,
                    max: maxValue,
                    ticks: {
                        callback: function(value) {
                            return value.toFixed(2);
                        }
                    }
                }
            },
            plugins: {
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        label: function(context) {
                            let value = parseFloat(context.raw).toFixed(2);
                            let label = context.dataset.label;
                            
                            // For groundwater level elevation, show "Elevation: XX.XX ft"
                            if (label.includes('Groundwater Level Elevation')) {
                                return 'Elevation: ' + value + ' ft';
                            }
                            // For temperature, show "Temperature: XX.XX °F"
                            else if (label.includes('Temperature')) {
                                return label + ': ' + value;
                            }
                            // For other parameters, show as is
                            else {
                                return label + ': ' + value;
                            }
                        }
                    }
                }
            }
        }
    };

    // Add warning line only if it's relevant to the data
    if (isWaterLevel && includeWarningInScale) {
        // Add a horizontal line dataset for the warning threshold
        chartConfig.data.datasets.push({
            label: 'Warning Threshold',
            data: Array(labels.length).fill(waterLevelWarning),
            borderColor: 'rgba(255, 0, 0, 0.5)',
            borderWidth: 2,
            borderDash: [5, 5],
            fill: false,
            pointRadius: 0
        });
    }

    // Create and return the chart
    return new Chart(ctx, chartConfig);
}

// Set up animation toggle
function setupAnimationToggle() {
    const toggleCheckbox = document.getElementById('animation-toggle');
    
    if (toggleCheckbox) {
        // Set initial state
        animationEnabled = toggleCheckbox.checked;
        
        // Add event listener
        toggleCheckbox.addEventListener('change', function() {
            animationEnabled = this.checked;
            
            if (animationEnabled) {
                startAnimation();
            } else {
                stopAnimation();
            }
        });
        
        // Start animation if enabled by default
        if (animationEnabled) {
            startAnimation();
        }
    }
}

// Start auto-refresh timer (15 minutes)
function startAutoRefresh() {
    autoRefreshTimer = setInterval(function() {
        refreshData();
    }, 15 * 60 * 1000);  // 15 minutes
}

// Refresh data from server
function refreshData() {
    // Show loading indicators
    document.querySelectorAll('.loading-overlay').forEach(function(overlay) {
        overlay.classList.add('active');
    });
    
    // Use fetch to get updated data
    fetch('fetch_data.php')
        .then(response => response.json())
        .then(data => {
            // Update latest readings
            updateLatestReadings(data);
            
            // Update chart data
            updateChartData(data);
            
            // Hide loading indicators
            document.querySelectorAll('.loading-overlay').forEach(function(overlay) {
                overlay.classList.remove('active');
            });
        })
        .catch(error => {
            console.error('Error refreshing data:', error);
            
            // Hide loading indicators
            document.querySelectorAll('.loading-overlay').forEach(function(overlay) {
                overlay.classList.remove('active');
            });
        });
}

// Update latest readings display
function updateLatestReadings(data) {
    // Update water level
    if (data.depth && data.depth.latest) {
        const depthValue = document.getElementById('latest-depth-value');
        const depthTime = document.getElementById('latest-depth-time');
        
        if (depthValue) {
            depthValue.textContent = data.depth.latest.value.toFixed(2) + ' ft';
            
            // Check if above warning threshold
            const depthContainer = document.querySelector('.water-level');
            if (depthContainer) {
                if (data.depth.latest.value >= waterLevelWarning) {
                    depthContainer.classList.add('high-level');
                } else {
                    depthContainer.classList.remove('high-level');
                }
            }
        }
        
        if (depthTime) {
            depthTime.textContent = 'as of ' + formatDateTime_t(data.depth.latest.timestamp);
        }
    }
    
    // Update temperature
    if (data.temperature && data.temperature.latest) {
        const tempValue = document.getElementById('latest-temp-value');
        const tempTime = document.getElementById('latest-temp-time');
        
        if (tempValue) {
            tempValue.textContent = data.temperature.latest.value.toFixed(2) + ' °F';
        }
        
        if (tempTime) {
            tempTime.textContent = 'as of ' + formatDateTime_t(data.temperature.latest.timestamp);
        }
    }
}

// Update chart data
function updateChartData(data) {
    // Update water level charts
    if (data.depth) {
        if (data.depth.week && charts.weekDepth) {
            latestData.weekDepth.labels = data.depth.week.map(r => r.timestamp);
            latestData.weekDepth.data = data.depth.week.map(r => r.value);
            
            if (!animationEnabled) {
                charts.weekDepth.data.labels = latestData.weekDepth.labels;
                charts.weekDepth.data.datasets[0].data = latestData.weekDepth.data;
                charts.weekDepth.update();
            }
        }
        
        if (data.depth.hour && charts.hourDepth) {
            latestData.hourDepth.labels = data.depth.hour.map(r => r.timestamp);
            latestData.hourDepth.data = data.depth.hour.map(r => r.value);
            
            if (!animationEnabled) {
                charts.hourDepth.data.labels = latestData.hourDepth.labels;
                charts.hourDepth.data.datasets[0].data = latestData.hourDepth.data;
                charts.hourDepth.update();
            }
        }
    }
    
    // Update temperature charts
    if (data.temperature) {
        if (data.temperature.week && charts.weekTemp) {
            latestData.weekTemp.labels = data.temperature.week.map(r => r.timestamp);
            latestData.weekTemp.data = data.temperature.week.map(r => r.value);
            
            if (!animationEnabled) {
                charts.weekTemp.data.labels = latestData.weekTemp.labels;
                charts.weekTemp.data.datasets[0].data = latestData.weekTemp.data;
                charts.weekTemp.update();
            }
        }
        
        if (data.temperature.hour && charts.hourTemp) {
            latestData.hourTemp.labels = data.temperature.hour.map(r => r.timestamp);
            latestData.hourTemp.data = data.temperature.hour.map(r => r.value);
            
            if (!animationEnabled) {
                charts.hourTemp.data.labels = latestData.hourTemp.labels;
                charts.hourTemp.data.datasets[0].data = latestData.hourTemp.data;
                charts.hourTemp.update();
            }
        }
    }
    
    // If animation is enabled, restart it with the new data
    if (animationEnabled) {
        stopAnimation();
        startAnimation();
    }
}

// Start animation cycle
function startAnimation() {
    if (animationTimer) {
        clearInterval(animationTimer);
    }
    
    animationTimer = setInterval(function() {
        animateCharts();
    }, 30000);  // Every 30 seconds
    
    // Run animation immediately
    animateCharts();
}

// Stop animation cycle
function stopAnimation() {
    if (animationTimer) {
        clearInterval(animationTimer);
        animationTimer = null;
    }
}

// Animate all charts
function animateCharts() {
    // Animate water level charts
    if (charts.weekDepth) {
        animateChart(charts.weekDepth, latestData.weekDepth.labels, latestData.weekDepth.data);
    }
    
    if (charts.hourDepth) {
        setTimeout(function() {
            animateChart(charts.hourDepth, latestData.hourDepth.labels, latestData.hourDepth.data);
        }, 800);
    }
    
    // Animate temperature charts
    if (charts.weekTemp) {
        setTimeout(function() {
            animateChart(charts.weekTemp, latestData.weekTemp.labels, latestData.weekTemp.data);
        }, 1000);
    }
    
    if (charts.hourTemp) {
        setTimeout(function() {
            animateChart(charts.hourTemp, latestData.hourTemp.labels, latestData.hourTemp.data);
        }, 1200);
    }
}

// Animate a single chart
function animateChart(chart, labels, data) {
    // Reset chart data
    chart.data.labels = [];
    chart.data.datasets[0].data = [];
    
    // Keep warning line if it exists
    if (chart.data.datasets.length > 1) {
        chart.data.datasets[1].data = [];
    }
    
    chart.update();
    
    // Format the labels before animation if they're timestamps
    const formattedLabels = labels.map(label => {
        // If this is already a formatted label (contains a newline), return as is
        if (typeof label === 'string' && label.includes('\n')) {
            return label;
        }
        
        // Otherwise, format it as a date
        if (typeof label === 'number' || (typeof label === 'string' && !isNaN(label))) {
            try {
                const timestamp = typeof label === 'string' ? parseInt(label) : label;
                const date = new Date(timestamp * 1000);
                
                // Format date as MM/DD/YY
                const dateStr = date.toLocaleDateString('en-US', {
                    month: 'numeric',
                    day: 'numeric',
                    year: '2-digit'
                });
                
                // Format time as HH:MM AM/PM
                const timeStr = date.toLocaleTimeString('en-US', {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: true
                });
                
                return dateStr + '\n' + timeStr;
            } catch (e) {
                console.error("Error formatting label:", label, e);
                return label;
            }
        }
        
        return label;
    });
    
    // Animate data points being added one by one
    let currentIndex = 0;
    const totalPoints = data.length;
    const animationInterval = Math.max(40, Math.min(30, 1000 / totalPoints)); // Adjust interval based on data size
    
    const pointInterval = setInterval(function() {
        if (currentIndex >= totalPoints) {
            clearInterval(pointInterval);
            return;
        }
        
        // Add the next data point with formatted label
        chart.data.labels.push(formattedLabels[currentIndex]);
        chart.data.datasets[0].data.push(data[currentIndex]);
        
        // Add warning line point if it exists
        if (chart.data.datasets.length > 1) {
            chart.data.datasets[1].data.push(waterLevelWarning);
        }
        
        chart.update();
        
        currentIndex++;
    }, animationInterval);
}

// Format timestamp for display in charts
// Format timestamp for display in charts
function formatDateTime_t(timestamp) {
    console.log("Original timestamp:", timestamp, "Type:", typeof timestamp);
    
    // If timestamp is already a formatted string like "2025-05-06 20:00:00"
    if (typeof timestamp === 'string' && timestamp.includes('-')) {
        // Parse the date string to create a Date object
        const parts = timestamp.split(/[- :]/);
        // parts[0] = year, parts[1] = month, parts[2] = day, 
        // parts[3] = hour, parts[4] = minutes, parts[5] = seconds
        const date = new Date(parts[0], parts[1]-1, parts[2], parts[3], parts[4], parts[5]);
        
        console.log("Parsed from string:", date);
        
        // Format using the date object
        const dateStr = date.toLocaleDateString('en-US', {
            month: 'numeric',
            day: 'numeric',
            year: '2-digit'
        });
        
        const timeStr = date.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        });
        
        return dateStr + '\n' + timeStr;
    }
    
    // Handle numeric timestamp (Unix time)
    timestamp = Number(timestamp);
    console.log("Numeric timestamp:", timestamp);
    
    if (isNaN(timestamp) || timestamp === 0) {
        console.error("Invalid timestamp:", timestamp);
        return "Invalid date";
    }
    
    const date = new Date(timestamp * 1000);
    console.log("Date object:", date);
    
    // Format date as MM/DD/YY
    const dateStr = date.toLocaleDateString('en-US', {
        month: 'numeric',
        day: 'numeric',
        year: '2-digit'
    });
    
    // Format time as HH:MM AM/PM
    const timeStr = date.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: true
    });
    
    console.log("Formatted:", dateStr, timeStr);
    
    // Return formatted date and time (on separate lines for charts)
    return dateStr + '\n' + timeStr;
}

// Date range controls
function updateWeekRange(days) {
    const weekStartInput = document.getElementById('week_start');
    const currentStart = weekStartInput.value;
    let date;
    
    if (currentStart) {
        // If there's a current value, use it as the base
        date = new Date(currentStart + 'T00:00:00');
    } else {
        // Otherwise, use 7 days ago as the base
        date = new Date();
        date.setDate(date.getDate() - 7);
    }
    
    // Add the days offset
    date.setDate(date.getDate() + days);
    
    // Format as YYYY-MM-DD
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const formattedDate = `${year}-${month}-${day}`;
    
    weekStartInput.value = formattedDate;
    
    // Submit the form
    document.getElementById('weekForm').submit();
}

function updateHourRange(hours) {
    const hourStartInput = document.getElementById('hour_start');
    const currentStart = hourStartInput.value;
    let date;
    
    if (currentStart) {
        // If there's a current value, use it as the base
        date = new Date(currentStart);
    } else {
        // Otherwise, use 24 hours ago as the base
        date = new Date();
        date.setHours(date.getHours() - 24);
    }
    
    // Add the hours offset
    date.setHours(date.getHours() + hours);
    
    // Format as YYYY-MM-DDTHH:MM
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hour = String(date.getHours()).padStart(2, '0');
    const minute = String(date.getMinutes()).padStart(2, '0');
    const formattedDate = `${year}-${month}-${day}T${hour}:${minute}`;
    
    hourStartInput.value = formattedDate;
    console.log("Updated hour_start to:", formattedDate);
    
    // Submit the form
    window.location.hash = '#hourly-data-header';
    document.getElementById('hourForm').submit();
}

function resetWeekRange() {
    // Remove the week_start parameter and reload
    const url = new URL(window.location.href);
    url.searchParams.delete('week_start');
    window.location.href = url.toString();
}

function resetHourRange() {
    // Remove the hour_start parameter and reload
    const url = new URL(window.location.href);
    url.searchParams.delete('hour_start');
    window.location.href = url.toString();
}


// Initialize export data
function initExportData(dataType, period, data, unit) {
    // Initialize the exportData object structure if it doesn't exist yet
    if (!exportData[period + dataType]) {
        exportData[period + dataType] = {
            data: data,
            unit: unit
        };
    } else {
        // Update existing data
        exportData[period + dataType].data = data;
        exportData[period + dataType].unit = unit;
    }
    
    // Log successful initialization for debugging
    console.log(`Initialized ${period} ${dataType} data with ${data.length} readings`);
}


// Main export function
function exportToCSV(dataType, period) {
    try {
        // Get the appropriate data
        const key = period + dataType;
        
        // Check if data exists
        if (!exportData[key] || !exportData[key].data) {
            console.error(`Error: No data found for ${key}`);
            alert(`Export failed: No data available for ${dataType} (${period})`);
            return;
        }
        
        const readings = exportData[key].data;
        const unitName = exportData[key].unit;
        
        // Validate data
        if (!Array.isArray(readings) || readings.length === 0) {
            console.error(`Error: Invalid or empty data for export: ${key}`);
            alert(`Export failed: No valid data available for ${dataType} (${period})`);
            return;
        }
        
        // Create filename
        const filename = `${dataType}_${period}.csv`;
        
        // Create CSV content
        let csvContent = `Timestamp,Value (${unitName})\n`;
        
        // Sort readings by timestamp (oldest first)
        const sortedReadings = [...readings].sort((a, b) => a.timestamp - b.timestamp);
        
        // Add data rows
        sortedReadings.forEach(reading => {
            const date = new Date(reading.timestamp * 1000);
            const timestamp = date.toISOString().replace('T', ' ').substring(0, 19);
            const value = parseFloat(reading.value).toFixed(2);
            csvContent += `${timestamp},${value}\n`;
        });
        
        // Create a download link
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        // Clean up
        setTimeout(() => {
            URL.revokeObjectURL(url);
        }, 100);
        
        console.log(`Successfully exported ${sortedReadings.length} readings to ${filename}`);
        
    } catch (error) {
        console.error("CSV Export error:", error);
        alert("Export failed: " + error.message);
    }
}