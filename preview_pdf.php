<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    header("Location: login.php");
    exit();
}

$servername = "fdb1028.awardspace.net";
$username   = "4516834_name";
$password   = "Shlomo1155";
$dbname     = "4516834_name";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8");

// Determine the upcoming Sunday ("ראשון") as the start date.
// If today is Sunday, we'll start today; otherwise, calculate next Sunday's date.
if (date('w') == 0) {
    $start_date = date('Y-m-d');
} else {
    $start_date = date('Y-m-d', strtotime("next sunday"));
}
// We want to display from Sunday ("ראשון") until Friday ("שישי") – 6 days total.
$end_date = date('Y-m-d', strtotime("$start_date +5 days"));

// Build an array of dates to display indexed by date with its Hebrew day name.
$hebrew_days = ['ראשון', 'שני', 'שלישי', 'רביעי', 'חמישי', 'שישי'];
$display_dates = [];
for ($i = 0; $i < 6; $i++) {
    $d = date('Y-m-d', strtotime("$start_date +$i days"));
    $index = date('w', strtotime($d));
    // We'll assume these are between Sunday (0) and Friday (5)
    if ($index == 6) { // safety check: skip Saturday
        continue;
    }
    $display_dates[$d] = $hebrew_days[$index];
}

// Prepare date range string for subtitle, e.g. "23-28/02/2025"
$start_display = date("d", strtotime($start_date));
$end_display = date("d/m/Y", strtotime($end_date));
$date_range = $start_display . "-" . $end_display;

// Optional branch filter via GET parameter.
$branch_filter = isset($_GET['branch']) ? $_GET['branch'] : null;

// Fetch only shifts within the display date range.
$sql = "SELECT s.date, s.shift_time, s.shift_end_time, s.shift_name, e.name AS employee_name, s.branch 
        FROM shifts s 
        LEFT JOIN employees e ON s.employee_id = e.id
        WHERE s.date BETWEEN '$start_date' AND '$end_date' ";
if ($branch_filter) {
    $sql .= "AND s.branch = '" . $conn->real_escape_string($branch_filter) . "' ";
}
$sql .= "ORDER BY s.branch, s.date, s.shift_time";

$result = $conn->query($sql);

// Group shifts by branch, then by date, then by shift type ("בוקר" or "ערב").
$shifts_by_branch = [];
while ($row = $result->fetch_assoc()) {
    $branch = $row['branch'];
    if (!isset($shifts_by_branch[$branch])) {
        // For each display date initialize an array for shift types.
        foreach ($display_dates as $d => $dayName) {
            $shifts_by_branch[$branch][$d] = ['בוקר' => [], 'ערב' => []];
        }
    }
    $shift_date = $row['date'];
    if (!isset($display_dates[$shift_date])) {
        continue;
    }
    $shift_type = trim($row['shift_name']);
    if ($shift_type !== "בוקר" && $shift_type !== "ערב") {
        continue;
    }
    $shifts_by_branch[$branch][$shift_date][$shift_type][] = $row;
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>סידור עבודה סבא-ספיקרפון</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            color: #333;
            text-align: center;
        }
        .container {
            width: 95%;
            margin: 20px auto;
            background: #fff;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .button-container {
            margin: 20px 0;
        }
        button {
            padding: 10px 20px;
            font-size: 16px;
            margin: 0 10px;
            cursor: pointer;
        }
        /* Hide buttons during printing */
        @media print {
            .button-container {
                display: none;
            }
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            vertical-align: top;
            text-align: center;
        }
        th {
            background: #007bff;
            color: #fff;
            font-weight: bold;
        }
        td.shift-cell {
            min-width: 130px;
        }
        .branch-title {
            font-size: 24px;
            margin: 20px 0;
            font-weight: bold;
        }
        .report-title {
            font-size: 28px;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .report-subtitle {
            font-size: 20px;
            margin-bottom: 20px;
        }
    </style>
    <script>
        function printPage() {
            window.print();
        }
    </script>
</head>
<body>
<div class="container">
    <div class="report-title">סידור עבודה סבא-ספיקרפון</div>
    <div class="report-subtitle"><?php echo $date_range; ?></div>
    
    <?php foreach ($shifts_by_branch as $branch => $dates): ?>
        <div class="branch-title">סניף: <?php echo htmlspecialchars($branch); ?></div>
        <?php
            // Set shift time display based on branch.
            if ($branch === "שמואל הנביא") {
                $morning_label = "09:30 - 17:30";
                $evening_label = "14:00 - 22:00";
            } else {
                $morning_label = "09:00 - 17:00";
                $evening_label = "13:00 - 21:00";
            }
        ?>
        <table>
            <thead>
                <tr>
                    <th>משמרת</th>
                    <?php foreach ($display_dates as $date => $dayName): ?>
                        <th><?php echo $dayName . " (" . date("d/m", strtotime($date)) . ")"; ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="shift-cell">משמרת בוקר<br>(<?php echo $morning_label; ?>)</td>
                    <?php foreach ($display_dates as $date => $dayName): ?>
                        <td class="shift-cell">
                            <?php
                            if (!empty($dates[$date]['בוקר'])) {
                                foreach ($dates[$date]['בוקר'] as $shift) {
                                    echo htmlspecialchars($shift['employee_name'] ?: 'לא שובץ') . "<br>";
                                }
                            } else {
                                echo "לא שובץ";
                            }
                            ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <td class="shift-cell">משמרת ערב<br>(<?php echo $evening_label; ?>)</td>
                    <?php foreach ($display_dates as $date => $dayName): ?>
                        <td class="shift-cell">
                            <?php
                            if (!empty($dates[$date]['ערב'])) {
                                foreach ($dates[$date]['ערב'] as $shift) {
                                    echo htmlspecialchars($shift['employee_name'] ?: 'לא שובץ') . "<br>";
                                }
                            } else {
                                echo "לא שובץ";
                            }
                            ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            </tbody>
        </table>
    <?php endforeach; ?>
    
    <div class="button-container">
        <button onclick="printPage()">הדפסה</button>
        <button onclick="window.location.href='admin_dashboard.php'">חזרה</button>
    </div>
</div>
</body>
</html>
<?php
$conn->close();
?>