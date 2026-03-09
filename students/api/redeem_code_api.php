<?php

function redeemCode($code, $userId) {
    $pdo = new PDO('mysql:host=your_host;dbname=your_db', 'username', 'password');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if the code exists in access_codes
    $query = "SELECT * FROM access_codes WHERE code = :code";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':code' => $code]);
    $accessCode = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($accessCode) {
        // Code found in access_codes
        if ($accessCode['used_count'] < $accessCode['max_uses']) {
            // Update used_count
            $updateQuery = "UPDATE access_codes SET used_count = used_count + 1 WHERE code = :code";
            $updateStmt = $pdo->prepare($updateQuery);
            $updateStmt->execute([':code' => $code]);

            // Write to access_code_redemptions
            $insertQuery = "INSERT INTO access_code_redemptions (code, user_id) VALUES (:code, :userId)";
            $insertStmt = $pdo->prepare($insertQuery);
            $insertStmt->execute([':code' => $code, ':userId' => $userId]);

            // Enroll student
            enrollStudent($userId, $code);
            return json_encode(['status' => 'success', 'message' => 'Code redeemed successfully.']);
        } else {
            return json_encode(['status' => 'error', 'message' => 'Code has exceeded maximum uses.']);
        }
    } else {
        // Code not found, migrate from course_codes or lecture_codes
        migrateCode($code, $userId);
        return json_encode(['status' => 'info', 'message' => 'Code migrated and redeemed successfully.']);
    }
}

function migrateCode($code, $userId) {
    // Logic to migrate from course_codes/lecture_codes (similar to redeemCode)
    // Update used_count in course_codes/lecture_codes and write to access_code_redemptions
}

function enrollStudent($userId, $code) {
    // Logic for enrolling the student using the code
}

?>