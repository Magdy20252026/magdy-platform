<?php
require __DIR__ . '/../admin/inc/db.php';
require __DIR__ . '/inc/student_auth.php';
require __DIR__ . '/inc/access_control.php';

no_cache_headers();
student_require_login();

if (!function_exists('h')) {
  function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
}

$studentId = (int)($_SESSION['student_id'] ?? 0);

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $code = trim((string)($_POST['code'] ?? ''));

  if ($code === '') {
    $err = 'من فضلك أدخل الكود.';
  } else {
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

      $maxUses = $row['max_uses'] !== null ? (int)$row['max_uses'] : null;
      $usedCount = (int)$row['used_count'];
      if ($maxUses !== null && $usedCount >= $maxUses) throw new RuntimeException('تم استهلاك هذا الكود بالكامل.');

      $codeId = (int)$row['id'];
      $type = (string)$row['type'];

      // Prevent redeem same code by same student
      $stmt = $pdo->prepare("SELECT 1 FROM access_code_redemptions WHERE code_id=? AND student_id=? LIMIT 1");
      $stmt->execute([$codeId, $studentId]);
      if ($stmt->fetchColumn()) throw new RuntimeException('أنت استخدمت هذا الكود من قبل.');

      if ($type === 'course') {
        $courseId = (int)($row['course_id'] ?? 0);
        if ($courseId <= 0) throw new RuntimeException('الكود غير مرتبط بكورس.');

        // If already has course access => do NOT consume code
        if (student_has_course_access($pdo, $studentId, $courseId)) {
          $pdo->rollBack();
          $msg = 'أنت بالفعل مشترك في هذا الكورس.';
        } else {
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
          $msg = 'تم تفعيل الكورس بنجاح، وتم فتح جميع محاضراته.';
        }

      } elseif ($type === 'lecture') {
        $lectureId = (int)($row['lecture_id'] ?? 0);
        if ($lectureId <= 0) throw new RuntimeException('الكود غير مرتبط بمحاضرة.');

        $courseId = lecture_get_course_id($pdo, $lectureId);
        if ($courseId <= 0) throw new RuntimeException('المحاضرة غير موجودة.');

        // If already has course access => do NOT consume code
        if (student_has_course_access($pdo, $studentId, $courseId)) {
          $pdo->rollBack();
          $msg = 'أنت مشترك في الكورس بالفعل، كل المحاضرات مفتوحة.';
        } else {
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
          $msg = 'تم تفعيل المحاضرة بنجاح.';
        }

      } else {
        throw new RuntimeException('نوع كود غير مدعوم.');
      }

    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $err = $e->getMessage();
    }
  }
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>تفعيل كود</title>
  <style>
    body{font-family:Tahoma, Arial; padding:18px; max-width:680px; margin:auto; line-height:1.8}
    .box{padding:12px;border:1px solid #ddd;border-radius:10px;margin:12px 0}
    .ok{background:#e9ffe9;border-color:#8ad08a}
    .bad{background:#ffe9e9;border-color:#d08a8a}
    input{padding:10px;border:1px solid #ccc;border-radius:10px;width:100%;box-sizing:border-box}
    button{padding:10px 14px;border:0;border-radius:10px;background:#111;color:#fff;font-weight:700;cursor:pointer;margin-top:10px}
    a{color:#0b63ce;text-decoration:none;font-weight:700}
  </style>
</head>
<body>
  <h2>تفعيل كود</h2>

  <?php if ($msg): ?><div class="box ok"><?php echo h($msg); ?></div><?php endif; ?>
  <?php if ($err): ?><div class="box bad"><?php echo h($err); ?></div><?php endif; ?>

  <form method="post" class="box">
    <label>أدخل كود الاشتراك:</label>
    <input name="code" required placeholder="مثال: ABC123...">
    <button type="submit">تفعيل</button>
  </form>

  <p><a href="account.php">⬅️ رجوع للحساب</a></p>
</body>
</html>