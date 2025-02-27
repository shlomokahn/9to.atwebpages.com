<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    header("Location: login.php");
    exit();
}

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

    $name = $_POST['name'];
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT); // הצפנת סיסמה
    $role = $_POST['role'];

    $stmt = $conn->prepare("INSERT INTO employees (name, username, password, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $username, $password, $role);

    if ($stmt->execute()) {
        $message = "המשתמש נוצר בהצלחה!";
    } else {
        $error = "שגיאה ביצירת המשתמש";
    }
    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>יצירת משתמש חדש</title>
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
        .create-user-container {
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
        input[type="password"],
        select {
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
        .message, .error {
            text-align: center;
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .message {
            color: #27ae60;
            background-color: #d4edda;
        }
        .error {
            color: #c0392b;
            background-color: #f8d7da;
        }
    </style>
</head>
<body>
    <div class="create-user-container">
        <h2>יצירת משתמש חדש</h2>
        <?php if (isset($message)): ?>
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="name">שם מלא:</label>
                <input type="text" id="name" name="name" required>
            </div>
            
            <div class="form-group">
                <label for="username">שם משתמש:</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="password">סיסמה:</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="form-group">
                <label for="role">תפקיד:</label>
                <select id="role" name="role" required>
                    <option value="employee">עובד</option>
                    <option value="manager">מנהל</option>
                </select>
            </div>
            
            <button type="submit" class="btn">צור משתמש</button>
        </form>
    </div>
</body>
</html>