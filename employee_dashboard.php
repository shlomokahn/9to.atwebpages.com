<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit();
}

$employee_id = $_SESSION['employee_id'];

$servername = "fdb1028.awardspace.net";
$username     = "4516834_name";
$password     = "Shlomo1155";
$dbname       = "4516834_name";
$conn         = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("חיבור למסד הנתונים נכשל: " . $conn->connect_error);
}

// טיפול בבקשות - הן לבקשות החלפה/ביטול והן לבקשות אילוץ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handling requests directly associated with a shift (Exchange / Cancellation)
    if (isset($_POST['shift_id']) && isset($_POST['request_type']) && !isset($_POST['add_constraint'])) {
        $shift_id = $_POST['shift_id'];
        $request_type = $_POST['request_type'];
        
        $insert_request = "INSERT INTO shift_requests (employee_id, shift_id, request_type, status, date_submitted) VALUES (?, ?, ?, 'ממתין', NOW())";
        $stmt = $conn->prepare($insert_request);
        $stmt->bind_param("iis", $employee_id, $shift_id, $request_type);
        $stmt->execute();
        $stmt->close();
        $message = "הבקשה נשלחה בהצלחה!";
    } 
    // Handling the separate constraint form submission.
    // For constraint requests, we do not provide a shift ID, so we insert a NULL.
    elseif (isset($_POST['add_constraint']) && isset($_POST['constraint_date']) && isset($_POST['constraint_type'])) {
        $constraint_date = $_POST['constraint_date'];
        $constraint_type = $_POST['constraint_type'];
        // In case of shift requests (בוקר or ערב), also grab the location.
        if ($constraint_type === "אילוץ - בקשה משמרת בוקר" || $constraint_type === "אילוץ - בקשה משמרת ערב") {
            $constraint_location = $_POST['constraint_location'] ?? "";
            $full_request_type = $constraint_type . " לתאריך " . $constraint_date . " במיקום " . $constraint_location;
        } else {
            $full_request_type = $constraint_type . " לתאריך " . $constraint_date;
        }
        $insert_request = "INSERT INTO shift_requests (employee_id, shift_id, request_type, status, date_submitted) VALUES (?, NULL, ?, 'ממתין', NOW())";
        $stmt = $conn->prepare($insert_request);
        $stmt->bind_param("is", $employee_id, $full_request_type);
        $stmt->execute();
        $stmt->close();
        $message = "האילוץ נשלח בהצלחה!";
    } else {
        $message = "נא למלא את כל השדות הנדרשים לבקשה.";
    }
}

// שליפת המשמרות של העובד
$shifts_query = "SELECT * FROM shifts WHERE employee_id = ? ORDER BY date";
$stmt = $conn->prepare($shifts_query);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$shifts_result = $stmt->get_result();

// שליפת בקשות העובד (בין החלפה, ביטול ואילוצים)
$requests_query = "SELECT sr.*, s.shift_name, s.date, s.shift_time 
                   FROM shift_requests sr 
                   LEFT JOIN shifts s ON sr.shift_id = s.id 
                   WHERE sr.employee_id = ? 
                   ORDER BY sr.date_submitted DESC";
$stmt = $conn->prepare($requests_query);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$requests_result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>לוח משמרות אישי</title>
    <style>
        /* עיצוב כללי */
        body { 
            font-family: Arial, sans-serif; 
            background-color: #f4f4f4; 
            margin: 0; 
            padding: 20px; 
            text-align: center;
        }
        .container { max-width: 90%; margin: 0 auto; }
        h2 { color: #4CAF50; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        th, td { padding: 10px; border: 1px solid #ccc; }
        th { background-color: #eaeaea; }
        .btn { 
            padding: 8px 12px; 
            background-color: #4CAF50; 
            color: white; 
            border: none; 
            cursor: pointer; 
            margin: 5px;
        }
        .btn:hover { background-color: #45a049; }
        .message { color: green; text-align: center; }
        form.inline { display: inline; }
        .form-input { 
            padding: 8px; 
            margin: 5px; 
            border: 1px solid #ddd; 
            border-radius: 4px; 
        }
        /* סגנונות לכפתור ההתנתקות */
        .logout-btn { 
            background-color: #e74c3c; 
            color: white; 
            padding: 10px 20px; 
            border: none; 
            border-radius: 5px; 
            text-decoration: none; 
            margin: 10px;
        }
        .logout-btn:hover { background-color: #c0392b; }
        /* סגנון לטופס הוספת אילוץ */
        .constraint-form {
            border: 1px solid #ccc;
            padding: 15px;
            margin: 20px auto;
            max-width: 500px;
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 0 5px rgba(0,0,0,0.1);
        }
        label { display: block; margin: 10px 0 5px; }
    </style>
    <script>
        // Script to build future dates options and to show/hide location select based on constraint type
        window.addEventListener('DOMContentLoaded', function() {
            // Populate the constraint_date select with future dates (next 30 days)
            var dateSelect = document.getElementById('constraint_date');
            if (dateSelect) {
                var today = new Date();
                for (var i = 1; i <= 30; i++) {
                    var futureDate = new Date();
                    futureDate.setDate(today.getDate() + i);
                    // Format date as YYYY-MM-DD
                    var year = futureDate.getFullYear();
                    var month = ("0" + (futureDate.getMonth() + 1)).slice(-2);
                    var day = ("0" + futureDate.getDate()).slice(-2);
                    var formattedDate = year + "-" + month + "-" + day;
                    var option = document.createElement('option');
                    option.value = formattedDate;
                    option.text = formattedDate;
                    dateSelect.appendChild(option);
                }
            }
            // Listen to changes on the constraint_type select to display location selection if needed
            var typeSelect = document.getElementById('constraint_type');
            var locationDiv = document.getElementById('locationDiv');
            typeSelect.addEventListener('change', function() {
                var selected = typeSelect.value;
                if (selected === "אילוץ - בקשה משמרת בוקר" || selected === "אילוץ - בקשה משמרת ערב") {
                    locationDiv.style.display = "block";
                } else {
                    locationDiv.style.display = "none";
                }
            });
        });
    </script>
</head>
<body>
    <div class="container">
        <!-- כפתור התנתקות -->
        <a href="logout.php" class="logout-btn">התנתק</a>
        
        <h2>ברוך הבא, <?php echo htmlspecialchars($_SESSION['name']); ?>!</h2>
        <?php if (isset($message)) { echo "<p class='message'>{$message}</p>"; } ?>
        
        <h3>המשמרות שלך:</h3>
        <table>
            <tr>
                <th>תאריך</th>
                <th>משמרת</th>
                <th>שעת התחלה</th>
                <th>מיקום</th>
                <th>פעולות</th>
            </tr>
            <?php while ($shift = $shifts_result->fetch_assoc()) { ?>
                <tr>
                    <td><?php echo htmlspecialchars($shift['date']); ?></td>
                    <td><?php echo htmlspecialchars($shift['shift_name']); ?></td>
                    <td><?php echo htmlspecialchars($shift['shift_time']); ?></td>
                    <td><?php echo htmlspecialchars($shift['shift_location']); ?></td>
                    <td>
                        <!-- אפשרות להגשת בקשה להחלפה -->
                        <form method="POST" class="inline">
                            <input type="hidden" name="shift_id" value="<?php echo $shift['id']; ?>">
                            <input type="hidden" name="request_type" value="החלפה">
                            <input type="submit" value="בקשת החלפה" class="btn">
                        </form>
                        <!-- אפשרות להגשת בקשה לביטול -->
                        <form method="POST" class="inline">
                            <input type="hidden" name="shift_id" value="<?php echo $shift['id']; ?>">
                            <input type="hidden" name="request_type" value="ביטול">
                            <input type="submit" value="בקשת ביטול" class="btn">
                        </form>
                        <!-- אפשרות להגשת בקשה לאילוץ inline -->
                        <form method="POST" class="inline">
                            <input type="hidden" name="shift_id" value="<?php echo $shift['id']; ?>">
                            <select name="request_type" class="form-input">
                                <option value="אילוץ - יום חופש">אילוץ - יום חופש</option>
                                <option value="אילוץ - בקשה משמרת בוקר">אילוץ - בקשה משמרת בוקר</option>
                                <option value="אילוץ - בקשה משמרת ערב">אילוץ - בקשה משמרת ערב</option>
                                <option value="אילוץ - בקשה לסניף">אילוץ - בקשה לסניף</option>
                            </select>
                            <input type="submit" value="שלח אילוץ" class="btn">
                        </form>
                    </td>
                </tr>
            <?php } ?>
        </table>

        <h3>הבקשות שלך:</h3>
        <table>
            <tr>
                <th>תאריך בקשה</th>
                <th>משמרת/אילוץ</th>
                <th>תאריך משמרת/תאריך אילוץ</th>
                <th>שעת התחלה</th>
                <th>סוג בקשה</th>
                <th>סטטוס</th>
            </tr>
            <?php while ($request = $requests_result->fetch_assoc()) { ?>
                <tr>
                    <td><?php echo htmlspecialchars($request['date_submitted']); ?></td>
                    <td><?php echo htmlspecialchars($request['shift_name'] ? $request['shift_name'] : "אילוץ"); ?></td>
                    <td><?php echo htmlspecialchars($request['date'] ? $request['date'] : ""); ?></td>
                    <td><?php echo htmlspecialchars($request['shift_time'] ? $request['shift_time'] : ""); ?></td>
                    <td><?php echo htmlspecialchars($request['request_type']); ?></td>
                    <td><?php echo htmlspecialchars($request['status']); ?></td>
                </tr>
            <?php } ?>
        </table>
        
        <h3>הוספת אילוץ חדש</h3>
        <!-- טופס הוספת אילוץ עם בחירת תאריך מתוך רשימה של תאריכים עתידיים -->
        <div class="constraint-form">
            <form method="POST">
                <label for="constraint_date">בחר תאריך:</label>
                <select name="constraint_date" id="constraint_date" class="form-input" required>
                    <!-- Options will be populated by JavaScript -->
                </select>
                <label for="constraint_type">סוג אילוץ:</label>
                <select name="constraint_type" id="constraint_type" class="form-input" required>
                    <option value="אילוץ - יום חופש">אילוץ - יום חופש</option>
                    <option value="אילוץ - בקשה משמרת בוקר">אילוץ - בקשה משמרת בוקר</option>
                    <option value="אילוץ - בקשה משמרת ערב">אילוץ - בקשה משמרת ערב</option>
                    <option value="אילוץ - בקשה לסניף">אילוץ - בקשה לסניף</option>
                </select>
                <div id="locationDiv" style="display:none;">
                    <label for="constraint_location">בחר מיקום:</label>
                    <select name="constraint_location" id="constraint_location" class="form-input">
                        <option value="דוידקה">דוידקה</option>
                        <option value="שמואל הנביא">שמואל הנביא</option>
                    </select>
                </div>
                <input type="submit" name="add_constraint" value="שלח אילוץ" class="btn">
            </form>
        </div>
    </div>
</body>
</html>