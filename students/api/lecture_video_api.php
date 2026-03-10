<?php
require __DIR__ . '/../../admin/inc/db.php';
require_once __DIR__ . '/../inc/platform_settings.php';
require __DIR__ . '/../inc/student_auth.php';
require __DIR__ . '/../inc/access_control.php';

no_cache_headers();
student_require_login();

header('Content-Type: application/json; charset=utf-8');

$studentId = (int)($_SESSION['student_id'] ?? 0);
$action = trim((string)($_POST['action'] ?? ''));
$videoId = (int)($_POST['video_id'] ?? 0);

function lecture_video_api_response(array $payload): void {
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

if ($studentId <= 0 || $videoId <= 0 || !in_array($action, ['start', 'complete'], true)) {
  lecture_video_api_response(['ok' => false, 'message' => 'طلب غير صالح.']);
}

$video = student_get_video_row($pdo, $videoId);
if (!$video) {
  lecture_video_api_response(['ok' => false, 'message' => 'الفيديو غير موجود.']);
}

$lectureId = (int)($video['lecture_id'] ?? 0);
if (!student_has_lecture_access($pdo, $studentId, $lectureId)) {
  lecture_video_api_response(['ok' => false, 'message' => 'لا تملك صلاحية مشاهدة هذا الفيديو.']);
}

$stats = student_get_video_watch_stats($pdo, $studentId, $videoId, $video);
$halfSeconds = student_video_half_watch_seconds((int)($video['duration_minutes'] ?? 0));

if (!isset($_SESSION['lecture_video_watch'])) {
  $_SESSION['lecture_video_watch'] = [];
}

if ($action === 'start') {
  if ($stats['blocked']) {
    lecture_video_api_response([
      'ok' => false,
      'message' => 'انتهت عدد المشاهدات المسموحة لهذا الفيديو.',
      'stats' => $stats,
    ]);
  }

  foreach ($_SESSION['lecture_video_watch'] as $token => $watch) {
    $startedAt = (int)($watch['started_at'] ?? 0);
    if ($startedAt > 0 && ($startedAt + 43200) < time()) {
      unset($_SESSION['lecture_video_watch'][$token]);
    }
  }

  $existingToken = '';
  foreach ($_SESSION['lecture_video_watch'] as $token => $watch) {
    if (
      (int)($watch['student_id'] ?? 0) === $studentId &&
      (int)($watch['video_id'] ?? 0) === $videoId &&
      empty($watch['counted'])
    ) {
      $existingToken = (string)$token;
      break;
    }
  }

  $token = $existingToken !== '' ? $existingToken : bin2hex(random_bytes(18));
  $_SESSION['lecture_video_watch'][$token] = [
    'student_id' => $studentId,
    'video_id' => $videoId,
    'lecture_id' => $lectureId,
    'started_at' => time(),
    'half_seconds' => $halfSeconds,
    'counted' => false,
  ];

  $origin = '';
  if (!empty($_SERVER['HTTP_HOST'])) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $origin = $scheme . '://' . $_SERVER['HTTP_HOST'];
  }

  $playerHtml = student_build_video_player_html($video, $origin);
  if ($playerHtml === '') {
    lecture_video_api_response([
      'ok' => false,
      'message' => 'تعذر تجهيز مشغل الفيديو داخل المنصة.',
      'stats' => $stats,
    ]);
  }

  lecture_video_api_response([
    'ok' => true,
    'message' => 'تم تجهيز الفيديو داخل المنصة.',
    'watch_token' => $token,
    'half_seconds' => $halfSeconds,
    'player_html' => $playerHtml,
    'stats' => $stats,
    'video' => [
      'id' => (int)$video['id'],
      'title' => (string)($video['title'] ?? ''),
      'duration_minutes' => (int)($video['duration_minutes'] ?? 0),
      'video_type' => (string)($video['video_type'] ?? ''),
    ],
  ]);
}

$token = trim((string)($_POST['watch_token'] ?? ''));
if ($token === '' || empty($_SESSION['lecture_video_watch'][$token])) {
  lecture_video_api_response([
    'ok' => false,
    'message' => 'جلسة المشاهدة غير صالحة.',
    'stats' => $stats,
  ]);
}

$watch = $_SESSION['lecture_video_watch'][$token];
if (
  (int)($watch['student_id'] ?? 0) !== $studentId ||
  (int)($watch['video_id'] ?? 0) !== $videoId
) {
  lecture_video_api_response([
    'ok' => false,
    'message' => 'جلسة المشاهدة لا تخص هذا الفيديو.',
    'stats' => $stats,
  ]);
}

if (!empty($watch['counted'])) {
  lecture_video_api_response([
    'ok' => true,
    'message' => 'تم احتساب هذه المشاهدة بالفعل.',
    'counted' => true,
    'stats' => $stats,
  ]);
}

$elapsed = time() - (int)($watch['started_at'] ?? time());
if ($elapsed < max(5, (int)($watch['half_seconds'] ?? $halfSeconds))) {
  lecture_video_api_response([
    'ok' => false,
    'message' => 'لم يصل زمن المشاهدة إلى الحد المطلوب بعد.',
    'stats' => $stats,
  ]);
}

$result = student_increment_video_watch($pdo, $studentId, $videoId, (int)$stats['allowed']);
if (!$result['ok']) {
  lecture_video_api_response([
    'ok' => false,
    'message' => 'انتهت عدد المشاهدات المسموحة لهذا الفيديو.',
    'stats' => $result,
  ]);
}

$_SESSION['lecture_video_watch'][$token]['counted'] = true;

lecture_video_api_response([
  'ok' => true,
  'message' => 'تم احتساب مشاهدة الفيديو بنجاح.',
  'counted' => true,
  'stats' => $result,
]);
