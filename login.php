<?php
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $servername = "fdb1028.awardspace.net";
    $username = "4516834_name";
    $password = "Shlomo1155";
    $dbname = "4516834_name";

    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8");

    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, name, username, password, role FROM employees WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();

        // בדיקת הסיסמה המוצפנת
        if (password_verify($password, $user['password'])) {
            // שמירת כל הפרטים הנדרשים ב-session
            $_SESSION['logged_in'] = true;
            $_SESSION['employee_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            // הפניה בהתאם לתפקיד
            if ($user['role'] === 'manager') {
                header("Location: admin_dashboard.php");
            } else {
                header("Location: employee_dashboard.php");
            }
            exit();
        } else {
            $error = "שם משתמש או סיסמה שגויים";
        }
    } else {
        $error = "שם משתמש או סיסמה שגויים";
    }
    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>התחברות למערכת</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .login-container {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        h2 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #2c3e50;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        .btn {
            width: 100%;
            padding: 10px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:hover {
            background-color: #2980b9;
        }
        .error {
            color: #c0392b;
            text-align: center;
            padding: 10px;
            margin: 10px 0;
            background-color: #f8d7da;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>התחברות למערכת</h2>
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">שם משתמש:</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="password">סיסמה:</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn">התחבר</button>
        </form>
    </div>
</body>
</html>