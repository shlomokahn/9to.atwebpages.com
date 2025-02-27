<?php
session_start();
// בדיקת הרשאות מנהל - אם המשתמש אינו מנהל, נשלח לעמוד ההתחברות
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    header("Location: login.php");
    exit();
}

// פרטי החיבור למסד הנתונים
$servername = "fdb1028.awardspace.net";
$username     = "4516834_name";
$password     = "Shlomo1155";
$dbname       = "4516834_name";
$conn         = new mysqli($servername, $username, $password, $dbname);

// בדיקה האם ההתחברות למסד הצליחה
if ($conn->connect_error) {
    die("חיבור למסד הנתונים נכשל: " . $conn->connect_error);
}

// טיפול בבקשות כאשר המנהל לוחץ על אישור או דחייה
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $request_id = $_POST['request_id'];
    $action     = $_POST['action'];

    // שליפת פרטי הבקשה מהטבלה לפי מזהה הבקשה
    $request_query = "SELECT * FROM shift_requests WHERE id = ?";
    $stmt          = $conn->prepare($request_query);
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $request_result = $stmt->get_result();
    $request        = $request_result->fetch_assoc();
    $stmt->close();

    if ($action === 'approve') {
        // עדכון סטטוס הבקשה ל"אושר"
        $update_request = "UPDATE shift_requests SET status = 'אושר' WHERE id = ?";
        $stmt           = $conn->prepare($update_request);
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $stmt->close();

        // אם הבקשה מתייחסת למשמרת ספציפית (shift_id אינו NULL)
        if (!is_null($request['shift_id'])) {
            // במקרה של בקשת ביטול – משחררים את העובד מהמשמרת
            if ($request['request_type'] == 'ביטול') {
                $update_shift = "UPDATE shifts SET employee_id = NULL WHERE id = ?";
                $stmt         = $conn->prepare($update_shift);
                $stmt->bind_param("i", $request['shift_id']);
                $stmt->execute();
                $stmt->close();
            }
            // ניתן להוסיף כאן טיפול אוטומטי לבקשות החלפה במידת הצורך
        } else {
            // טיפול בבקשות אילוץ שאינן קשורות למשמרת ספציפית – למשל, בקשה לסניף מסוים או משמרת בוקר/ערב
            // לדוגמה:
            // "אילוץ - בקשה משמרת בוקר לתאריך YYYY-MM-DD במיקום <מיקום>"
            // "אילוץ - בקשה משמרת ערב לתאריך YYYY-MM-DD במיקום <מיקום>"
            // "אילוץ - יום חופש לתאריך YYYY-MM-DD"  (מקרה זה לא מבצע שיבוץ אוטומטי)
            
            $reqType = $request['request_type'];
            // בודקים האם קיימת בקשה אוטומטית לסוג משמרת (בוקר או ערב)
            if (strpos($reqType, "בקשה משמרת") !== false) {
                // חלוקת המחרוזת כדי להוציא את סוג הבקשה (בוקר/ערב) ואת התאריך ואפשרי את המיקום
                $parts = explode(" לתאריך ", $reqType);
                if (count($parts) >= 2) {
                    // חלק ראשון מכיל את סוג הבקשה לדוגמה: "אילוץ - בקשה משמרת בוקר" או "אילוץ - בקשה משמרת ערב"
                    $baseType = trim($parts[0]);
                    // חלק שני מכיל את התאריך ואפשרי את המיקום
                    $dateAndLocation = trim($parts[1]);
                    $constraint_date = "";
                    $constraint_location = "";
                    // בדיקה אם מופיע "במיקום" במחרוזת
                    if (strpos($dateAndLocation, " במיקום ") !== false) {
                        $dateParts = explode(" במיקום ", $dateAndLocation);
                        $constraint_date = trim($dateParts[0]);
                        $constraint_location = trim($dateParts[1]);
                    } else {
                        $constraint_date = $dateAndLocation;
                    }
                    
                    // קביעת סוג המשמרת – בוקר או ערב – מתוך המחרוזת
                    if (strpos($baseType, "בוקר") !== false) {
                        $shift_name = "בוקר";
                    } elseif (strpos($baseType, "ערב") !== false) {
                        $shift_name = "ערב";
                    } else {
                        $shift_name = null;
                    }
                    
                    // אם יש לנו סוג משמרת ותאריך, ננסה למצוא משמרת פנויה התואמת את התנאים
                    if (!is_null($shift_name) && !empty($constraint_date)) {
                        // חיפוש משמרת פנויה בתאריך המבוקש עבור סוג המשמרת, ובמקרה שיש מיקום, גם לפי מיקום
                        $select_shift = "SELECT id FROM shifts 
                                         WHERE date = ? AND shift_name = ? " . 
                                         ($constraint_location ? "AND shift_location = ? " : "") . 
                                         "AND employee_id IS NULL LIMIT 1";
                        $stmt = $conn->prepare($select_shift);
                        if ($constraint_location) {
                            $stmt->bind_param("sss", $constraint_date, $shift_name, $constraint_location);
                        } else {
                            $stmt->bind_param("ss", $constraint_date, $shift_name);
                        }
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if ($shiftData = $result->fetch_assoc()) {
                            $shift_id = $shiftData['id'];
                            // ביצוע השיבוץ האוטומטי – עדכון המשמרת עם העובד המתאים
                            $update_shift = "UPDATE shifts SET employee_id = ? WHERE id = ?";
                            $stmt_update = $conn->prepare($update_shift);
                            $stmt_update->bind_param("ii", $request['employee_id'], $shift_id);
                            $stmt_update->execute();
                            $stmt_update->close();
                        }
                        $stmt->close();
                    }
                }
            }
            // עבור בקשת "יום חופש" או אילוצים שאינם דורשים שיבוץ אוטומטי, העובד לא ישובץ למשמרת זו.
        }
    } elseif ($action === 'deny') {
        // עדכון סטטוס בקשה ל"נדחה"
        $update_request = "UPDATE shift_requests SET status = 'נדחה' WHERE id = ?";
        $stmt = $conn->prepare($update_request);
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $stmt->close();
    }
}

// שליפת כל הבקשות – שימוש ב-LEFT JOIN כדי לכלול גם בקשות אילוץ (שאין להן משמרת ספציפית)
$requests_query = "SELECT sr.*, e.name AS employee_name, s.shift_name, s.date, s.shift_time 
                   FROM shift_requests sr 
                   JOIN employees e ON sr.employee_id = e.id 
                   LEFT JOIN shifts s ON sr.shift_id = s.id 
                   ORDER BY sr.date_submitted DESC";
$requests_result = $conn->query($requests_query);
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>ניהול בקשות משמרת</title>
    <style>
        /* עיצוב כללי */
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 90%;
            margin: 0 auto;
            padding: 20px;
        }
        h2 {
            text-align: center;
            color: #4CAF50;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ccc;
            text-align: center;
        }
        th {
            background-color: #eaeaea;
        }
        .btn {
            padding: 8px 12px;
            background-color: #4CAF50;
            color: white;
            border: none;
            cursor: pointer;
            margin: 2px;
        }
        .btn:hover {
            background-color: #45a049;
        }
        .btn-deny {
            background-color: #f44336;
        }
        .btn-deny:hover {
            background-color: #e53935;
        }
        /* סגנון לכפתור החזרה שמוביל ל-admin_dashboard */
        .back-btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #555;
            color: #fff;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
        }
        .back-btn:hover {
            background-color: #444;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>ניהול בקשות משמרת</h2>
        <table>
            <tr>
                <th>עובד</th>
                <th>משמרת / אילוץ</th>
                <th>תאריך משמרת / תאריך אילוץ</th>
                <th>שעה</th>
                <th>סוג בקשה</th>
                <th>סטטוס</th>
                <th>תאריך בקשה</th>
                <th>פעולות</th>
            </tr>
            <?php while ($request = $requests_result->fetch_assoc()) { ?>
                <tr>
                    <td><?php echo htmlspecialchars($request['employee_name']); ?></td>
                    <td><?php echo htmlspecialchars($request['shift_name'] ? $request['shift_name'] : "אילוץ"); ?></td>
                    <td><?php echo htmlspecialchars(isset($request['date']) ? $request['date'] : ""); ?></td>
                    <td><?php echo htmlspecialchars(isset($request['shift_time']) ? $request['shift_time'] : ""); ?></td>
                    <td><?php echo htmlspecialchars($request['request_type']); ?></td>
                    <td><?php echo htmlspecialchars($request['status']); ?></td>
                    <td><?php echo htmlspecialchars($request['date_submitted']); ?></td>
                    <td>
                        <?php if ($request['status'] === 'ממתין') { ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                <input type="submit" name="action" value="approve" class="btn">
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                <input type="submit" name="action" value="deny" class="btn btn-deny">
                            </form>
                        <?php } else { ?>
                            -
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>
        </table>
        <!-- כפתור חזרה שמפנה לעמוד admin_dashboard -->
        <a href="admin_dashboard.php" class="back-btn">חזור</a>
    </div>
</body>
</html>