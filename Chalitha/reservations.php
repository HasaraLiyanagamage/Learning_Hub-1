<?php
session_start();
require_once 'db_connect.php';

// Session Security: Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$username = $_SESSION['username'];

$message = "";
$error = "";

// 1. CREATE OPERATION: Handle Student Reservation Submission
if (isset($_POST['submit_reservation'])) {
    $student_name = trim($_POST['student_name']);
    $student_id_code = trim($_POST['student_id_code']);
    $room_id = intval($_POST['room_id']);
    $check_in = $_POST['check_in'];
    $check_out = $_POST['check_out'];

    // Real-time Date Validation [JavaScript Requirements / Validation]
    if (empty($check_in) || empty($check_out)) {
        $error = "Please select both check-in and check-out dates.";
    } elseif (strtotime($check_in) < strtotime(date('Y-m-d'))) {
        $error = "Check-in date cannot be in the past.";
    } elseif (strtotime($check_out) <= strtotime($check_in)) {
        $error = "Check-out date must be after the check-in date.";
    } else {
        // Prevent Duplicate Bookings: Check if room is already taken
        $room_check = $conn->prepare("SELECT status FROM rooms WHERE id = ?");
        $room_check->bind_param("i", $room_id);
        $room_check->execute();
        $room_status = $room_check->get_result()->fetch_assoc()['status'];
        $room_check->close();

        if ($room_status !== 'Available') {
            $error = "Sorry, this room has already been reserved or is unavailable.";
        } else {
            // Insert Reservation Statement
            $stmt = $conn->prepare("INSERT INTO reservations (student_name, student_id, room_id, check_in, check_out, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
            $stmt->bind_param("ssiss", $student_name, $student_id_code, $room_id, $check_in, $check_out);
            
            if ($stmt->execute()) {
                $message = "Your reservation request has been submitted successfully and is pending approval!";
            } else {
                $error = "Something went wrong. Please try again.";
            }
            $stmt->close();
        }
    }
}

// 2. UPDATE OPERATION: Handle Admin Approvals / Rejections
if ($user_role === 'Admin' && isset($_GET['action']) && isset($_GET['res_id'])) {
    $action = $_GET['action'];
    $res_id = intval($_GET['res_id']);
    
    if ($action === 'approve') {
        // Update reservation status to Approved
        $stmt = $conn->prepare("UPDATE reservations SET status = 'Approved' WHERE id = ?");
        $stmt->bind_param("i", $res_id);
        $stmt->execute();
        $stmt->close();

        // Automatically mark the room as Reserved in the database
        $room_stmt = $conn->prepare("UPDATE rooms SET status = 'Reserved' WHERE id = (SELECT room_id FROM reservations WHERE id = ?)");
        $room_stmt->bind_param("i", $res_id);
        $room_stmt->execute();
        $room_stmt->close();
        
        $message = "Reservation approved and room status updated.";
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE reservations SET status = 'Rejected' WHERE id = ?");
        $stmt->bind_param("i", $res_id);
        $stmt->execute();
        $stmt->close();
        
        $message = "Reservation request rejected.";
    }
}

// 3. READ OPERATION: Fetch records based on roles
if ($user_role === 'Admin') {
    // Admins see all reservation records
    $res_query = "SELECT r.*, rm.room_number FROM reservations r JOIN rooms rm ON r.room_id = rm.id ORDER BY r.id DESC";
} else {
    // Students only see their own application records matching their name
    $res_query = "SELECT r.*, rm.room_number FROM reservations r JOIN rooms rm ON r.room_id = rm.id WHERE r.student_name = '" . $conn->real_escape_string($username) . "' ORDER BY r.id DESC";
}
$res_result = $conn->query($res_query);

// Fetch available rooms for the dropdown selection
$avail_rooms_dropdown = $conn->query("SELECT id, room_number, room_type, price FROM rooms WHERE status = 'Available'");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservations - Orion College</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <aside class="sidebar">
            <h3>Orion Portal</h3>
            <div class="user-info">
                <p>Welcome, <strong><?php echo htmlspecialchars($username); ?></strong></p>
                <span>Role: <?php echo htmlspecialchars($user_role); ?></span>
            </div>
            <nav class="nav-menu">
                <a href="dashboard.php">📊 Dashboard</a>
                <?php if ($user_role === 'Admin'): ?>
                    <a href="rooms.php">🛏️ Room Management</a>
                <?php endif; ?>
                <a href="reservations.php" class="active">📝 Reservations</a>
                <a href="payments.php">💳 Payments</a>
                <a href="logout.php" class="logout-btn">🚪 Logout</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="content-header">
                <h2>Hostel Bookings & Reservations</h2>
            </header>

            <?php if (!empty($message)): ?>
                <div class="success-msg" style="background: #d1fae5; color: #065f46; padding: 10px; margin-bottom: 15px; border-radius: 4px; border: 1px solid #a7f3d0;"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($user_role === 'Student'): ?>
                <section class="table-section" style="margin-bottom: 30px;">
                    <h3>Submit a Hostel Room Reservation Request</h3>
                    <form action="reservations.php" method="POST" id="reservationForm" onsubmit="return validateDates();">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 15px;">
                            <div class="input-group">
                                <label>Your Full Name</label>
                                <input type="text" name="student_name" value="<?php echo htmlspecialchars($username); ?>" readonly required>
                            </div>
                            <div class="input-group">
                                <label>Student Identification ID</label>
                                <input type="text" name="student_id_code" placeholder="e.g., OC/STU/2026/04" required>
                            </div>
                            <div class="input-group">
                                <label>Select an Available Room</label>
                                <select name="room_id" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                                    <?php if ($avail_rooms_dropdown->num_rows > 0): ?>
                                        <?php while($rm = $avail_rooms_dropdown->fetch_assoc()): ?>
                                            <option value="<?php echo $rm['id']; ?>">
                                                Room <?php echo htmlspecialchars($rm['room_number']); ?> - <?php echo htmlspecialchars($rm['room_type']); ?> (Rs. <?php echo number_format($rm['price'], 2); ?>/mo)
                                            </option>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <option value="">No rooms available currently</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="input-group">
                                <label>Expected Check-In Date</label>
                                <input type="date" id="check_in" name="check_in" required>
                            </div>
                            <div class="input-group">
                                <label>Expected Check-Out Date</label>
                                <input type="date" id="check_out" name="check_out" required>
                            </div>
                        </div>
                        <button type="submit" name="submit_reservation" class="btn-submit" style="width: auto; padding: 12px 30px;">Submit Request</button>
                    </form>
                </section>
            <?php endif; ?>

            <section class="table-section">
                <h3><?php echo ($user_role === 'Admin') ? "All System Booking Requests" : "Your Reservation History"; ?></h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Student Name</th>
                            <th>Student ID</th>
                            <th>Room No.</th>
                            <th>Check-In</th>
                            <th>Check-Out</th>
                            <th>Status</th>
                            <?php if ($user_role === 'Admin'): ?>
                                <th style="text-align: center;">Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($res_result->num_rows > 0): ?>
                            <?php while($row = $res_result->fetch_assoc()): ?>
                                <tr>
                                    <td>#<?php echo $row['id']; ?></td>
                                    <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['student_id']); ?></td>
                                    <td>Room <?php echo htmlspecialchars($row['room_number']); ?></td>
                                    <td><?php echo $row['check_in']; ?></td>
                                    <td><?php echo $row['check_out']; ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($row['status']); ?>">
                                            <?php echo htmlspecialchars($row['status']); ?>
                                        </span>
                                    </td>
                                    <?php if ($user_role === 'Admin'): ?>
                                        <td style="text-align: center;">
                                            <?php if ($row['status'] === 'Pending'): ?>
                                                <a href="reservations.php?action=approve&res_id=<?php echo $row['id']; ?>" class="status-badge status-approved" style="text-decoration: none; margin-right: 5px;">Approve</a>
                                                <a href="reservations.php?action=reject&res_id=<?php echo $row['id']; ?>" class="status-badge status-rejected" style="text-decoration: none;">Reject</a>
                                            <?php else: ?>
                                                <span style="font-size: 12px; color: #94a3b8;">Processed</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?php echo ($user_role === 'Admin') ? '8' : '7'; ?>" style="text-align: center;">No reservation records found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </section>
        </main>
    </div>

    <script>
    function validateDates() {
        var checkIn = document.getElementById('check_in').value;
        var checkOut = document.getElementById('check_out').value;
        var today = new Date().toISOString().split('T')[0];

        if (!checkIn || !checkOut) {
            alert("Please fill in both dates.");
            return false;
        }
        if (checkIn < today) {
            alert("Check-in date cannot be in the past.");
            return false;
        }
        if (checkOut <= checkIn) {
            alert("Check-out date must be after your check-in date.");
            return false;
        }
        return true;
    }
    </script>
</body>
</html>