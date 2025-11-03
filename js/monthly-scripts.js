/**
 * Monthly Page AJAX Data Loading
 * Handles responsive loading of monthly data without page refresh
 */

// Global variables
let monthlyCharts = {};
let currentWellId = '';
let currentMonthStart = null;
let isLoading = false;
let fullDatasets = {}; // Store full datasets for export
let detectedIntervals = {}; // Store detected recording intervals

/**
 * Detect the recording interval from the data
 */
function detectRecordingInterval(readings) {
    if (!readings || readings.length < 2) {
        return 15; // Default to 15 minutes
    }
    
    // Calculate intervals between first few readings
    const intervals = [];
    for (let i = 1; i < Math.min(10, readings.length); i++) {
        const interval = readings[i].timestamp - readings[i-1].timestamp;
        intervals.push(interval);
    }
    
    // Get the most common interval
    const avgInterval = intervals.reduce((a, b) => a + b, 0) / intervals.length;
    
    // Convert to minutes and round to nearest common interval
    const minutes = Math.round(avgInterval / 60);
    
    // Round to common intervals: 5, 10, 15, 30, 60
    if (minutes <= 7) return 5;
    if (minutes <= 12) return 10;
    if (minutes <= 22) return 15;
    if (minutes <= 45) return 30;
    return 60;
}

/**
 * Format interval for display
 */
function formatInterval(minutes) {
    if (minutes === 60) {
        return '1-hour';
    } else if (minutes < 60) {
        return minutes + '-min';
    } else {
        const hours = Math.round(minutes / 60);
        return hours + '-hour';
    }
}

/**
 * Initialize the monthly page
 */
function initMonthlyPage(wellId, monthStart) {
    currentWellId = wellId;
    currentMonthStart = monthStart;
    
    // Load initial data
    loadMonthlyData();
}

/**
 * Load monthly data via AJAX
 */
function loadMonthlyData() {
    if (isLoading) return;
    
    isLoading = true;
    
    // Show loading overlays
    document.querySelectorAll('.loading-overlay').forEach(function(overlay) {
        overlay.classList.add('active');
    });
    
    // Build URL with parameters
    let url = 'fetch_data.php?well=' + encodeURIComponent(currentWellId) + '&type=monthly';
    if (currentMonthStart) {
        url += '&month_start=' + currentMonthStart;
    }
    
    console.log('Fetching monthly data from:', url);
    
    // Fetch data
    fetch(url)
        .then(response => response.json())
        .then(data => {
            console.log('Monthly data received:', data);
            
            if (data.success) {
                updateMonthlyCharts(data);
                updateDateDisplay(data.month_start, data.month_end);
            } else {
                console.error('Failed to load monthly data:', data.message);
                showError(data.message);
            }
        })
        .catch(error => {
            console.error('Error fetching monthly data:', error);
            showError('Failed to load data. Please try again.');
        })
        .finally(() => {
            // Hide loading overlays
            document.querySelectorAll('.loading-overlay').forEach(function(overlay) {
                overlay.classList.remove('active');
            });
            isLoading = false;
        });
}

/**
 * Update the charts with new data
 */
function updateMonthlyCharts(data) {
    console.log('updateMonthlyCharts called with data:', data);
    
    // Update depth chart
    if (data.depth && data.depth.readings) {
        console.log('Updating depth chart, readings:', data.depth.readings.length);
        
        // Detect recording interval
        const interval = detectRecordingInterval(data.depth.readings);
        detectedIntervals['depth'] = interval;
        console.log('Detected depth interval:', interval, 'minutes');
        
        // Store full dataset for export (before downsampling)
        fullDatasets['depth'] = {
            name: data.depth.name,
            unit: data.depth.unit,
            readings: data.depth.readings,
            interval: interval
        };
        
        // Update button label with detected interval
        updateButtonLabel('depth', interval);
        
        updateChart('month-chart-depth', data.depth, 'depth');
        updateStatistics('depth', data.depth);
    } else {
        console.warn('No depth data available');
    }
    
    // Update temperature chart
    if (data.temperature && data.temperature.readings) {
        console.log('Updating temperature chart, readings:', data.temperature.readings.length);
        
        // Detect recording interval
        const interval = detectRecordingInterval(data.temperature.readings);
        detectedIntervals['temperature'] = interval;
        console.log('Detected temperature interval:', interval, 'minutes');
        
        // Store full dataset for export (before downsampling)
        fullDatasets['temperature'] = {
            name: data.temperature.name,
            unit: data.temperature.unit,
            readings: data.temperature.readings,
            interval: interval
        };
        
        // Update button label with detected interval
        updateButtonLabel('temperature', interval);
        
        updateChart('month-chart-temperature', data.temperature, 'temperature');
        updateStatistics('temp', data.temperature);
    } else {
        console.warn('No temperature data available');
    }
}

/**
 * Update export button label with detected interval
 */
function updateButtonLabel(paramType, intervalMinutes) {
    const intervalText = formatInterval(intervalMinutes);
    const cardElement = document.getElementById('month-chart-' + paramType)?.closest('.card');
    
    if (cardElement) {
        const button = cardElement.querySelector('button[onclick*="exportToCSV"]');
        if (button) {
            button.textContent = 'Export Monthly Data (' + intervalText + ' intervals) to CSV';
            console.log('Updated', paramType, 'button label to:', intervalText);
        }
    }
}

/**
 * Update statistics display for a parameter
 */
function updateStatistics(prefix, paramData) {
    console.log('updateStatistics called with prefix:', prefix, 'data:', paramData);
    
    if (!paramData || !paramData.readings || paramData.readings.length === 0) {
        console.warn('No readings data for', prefix);
        return;
    }
    
    const values = paramData.readings.map(r => parseFloat(r.value));
    const unit = paramData.unit || '';
    
    console.log('Calculating stats for', prefix, '- readings:', values.length);
    
    // Calculate statistics
    const current = values[values.length - 1];
    const min = Math.min(...values);
    const max = Math.max(...values);
    const avg = values.reduce((a, b) => a + b, 0) / values.length;
    const count = values.length;
    
    console.log('Stats calculated:', { current, min, max, avg, count });
    
    // Update DOM elements
    const currentEl = document.getElementById(prefix + '-current');
    const minEl = document.getElementById(prefix + '-min');
    const maxEl = document.getElementById(prefix + '-max');
    const avgEl = document.getElementById(prefix + '-avg');
    const countEl = document.getElementById(prefix + '-count');
    
    if (currentEl) {
        currentEl.textContent = current.toFixed(2) + ' ' + unit;
        console.log('Updated', prefix + '-current');
    } else {
        console.error('Element not found:', prefix + '-current');
    }
    
    if (minEl) minEl.textContent = min.toFixed(2) + ' ' + unit;
    if (maxEl) maxEl.textContent = max.toFixed(2) + ' ' + unit;
    if (avgEl) avgEl.textContent = avg.toFixed(2) + ' ' + unit;
    if (countEl) countEl.textContent = count;
    
    console.log('Statistics updated for', prefix);
}

/**
 * Update or create a chart
 */
function updateChart(chartId, paramData, paramType) {
    const canvas = document.getElementById(chartId);
    if (!canvas) return;
    
    // Sort readings by timestamp
    const sortedReadings = paramData.readings.sort((a, b) => a.timestamp - b.timestamp);
    
    // Downsample if too many points
    const sampledReadings = downsampleReadings(sortedReadings, 200);
    
    // Prepare chart data
    const labels = sampledReadings.map(r => r.timestamp);
    const values = sampledReadings.map(r => parseFloat(r.value));
    
    // Determine chart color based on parameter type
    const color = paramType === 'depth' ? 'rgb(75, 192, 192)' : 'rgb(255, 99, 132)';
    const bgColor = paramType === 'depth' ? 'rgba(75, 192, 192, 0.1)' : 'rgba(255, 99, 132, 0.1)';
    
    // Check if chart already exists
    if (monthlyCharts[chartId]) {
        // Update existing chart
        monthlyCharts[chartId].data.labels = labels;
        monthlyCharts[chartId].data.datasets[0].data = values;
        monthlyCharts[chartId].data.datasets[0].label = paramData.name;
        monthlyCharts[chartId].options.scales.y.title.text = paramData.name + ' (' + paramData.unit + ')';
        monthlyCharts[chartId].update();
    } else {
        // Create new chart
        monthlyCharts[chartId] = new Chart(canvas, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: paramData.name,
                    data: values,
                    borderColor: color,
                    backgroundColor: bgColor,
                    tension: 0.1,
                    pointRadius: 1,
                    pointHoverRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { 
                        display: true, 
                        position: 'top' 
                    },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => ctx.dataset.label + ': ' + ctx.raw.toFixed(3) + ' ' + paramData.unit,
                            title: function(tooltipItems) {
                                const timestamp = tooltipItems[0].parsed.x;
                                return formatTimestamp(timestamp);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        type: 'linear',
                        title: { 
                            display: true, 
                            text: 'Date/Time' 
                        },
                        ticks: {
                            callback: function(value) {
                                return formatTimestamp(value, true);
                            },
                            maxRotation: 45,
                            minRotation: 45,
                            maxTicksLimit: 25
                        }
                    },
                    y: {
                        title: { 
                            display: true, 
                            text: paramData.name + ' (' + paramData.unit + ')' 
                        },
                        ticks: {
                            callback: function(value) {
                                return value.toFixed(2);
                            }
                        }
                    }
                }
            }
        });
    }
}

/**
 * Downsample readings to reduce chart complexity
 */
function downsampleReadings(readings, maxPoints) {
    if (readings.length <= maxPoints) {
        return readings;
    }
    
    const downsampled = [];
    const step = Math.ceil(readings.length / maxPoints);
    
    for (let i = 0; i < readings.length; i += step) {
        downsampled.push(readings[i]);
    }
    
    // Always include the last point
    if (downsampled[downsampled.length - 1] !== readings[readings.length - 1]) {
        downsampled.push(readings[readings.length - 1]);
    }
    
    return downsampled;
}

/**
 * Format timestamp for display
 */
function formatTimestamp(timestamp, short = false) {
    const date = new Date(timestamp * 1000);
    
    if (short) {
        return (date.getMonth() + 1) + '/' + date.getDate();
    }
    
    const options = { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric', 
        hour: '2-digit', 
        minute: '2-digit' 
    };
    return date.toLocaleString('en-US', options);
}

/**
 * Update date display in the header
 */
function updateDateDisplay(startTimestamp, endTimestamp) {
    const startDate = new Date(startTimestamp * 1000);
    const endDate = new Date(endTimestamp * 1000);
    
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    const startStr = startDate.toLocaleDateString('en-US', options);
    const endStr = endDate.toLocaleDateString('en-US', options);
    
    const dateElement = document.querySelector('.section-title');
    if (dateElement) {
        dateElement.textContent = startStr + ' to ' + endStr;
    }
}

/**
 * Show error message
 */
function showError(message) {
    const container = document.querySelector('.container');
    if (!container) return;
    
    const existingAlert = container.querySelector('.alert-danger');
    if (existingAlert) {
        existingAlert.textContent = message;
    } else {
        const alert = document.createElement('div');
        alert.className = 'alert alert-danger';
        alert.textContent = message;
        container.insertBefore(alert, container.firstChild);
    }
}

/**
 * Navigate to different month ranges
 */
function updateMonthRange(days) {
    const currentDate = currentMonthStart ? new Date(currentMonthStart) : new Date();
    currentDate.setDate(currentDate.getDate() + days);
    
    const year = currentDate.getFullYear();
    const month = String(currentDate.getMonth() + 1).padStart(2, '0');
    const day = String(currentDate.getDate()).padStart(2, '0');
    currentMonthStart = year + '-' + month + '-' + day;
    
    // Update the date input
    const dateInput = document.getElementById('month_start');
    if (dateInput) {
        dateInput.value = currentMonthStart;
    }
    
    // Reload data
    loadMonthlyData();
}

/**
 * Reset to current month
 */
function resetMonthRange() {
    currentMonthStart = null;
    const dateInput = document.getElementById('month_start');
    if (dateInput) {
        const today = new Date();
        const thirtyDaysAgo = new Date(today.getTime() - (30 * 24 * 60 * 60 * 1000));
        const year = thirtyDaysAgo.getFullYear();
        const month = String(thirtyDaysAgo.getMonth() + 1).padStart(2, '0');
        const day = String(thirtyDaysAgo.getDate()).padStart(2, '0');
        dateInput.value = year + '-' + month + '-' + day;
        currentMonthStart = year + '-' + month + '-' + day;
    }
    loadMonthlyData();
}

/**
 * Export data to CSV
 */
function exportToCSV(paramType) {
    console.log('exportToCSV called for:', paramType);
    
    // Get full dataset (not the downsampled chart data)
    const dataset = fullDatasets[paramType];
    
    if (!dataset || !dataset.readings || dataset.readings.length === 0) {
        alert('No data available to export for ' + paramType);
        console.error('No data in fullDatasets for', paramType);
        return;
    }
    
    const readings = dataset.readings;
    const paramName = dataset.name;
    const unit = dataset.unit;
    const interval = dataset.interval || detectedIntervals[paramType] || 15;
    const intervalText = formatInterval(interval);
    
    console.log('Exporting', readings.length, 'readings for', paramType, 'at', intervalText, 'intervals');
    
    // Build CSV content
    let csv = 'Timestamp,Date/Time,' + paramName + ' (' + unit + ')\n';
    
    // Sort by timestamp
    const sortedReadings = [...readings].sort((a, b) => a.timestamp - b.timestamp);
    
    for (let i = 0; i < sortedReadings.length; i++) {
        const timestamp = sortedReadings[i].timestamp;
        const dateStr = formatTimestamp(timestamp);
        const value = sortedReadings[i].value.toFixed(3);
        csv += timestamp + ',' + dateStr + ',' + value + '\n';
    }
    
    // Create download link with interval in filename
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = paramType + '_monthly_' + intervalText + '_' + Date.now() + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
    
    console.log('CSV export complete:', a.download);
}