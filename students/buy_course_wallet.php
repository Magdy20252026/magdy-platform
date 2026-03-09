<?php
require __DIR__ . '/../admin/inc/db.php';
require __DIR__ . '/inc/student_auth.php';
require __DIR__ . '/inc/access_control.php';

no_cache_headers();
student_require_login();

$studentId = (int)($_SESSION['student_id'] ?? 0);
$courseId = (int)($_POST['course_id'] ?? $_GET['course_id'] ?? 0);

if ($courseId <= 0) {
  http_response_code(400);
  exit('Invalid course_id');
}

try {
  // already enrolled?
  if (student_has_course_access($pdo, $studentId, $courseId)) {
    header("Location: account_course.php?course_id=" . $courseId);
    exit;
  }

  // course price (discount logic)
  $stmt = $pdo->prepare("SELECT price, price_discount, buy_type, discount_end FROM courses WHERE id=? LIMIT 1");
  $stmt->execute([$courseId]);
  $c = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$c) throw new RuntimeException('Course not found');

  $price = (float)($c['price'] ?? 0);

  if (($c['buy_type'] ?? '') === 'discount' && !empty($c['price_discount'])) {
    $end = !empty($c['discount_end']) ? strtotime($c['discount_end'] . ' 23:59:59') : null;
    if ($end === null || $end >= time()) $price = (float)$c['price_discount'];
  }

  if ($price <= 0) throw new RuntimeException('هذا الكورس غير متاح للشراء بالمحفظة.');

  $pdo->beginTransaction();

  // lock wallet row
  $stmt = $pdo->prepare("SELECT wallet_balance FROM students WHERE id=? LIMIT 1 FOR UPDATE");
  $stmt->execute([$studentId]);
  $balance = (float)($stmt->fetchColumn() ?? 0);

  if ($balance < $price) throw new RuntimeException('رصيد المحفظة غير كافٍ.');

  $stmt = $pdo->prepare("UPDATE students SET wallet_balance = wallet_balance - ? WHERE id=?");
  $stmt->execute([$price, $studentId]);

  $stmt = $pdo->prepare("
    INSERT INTO student_course_enrollments (student_id, course_id, access_type)
    VALUES (?, ?, 'buy')
    ON DUPLICATE KEY UPDATE access_type='buy'
  ");
  $stmt->execute([$studentId, $courseId]);

  $pdo->commit();

  header("Location: account_course.php?course_id=" . $courseId);
  exit;

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(400);
  exit('Error: ' . htmlspecialchars($e->getMessage()));
}