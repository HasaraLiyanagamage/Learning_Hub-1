<?php
session_start();
require_once 'db_connect.php';

// Session Security: Kick user out if they are not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 1. Fetch KPI Counts from Database using SQL queries
$total_rooms = $conn->query("SELECT COUNT(*) as count FROM rooms")->fetch_assoc()['count'];
$avail_rooms = $conn->query("SELECT COUNT(*) as count FROM rooms WHERE status='Available'")->fetch_assoc()['count'];
$res_rooms   = $conn->query("SELECT COUNT(*) as count FROM rooms WHERE status='Reserved'")->fetch_assoc()['count'];
$total_studs = $conn->query("SELECT COUNT(DISTINCT id) as count FROM users WHERE role='Student'")->fetch_assoc()['count'];
$total_pymts = $conn->query("SELECT SUM(amount) as total FROM payments")->fetch_assoc()['total'] ?? 0.00;

// 2. Fetch Recent Reservations data
$recent_res_query = "SELECT id, student_name, student_id, room_id, reservation_date, status 
                     FROM reservations ORDER BY reservation_date DESC LIMIT 5";
$recent_res_result = $conn->query($recent_res_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Orion College Hostel</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <aside class="sidebar">
            <h3>Orion Portal</h3>
            <div class="user-info">
                <p>Welcome, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></p>
                <span>Role: <?php echo htmlspecialchars($_SESSION['role']); ?></span>
            </div>
            <nav class="nav-menu">
                <a href="dashboard.php" class="active">📊 Dashboard</a>
                <a href="rooms.php">🛏️ Room Management</a>
                <a href="reservations.php">📝 Reservations</a>
                <a href="payments.php">💳 Payments</a>
                <a href="logout.php" class="logout-btn">🚪 Logout</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="content-header">
                <h2>Hostel & Room Reservation Dashboard</h2>
            </header>

            <section class="stats-grid">
                <div class="card">
                    <h4>Total Rooms</h4>
                    <p class="stat-number"><?php echo $total_rooms; ?></p>
                </div>
                <div class="card card-green">
                    <h4>Available Rooms</h4>
                    <p class="stat-number"><?php echo $avail_rooms; ?></p>
                </div>
                <div class="card card-orange">
                    <h4>Reserved Rooms</h4>
                    <p class="stat-number"><?php echo $res_rooms; ?></p>
                </div>
                <div class="card card-blue">
                    <h4>Registered Students</h4>
                    <p class="stat-number"><?php echo $total_studs; ?></p>
                </div>
                <div class="card card-purple">
                    <h4>Total Collections</h4>
                    <p class="stat-number">Rs. <?php echo number_format($total_pymts, 2); ?></p>
                </div>
            </section>

            <section class="table-section">
                <h3>Recent Reservations</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Reservation ID</th>
                            <th>Student Name</th>
                            <th>Student ID</th>
                            <th>Room ID</th>
                            <th>Date Applied</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_res_result->num_rows > 0): ?>
                            <?php while($row = $recent_res_result->fetch_assoc()): ?>
                                <tr>
                                    <td>#<?php echo $row['id']; ?></td>
                                    <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['student_id']); ?></td>
                                    <td>Room <?php echo $row['room_id']; ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($row['reservation_date'])); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($row['status']); ?>">
                                            <?php echo htmlspecialchars($row['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center;">No reservations tracked yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </section>
        </main>
    </div>
</body>
</html>