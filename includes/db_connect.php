<?php
$servername = "localhost";
$username = "root";        // Your DB username (default for XAMPP/MAMP is often root with no password)
$password = "";            // Your DB password
$dbname = "tasktrack_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// Set charset to utf8mb4 for broader character support
$conn->set_charset("utf8mb4");
?>