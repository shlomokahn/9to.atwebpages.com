<?php
// handlers.php
// This file contains all POST form-handling logic.
// It assumes that config.php is loaded and $conn and $branch are defined.

// Handle "add employee" form
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

// Handle "add shift" form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_shift'])) {
    $shift_name   = $_POST['shift_name'];
    $date         = $_POST['date'];
    $shift_time   = $_POST['shift_time'];
    $shift_branch = $_POST['branch'];
    $shift_end_time = date('H:i', strtotime($shift_time) + 8 * 60 * 60);
    
    // Check if a shift already exists
    $check_existing_query = "SELECT id FROM shifts WHERE date = ? AND branch = ? AND shift_name = ?";
    $stmt_check = $conn->prepare($check_existing_query);
    $stmt_check->bind_param("sss", $date, $shift_branch, $shift_name);
    $stmt_check->execute();
    $stmt_check->bind_result($existing_shift_id);
    $stmt_check->fetch();
    $stmt_check->close();
    
    // If exists, delete it
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

// Handle "assign shift" form
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

// Handle "delete shift" form
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

// Handle "reset shifts" form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_shifts'])) {
    $reset_query = "UPDATE shifts SET employee_id = NULL WHERE branch = ?";
    $stmt = $conn->prepare($reset_query);
    $stmt->bind_param("s", $branch);
    if ($stmt->execute()) {
        echo "<script>alert('כל המשמרות אופסו בהצלחה!'); window.location.href = 'admin_dashboard.php?branch=" . urlencode($branch) . "';</script>";
    } else {
        echo "שגיאה באיפוס המשמרות: " . $conn->error;
    }
    $stmt->close();
}

// Handle "generate PDF" form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_pdf'])) {
    header("Location: preview_pdf.php");
    exit();
}
?>