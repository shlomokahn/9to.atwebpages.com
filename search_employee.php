<?php
$servername = "fdb1028.awardspace.net";
$username = "4516834_name";
$password = "Shlomo1155";
$dbname = "4516834_name";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_GET['employee_search'])) {
    $employee_search = $_GET['employee_search'];
    $employee_query = "SELECT * FROM employees WHERE name LIKE '%$employee_search%' OR role LIKE '%$employee_search%'";
    $employee_result = $conn->query($employee_query);

    echo "<h3>תוצאות חיפוש עובדים:</h3>";
    if ($employee_result->num_rows > 0) {
        echo "<table><tr><th>שם</th><th>תפקיד</th><th>שם משתמש</th></tr>";
        while ($employee = $employee_result->fetch_assoc()) {
            echo "<tr><td>" . htmlspecialchars($employee['name']) . "</td><td>" . htmlspecialchars($employee['role']) . "</td><td>" . htmlspecialchars($employee['username']) . "</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p>לא נמצאו עובדים התואמים לחיפוש שלך.</p>";
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