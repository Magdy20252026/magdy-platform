<?php
// students/inc/access_control.php
// ✅ Rules:
// 1) If student enrolled in course => all lectures open automatically.
// 2) If not enrolled in course => lecture open only if enrolled in that lecture.
// 3) If course.access_type = free => open.

require __DIR__ . '/../../admin/inc/db.php';

function student_has_course_access(PDO $pdo, int $studentId, int $courseId): bool {
  if ($studentId <= 0 || $courseId <= 0) return false;

  // free course?
  $stmt = $pdo->prepare("SELECT access_type FROM courses WHERE id=? LIMIT 1");
  $stmt->execute([$courseId]);
  $accessType = (string)($stmt->fetchColumn() ?: '');
  if ($accessType === 'free') return true;

  // enrolled in course?
  $stmt = $pdo->prepare("SELECT 1 FROM student_course_enrollments WHERE student_id=? AND course_id=? LIMIT 1");
  $stmt->execute([$studentId, $courseId]);
  return (bool)$stmt->fetchColumn();
}

function lecture_get_course_id(PDO $pdo, int $lectureId): int {
  if ($lectureId <= 0) return 0;
  $stmt = $pdo->prepare("SELECT course_id FROM lectures WHERE id=? LIMIT 1");
  $stmt->execute([$lectureId]);
  return (int)($stmt->fetchColumn() ?: 0);
}

function student_has_lecture_access(PDO $pdo, int $studentId, int $lectureId): bool {
  if ($studentId <= 0 || $lectureId <= 0) return false;

  $courseId = lecture_get_course_id($pdo, $lectureId);
  if ($courseId <= 0) return false;

  // ✅ IMPORTANT: course access opens all lectures
  if (student_has_course_access($pdo, $studentId, $courseId)) return true;

  // lecture enrollment opens single lecture
  $stmt = $pdo->prepare("SELECT 1 FROM student_lecture_enrollments WHERE student_id=? AND lecture_id=? LIMIT 1");
  $stmt->execute([$studentId, $lectureId]);
  return (bool)$stmt->fetchColumn();
}

function student_video_views_ensure_table(PDO $pdo): void {
  static $ready = false;
  if ($ready) return;
  $ready = true;

  try {
    $pdo->exec("
      CREATE TABLE IF NOT EXISTS video_student_views (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        video_id INT UNSIGNED NOT NULL,
        student_id INT UNSIGNED NOT NULL,
        views_used INT UNSIGNED NOT NULL DEFAULT 0,
        last_view_at TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_video_student (video_id, student_id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
  } catch (Throwable $e) {
    // ignore: live schema may already exist or current DB user may not have DDL privileges
  }
}

function student_get_video_row(PDO $pdo, int $videoId): ?array {
  if ($videoId <= 0) return null;

  $stmt = $pdo->prepare("
    SELECT
      id,
      lecture_id,
      title,
      duration_minutes,
      allowed_views_per_student,
      video_type,
      embed_iframe,
      embed_iframe_enc,
      embed_iframe_iv
    FROM videos
    WHERE id=?
    LIMIT 1
  ");
  $stmt->execute([$videoId]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  return $row ?: null;
}

function student_get_video_watch_stats(PDO $pdo, int $studentId, int $videoId, ?array $videoRow = null): array {
  $video = $videoRow ?: student_get_video_row($pdo, $videoId);
  $allowed = max(1, (int)($video['allowed_views_per_student'] ?? 1));
  $used = 0;

  if ($studentId > 0 && $videoId > 0) {
    student_video_views_ensure_table($pdo);
    try {
      $stmt = $pdo->prepare("SELECT views_used FROM video_student_views WHERE video_id=? AND student_id=? LIMIT 1");
      $stmt->execute([$videoId, $studentId]);
      $used = max(0, (int)($stmt->fetchColumn() ?: 0));
    } catch (Throwable $e) {
      $used = 0;
    }
  }

  $remaining = max(0, $allowed - $used);
  return [
    'allowed' => $allowed,
    'used' => $used,
    'remaining' => $remaining,
    'blocked' => ($remaining <= 0),
  ];
}

function student_increment_video_watch(PDO $pdo, int $studentId, int $videoId, int $allowedViews): array {
  $allowedViews = max(1, $allowedViews);
  student_video_views_ensure_table($pdo);

  try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
      SELECT id, views_used
      FROM video_student_views
      WHERE video_id=? AND student_id=?
      LIMIT 1
      FOR UPDATE
    ");
    $stmt->execute([$videoId, $studentId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $used = max(0, (int)($row['views_used'] ?? 0));
    if ($used >= $allowedViews) {
      $pdo->commit();
      return [
        'ok' => false,
        'allowed' => $allowedViews,
        'used' => $used,
        'remaining' => 0,
      ];
    }

    if ($row) {
      $stmt = $pdo->prepare("
        UPDATE video_student_views
        SET views_used = views_used + 1,
            last_view_at = CURRENT_TIMESTAMP
        WHERE id=?
      ");
      $stmt->execute([(int)$row['id']]);
      $used++;
    } else {
      $stmt = $pdo->prepare("
        INSERT INTO video_student_views (video_id, student_id, views_used, last_view_at)
        VALUES (?, ?, 1, CURRENT_TIMESTAMP)
      ");
      $stmt->execute([$videoId, $studentId]);
      $used = 1;
    }

    $pdo->commit();

    return [
      'ok' => true,
      'allowed' => $allowedViews,
      'used' => $used,
      'remaining' => max(0, $allowedViews - $used),
    ];
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    return [
      'ok' => false,
      'allowed' => $allowedViews,
      'used' => 0,
      'remaining' => max(0, $allowedViews),
    ];
  }
}

function student_video_half_watch_seconds(int $durationMinutes): int {
  $durationSeconds = max(60, $durationMinutes * 60);
  return max(30, (int)ceil($durationSeconds / 2));
}

function student_extract_iframe_src(string $iframeHtml): string {
  $iframeHtml = trim($iframeHtml);
  if ($iframeHtml === '') return '';

  if (preg_match('/src\s*=\s*([\"\'])(.*?)\1/i', $iframeHtml, $m)) {
    return html_entity_decode((string)$m[2], ENT_QUOTES, 'UTF-8');
  }

  if (preg_match('/src\s*=\s*([^\s>]+)/i', $iframeHtml, $m)) {
    return html_entity_decode(trim((string)$m[1], "\"'"), ENT_QUOTES, 'UTF-8');
  }

  if (preg_match('~^(https?:)?//~i', $iframeHtml)) {
    return $iframeHtml;
  }

  return '';
}

function student_append_url_params(string $url, array $params): string {
  if ($url === '') return '';
  $sep = (strpos($url, '?') === false) ? '?' : '&';
  return $url . $sep . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

function student_extract_youtube_video_id(string $url): string {
  $parts = @parse_url($url);
  if (!is_array($parts)) return '';

  $host = strtolower((string)($parts['host'] ?? ''));
  $path = trim((string)($parts['path'] ?? ''), '/');

  if ($host === 'youtu.be') return preg_replace('~[^A-Za-z0-9_-]~', '', $path);

  if (strpos($host, 'youtube.com') !== false || strpos($host, 'youtube-nocookie.com') !== false) {
    $segments = $path === '' ? [] : explode('/', $path);
    if (!empty($segments[0]) && in_array($segments[0], ['embed', 'shorts', 'live'], true) && !empty($segments[1])) {
      return preg_replace('~[^A-Za-z0-9_-]~', '', (string)$segments[1]);
    }

    $query = [];
    parse_str((string)($parts['query'] ?? ''), $query);
    if (!empty($query['v'])) {
      return preg_replace('~[^A-Za-z0-9_-]~', '', (string)$query['v']);
    }
  }

  return '';
}

function student_normalize_video_src(string $src, string $videoType, string $origin = ''): string {
  $src = trim(html_entity_decode($src, ENT_QUOTES, 'UTF-8'));
  if ($src === '') return '';
  if (strpos($src, '//') === 0) $src = 'https:' . $src;

  $parts = @parse_url($src);
  $scheme = strtolower((string)($parts['scheme'] ?? ''));
  if (!in_array($scheme, ['http', 'https'], true)) return '';

  if ($videoType === 'youtube') {
    $videoId = student_extract_youtube_video_id($src);
    if ($videoId === '') return '';

    $embed = 'https://www.youtube-nocookie.com/embed/' . rawurlencode($videoId);
    $params = [
      'rel' => 0,
      'modestbranding' => 1,
      'playsinline' => 1,
      'enablejsapi' => 1,
      'iv_load_policy' => 3,
      'fs' => 0,
    ];
    if ($origin !== '') $params['origin'] = $origin;
    return student_append_url_params($embed, $params);
  }

  if ($videoType === 'vimeo') {
    return student_append_url_params($src, [
      'title' => 0,
      'byline' => 0,
      'portrait' => 0,
    ]);
  }

  return $src;
}

function student_decrypt_video_iframe(?string $cipherBase64, ?string $ivHex): string {
  $cipherBase64 = (string)$cipherBase64;
  $ivHex = (string)$ivHex;
  if ($cipherBase64 === '' || $ivHex === '') return '';
  if (!defined('APP_EMBED_SECRET_KEY')) return '';
  if (!function_exists('openssl_decrypt')) return '';

  $secret = (string)APP_EMBED_SECRET_KEY;
  if (strlen($secret) !== 32) return '';

  $cipherRaw = base64_decode($cipherBase64, true);
  $iv = hex2bin($ivHex);
  if ($cipherRaw === false || $iv === false || strlen($iv) !== 16) return '';

  $key = hash('sha256', $secret, true);
  $plain = openssl_decrypt($cipherRaw, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
  return ($plain === false) ? '' : (string)$plain;
}

function student_build_video_player_html(array $videoRow, string $origin = ''): string {
  $iframeHtml = student_decrypt_video_iframe($videoRow['embed_iframe_enc'] ?? null, $videoRow['embed_iframe_iv'] ?? null);
  if ($iframeHtml === '') $iframeHtml = (string)($videoRow['embed_iframe'] ?? '');
  $iframeHtml = trim($iframeHtml);

  $src = student_extract_iframe_src($iframeHtml);
  $src = student_normalize_video_src($src, (string)($videoRow['video_type'] ?? ''), $origin);
  if ($src === '') {
    if ($iframeHtml === '') return '';
    return '<div class="acc-embeddedHtml" id="lectureVideoEmbed">' . $iframeHtml . '</div>';
  }

  $title = htmlspecialchars((string)($videoRow['title'] ?? 'مشغل الفيديو'), ENT_QUOTES, 'UTF-8');
  $srcAttr = htmlspecialchars($src, ENT_QUOTES, 'UTF-8');

  return '<iframe class="acc-embeddedFrame" id="lectureVideoFrame" src="' . $srcAttr . '" title="' . $title . '" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share; fullscreen" allowfullscreen></iframe>';
}

function student_resolve_pdf_absolute_path(string $filePath): string {
  $filePath = trim(str_replace('\\', '/', $filePath));
  if ($filePath === '') return '';

  $adminBase = realpath(__DIR__ . '/../../admin');
  if ($adminBase === false) return '';

  $absolute = realpath($adminBase . '/' . ltrim($filePath, '/'));
  if ($absolute === false || !is_file($absolute)) return '';
  if (strpos($absolute, $adminBase) !== 0) return '';
  if (strtolower((string)pathinfo($absolute, PATHINFO_EXTENSION)) !== 'pdf') return '';

  return $absolute;
}
