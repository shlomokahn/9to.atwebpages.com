<?php
// functions.php
// Returns shifts for a given date and branch (dividing into morning and evening)
function getShiftsForDay($conn, $date, $branch) {
    $sql = "SELECT s.id, s.shift_name, s.shift_time, s.shift_end_time, s.employee_id, e.name AS employee_name
            FROM shifts s
            LEFT JOIN employees e ON s.employee_id = e.id
            WHERE s.date = ? AND s.branch = ?
            ORDER BY s.shift_time";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $date, $branch);
    $stmt->execute();
    $result = $stmt->get_result();
    $morning = [];
    $evening = [];
    while ($shift = $result->fetch_assoc()) {
        if ($shift['shift_name'] == 'בוקר') {
            $morning[] = $shift;
        } else {
            $evening[] = $shift;
        }
    }
    $stmt->close();
    return ['morning' => $morning, 'evening' => $evening];
}

// Adds default shifts for a given date and branch if none are found in the database.
function addDefaultShiftsIfNeeded($conn, $date, $branch) {
    $checkQuery = "SELECT COUNT(*) FROM shifts WHERE date = ? AND branch = ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("ss", $date, $branch);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    
    if ($count == 0) {
        $defaultShifts = [
            'דוידקה' => [
                ['shift_name' => 'בוקר', 'shift_time' => '09:00'],
                ['shift_name' => 'ערב', 'shift_time' => '13:00']
            ],
            'שמואל הנביא' => [
                ['shift_name' => 'בוקר', 'shift_time' => '09:30'],
                ['shift_name' => 'ערב', 'shift_time' => '14:00']
            ],
        ];
        if (isset($defaultShifts[$branch])) {
            foreach ($defaultShifts[$branch] as $shift) {
                $shiftName = $shift['shift_name'];
                $shiftTime = $shift['shift_time'];
                $shiftEnd  = date('H:i', strtotime($shiftTime) + 8 * 60 * 60);
                $insertQuery = "INSERT INTO shifts (shift_name, date, shift_time, shift_end_time, branch) VALUES (?, ?, ?, ?, ?)";
                $stmtIns = $conn->prepare($insertQuery);
                $stmtIns->bind_param("sssss", $shiftName, $date, $shiftTime, $shiftEnd, $branch);
                $stmtIns->execute();
                $stmtIns->close();
            }
        }
    }
}
?>