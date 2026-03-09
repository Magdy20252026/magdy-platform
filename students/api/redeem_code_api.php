<?php

function redeemCode($code, $studentId) {
    // Start transaction
    mysqli_begin_transaction($conn);

    try {
        // Check if the code exists in course_codes or lecture_codes
        $stmt = $conn->prepare("SELECT * FROM course_codes WHERE code = ? FOR UPDATE");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if ($row['is_used'] == 1) {
                return json_encode(['message' => 'تم استهلاك هذا الكود بالكامل.']);
            }

            // Migrate to access_codes
            $stmt = $conn->prepare("INSERT INTO access_codes (code, max_uses, used_count, is_active, expires_at, is_global) VALUES (?, 1, 0, 1, DATE_ADD(NOW(), INTERVAL 1 DAY), ?)"
            );
            $is_global = $row['is_global'] ? 1 : 0;
            $stmt->bind_param("si", $code, $is_global);
            $stmt->execute();

            // Update course_code
            $stmt = $conn->prepare("UPDATE course_codes SET is_used = 1, used_by_student_id = ?, used_at = NOW() WHERE code = ?");
            $stmt->bind_param("is", $studentId, $code);
            $stmt->execute();

            // Commit transaction
            mysqli_commit($conn);
            return json_encode(['message' => 'Redemption successful.']);

        } else {
            // Check in lecture_codes
            $stmt = $conn->prepare("SELECT * FROM lecture_codes WHERE code = ? FOR UPDATE");
            $stmt->bind_param("s", $code);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                if ($row['is_used'] == 1) {
                    return json_encode(['message' => 'تم استهلاك هذا الكود بالكامل.']);
                }

                // Migrate to access_codes
                $stmt = $conn->prepare("INSERT INTO access_codes (code, max_uses, used_count, is_active, expires_at, is_global) VALUES (?, 1, 0, 1, DATE_ADD(NOW(), INTERVAL 1 DAY), ?)"
                );
                $is_global = $row['is_global'] ? 1 : 0;
                $stmt->bind_param("si", $code, $is_global);
                $stmt->execute();

                // Update lecture_code
                $stmt = $conn->prepare("UPDATE lecture_codes SET is_used = 1, used_by_student_id = ?, used_at = NOW() WHERE code = ?");
                $stmt->bind_param("is", $studentId, $code);
                $stmt->execute();

                // Commit transaction
                mysqli_commit($conn);
                return json_encode(['message' => 'Redemption successful.']);
            }
        }

        return json_encode(['message' => 'Invalid code.']);

    } catch (Exception $e) {
        // Rollback transaction
        mysqli.rollback($conn);
        return json_encode(['message' => 'Error occurred: ' . $e->getMessage()]);
    }
}

?>