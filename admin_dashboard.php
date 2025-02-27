<?php
// התחלת output buffering כדי למנוע שליחת פלט לפני קריאות header()
ob_start();

session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    header("Location: login.php");
    exit();
}

$servername   = "fdb1028.awardspace.net";
$username     = "4516834_name";
$password     = "Shlomo1155";
$dbname       = "4516834_name";
$conn         = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$branch = isset($_GET['branch']) ? $_GET['branch'] : 'דוידקה';

// שאילתה לספירת בקשות אילוץ חדשות (סטטוס 'ממתין')
$new_constraint_query = "SELECT COUNT(*) as cnt FROM shift_requests WHERE request_type LIKE 'אילוץ - %' AND status = 'ממתין'";
$stmt_count           = $conn->prepare($new_constraint_query);
$stmt_count->execute();
$stmt_count->bind_result($new_request_count);
$stmt_count->fetch();
$stmt_count->close();

// טיפול בטופס איפוס משמרות לסניף
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_shifts'])) {
    $reset_query = "UPDATE shifts SET employee_id = NULL WHERE branch = ?";
    $stmt        = $conn->prepare($reset_query);
    $stmt->bind_param("s", $branch);
    if ($stmt->execute()) {
        echo "<script>alert('כל המשמרות אופסו בהצלחה!'); window.location.href = 'admin_dashboard.php?branch=" . urlencode($branch) . "';</script>";
    } else {
        echo "שגיאה באיפוס המשמרות: " . $conn->error;
    }
    $stmt->close();
}

// טיפול בטופס הוספת עובד
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_employee'])) {
    $name     = $_POST['name'];
    $role     = $_POST['role'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    $insert_employee = "INSERT INTO employees (name, role, username, password) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_employee);
    $stmt->bind_param("ssss", $name, $role, $username, $password);
    $stmt->execute();
    $stmt->close();
}

// טיפול בטופס הוספת משמרת חדשה
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_shift'])) {
    $shift_name   = $_POST['shift_name'];
    $date         = $_POST['date'];
    $shift_time   = $_POST['shift_time'];
    $shift_branch = $_POST['branch'];

    // חישוב זמן הסיום (8 שעות לאחר זמן ההתחלה)
    $shift_end_time = date('H:i', strtotime($shift_time) + 8 * 60 * 60);

    // בדיקה אם קיימת משמרת חופפת עם אותו סוג ומחיקתה
    $check_existing_query = "SELECT id FROM shifts WHERE date = ? AND branch = ? AND shift_name = ?";
    $stmt_check = $conn->prepare($check_existing_query);
    $stmt_check->bind_param("sss", $date, $shift_branch, $shift_name);
    $stmt_check->execute();
    $stmt_check->bind_result($existing_shift_id);
    $stmt_check->fetch();
    $stmt_check->close();

    if ($existing_shift_id) {
        $delete_existing_query = "DELETE FROM shifts WHERE id = ?";
        $stmt_delete = $conn->prepare($delete_existing_query);
        $stmt_delete->bind_param("i", $existing_shift_id);
        $stmt_delete->execute();
        $stmt_delete->close();
    }

    $insert_shift = "INSERT INTO shifts (shift_name, date, shift_time, shift_end_time, branch) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_shift);
    $stmt->bind_param("sssss", $shift_name, $date, $shift_time, $shift_end_time, $shift_branch);
    $stmt->execute();
    $stmt->close();
}

// טיפול בשיבוץ משמרת
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_shift'])) {
    $shift_id    = $_POST['shift_id'];
    $employee_id = $_POST['employee_id'];
    $assign_shift = "UPDATE shifts SET employee_id = ? WHERE id = ?";
    $stmt = $conn->prepare($assign_shift);
    $stmt->bind_param("ii", $employee_id, $shift_id);
    $stmt->execute();
    $stmt->close();
    header("Location: admin_dashboard.php?branch=" . urlencode($branch));
    exit();
}

// טיפול במחיקת משמרת
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_shift'])) {
    $shift_id = $_POST['shift_id'];
    $delete_query = "DELETE FROM shifts WHERE id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("i", $shift_id);
    if ($stmt->execute()) {
        echo "<script>alert('המשמרת נמחקה בהצלחה!'); window.location.href = 'admin_dashboard.php?branch=" . urlencode($branch) . "';</script>";
    } else {
        echo "שגיאה במחיקת המשמרת: " . $conn->error;
    }
    $stmt->close();
}

// טיפול בטופס הפקת PDF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_pdf'])) {
    header("Location: preview_pdf.php");
    exit();
}

// שליפת משמרות שלא שובצו
$query = "SELECT * FROM shifts WHERE employee_id IS NULL AND branch = ? ORDER BY shift_time";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $branch);
$stmt->execute();
$result = $stmt->get_result();

// שליפת משמרות שובצו מראש לצורך תצוגה
$assigned_query = "SELECT employees.name, shifts.shift_name, shifts.date, shifts.shift_time, shifts.shift_end_time
                   FROM shifts JOIN employees ON shifts.employee_id = employees.id
                   WHERE shifts.branch = ? ORDER BY shifts.date";
$stmt = $conn->prepare($assigned_query);
$stmt->bind_param("s", $branch);
$stmt->execute();
$assigned_result = $stmt->get_result();

// קביעת מצב תצוגת לוח השנה: שבועי או חודשי (ברירת מחדל: חודשי)
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'month';
if ($mode === 'week') {
    // עבור תצוגת שבוע: מחשבים את יום ראשון הבא ומציגים 6 ימים (יום ראשון עד יום שישי)
    $start_date = date('Y-m-d', strtotime('next sunday'));
    $days_to_show = 6;
} else {
    // עבור תצוגת חודש: מציגים 28 ימים החל מ"ראשון האחרון"
    $start_date = date('Y-m-d', strtotime('last sunday'));
    $days_to_show = 28;
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>לוח ניהול - סניף <?php echo htmlspecialchars($branch); ?></title>
    <style>
        body { 
            margin: 0; 
            font-family: Arial, sans-serif; 
            background-color: #f0f0f0;
        }
        /* Header styling with hamburger menu */
        .header {
            background-color: #333;
            color: #fff;
            padding: 15px 20px;
            position: fixed;
            top: 0;
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 100;
        }
        .header h2 {
            margin: 0;
            font-size: 20px;
        }
        /* Bold hamburger link */
        .hamburger {
            font-size: 28px;
            text-decoration: none;
            color: #fff;
            font-weight: bold;
            border: 2px solid #fff;
            border-radius: 4px;
            padding: 4px 8px;
        }
        /* Red close icon at the top right of side menu */
        .menu-close {
            font-size: 24px;
            color: red;
            font-weight: bold;
            text-decoration: none;
            position: absolute;
            top: 10px;
            right: 10px;
        }
        /* Side drawer menu using :target hack */
        .side-menu {
            position: fixed;
            top: 0;
            right: -320px;
            width: 300px;
            height: 100%;
            background-color: #444;
            overflow-y: auto;
            transition: right 0.3s ease;
            padding-top: 50px;
            z-index: 200;
        }
        .side-menu a, .side-menu form, .side-menu h3, .side-menu details {
            display: block;
            padding: 10px 20px;
            text-decoration: none;
            font-size: 16px;
            color: #ddd;
        }
        .side-menu a:hover {
            background-color: #575757;
        }
        /* When the side-menu is targeted, slide into view */
        #side-menu:target {
            right: 0;
        }
        /* Styling for collapsible details */
        details {
            background: #555;
            margin: 10px 0;
            border-radius: 4px;
        }
        details summary {
            padding: 10px;
            font-weight: bold;
            cursor: pointer;
            list-style: none;
            outline: none;
        }
        details[open] summary {
            background-color: #666;
        }
        details form {
            background-color: #444;
            padding: 10px 0;
        }
        /* Main content styling */
        .main-content {
            padding: 90px 20px 20px 20px;
        }
        /* Buttons and input styling */
        .btn, .reset-btn, .form-input {
            display: inline-block;
            margin: 10px; 
            padding: 10px 20px; 
            border-radius: 5px;
        }
        .btn {
            background-color: #4CAF50; 
            color: white; 
            text-decoration: none; 
            border: none;
        }
        .reset-btn {
            background-color: red; 
            color: white; 
            border: none; 
            cursor: pointer; 
            font-size: 16px;
        }
        .form-input {
            padding: 8px;
            border: 1px solid #ddd;
            width: 90%;
        }
        .calendar {
            display: grid; 
            grid-template-columns: repeat(<?php echo ($mode === 'week') ? 6 : 7; ?>, 1fr); 
            gap: 10px; 
            text-align: center; 
            margin: 20px auto;
            width: 80%;
        }
        .day {
            padding: 15px; 
            border: 1px solid #ddd; 
            background-color: #fff; 
            border-radius: 5px; 
            box-shadow: 0 0 5px rgba(0,0,0,0.1);
        }
        .shift-card {
            background-color: #f0f8ff; 
            border: 1px solid #ccc; 
            border-radius: 5px; 
            padding: 8px; 
            margin-bottom: 8px; 
            box-shadow: 0 0 5px rgba(0,0,0,0.1);
        }
        /* View mode toggle styling */
        .view-toggle {
            margin: 20px 0;
            text-align: center;
        }
        .view-toggle a {
            margin: 0 10px;
            padding: 8px 16px;
            background: #eee;
            color: #333;
            text-decoration: none;
            border-radius: 4px;
            border: 1px solid #ccc;
        }
        .view-toggle a.active {
            background: #4CAF50;
            color: #fff;
            border-color: #4CAF50;
        }
    </style>
</head>
<body>
    <!-- Header with hamburger icon -->
    <div class="header">
        <h2>לוח ניהול - סניף <?php echo htmlspecialchars($branch); ?></h2>
        <a href="#side-menu" class="hamburger">&#9776;</a>
    </div>

    <!-- Side menu -->
    <div id="side-menu" class="side-menu">
        <a href="#" class="menu-close">X</a>
        <!-- "החלף מחלקה" with nested options -->
        <details open>
            <summary>החלף מחלקה</summary>
            <details>
                <summary>חנויות</summary>
                <a href="admin_dashboard.php?branch=דוידקה">דוידקה</a>
                <a href="admin_dashboard.php?branch=שמואל%20הנביא">שמואל הנביא</a>
            </details>
            <a href="admin_dashboard.php?branch=מעבדה">מעבדה</a>
            <a href="admin_dashboard.php?branch=שליחים">שליחים</a>
        </details>

        <!-- ניהול בקשות משמרת -->
        <a href="manage_requests.php">
            ניהול בקשות משמרת 
            <?php if ($new_request_count > 0) { ?>
                <span style="background-color:red; color:white; padding: 3px 7px; border-radius:50%; font-size:12px;">
                    <?php echo $new_request_count; ?>
                </span>
            <?php } ?>
        </a>

        <!-- הפקת PDF -->
        <details>
            <summary>הפק PDF</summary>
            <form method="POST">
                <input type="submit" name="generate_pdf" value="הפק PDF" class="form-input">
            </form>
        </details>

        <!-- Collapsible sections for forms -->

        <!-- חיפוש עובדים -->
        <details>
            <summary>חיפוש עובדים</summary>
            <form method="GET" action="search_employee.php">
                <input type="text" name="employee_search" placeholder="חפש עובד לפי שם או תפקיד" class="form-input">
                <input type="submit" value="חפש" class="form-input">
            </form>
        </details>

        <!-- חיפוש משמרות -->
        <details>
            <summary>חיפוש משמרות</summary>
            <form method="GET" action="search_shift.php">
                <input type="text" name="shift_date" placeholder="חפש משמרת לפי תאריך" class="form-input">
                <input type="submit" value="חפש" class="form-input">
            </form>
        </details>

        <!-- הוספת עובד חדש -->
        <details>
            <summary>הוספת עובד חדש</summary>
            <form method="POST">
                <input type="text" name="name" placeholder="שם העובד" required class="form-input">
                <input type="text" name="username" placeholder="שם משתמש" required class="form-input">
                <input type="password" name="password" placeholder="סיסמה" required class="form-input">
                <select name="role" class="form-input">
                    <option value="employee">עובד</option>
                    <option value="manager">מנהל</option>
                </select>
                <input type="submit" name="add_employee" value="הוסף עובד" class="form-input">
            </form>
        </details>

        <!-- הוספת משמרת חדשה -->
        <details>
            <summary>הוספת משמרת חדשה</summary>
            <form method="POST">
                <select name="shift_name" required class="form-input">
                    <option value="">בחר סוג משמרת</option>
                    <option value="בוקר">בוקר</option>
                    <option value="ערב">ערב</option>
                </select>
                <input type="date" name="date" required class="form-input">
                <input type="text" name="shift_time" placeholder="שעת התחלה (HH:MM)" required class="form-input">
                <select name="branch" required class="form-input">
                    <option value="דוידקה">דוידקה</option>
                    <option value="שמואל הנביא">שמואל הנביא</option>
                </select>
                <input type="submit" name="add_shift" value="הוסף משמרת" class="form-input">
            </form>
        </details>

        <!-- שיבוץ משמרת -->
        <details>
            <summary>שיבוץ משמרת</summary>
            <form method="POST">
                <select name="shift_id" class="form-input">
                    <?php
                    $result->data_seek(0);
                    while ($row = $result->fetch_assoc()) {
                        echo "<option value='" . $row['id'] . "'>" . $row['shift_name'] . " - " . $row['date'] . "</option>";
                    }
                    ?>
                </select>
                <select name="employee_id" class="form-input">
                    <?php
                    $employees = $conn->query("SELECT id, name FROM employees WHERE role='employee'");
                    while ($emp = $employees->fetch_assoc()) {
                        echo "<option value='" . $emp['id'] . "'>" . $emp['name'] . "</option>";
                    }
                    ?>
                </select>
                <input type="submit" name="assign_shift" value="שבץ משמרת" class="form-input">
            </form>
        </details>
    </div>

    <!-- Main content area -->
    <div class="main-content">
        <!-- View mode toggles -->
        <div class="view-toggle">
            <a href="admin_dashboard.php?branch=<?php echo urlencode($branch); ?>&mode=week" class="<?php echo ($mode==='week') ? 'active' : ''; ?>">הצגת השבוע הבא</a>
            <a href="admin_dashboard.php?branch=<?php echo urlencode($branch); ?>&mode=month" class="<?php echo ($mode==='month') ? 'active' : ''; ?>">הצגה חודשית</a>
        </div>

        <h3>לוח משמרות <?php echo ($mode==='week') ? "השבוע הבא" : "חודשי"; ?> לסניף <?php echo htmlspecialchars($branch); ?>:</h3>
        <div class="calendar">
        <?php
        for ($i = 0; $i < $days_to_show; $i++) {
            $current_date = date('Y-m-d', strtotime($start_date . " +$i days"));
            $day_name = date('l', strtotime($current_date));
            // תרגום שמות ימים לעברית
            $days_translation = [
                'Sunday'    => 'ראשון',
                'Monday'    => 'שני',
                'Tuesday'   => 'שלישי',
                'Wednesday' => 'רביעי',
                'Thursday'  => 'חמישי',
                'Friday'    => 'שישי',
                'Saturday'  => 'שבת'
            ];
            $hebrew_day = isset($days_translation[$day_name]) ? $days_translation[$day_name] : $day_name;
            echo "<div class='day'><strong>$hebrew_day<br>($current_date)</strong><br>";

            // הגדרת משמרות ברירת מחדל
            $default_shifts = [
                'דוידקה' => [
                    ['shift_name' => 'בוקר', 'shift_time' => '09:00'],
                    ['shift_name' => 'ערב', 'shift_time' => '13:00'],
                ],
                'שמואל הנביא' => [
                    ['shift_name' => 'בוקר', 'shift_time' => '09:30'],
                    ['shift_name' => 'ערב', 'shift_time' => '14:00'],
                ],
            ];

            // בדיקה אם קיימות משמרות ליום הנוכחי בסניף
            $check_shifts_query = "SELECT COUNT(*) FROM shifts WHERE date = ? AND branch = ?";
            $stmt_check = $conn->prepare($check_shifts_query);
            $stmt_check->bind_param("ss", $current_date, $branch);
            $stmt_check->execute();
            $stmt_check->bind_result($shift_count);
            $stmt_check->fetch();
            $stmt_check->close();

            // אם אין משמרות, הוסף משמרות ברירת מחדל (כאשר יש הגדרה עבור הסניף)
            if ($shift_count == 0 && isset($default_shifts[$branch])) {
                foreach ($default_shifts[$branch] as $default_shift) {
                    $shift_name = $default_shift['shift_name'];
                    $shift_time = $default_shift['shift_time'];
                    $shift_end_time = date('H:i', strtotime($shift_time) + 8 * 60 * 60);
                    
                    $insert_default_query = "INSERT INTO shifts (shift_name, date, shift_time, shift_end_time, branch) VALUES (?, ?, ?, ?, ?)";
                    $stmt_insert = $conn->prepare($insert_default_query);
                    $stmt_insert->bind_param("sssss", $shift_name, $current_date, $shift_time, $shift_end_time, $branch);
                    $stmt_insert->execute();
                    $stmt_insert->close();
                }
            }
            // שליפת כל המשמרות ליום הנוכחי
            $day_shifts_query = "SELECT s.id, s.shift_name, s.shift_time, s.shift_end_time, s.employee_id, e.name as employee_name
                                 FROM shifts s
                                 LEFT JOIN employees e ON s.employee_id = e.id
                                 WHERE s.date = ? AND s.branch = ?
                                 ORDER BY s.shift_time";
            $stmt_day = $conn->prepare($day_shifts_query);
            $stmt_day->bind_param("ss", $current_date, $branch);
            $stmt_day->execute();
            $day_shifts = $stmt_day->get_result();
            $morning_shifts = [];
            $evening_shifts = [];
            while ($shift = $day_shifts->fetch_assoc()) {
                if ($shift['shift_name'] == 'בוקר') {
                    $morning_shifts[] = $shift;
                } else {
                    $evening_shifts[] = $shift;
                }
            }
            $stmt_day->close();

            // תצוגת משמרות בוקר
            echo "<div class='shift-card'><strong>משמרות בוקר</strong><br>";
            if (count($morning_shifts) > 0) {
                foreach ($morning_shifts as $shift) {
                    echo "<div><strong>" . htmlspecialchars($shift['shift_time']) . " עד " . htmlspecialchars($shift['shift_end_time']) . "</strong><br>";
                    if ($shift['employee_id']) {
                        echo "עובד: " . htmlspecialchars($shift['employee_name']) . "<br>";
                        echo "<form method='POST'>";
                        echo "<input type='hidden' name='shift_id' value='" . $shift['id'] . "'>";
                        echo "<select name='employee_id' class='form-input'>";
                        $emps = $conn->query("SELECT id, name FROM employees WHERE role='employee'");
                        while ($emp = $emps->fetch_assoc()) {
                            $selected = ($emp['id'] == $shift['employee_id']) ? 'selected' : '';
                            echo "<option value='" . $emp['id'] . "' $selected>" . $emp['name'] . "</option>";
                        }
                        echo "</select>";
                        echo "<input type='submit' name='assign_shift' value='החלף' class='btn'>";
                        echo "</form>";
                    } else {
                        echo "לא שובץ<br>";
                        echo "<form method='POST'>";
                        echo "<input type='hidden' name='shift_id' value='" . $shift['id'] . "'>";
                        echo "<select name='employee_id' class='form-input'>";
                        $emps = $conn->query("SELECT id, name FROM employees WHERE role='employee'");
                        while ($emp = $emps->fetch_assoc()) {
                            echo "<option value='" . $emp['id'] . "'>" . $emp['name'] . "</option>";
                        }
                        echo "</select>";
                        echo "<input type='submit' name='assign_shift' value='שבץ' class='btn'>";
                        echo "</form>";
                    }
                    // כפתור למחיקת משמרת
                    echo "<form method='POST' onsubmit='return confirm(\"האם אתה בטוח שברצונך למחוק את המשמרת?\");'>";
                    echo "<input type='hidden' name='shift_id' value='" . $shift['id'] . "'>";
                    echo "<input type='submit' name='delete_shift' value='מחק משמרת' class='btn'>";
                    echo "</form></div>";
                }
            } else {
                echo "<p>אין משמרות בוקר ליום זה</p>";
            }
            echo "</div>"; // end morning shifts

            // תצוגת משמרות ערב
            echo "<div class='shift-card'><strong>משמרות ערב</strong><br>";
            if (count($evening_shifts) > 0) {
                foreach ($evening_shifts as $shift) {
                    echo "<div><strong>" . htmlspecialchars($shift['shift_time']) . " עד " . htmlspecialchars($shift['shift_end_time']) . "</strong><br>";
                    if ($shift['employee_id']) {
                        echo "עובד: " . htmlspecialchars($shift['employee_name']) . "<br>";
                        echo "<form method='POST'>";
                        echo "<input type='hidden' name='shift_id' value='" . $shift['id'] . "'>";
                        echo "<select name='employee_id' class='form-input'>";
                        $emps = $conn->query("SELECT id, name FROM employees WHERE role='employee'");
                        while ($emp = $emps->fetch_assoc()) {
                            $selected = ($emp['id'] == $shift['employee_id']) ? 'selected' : '';
                            echo "<option value='" . $emp['id'] . "' $selected>" . $emp['name'] . "</option>";
                        }
                        echo "</select>";
                        echo "<input type='submit' name='assign_shift' value='החלף' class='btn'>";
                        echo "</form>";
                    } else {
                        echo "לא שובץ<br>";
                        echo "<form method='POST'>";
                        echo "<input type='hidden' name='shift_id' value='" . $shift['id'] . "'>";
                        echo "<select name='employee_id' class='form-input'>";
                        $emps = $conn->query("SELECT id, name FROM employees WHERE role='employee'");
                        while ($emp = $emps->fetch_assoc()) {
                            echo "<option value='" . $emp['id'] . "'>" . $emp['name'] . "</option>";
                        }
                        echo "</select>";
                        echo "<input type='submit' name='assign_shift' value='שבץ' class='btn'>";
                        echo "</form>";
                    }
                    // כפתור למחיקת משמרת
                    echo "<form method='POST' onsubmit='return confirm(\"האם אתה בטוח שברצונך למחוק את המשמרת?\");'>";
                    echo "<input type='hidden' name='shift_id' value='" . $shift['id'] . "'>";
                    echo "<input type='submit' name='delete_shift' value='מחק משמרת' class='btn'>";
                    echo "</form></div>";
                }
            } else {
                echo "<p>אין משמרות ערב ליום זה</p>";
            }
            echo "</div>"; // end evening shifts

            echo "</div>"; // end day
        }
        ?>
        </div> <!-- end calendar -->

        <form method="POST">
            <input type="submit" name="reset_shifts" value="איפוס כל המשמרות לסניף" class="reset-btn" onclick="return confirm('האם אתה בטוח שברצונך לאפס את כל המשמרות בסניף?');">
        </form>
    </div> <!-- end main content -->
</body>
</html>
<?php
ob_end_flush();
$conn->close();
?>