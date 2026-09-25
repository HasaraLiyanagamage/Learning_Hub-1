<?php
$host = "localhost";
$user = "root";
$password = "";
$dbname = "hostel_management_db";

// Create connection
$conn = new mysqli($host, $user, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}
?>