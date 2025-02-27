<?php
$servername = "fdb1028.awardspace.net";
$username = "4516834_name";
$password = "Shlomo1155";
$dbname = "4516834_name";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_GET['shift_date']) || isset($_GET['shift_location'])) {
    $shift_date = $_GET['shift_date'];
    $shift_location = $_GET['shift_location'];
    $shift_query = "SELECT * FROM shifts WHERE date LIKE '%$shift_date%' OR shift_location LIKE '%$shift_location%'";
    $shift_result = $conn->query($shift_query);

    echo "<h3>תוצאות חיפוש משמרות:</h3>";
    if ($shift_result->num_rows > 0) {
        echo "<table><tr><th>סוג משמרת</th><th>תאריך</th><th>שעה</th><th>מיקום</th></tr>";
        while ($shift = $shift_result->fetch_assoc()) {
            echo "<tr><td>" . htmlspecialchars($shift['shift_name']) . "</td><td>" . htmlspecialchars($shift['date']) . "</td><td>" . htmlspecialchars($shift['shift_time']) . "</td><td>" . htmlspecialchars($shift['shift_location']) . "</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p>לא נמצאו משמרות התואמות לחיפוש שלך.</p>";
    }
}
?>
<a href="admin_dashboard.php" class="btn">חזור לעמוד הניהול</a>

<style>
    body { text-align: center; font-family: Arial, sans-serif; }
    table { width: 80%; margin: 20px auto; border-collapse: collapse; }
    th, td { padding: 10px; border: 1px solid black; text-align: center; }
    th { background-color: #f2f2f2; }
    .btn { margin: 10px; padding: 10px 20px; background-color: #4CAF50; color: white; text-decoration: none; }
</style>