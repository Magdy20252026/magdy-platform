<?php
// students/api/buy_lecture_wallet_api.php
// JSON API for buying a single lecture with wallet balance.

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

$lectureId = (int)($_POST['lecture_id'] ?? 0);
if ($lectureId <= 0) {
  echo json_encode(['ok' => false, 'message' => 'معرف المحاضرة غير صحيح.'], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $stmt = $pdo->prepare("SELECT l.course_id, l.price, c.access_type AS course_access_type FROM lectures l INNER JOIN courses c ON c.id = l.course_id WHERE l.id=? LIMIT 1");
  $stmt->execute([$lectureId]);
  $lec = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$lec) throw new RuntimeException('المحاضرة غير موجودة.');

  $courseId = (int)$lec['course_id'];
  $price = (float)($lec['price'] ?? 0);

  if ((string)($lec['course_access_type'] ?? '') === 'attendance') {
    throw new RuntimeException('هذه المحاضرة بالحضور فقط ولا يمكن شراؤها بالمحفظة.');
  }

  if ($price <= 0) throw new RuntimeException('هذه المحاضرة غير متاحة للشراء بالمحفظة.');

  if (student_has_course_access($pdo, $studentId, $courseId)) {
    echo json_encode(['ok' => true, 'already' => true, 'message' => 'أنت مشترك في الكورس بالفعل، المحاضرة مفتوحة.', 'lecture_id' => $lectureId], JSON_UNESCAPED_UNICODE);
    exit;
  }

  if (student_has_lecture_access($pdo, $studentId, $lectureId)) {
    echo json_encode(['ok' => true, 'already' => true, 'message' => 'أنت بالفعل لديك صلاحية هذه المحاضرة.', 'lecture_id' => $lectureId], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $pdo->beginTransaction();

  $stmt = $pdo->prepare("SELECT wallet_balance FROM students WHERE id=? LIMIT 1 FOR UPDATE");
  $stmt->execute([$studentId]);
  $balance = (float)($stmt->fetchColumn() ?? 0);

  if ($balance < $price) throw new RuntimeException('رصيد المحفظة غير كافٍ. رصيدك الحالي: ' . number_format($balance, 2) . ' جنيه، سعر المحاضرة: ' . number_format($price, 2) . ' جنيه.');

  $stmt = $pdo->prepare("UPDATE students SET wallet_balance = wallet_balance - ? WHERE id=?");
  $stmt->execute([$price, $studentId]);

  $stmt = $pdo->prepare("
    INSERT INTO student_lecture_enrollments
      (student_id, lecture_id, course_id, access_type, paid_amount, lecture_code_id)
    VALUES
      (?, ?, ?, 'wallet', ?, NULL)
    ON DUPLICATE KEY UPDATE access_type='wallet', paid_amount=VALUES(paid_amount)
  ");
  $stmt->execute([$studentId, $lectureId, $courseId, $price]);

  $pdo->commit();

  // Return updated wallet balance
  $stmtW = $pdo->prepare("SELECT wallet_balance FROM students WHERE id=? LIMIT 1");
  $stmtW->execute([$studentId]);
  $newBalance = (float)($stmtW->fetchColumn() ?? 0);

  echo json_encode([
    'ok'          => true,
    'message'     => 'تم شراء المحاضرة بنجاح.',
    'lecture_id'  => $lectureId,
    'new_balance' => $newBalance,
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
