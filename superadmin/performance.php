<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/include/header.php';
require_once __DIR__ . '/include/sidebar.php';

// Check authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header('Location: /dutsca/index.php');
    exit();
}

// Get database connection
$db = getDB();

// Handle performance actions
$success = false;
$error = '';
$logs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'collect_metrics':
                // Collect system metrics
                $result = collectMetrics($db);
                if ($result['success']) {
                    $success = true;
                    $logs = $result['logs'];
                } else {
                    $error = $result['error'];
                }
                break;

            case 'clear_metrics':
                // Clear collected metrics
                $result = clearMetrics($db);
                if ($result['success']) {
                    $success = true;
                    $logs = $result['logs'];
                } else {
                    $error = $result['error'];
                }
                break;
        }
    } catch (Exception $e) {
        $error = 'Error performing performance action: ' . $e->getMessage();
    }
}

// Get system metrics
$metrics = [
    'cpu' => [
        'usage' => getCPUUsage(),
        'load' => getCPULoad()
    ],
    'memory' => [
        'total' => getMemoryTotal(),
        'used' => getMemoryUsed(),
        'free' => getMemoryFree()
    ],
    'disk' => [
        'usage' => getDiskUsage(),
        'space' => getDiskSpace()
    ],
    'database' => [
        'queries' => getDatabaseQueries(),
        'connections' => getDatabaseConnections()
    ],
    'network' => [
        'bandwidth' => getNetworkBandwidth(),
        'connections' => getNetworkConnections()
    ]
];

// Get historical metrics
$historicalMetrics = $db->executeQuery("
    SELECT 
        metric,
        value,
        unit,
        recorded_at,
        notes
    FROM performance_monitoring
    ORDER BY recorded_at DESC
    LIMIT 100
")->fetchAll();
?>

// Helper functions
function collectMetrics($db) {
    try {
        $logs = [];
        
        // Get system metrics
        $metrics = [
            'cpu_usage' => getCPUUsage(),
            'memory_usage' => getMemoryUsage(),
            'disk_usage' => getDiskUsage(),
            'database_queries' => getDatabaseQueries(),
            'network_bandwidth' => getNetworkBandwidth()
        ];
        
        // Log metrics to database
        foreach ($metrics as $metric => $value) {
            $db->executeQuery(
                "INSERT INTO performance_monitoring (metric, value, unit, recorded_by) VALUES (?, ?, ?, ?)",
                [
                    $metric,
                    $value,
                    getMetricUnit($metric),
                    $_SESSION['user_id']
                ]
            );
        }
        
        $logs[] = "Metrics collected successfully";
        
        return [
            'success' => true,
            'logs' => $logs
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => "Failed to collect metrics: {$e->getMessage()}"
        ];
    }
}

function clearMetrics($db) {
    try {
        $logs = [];
        
        // Clear metrics older than 30 days
        $db->executeQuery(
            "DELETE FROM performance_monitoring WHERE recorded_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        $logs[] = "Old metrics cleared successfully";
        
        return [
            'success' => true,
            'logs' => $logs
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => "Failed to clear metrics: {$e->getMessage()}"
        ];
    }
}

function getCPUUsage() {
    // Simulate CPU usage monitoring
    return rand(10, 90) . '%';
}

function getMemoryUsage() {
    // Simulate memory usage monitoring
    return rand(100, 2000) . ' MB';
}

function getDiskUsage() {
    // Simulate disk usage monitoring
    return rand(10, 90) . '%';
}

function getDatabaseQueries() {
    // Simulate database query monitoring
    return rand(100, 1000) . ' queries/min';
}

function getNetworkBandwidth() {
    // Simulate network bandwidth monitoring
    return rand(1, 100) . ' Mbps';
}

function getMetricUnit($metric) {
    $units = [
        'cpu_usage' => '%',
        'memory_usage' => 'MB',
        'disk_usage' => '%',
        'database_queries' => 'queries/min',
        'network_bandwidth' => 'Mbps'
    ];
    return $units[$metric] ?? '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Monitoring - DUTS CA</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="/dutsca/superadmin/assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">
    <?php include __DIR__ . '/include/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-[#00572d]">Performance Monitoring</h1>
                <button onclick="collectMetrics()" class="bg-[#00572d] text-white px-4 py-2 rounded-lg hover:bg-[#1f9345]">
                    <i class="fas fa-sync-alt mr-2"></i> Collect Metrics
                </button>
            </div>

            <!-- Real-time Metrics Section -->
            <div class="bg-white rounded-xl shadow p-6 mb-8">
                <h2 class="text-xl font-bold text-[#00572d] mb-4">Real-time System Metrics</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <!-- CPU Usage Card -->
                    <div class="bg-white rounded-xl shadow p-6">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 rounded-full flex items-center justify-center mr-4 bg-red-100">
                                <i class="fas fa-microchip text-red-600 text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800">CPU Usage</h3>
                                <p class="text-sm text-gray-600">Current load and performance</p>
                            </div>
                        </div>
                        <div class="text-3xl font-bold text-red-600" id="cpuUsage"><?php echo $metrics['cpu']['usage']; ?></div>
                        <div class="text-sm text-gray-600 mt-2">Last updated: <span id="cpuLastUpdated"><?php echo date('H:i:s'); ?></span></div>
                    </div>

                    <!-- Memory Usage Card -->
                    <div class="bg-white rounded-xl shadow p-6">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 rounded-full flex items-center justify-center mr-4 bg-blue-100">
                                <i class="fas fa-memory text-blue-600 text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800">Memory Usage</h3>
                                <p class="text-sm text-gray-600">RAM utilization</p>
                            </div>
                        </div>
                        <div class="text-3xl font-bold text-blue-600" id="memoryUsage"><?php echo $metrics['memory']['used']; ?></div>
                        <div class="text-sm text-gray-600 mt-2">Total: <?php echo $metrics['memory']['total']; ?></div>
                    </div>

                    <!-- Disk Usage Card -->
                    <div class="bg-white rounded-xl shadow p-6">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 rounded-full flex items-center justify-center mr-4 bg-green-100">
                                <i class="fas fa-hdd text-green-600 text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800">Disk Usage</h3>
                                <p class="text-sm text-gray-600">Storage utilization</p>
                            </div>
                        </div>
                        <div class="text-3xl font-bold text-green-600" id="diskUsage"><?php echo $metrics['disk']['usage']; ?></div>
                        <div class="text-sm text-gray-600 mt-2">Free space: <?php echo $metrics['disk']['space']; ?></div>
                    </div>

                    <!-- Database Performance Card -->
                    <div class="bg-white rounded-xl shadow p-6">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 rounded-full flex items-center justify-center mr-4 bg-purple-100">
                                <i class="fas fa-database text-purple-600 text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800">Database Performance</h3>
                                <p class="text-sm text-gray-600">Query statistics</p>
                            </div>
                        </div>
                        <div class="text-3xl font-bold text-purple-600" id="dbQueries"><?php echo $metrics['database']['queries']; ?></div>
                        <div class="text-sm text-gray-600 mt-2">Connections: <?php echo $metrics['database']['connections']; ?></div>
                    </div>

                    <!-- Network Bandwidth Card -->
                    <div class="bg-white rounded-xl shadow p-6">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 rounded-full flex items-center justify-center mr-4 bg-orange-100">
                                <i class="fas fa-wifi text-orange-600 text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800">Network Bandwidth</h3>
                                <p class="text-sm text-gray-600">Current throughput</p>
                            </div>
                        </div>
                        <div class="text-3xl font-bold text-orange-600" id="networkBandwidth"><?php echo $metrics['network']['bandwidth']; ?></div>
                        <div class="text-sm text-gray-600 mt-2">Connections: <?php echo $metrics['network']['connections']; ?></div>
                    </div>
                </div>
            </div>

            <!-- Historical Metrics Chart -->
            <div class="bg-white rounded-xl shadow p-6 mb-8">
                <h2 class="text-xl font-bold text-[#00572d] mb-4">Historical Metrics</h2>
                <div class="relative h-96">
                    <canvas id="historicalChart"></canvas>
                </div>
            </div>

            <!-- Metrics Table -->
            <div class="bg-white rounded-xl shadow p-6">
                <h2 class="text-xl font-bold text-[#00572d] mb-4">Recent Metrics</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Metric</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Value</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recorded At</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($historicalMetrics as $metric): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $metric['metric']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $metric['value']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $metric['unit']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo date('M d, Y H:i', strtotime($metric['recorded_at'])); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $metric['notes'] ?? '-'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Metrics Collection Modal -->
    <div id="metricsModal" class="modal hidden">
        <div class="modal-overlay"></div>
        <div class="modal-content">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-[#00572d]">Collecting Metrics</h3>
                    <button onclick="closeMetricsModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="space-y-4">
                    <div class="w-full bg-gray-200 rounded-full h-2.5">
                        <div id="progressBar" class="bg-[#00572d] h-2.5 rounded-full transition-all duration-300"></div>
                    </div>
                    
                    <div id="progressText" class="text-sm text-gray-600 text-center py-2">Collecting metrics...</div>
                    
                    <div id="logsContainer" class="space-y-2 mt-4">
                        <!-- Logs will be added here dynamically -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Chart.js configuration
    const ctx = document.getElementById('historicalChart').getContext('2d');
    const historicalChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [
                {
                    label: 'CPU Usage',
                    data: [],
                    borderColor: '#dc2626',
                    tension: 0.1
                },
                {
                    label: 'Memory Usage',
                    data: [],
                    borderColor: '#3b82f6',
                    tension: 0.1
                },
                {
                    label: 'Disk Usage',
                    data: [],
                    borderColor: '#10b981',
                    tension: 0.1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: {
                        stepSize: 20
                    }
                }
            }
        }
    });

    // Update historical chart
    function updateHistoricalChart(metrics) {
        const labels = metrics.map(m => m.recorded_at);
        const cpuData = metrics.map(m => m.value);
        
        historicalChart.data.labels = labels;
        historicalChart.data.datasets[0].data = cpuData;
        historicalChart.update();
    }

    // Metrics Collection
    function collectMetrics() {
        const modal = document.getElementById('metricsModal');
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';

        // Start progress bar
        const progressBar = document.getElementById('progressBar');
        const progressText = document.getElementById('progressText');
        const logsContainer = document.getElementById('logsContainer');

        // Simulate collection process
        let progress = 0;
        const interval = setInterval(() => {
            progress += 10;
            progressBar.style.width = progress + '%';
            progressText.textContent = 'Collecting metrics... ' + progress + '%';

            if (progress >= 100) {
                clearInterval(interval);
                progressText.textContent = 'Metrics collected successfully!';
                
                // Add logs
                const logs = [
                    'Starting metrics collection...',
                    'Collecting CPU metrics...',
                    'Collecting memory metrics...',
                    'Collecting disk metrics...',
                    'Collecting database metrics...',
                    'Collecting network metrics...',
                    'Metrics collection completed!'
                ];
                
                logs.forEach(log => {
                    const logElement = document.createElement('div');
                    logElement.className = 'text-sm text-gray-600';
                    logElement.textContent = log;
                    logsContainer.appendChild(logElement);
                });

                // Refresh metrics after a delay
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            }
        }, 500);
    }

    function closeMetricsModal() {
        const modal = document.getElementById('metricsModal');
        modal.classList.add('hidden');
        document.body.style.overflow = '';

        // Reset progress bar and logs
        document.getElementById('progressBar').style.width = '0%';
        document.getElementById('progressText').textContent = 'Collecting metrics...';
        document.getElementById('logsContainer').innerHTML = '';
    }

    // Auto-refresh metrics every 30 seconds
    setInterval(() => {
        const cpuUsage = document.getElementById('cpuUsage');
        const memoryUsage = document.getElementById('memoryUsage');
        const diskUsage = document.getElementById('diskUsage');
        const dbQueries = document.getElementById('dbQueries');
        const networkBandwidth = document.getElementById('networkBandwidth');
        const cpuLastUpdated = document.getElementById('cpuLastUpdated');

        // Update metrics with random values (in production, this would be real data)
        cpuUsage.textContent = Math.floor(Math.random() * 90) + 10 + '%';
        memoryUsage.textContent = Math.floor(Math.random() * 1900) + 100 + ' MB';
        diskUsage.textContent = Math.floor(Math.random() * 90) + 10 + '%';
        dbQueries.textContent = Math.floor(Math.random() * 900) + 100 + ' queries/min';
        networkBandwidth.textContent = Math.floor(Math.random() * 90) + 10 + ' Mbps';
        cpuLastUpdated.textContent = new Date().toLocaleTimeString();
    }, 30000);
    </script>
</body>
</html>
