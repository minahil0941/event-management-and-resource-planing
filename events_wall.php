<?php
require_once 'core/db.php';

// Public page, no session check required for viewing (Kiosk mode)
// Fetch System Settings
$settings = [];
$sStmt = $pdo->query("SELECT * FROM system_settings");
while ($row = $sStmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Event Wall | <?= htmlspecialchars($settings['system_name']) ?></title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/bootstrap-icons.css">
    <style>
        :root {
            --bg-color: #0b0e14;
            --card-bg: rgba(255, 255, 255, 0.03);
            --accent-color: #3b82f6;
        }
        body {
            background-color: var(--bg-color);
            color: white;
            font-family: 'Inter', sans-serif;
            overflow: hidden; /* Typical for kiosk displays */
        }
        .wall-container {
            height: 100vh;
            padding: 40px;
            display: flex;
            flex-direction: column;
        }
        .wall-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding-bottom: 20px;
        }
        .wall-title {
            font-weight: 800;
            letter-spacing: -0.02em;
            font-size: 2.5rem;
        }
        .live-indicator {
            background: #ef4444;
            color: white;
            padding: 5px 15px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.9rem;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        .event-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 25px;
            flex-grow: 1;
            overflow-y: auto;
        }
        .event-card {
            background: var(--card-bg);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px;
            padding: 30px;
            backdrop-filter: blur(10px);
            transition: transform 0.3s ease;
        }
        .event-card.now {
            border-left: 8px solid var(--accent-color);
            background: rgba(59, 130, 246, 0.05);
        }
        .event-time {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--accent-color);
            margin-bottom: 10px;
        }
        .event-name {
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 15px;
            line-height: 1.2;
        }
        .event-venue {
            display: flex;
            align-items: center;
            color: #9ca3af;
            font-size: 1.1rem;
        }
        .digital-clock {
            font-size: 3rem;
            font-weight: 200;
            color: #64748b;
        }
        /* Hide scrollbar but keep functionality */
        .event-grid::-webkit-scrollbar { display: none; }
        .event-grid { -ms-overflow-style: none; scrollbar-width: none; }

        .back-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
            background: rgba(11, 14, 20, 0.6);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.8);
            padding: 10px 20px;
            border-radius: 16px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 10px 40px rgba(0,0,0,0.4);
        }
        .back-btn:hover {
            background: var(--accent-color);
            color: white;
            transform: translateY(-5px);
            border-color: var(--accent-color);
            box-shadow: 0 15px 50px rgba(59, 130, 246, 0.3);
        }
        .back-btn i { font-size: 1.1rem; }
    </style>
</head>
<body>

<a href="index.php" class="back-btn">
    <i class="bi bi-x-lg"></i>
    <span>Exit Wall</span>
</a>

<div class="wall-container">
    <div class="wall-header">
        <div>
            <div class="wall-title">
                <img src="<?= $settings['system_logo'] ?>" height="65" class="me-3 mb-2" style="filter: drop-shadow(0 0 10px rgba(255,255,255,0.2));">
                Live Campus Events
            </div>
            <div class="d-flex align-items-center mt-2">
                <span class="live-indicator me-3"><i class="bi bi-broadcast me-2"></i>LIVE STATUS</span>
                <span class="text-white fs-5 opacity-75 fw-medium" id="current-date"></span>
            </div>
        </div>
        <div class="text-end">
            <div id="clock" class="digital-clock">00:00:00</div>
        </div>
    </div>

    <!-- The list of events will be injected here via Ajax -->
    <div id="events-grid" class="event-grid">
        <div class="text-center w-100 mt-5 opacity-50">
            <div class="spinner-border text-primary mb-3" role="status"></div>
            <h3>Fetching Real-time Schedule...</h3>
        </div>
    </div>
</div>

<script>
    function updateClock() {
        const now = new Date();
        const clock = document.getElementById('clock');
        clock.innerText = now.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        
        const dateDisplay = document.getElementById('current-date');
        dateDisplay.innerText = now.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    }

    function fetchEvents() {
        fetch('ajax_events_wall.php')
            .then(response => response.text())
            .then(html => {
                document.getElementById('events-grid').innerHTML = html;
            });
    }

    // Initial load
    updateClock();
    fetchEvents();

    // Intervals
    setInterval(updateClock, 1000);
    setInterval(fetchEvents, 30000); // Update wall every 30 seconds
</script>

</body>
</html>
