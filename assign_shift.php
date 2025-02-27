<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    header("Location: login.php");
    exit();
}

// חיבור לבסיס הנתונים
$mysqli = new mysqli('fdb1028.awardspace.net', '4516834_name', 'Shlomo1155', '4516834_name');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// בדיקה אם התקבלו נתונים מהטופס
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['shift_id']) && isset($_POST['employee_id'])) {
    $shift_id = $_POST['shift_id'];
    $employee_id = $_POST['employee_id'];

    // עדכון המשמרת כך שתשויך לעובד שנבחר
    $update_query = "UPDATE shifts SET employee_id = ? WHERE id = ?";
    $stmt = $mysqli->prepare($update_query);
    $stmt->bind_param("ii", $employee_id, $shift_id);

    if ($stmt->execute()) {
        echo "<script>alert('המשמרת הוקצתה בהצלחה!'); window.location.href = 'admin_dashboard.php';</script>";
    } else {
        echo "שגיאה בהקצאת המשמרת: " . $mysqli->error;
    }

    $stmt->close();
}

$mysqli->close();
?>
