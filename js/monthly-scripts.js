/**
 * Monthly Page AJAX Data Loading
 * Handles responsive loading of monthly data without page refresh
 */

// Global variables
let monthlyCharts = {};
let currentWellId = '';
let currentMonthStart = null;
let isLoading = false;

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
    
    // Fetch data
    fetch(url)
        .then(response => response.json())
        .then(data => {
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
    // Update depth chart
    if (data.depth && data.depth.readings) {
        updateChart('month-chart-depth', data.depth, 'depth');
        updateStatistics('depth', data.depth);
    }
    
    // Update temperature chart
    if (data.temperature && data.temperature.readings) {
        updateChart('month-chart-temperature', data.temperature, 'temperature');
        updateStatistics('temp', data.temperature);
    }
}

/**
 * Update statistics display for a parameter
 */
function updateStatistics(prefix, paramData) {
    if (!paramData.readings || paramData.readings.length === 0) return;
    
    const values = paramData.readings.map(r => parseFloat(r.value));
    const unit = paramData.unit;
    
    // Calculate statistics
    const current = values[values.length - 1];
    const min = Math.min(...values);
    const max = Math.max(...values);
    const avg = values.reduce((a, b) => a + b, 0) / values.length;
    const count = values.length;
    
    // Update DOM elements
    const currentEl = document.getElementById(prefix + '-current');
    const minEl = document.getElementById(prefix + '-min');
    const maxEl = document.getElementById(prefix + '-max');
    const avgEl = document.getElementById(prefix + '-avg');
    const countEl = document.getElementById(prefix + '-count');
    
    if (currentEl) currentEl.textContent = current.toFixed(2) + ' ' + unit;
    if (minEl) minEl.textContent = min.toFixed(2) + ' ' + unit;
    if (maxEl) maxEl.textContent = max.toFixed(2) + ' ' + unit;
    if (avgEl) avgEl.textContent = avg.toFixed(2) + ' ' + unit;
    if (countEl) countEl.textContent = count;
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
    const chartId = paramType === 'depth' ? 'month-chart-depth' : 'month-chart-temperature';
    const chart = monthlyCharts[chartId];
    
    if (!chart) {
        alert('No data available to export');
        return;
    }
    
    const labels = chart.data.labels;
    const values = chart.data.datasets[0].data;
    const paramName = chart.data.datasets[0].label;
    const unit = chart.options.scales.y.title.text.match(/\((.*?)\)/)[1];
    
    // Build CSV content
    let csv = 'Timestamp,Date/Time,' + paramName + ' (' + unit + ')\n';
    
    for (let i = 0; i < labels.length; i++) {
        const timestamp = labels[i];
        const dateStr = formatTimestamp(timestamp);
        const value = values[i].toFixed(3);
        csv += timestamp + ',' + dateStr + ',' + value + '\n';
    }
    
    // Create download link
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = paramType + '_monthly_data_' + Date.now() + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}