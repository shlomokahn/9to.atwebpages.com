<?php
$servername = "fdb1028.awardspace.net";
$username = "4516834_name";
$password = "Shlomo1155";
$dbname = "4516834_name";

// יצירת חיבור למסד הנתונים
$conn = new mysqli($servername, $username, $password, $dbname);

// בדיקת חיבור
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// קבלת פרטי ההתחברות מהטופס
$username = $_POST['username'];
$password = $_POST['password'];

// הכנת השאילתה עם הטבלה הנכונה (employees)
$sql = "SELECT * FROM employees WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

// בדיקת אם המשתמש קיים
if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();

    // בדיקת הסיסמה (בהנחה שהיא שמורה כהאש)
    if (password_verify($password, $user['password'])) {
        session_start();
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
            

        // הפניה לדף המתאים
        if ($user['role'] === 'manager') {
            header("Location: admin_dashboard.php");
        } else {
            header("Location: employee_dashboard.php");
        }
        exit();
    } else {
        echo "הסיסמה שגויה.";
    }
} else {
    echo "שם המשתמש לא נמצא.";
}

$conn->close();
?>
