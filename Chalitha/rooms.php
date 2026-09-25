<?php
session_start();
require_once 'db_connect.php';

// Session Security: Restrict this page to Admin users only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$message = "";
$error = "";

// 1. CREATE OPERATION (Add New Room) with Image Upload
if (isset($_POST['add_room'])) {
    $room_number = trim($_POST['room_number']);
    $room_type = trim($_POST['room_type']);
    $capacity = intval($_POST['capacity']);
    $price = floatval($_POST['price']);
    $status = trim($_POST['status']);
    
    // Image Upload Logic [Advanced Feature]
    $image_name = "";
    if (isset($_FILES['room_image']) && $_FILES['room_image']['error'] == 0) {
        $target_dir = "uploads/";
        $image_name = time() . "_" . basename($_FILES["room_image"]["name"]);
        $target_file = $target_dir . $image_name;
        
        // Basic image validation
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        $allowed_types = array("jpg", "jpeg", "png", "gif");
        
        if (in_array($imageFileType, $allowed_types)) {
            move_uploaded_file($_FILES["room_image"]["tmp_name"], $target_file);
        } else {
            $error = "Only JPG, JPEG, PNG & GIF image formats are allowed.";
        }
    }

    if (empty($error)) {
        // Prepared Statement to prevent SQL Injection
        $stmt = $conn->prepare("INSERT INTO rooms (room_number, room_type, capacity, price, status, image) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssisss", $room_number, $room_type, $capacity, $price, $status, $image_name);
        
        if ($stmt->execute()) {
            $message = "Room added successfully!";
        } else {
            $error = "Error adding room. Room number might already exist.";
        }
        $stmt->close();
    }
}

// 2. DELETE OPERATION
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    
    // Fetch image path first to delete it from the server directory
    $img_stmt = $conn->prepare("SELECT image FROM rooms WHERE id = ?");
    $img_stmt->bind_param("i", $delete_id);
    $img_stmt->execute();
    $res = $img_stmt->get_result()->fetch_assoc();
    if (!empty($res['image']) && file_exists("uploads/" . $res['image'])) {
        unlink("uploads/" . $res['image']);
    }
    $img_stmt->close();

    // Delete room entry from database
    $stmt = $conn->prepare("DELETE FROM rooms WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $message = "Room record deleted successfully!";
    } else {
        $error = "Unable to delete room. It might be linked to an existing reservation.";
    }
    $stmt->close();
}

// 3. READ OPERATION (Fetch all rooms for display)
$rooms_result = $conn->query("SELECT * FROM rooms ORDER BY room_number ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Management - Orion College</title>
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
                <a href="dashboard.php">📊 Dashboard</a>
                <a href="rooms.php" class="active">🛏️ Room Management</a>
                <a href="reservations.php">📝 Reservations</a>
                <a href="payments.php">💳 Payments</a>
                <a href="logout.php" class="logout-btn">🚪 Logout</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="content-header">
                <h2>Hostel Room Management Portal</h2>
            </header>

            <?php if (!empty($message)): ?>
                <div class="success-msg" style="background: #d1fae5; color: #065f46; padding: 10px; margin-bottom: 15px; border-radius: 4px; border: 1px solid #a7f3d0;"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <section class="table-section" style="margin-bottom: 30px;">
                <h3>Add a New Room Entry</h3>
                <form action="rooms.php" method="POST" enctype="multipart/form-data" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end;">
                    <div class="input-group">
                        <label>Room Number</label>
                        <input type="text" name="room_number" required placeholder="e.g., A-101">
                    </div>
                    <div class="input-group">
                        <label>Room Type</label>
                        <select name="room_type" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                            <option value="Single AC">Single AC</option>
                            <option value="Single Non-AC">Single Non-AC</option>
                            <option value="Sharing 2-Bed AC">Sharing 2-Bed AC</option>
                            <option value="Sharing 4-Bed Non-AC">Sharing 4-Bed Non-AC</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label>Capacity (Beds)</label>
                        <input type="number" name="capacity" min="1" max="10" required>
                    </div>
                    <div class="input-group">
                        <label>Monthly Price (Rs.)</label>
                        <input type="number" step="0.01" name="price" required>
                    </div>
                    <div class="input-group">
                        <label>Availability Status</label>
                        <select name="status" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                            <option value="Available">Available</option>
                            <option value="Reserved">Reserved</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label>Room Image Thumbnail</label>
                        <input type="file" name="room_image" accept="image/*" style="border: none; padding: 0;">
                    </div>
                    <button type="submit" name="add_room" class="btn-submit" style="padding: 11px;">Save Room</button>
                </form>
            </section>

            <section class="table-section">
                <h3>Current Registered Hostel Rooms</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Room No.</th>
                            <th>Type Classification</th>
                            <th>Capacity</th>
                            <th>Monthly Price</th>
                            <th>Current Status</th>
                            <th style="text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($rooms_result->num_rows > 0): ?>
                            <?php while($row = $rooms_result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <?php if(!empty($row['image'])): ?>
                                            <img src="uploads/<?php echo $row['image']; ?>" alt="Room thumbnail" style="width: 60px; height: 45px; object-fit: cover; border-radius: 4px;">
                                        <?php else: ?>
                                            <span style="font-size: 11px; color:#94a3b8;">No Image</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($row['room_number']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['room_type']); ?></td>
                                    <td><?php echo $row['capacity']; ?> Students</td>
                                    <td>Rs. <?php echo number_format($row['price'], 2); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo ($row['status'] == 'Available') ? 'status-approved' : 'status-rejected'; ?>">
                                            <?php echo htmlspecialchars($row['status']); ?>
                                        </span>
                                    </td>
                                    <td style="text-align: center;">
                                        <a href="rooms.php?delete_id=<?php echo $row['id']; ?>" class="status-badge status-rejected" style="text-decoration: none;" onclick="return confirmDeletion('<?php echo $row['room_number']; ?>');">Remove</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center;">No room records found in database. Create one above!</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </section>
        </main>
    </div>

    <script>
    function confirmDeletion(roomNumber) {
        return confirm("Are you sure you want to permanently delete Room " + roomNumber + "? This action cannot be reversed.");
    }
    </script>
</body>
</html>