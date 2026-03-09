<?php
// students/api/redeem_code_api.php
// JSON API for code redemption — supports course codes, lecture codes, and global codes.
// Global course code: target_course_id must be provided.
// Global lecture code: target_lecture_id must be provided.

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../admin/inc/db.php';
require __DIR__ . '/../inc/student_auth.php';
require __DIR__ . '/../inc/access_control.php';

no_cache_headers();
student_require_login();

$studentId = (int)($_SESSION['student_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
  exit;
}

$code          = trim((string)($_POST['code'] ?? ''));
$targetCourseId  = (int)($_POST['target_course_id'] ?? 0);
$targetLectureId = (int)($_POST['target_lecture_id'] ?? 0);

if ($code === '') {
  echo json_encode(['ok' => false, 'message' => 'من فضلك أدخل الكود.'], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $pdo->beginTransaction();

  // Lock code row to avoid race conditions
  $stmt = $pdo->prepare("
    SELECT id, type, course_id, lecture_id, is_active, max_uses, used_count, expires_at
    FROM access_codes
    WHERE code = ?
    LIMIT 1
    FOR UPDATE
  ");
  $stmt->execute([$code]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$row) throw new RuntimeException('الكود غير صحيح.');
  if ((int)$row['is_active'] !== 1) throw new RuntimeException('الكود غير مفعل.');

  if (!empty($row['expires_at'])) {
    $expiresAt = strtotime((string)$row['expires_at']);
    if ($expiresAt !== false && $expiresAt < time()) throw new RuntimeException('انتهت صلاحية هذا الكود.');
  }

  $maxUses   = $row['max_uses'] !== null ? (int)$row['max_uses'] : null;
  $usedCount = (int)$row['used_count'];
  if ($maxUses !== null && $usedCount >= $maxUses) throw new RuntimeException('تم استهلاك هذا الكود بالكامل.');

  $codeId = (int)$row['id'];
  $type   = (string)$row['type'];

  // Prevent redeem same code by same student
  $stmt = $pdo->prepare("SELECT 1 FROM access_code_redemptions WHERE code_id=? AND student_id=? LIMIT 1");
  $stmt->execute([$codeId, $studentId]);
  if ($stmt->fetchColumn()) throw new RuntimeException('أنت استخدمت هذا الكود من قبل.');

  if ($type === 'course') {
    $courseId = (int)($row['course_id'] ?? 0);
    $isGlobal = ($courseId <= 0);

    // Global code: needs a target_course_id from the caller
    if ($isGlobal) {
      if ($targetCourseId <= 0) {
        $pdo->rollBack();
        // Return special response so the modal can show a course picker
        $stmtC = $pdo->prepare("SELECT id, name FROM courses ORDER BY name ASC");
        $stmtC->execute();
        $courses = $stmtC->fetchAll(PDO::FETCH_ASSOC) ?: [];
        echo json_encode([
          'ok'          => false,
          'needs_target'=> true,
          'target_type' => 'course',
          'message'     => 'هذا الكود عام — اختر الكورس الذي تريد فتحه.',
          'courses'     => array_map(fn($c) => ['id' => (int)$c['id'], 'name' => (string)$c['name']], $courses),
        ], JSON_UNESCAPED_UNICODE);
        exit;
      }
      $courseId = $targetCourseId;
    }

    // Verify course exists
    $stmt = $pdo->prepare("SELECT id, access_type FROM courses WHERE id=? LIMIT 1");
    $stmt->execute([$courseId]);
    $courseRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$courseRow) throw new RuntimeException('الكورس غير موجود.');
    if ((string)($courseRow['access_type'] ?? '') === 'attendance') {
      throw new RuntimeException('هذا الكورس بالحضور فقط ولا يمكن فتحه بالكود.');
    }

    if (student_has_course_access($pdo, $studentId, $courseId)) {
      $pdo->rollBack();
      echo json_encode(['ok' => true, 'already' => true, 'message' => 'أنت بالفعل مشترك في هذا الكورس.', 'course_id' => $courseId], JSON_UNESCAPED_UNICODE);
      exit;
    }

    $stmt = $pdo->prepare("INSERT INTO access_code_redemptions (code_id, student_id) VALUES (?, ?)");
    $stmt->execute([$codeId, $studentId]);

    $stmt = $pdo->prepare("UPDATE access_codes SET used_count = used_count + 1 WHERE id=?");
    $stmt->execute([$codeId]);

    $stmt = $pdo->prepare("
      INSERT INTO student_course_enrollments (student_id, course_id, access_type)
      VALUES (?, ?, 'code')
      ON DUPLICATE KEY UPDATE access_type='code'
    ");
    $stmt->execute([$studentId, $courseId]);

    $pdo->commit();
    echo json_encode(['ok' => true, 'message' => 'تم تفعيل الكورس بنجاح، وتم فتح جميع محاضراته.', 'course_id' => $courseId], JSON_UNESCAPED_UNICODE);

  } elseif ($type === 'lecture') {
    $lectureId = (int)($row['lecture_id'] ?? 0);
    $isGlobal  = ($lectureId <= 0);

    // Global code: needs a target_lecture_id from the caller
    if ($isGlobal) {
      if ($targetLectureId <= 0) {
        $pdo->rollBack();
        echo json_encode([
          'ok'          => false,
          'needs_target'=> true,
          'target_type' => 'lecture',
          'message'     => 'هذا الكود عام — يجب تحديد المحاضرة من صفحة الكورس.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
      }
      $lectureId = $targetLectureId;
    }

    $courseId = lecture_get_course_id($pdo, $lectureId);
    if ($courseId <= 0) throw new RuntimeException('المحاضرة غير موجودة.');

    // Block attendance courses
    $stmt = $pdo->prepare("SELECT access_type FROM courses WHERE id=? LIMIT 1");
    $stmt->execute([$courseId]);
    $lecCourseAccessType = (string)($stmt->fetchColumn() ?: '');
    if ($lecCourseAccessType === 'attendance') {
      throw new RuntimeException('هذه المحاضرة بالحضور فقط ولا يمكن فتحها بالكود.');
    }

    if (student_has_course_access($pdo, $studentId, $courseId)) {
      $pdo->rollBack();
      echo json_encode(['ok' => true, 'already' => true, 'message' => 'أنت مشترك في الكورس بالفعل، كل المحاضرات مفتوحة.', 'lecture_id' => $lectureId], JSON_UNESCAPED_UNICODE);
      exit;
    }

    if (student_has_lecture_access($pdo, $studentId, $lectureId)) {
      $pdo->rollBack();
      echo json_encode(['ok' => true, 'already' => true, 'message' => 'أنت بالفعل لديك صلاحية هذه المحاضرة.', 'lecture_id' => $lectureId], JSON_UNESCAPED_UNICODE);
      exit;
    }

    $stmt = $pdo->prepare("INSERT INTO access_code_redemptions (code_id, student_id) VALUES (?, ?)");
    $stmt->execute([$codeId, $studentId]);

    $stmt = $pdo->prepare("UPDATE access_codes SET used_count = used_count + 1 WHERE id=?");
    $stmt->execute([$codeId]);

    $stmt = $pdo->prepare("
      INSERT INTO student_lecture_enrollments
        (student_id, lecture_id, course_id, access_type, paid_amount, lecture_code_id)
      VALUES
        (?, ?, ?, 'code', NULL, ?)
      ON DUPLICATE KEY UPDATE access_type='code', lecture_code_id=VALUES(lecture_code_id)
    ");
    $stmt->execute([$studentId, $lectureId, $courseId, $codeId]);

    $pdo->commit();
    echo json_encode(['ok' => true, 'message' => 'تم تفعيل المحاضرة بنجاح.', 'lecture_id' => $lectureId], JSON_UNESCAPED_UNICODE);

  } else {
    throw new RuntimeException('نوع كود غير مدعوم.');
  }

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
