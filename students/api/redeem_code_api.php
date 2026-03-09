<?php
// students/api/redeem_code_api.php
// JSON API for code redemption — supports access_codes + legacy course_codes/lecture_codes (migration + single-use)

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

$code            = trim((string)($_POST['code'] ?? ''));
$targetCourseId  = (int)($_POST['target_course_id'] ?? 0);
$targetLectureId = (int)($_POST['target_lecture_id'] ?? 0);

if ($code === '') {
  echo json_encode(['ok' => false, 'message' => 'من فضلك أدخل الكود.'], JSON_UNESCAPED_UNICODE);
  exit;
}

function legacy_date_to_eod_datetime(?string $dateYmd): ?string {
  $d = trim((string)$dateYmd);
  if ($d === '') return null;
  return $d . ' 23:59:59';
}

/**
 * Ensure code exists in access_codes.
 * If missing, try migrate from course_codes/lecture_codes (and lock that row).
 * Returns ['legacy_table' => 'course_codes'|'lecture_codes'|null, 'legacy_id' => int]
 */
function ensure_access_code(PDO $pdo, string $code): array {
  $stmt = $pdo->prepare("SELECT id FROM access_codes WHERE code=? LIMIT 1");
  $stmt->execute([$code]);
  if ((int)($stmt->fetchColumn() ?: 0) > 0) {
    return ['legacy_table' => null, 'legacy_id' => 0];
  }

  // Try course_codes
  $stmt = $pdo->prepare("
    SELECT id, is_global, course_id, expires_at, is_used
    FROM course_codes
    WHERE code=?
    LIMIT 1
    FOR UPDATE
  ");
  $stmt->execute([$code]);
  $cc = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($cc) {
    if ((int)($cc['is_used'] ?? 0) === 1) throw new RuntimeException('تم استهلاك هذا الكود بالكامل.');

    $isGlobal = ((int)($cc['is_global'] ?? 0) === 1);
    $courseId = $isGlobal ? null : (int)($cc['course_id'] ?? 0);
    if (!$isGlobal && (!$courseId || $courseId <= 0)) throw new RuntimeException('الكود غير صالح.');

    $expiresAt = legacy_date_to_eod_datetime($cc['expires_at'] ?? null);

    $stmtIns = $pdo->prepare("
      INSERT INTO access_codes (code, type, course_id, lecture_id, is_active, max_uses, used_count, expires_at)
      VALUES (?, 'course', ?, NULL, 1, 1, 0, ?)
    ");
    $stmtIns->execute([$code, $courseId, $expiresAt]);

    return ['legacy_table' => 'course_codes', 'legacy_id' => (int)$cc['id']];
  }

  // Try lecture_codes
  $stmt = $pdo->prepare("
    SELECT id, is_global, lecture_id, expires_at, is_used
    FROM lecture_codes
    WHERE code=?
    LIMIT 1
    FOR UPDATE
  ");
  $stmt->execute([$code]);
  $lc = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($lc) {
    if ((int)($lc['is_used'] ?? 0) === 1) throw new RuntimeException('تم استهلاك هذا الكود بالكامل.');

    $isGlobal  = ((int)($lc['is_global'] ?? 0) === 1);
    $lectureId = $isGlobal ? null : (int)($lc['lecture_id'] ?? 0);
    if (!$isGlobal && (!$lectureId || $lectureId <= 0)) throw new RuntimeException('الكود غير صالح.');

    $expiresAt = legacy_date_to_eod_datetime($lc['expires_at'] ?? null);

    $stmtIns = $pdo->prepare("
      INSERT INTO access_codes (code, type, course_id, lecture_id, is_active, max_uses, used_count, expires_at)
      VALUES (?, 'lecture', NULL, ?, 1, 1, 0, ?)
    ");
    $stmtIns->execute([$code, $lectureId, $expiresAt]);

    return ['legacy_table' => 'lecture_codes', 'legacy_id' => (int)$lc['id']];
  }

  throw new RuntimeException('الكود غير صحيح.');
}

function mark_legacy_used(PDO $pdo, ?string $legacyTable, int $legacyId, int $studentId): void {
  if (!$legacyTable || $legacyId <= 0) return;

  if ($legacyTable === 'course_codes') {
    $stmt = $pdo->prepare("
      UPDATE course_codes
      SET is_used=1, used_by_student_id=?, used_at=NOW()
      WHERE id=? AND is_used=0
    ");
    $stmt->execute([$studentId, $legacyId]);
  } elseif ($legacyTable === 'lecture_codes') {
    $stmt = $pdo->prepare("
      UPDATE lecture_codes
      SET is_used=1, used_by_student_id=?, used_at=NOW()
      WHERE id=? AND is_used=0
    ");
    $stmt->execute([$studentId, $legacyId]);
  }
}

try {
  $pdo->beginTransaction();

  // Make sure code exists in access_codes (migrate from legacy if needed)
  $legacyInfo = ensure_access_code($pdo, $code);

  // Lock access_codes row
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

    if ($isGlobal) {
      if ($targetCourseId <= 0) {
        $pdo->rollBack();
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

    // Verify course exists and not attendance-only
    $stmt = $pdo->prepare("SELECT id, access_type FROM courses WHERE id=? LIMIT 1");
    $stmt->execute([$courseId]);
    $courseRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$courseRow) throw new RuntimeException('الكورس غير موجود.');
    if ((string)($courseRow['access_type'] ?? '') === 'attendance') throw new RuntimeException('هذا الكورس يفتح بالحضور فقط ولا يمكن تفعيله بالكود.');

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

    mark_legacy_used($pdo, $legacyInfo['legacy_table'] ?? null, (int)($legacyInfo['legacy_id'] ?? 0), $studentId);

    $pdo->commit();
    echo json_encode(['ok' => true, 'message' => 'تم تفعيل الكورس بنجاح، وتم فتح جميع محاضراته.', 'course_id' => $courseId], JSON_UNESCAPED_UNICODE);
    exit;

  } elseif ($type === 'lecture') {
    $lectureId = (int)($row['lecture_id'] ?? 0);
    $isGlobal  = ($lectureId <= 0);

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

    $stmtCAT = $pdo->prepare("SELECT access_type FROM courses WHERE id=? LIMIT 1");
    $stmtCAT->execute([$courseId]);
    $courseAccessType = (string)($stmtCAT->fetchColumn() ?: '');
    if ($courseAccessType === 'attendance') throw new RuntimeException('هذه المحاضرة تفتح بالحضور فقط ولا يمكن تفعيلها بالكود.');

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

    mark_legacy_used($pdo, $legacyInfo['legacy_table'] ?? null, (int)($legacyInfo['legacy_id'] ?? 0), $studentId);

    $pdo->commit();
    echo json_encode(['ok' => true, 'message' => 'تم تفعيل المحاضرة بنجاح.', 'lecture_id' => $lectureId], JSON_UNESCAPED_UNICODE);
    exit;

  } else {
    throw new RuntimeException('نوع كود غير مدعوم.');
  }

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
  exit;
}
