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

// 1. CREATE OPERATION: Handle Student Payment Record Submission
if (isset($_POST['submit_payment'])) {
    $reservation_id = intval($_POST['reservation_id']);
    $amount = floatval($_POST['amount']);

    if ($amount <= 0) {
        $error = "Payment amount must be greater than zero.";
    } else {
        // Prepared Statement to inject payment transaction securely
        $stmt = $conn->prepare("INSERT INTO payments (reservation_id, amount, payment_status) VALUES (?, ?, 'Paid')");
        $stmt->bind_param("id", $reservation_id, $amount);
        
        if ($stmt->execute()) {
            $message = "Payment of Rs. " . number_format($amount, 2) . " processed successfully!";
        } else {
            $error = "Payment processing failed. Please check your reservation reference.";
        }
        $stmt->close();
    }
}

// 2. READ OPERATION: Fetch payments based on account role
if ($user_role === 'Admin') {
    // Admins see a comprehensive log of all financial receipts across the campus
    $pay_query = "SELECT p.*, r.student_name, r.student_id, rm.room_number 
                  FROM payments p 
                  JOIN reservations r ON p.reservation_id = r.id 
                  JOIN rooms rm ON r.room_id = rm.id 
                  ORDER BY p.id DESC";
} else {
    // Students only see financial records tied to their own approved bookings
    $pay_query = "SELECT p.*, r.student_name, r.student_id, rm.room_number 
                  FROM payments p 
                  JOIN reservations r ON p.reservation_id = r.id 
                  JOIN rooms rm ON r.room_id = rm.id 
                  WHERE r.student_name = '" . $conn->real_escape_string($username) . "' 
                  ORDER BY p.id DESC";
}
$pay_result = $conn->query($pay_query);

// Fetch approved reservations for the logged-in student to choose from when paying
$student_approved_bookings = $conn->query("SELECT r.id, rm.room_number, rm.price 
                                           FROM reservations r 
                                           JOIN rooms rm ON r.room_id = rm.id 
                                           WHERE r.student_name = '" . $conn->real_escape_string($username) . "' AND r.status = 'Approved'");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - Orion College</title>
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
                <a href="reservations.php">📝 Reservations</a>
                <a href="payments.php" class="active">💳 Payments</a>
                <a href="logout.php" class="logout-btn">🚪 Logout</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="content-header">
                <h2>Fee Collections & Payment Tracking</h2>
            </header>

            <?php if (!empty($message)): ?>
                <div class="success-msg" style="background: #d1fae5; color: #065f46; padding: 10px; margin-bottom: 15px; border-radius: 4px; border: 1px solid #a7f3d0;"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($user_role === 'Student'): ?>
                <section class="table-section" style="margin-bottom: 30px;">
                    <h3>Submit Hostel Fee Payment</h3>
                    <form action="payments.php" method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; align-items: end;">
                        <div class="input-group">
                            <label>Select Your Approved Reservation Reference</label>
                            <select name="reservation_id" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                                <?php if ($student_approved_bookings->num_rows > 0): ?>
                                    <?php while($bk = $student_approved_bookings->fetch_assoc()): ?>
                                        <option value="<?php echo $bk['id']; ?>">
                                            Booking #<?php echo $bk['id']; ?> (Room <?php echo htmlspecialchars($bk['room_number']); ?>) - Rs. <?php echo number_format($bk['price'], 2); ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <option value="">No approved active bookings found</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="input-group">
                            <label>Amount to Pay (Rs.)</label>
                            <input type="number" step="0.01" name="amount" placeholder="0.00" required>
                        </div>
                        <button type="submit" name="submit_payment" class="btn-submit" style="padding: 11px;">Submit Payment</button>
                    </form>
                </section>
            <?php endif; ?>

            <section class="table-section">
                <h3><?php echo ($user_role === 'Admin') ? "Master Fee Collection Log" : "Your Personal Payment Receipts"; ?></h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Receipt ID</th>
                            <th>Student Name</th>
                            <th>Student ID</th>
                            <th>Room Location</th>
                            <th>Amount Cleared</th>
                            <th>Transaction Timestamp</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($pay_result->num_rows > 0): ?>
                            <?php while($row = $pay_result->fetch_assoc()): ?>
                                <tr>
                                    <td>#<?php echo $row['id']; ?></td>
                                    <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['student_id']); ?></td>
                                    <td>Room <?php echo htmlspecialchars($row['room_number']); ?></td>
                                    <td><strong>Rs. <?php echo number_format($row['amount'], 2); ?></strong></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($row['payment_date'])); ?></td>
                                    <td>
                                        <span class="status-badge status-approved">
                                            <?php echo htmlspecialchars($row['payment_status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center;">No payment logs tracked in the database yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </section>
        </main>
    </div>
</body>
</html>