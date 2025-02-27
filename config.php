<?php
// config.php
// Start output buffering and session management
ob_start();
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    header("Location: login.php");
    exit();
}

// Database connection configuration
$servername = "fdb1028.awardspace.net";
$username   = "4516834_name";
$password   = "Shlomo1155";
$dbname     = "4516834_name";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Determine branch (defaulting to 'דוידקה' if not specified)
$branch = isset($_GET['branch']) ? $_GET['branch'] : 'דוידקה';
?>