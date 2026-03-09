<?php
require __DIR__ . '/../admin/inc/db.php';
require __DIR__ . '/inc/student_auth.php';

no_cache_headers();
student_require_login();

header('Content-Type: application/json; charset=utf-8');

$studentId = (int)($_SESSION['student_id'] ?? 0);
if ($studentId <= 0) {
  echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
  exit;
}

$markRead = ((string)($_GET['mark_read'] ?? '') === '1');

try {
  $stmt = $pdo->prepare("SELECT grade_id FROM students WHERE id=? LIMIT 1");
  $stmt->execute([$studentId]);
  $st = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$st) {
    echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $gradeId = (int)($st['grade_id'] ?? 0);
  if ($gradeId <= 0) {
    echo json_encode(['ok' => true, 'unread_count' => 0, 'items' => []], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // Latest notifications for this grade
  $rows = $pdo->prepare("
    SELECT n.id, n.title, n.body, n.created_at
    FROM student_notifications n
    WHERE n.grade_id=? AND n.is_active=1
    ORDER BY n.id DESC
    LIMIT 15
  ");
  $rows->execute([$gradeId]);
  $items = $rows->fetchAll(PDO::FETCH_ASSOC) ?: [];

  // ✅ Mark visible notifications as read for this student (optional)
  if ($markRead && !empty($items)) {
    $pdo->beginTransaction();
    try {
      $ins = $pdo->prepare("
        INSERT IGNORE INTO student_notification_reads (student_id, notification_id, read_at)
        VALUES (?, ?, NOW())
      ");
      foreach ($items as $r) {
        $nid = (int)($r['id'] ?? 0);
        if ($nid > 0) $ins->execute([$studentId, $nid]);
      }
      $pdo->commit();
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      // we don't fail the whole request just because marking failed
    }
  }

  // ✅ unread_count = active grade notifications NOT read by this student
  $countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM student_notifications n
    LEFT JOIN student_notification_reads r
      ON r.notification_id = n.id AND r.student_id = ?
    WHERE n.grade_id=? AND n.is_active=1
      AND r.notification_id IS NULL
  ");
  $countStmt->execute([$studentId, $gradeId]);
  $cnt = (int)$countStmt->fetchColumn();

  $out = [];
  foreach ($items as $r) {
    $out[] = [
      'id' => (int)($r['id'] ?? 0),
      'title' => (string)($r['title'] ?? ''),
      'body' => (string)($r['body'] ?? ''),
      'created_at' => (string)($r['created_at'] ?? ''),
    ];
  }

  echo json_encode([
    'ok' => true,
    'unread_count' => $cnt,
    'items' => $out
  ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
}