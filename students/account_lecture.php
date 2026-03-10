<?php
require __DIR__ . '/../admin/inc/db.php';
require_once __DIR__ . '/inc/platform_settings.php';
require __DIR__ . '/inc/student_auth.php';
require __DIR__ . '/inc/access_control.php';

no_cache_headers();
student_require_login();

if (!function_exists('h')) {
  function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
}

function to_money($v): string {
  if ($v === null || $v === '') return '0';
  return number_format((float)$v, 2);
}

/* platform settings (for header/footer) */
$row = get_platform_settings_row($pdo);
$platformName = trim((string)($row['platform_name'] ?? 'منصتي التعليمية'));
if ($platformName === '') $platformName = 'منصتي التعليمية';

/* ✅ show platform logo */
$logoDb = trim((string)($row['platform_logo'] ?? ''));
$logoUrl = null;
if ($logoDb !== '') $logoUrl = '../admin/' . ltrim($logoDb, '/');

/* footer */
$footerEnabled = (int)($row['footer_enabled'] ?? 1);

$footerLogoDb = trim((string)($row['footer_logo_path'] ?? ''));
$footerLogoUrl = null;
if ($footerLogoDb !== '') $footerLogoUrl = '../admin/' . ltrim($footerLogoDb, '/');

$footerSocialTitle = trim((string)($row['footer_social_title'] ?? 'السوشيال ميديا'));
$footerContactTitle = trim((string)($row['footer_contact_title'] ?? 'تواصل معنا'));
$footerPhone1 = trim((string)($row['footer_phone_1'] ?? ''));
$footerPhone2 = trim((string)($row['footer_phone_2'] ?? ''));
$footerRights = trim((string)($row['footer_rights_line'] ?? ''));
$footerDev = trim((string)($row['footer_developed_by_line'] ?? ''));

$footerSocials = [];
if ($footerEnabled === 1) {
  try {
    $footerSocials = $pdo->query("
      SELECT label, url, icon_path
      FROM platform_footer_social_links
      WHERE is_active=1
      ORDER BY sort_order ASC, id ASC
    ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) {
    $footerSocials = [];
  }
}

$hasFooter = ($footerEnabled === 1) && (
  $footerLogoUrl !== null ||
  $footerSocialTitle !== '' ||
  $footerContactTitle !== '' ||
  $footerPhone1 !== '' ||
  $footerPhone2 !== '' ||
  $footerRights !== '' ||
  $footerDev !== '' ||
  count($footerSocials) > 0
);

function footer_icon_svg(string $key): string {
  $key = strtolower(trim($key));
  return '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm7.9 9h-3.2a15.7 15.7 0 0 0-1.2-5A8.1 8.1 0 0 1 19.9 11zM12 4c.8 1 1.7 2.8 2.2 7H9.8c.5-4.2 1.4-6 2.2-7zM4.1 13h3.2a15.7 15.7 0 0 0 1.2 5A8.1 8.1 0 0 1 4.1 13zm3.2-2H4.1A8.1 8.1 0 0 1 8.5 6a15.7 15.7 0 0 0-1.2 5zm2.5 2h4.4c-.5 4.2-1.4-6-2.2-7c-.8-1-1.7-2.8-2.2-7zm5.7 5a15.7 15.7 0 0 0 1.2-5h3.2a8.1 8.1 0 0 1-4.4 5z"/></svg>';
}

/* student */
$studentId = (int)($_SESSION['student_id'] ?? 0);
$stmt = $pdo->prepare("
  SELECT s.*, gr.name AS grade_name
  FROM students s
  INNER JOIN grades gr ON gr.id = s.grade_id
  WHERE s.id=?
  LIMIT 1
");
$stmt->execute([$studentId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$student) {
  header('Location: logout.php');
  exit;
}
$studentName = (string)($student['full_name'] ?? ($_SESSION['student_name'] ?? ''));
$wallet = (float)($student['wallet_balance'] ?? 0);

/* inputs */
$lectureId = (int)($_GET['lecture_id'] ?? 0);
if ($lectureId <= 0) {
  header('Location: account.php?page=platform_courses');
  exit;
}

/* lecture + course */
$lecture = null;
try {
  $stmt = $pdo->prepare("
    SELECT
      l.*,
      c.id AS course_id,
      c.name AS course_name,
      c.access_type AS course_access_type,
      c.buy_type AS course_buy_type,
      c.price_base AS course_price_base,
      c.price_discount AS course_price_discount,
      c.discount_end AS course_discount_end
    FROM lectures l
    INNER JOIN courses c ON c.id = l.course_id
    WHERE l.id=?
    LIMIT 1
  ");
  $stmt->execute([$lectureId]);
  $lecture = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
  $lecture = null;
}

if (!$lecture) {
  header('Location: account.php?page=platform_courses');
  exit;
}

$courseId = (int)($lecture['course_id'] ?? 0);

// ✅ Access checks
$isCourseEnrolled = student_has_course_access($pdo, $studentId, $courseId);
$isLectureOpen = student_has_lecture_access($pdo, $studentId, $lectureId);

/* Redirect online student away from attendance-only course lectures */
$studentStatus = (string)($student['status'] ?? 'اونلاين');
$isOnline = ($studentStatus === 'اونلاين');
if ($isOnline && (string)($lecture['course_access_type'] ?? '') === 'attendance') {
  header('Location: account.php?page=platform_courses');
  exit;
}

/* ✅ Last update inside this lecture */
$lastLectureContentAt = '';
try {
  $stmt = $pdo->prepare("
    SELECT MAX(dt) AS last_dt
    FROM (
      SELECT v.created_at AS dt FROM videos v WHERE v.lecture_id = ?
      UNION ALL
      SELECT p.created_at AS dt FROM pdfs  p WHERE p.lecture_id = ?
    ) x
  ");
  $stmt->execute([$lectureId, $lectureId]);
  $lastLectureContentAt = (string)($stmt->fetchColumn() ?: '');
} catch (Throwable $e) {
  $lastLectureContentAt = '';
}

/* lists: videos + pdfs inside lecture */
$videos = [];
$pdfs = [];

try {
  $stmt = $pdo->prepare("
    SELECT
      id,
      title,
      duration_minutes,
      allowed_views_per_student,
      video_type,
      embed_iframe,
      embed_iframe_enc,
      embed_iframe_iv
    FROM videos
    WHERE lecture_id=?
    ORDER BY id DESC
  ");
  $stmt->execute([$lectureId]);
  $videos = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) { $videos = []; }

try {
  $stmt = $pdo->prepare("
    SELECT id, title, file_path
    FROM pdfs
    WHERE lecture_id=?
    ORDER BY id DESC
  ");
  $stmt->execute([$lectureId]);
  $pdfs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) { $pdfs = []; }

$videosCount = count($videos);
$pdfsCount = count($pdfs);

$studentCode = trim((string)($student['barcode'] ?? ''));
if ($studentCode === '') $studentCode = 'STD-' . $studentId;
$studentWatermark = $studentCode . ' • ' . $studentName;

$selectedVideoId = 0;
$selectedPdfId = 0;
$videosForJs = [];

if ($isLectureOpen && !empty($videos)) {
  student_video_views_ensure_table($pdo);

  foreach ($videos as &$videoRow) {
    $videoId = (int)($videoRow['id'] ?? 0);
    $stats = student_get_video_watch_stats($pdo, $studentId, $videoId, $videoRow);
    $videoRow['views_allowed'] = (int)$stats['allowed'];
    $videoRow['views_used'] = (int)$stats['used'];
    $videoRow['views_remaining'] = (int)$stats['remaining'];
    $videoRow['is_blocked'] = (bool)$stats['blocked'];
    $videoRow['half_watch_seconds'] = student_video_half_watch_seconds((int)($videoRow['duration_minutes'] ?? 0));

    if ($selectedVideoId <= 0 && !$videoRow['is_blocked']) {
      $selectedVideoId = $videoId;
    }

    $videosForJs[] = [
      'id' => $videoId,
      'title' => (string)($videoRow['title'] ?? ''),
      'duration_minutes' => (int)($videoRow['duration_minutes'] ?? 0),
      'views_allowed' => (int)$videoRow['views_allowed'],
      'views_used' => (int)$videoRow['views_used'],
      'views_remaining' => (int)$videoRow['views_remaining'],
      'is_blocked' => (bool)$videoRow['is_blocked'],
      'half_watch_seconds' => (int)$videoRow['half_watch_seconds'],
      'video_type' => (string)($videoRow['video_type'] ?? ''),
    ];
  }
  unset($videoRow);

  if ($selectedVideoId <= 0 && !empty($videos)) {
    $selectedVideoId = (int)($videos[0]['id'] ?? 0);
  }
}

if ($isLectureOpen && !empty($pdfs)) {
  $selectedPdfId = (int)($pdfs[0]['id'] ?? 0);
}

/* lecture price show */
$courseAccessType = (string)($lecture['course_access_type'] ?? 'attendance');
$lecturePriceText = ($courseAccessType === 'buy')
  ? (to_money($lecture['price'] ?? null) . ' جنيه')
  : 'غير مطلوب';

/* page assets */
$cssVer = (string)@filemtime(__DIR__ . '/assets/css/account.css');
if ($cssVer === '' || $cssVer === '0') $cssVer = (string)time();
$lecCssVer = (string)@filemtime(__DIR__ . '/assets/css/account-lecture.css');
if ($lecCssVer === '' || $lecCssVer === '0') $lecCssVer = (string)time();
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700;800;900;1000&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="assets/css/site.css">
  <link rel="stylesheet" href="assets/css/footer.css">
  <link rel="stylesheet" href="assets/css/account.css?v=<?php echo h($cssVer); ?>">
  <link rel="stylesheet" href="assets/css/account-lecture.css?v=<?php echo h($lecCssVer); ?>">

  <style>
    .buy-row{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}
    .buy-row form{display:inline}
    .pill{padding:10px 12px;border:1px solid #ddd;border-radius:14px;font-weight:900}
    .acc-modal-btn{display:inline-flex;align-items:center;gap:6px;padding:10px 14px;border:2px solid transparent;border-radius:12px;font-weight:700;cursor:pointer;font-family:inherit;font-size:1em}
    .acc-modal-btn--primary{background:#111;color:#fff}
    .acc-modal-btn--ghost{background:var(--page-bg);border-color:var(--border);color:var(--text)}
  </style>

  <title>تفاصيل المحاضرة - <?php echo h((string)$lecture['name']); ?></title>
</head>
<body>

<header class="acc-topbar" role="banner">
  <div class="container">
    <div class="acc-topbar__bar">
      <div class="acc-topbar__right">
        <a class="acc-brand" href="account_course.php?course_id=<?php echo (int)$courseId; ?>" aria-label="<?php echo h($platformName); ?>">
          <?php if ($logoUrl): ?>
            <img class="acc-brand__logo" src="<?php echo h($logoUrl); ?>" alt="Logo">
          <?php else: ?>
            <span class="acc-brand__logoFallback" aria-hidden="true"></span>
          <?php endif; ?>
          <span class="acc-brand__name"><?php echo h($platformName); ?></span>
        </a>

        <div class="acc-theme" data-theme-switch aria-label="تبديل الوضع">
          <button class="acc-theme__btn" type="button" data-theme="light" aria-label="لايت">☀</button>
          <button class="acc-theme__btn" type="button" data-theme="dark" aria-label="دارك">🌙</button>
          <span class="acc-theme__knob" aria-hidden="true"></span>
        </div>
      </div>

      <div class="acc-topbar__left">
        <a class="acc-btn acc-btn--ghost" href="account_course.php?course_id=<?php echo (int)$courseId; ?>">⬅️ رجوع</a>

        <div class="acc-student" title="<?php echo h($studentName); ?>">
          <span aria-hidden="true">👤</span>
          <span class="acc-student__name"><?php echo h($studentName); ?></span>
        </div>

        <div class="acc-pill" title="رصيد المحفظة">
          <span aria-hidden="true">💳</span>
          <span><?php echo number_format($wallet, 2); ?> جنيه</span>
        </div>
      </div>
    </div>
  </div>
</header>

<div class="acc-screenWatermark" aria-hidden="true">
  <span class="acc-screenWatermark__chip acc-screenWatermark__chip--one"><?php echo h($studentWatermark); ?></span>
  <span class="acc-screenWatermark__chip acc-screenWatermark__chip--two"><?php echo h($studentWatermark); ?></span>
</div>

<main class="acc-lecturePage">
  <div class="container">

    <section class="acc-card" aria-label="تفاصيل المحاضرة">
      <div class="acc-card__head">
        <h2>📑 <?php echo h((string)$lecture['name']); ?></h2>
        <p>📚 الكورس: <b><?php echo h((string)$lecture['course_name']); ?></b></p>
      </div>

      <div class="acc-lectureInfo">
        <div class="acc-lectureInfo__row">
          الحالة:
          <?php if ($isLectureOpen): ?>
            <b style="color:green;">✅ مفتوح</b>
          <?php else: ?>
            <b style="color:#b00;">🔒 مقفول</b>
          <?php endif; ?>
        </div>

        <div class="acc-lectureInfo__row">💰 السعر: <b><?php echo h($lecturePriceText); ?></b></div>
        <div class="acc-lectureInfo__row">🎥 عدد الفيديوهات: <b><?php echo (int)$videosCount; ?></b></div>
        <div class="acc-lectureInfo__row">📑 عدد ملفات PDF: <b><?php echo (int)$pdfsCount; ?></b></div>

        <?php if (trim($lastLectureContentAt) !== ''): ?>
          <div class="acc-lectureInfo__row">
            🔁 آخر تحديث داخل المحاضرة:
            <b><?php echo h($lastLectureContentAt); ?></b>
          </div>
        <?php endif; ?>
      </div>

      <?php $details = trim((string)($lecture['details'] ?? '')); ?>
      <?php if ($details !== ''): ?>
        <div class="acc-lectureDetails"><?php echo nl2br(h($details)); ?></div>
      <?php endif; ?>

      <div class="buy-row">
        <?php if ($isLectureOpen): ?>
          <span class="pill">✅ لديك صلاحية مشاهدة المحاضرة</span>
        <?php elseif ($courseAccessType === 'attendance'): ?>
          <span class="pill">ℹ️ هذه المحاضرة تفتح بالحضور فقط.</span>
        <?php else: ?>
          <button class="acc-modal-btn acc-modal-btn--ghost" type="button" onclick="openRedeemModal('lecture', <?php echo (int)$lectureId; ?>)">🎫 تفعيل كود</button>
          <?php if (!$isCourseEnrolled && $courseAccessType === 'buy'): ?>
            <button class="acc-modal-btn acc-modal-btn--primary" type="button"
              onclick="openBuyLectureModal(<?php echo (int)$lectureId; ?>, '<?php echo h($lecturePriceText); ?>')">🛒 شراء المحاضرة بالمحفظة</button>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </section>

    <section class="acc-card" aria-label="فيديوهات المحاضرة">
      <div class="acc-card__head">
        <h2>🎥 فيديوهات المحاضرة</h2>
      </div>

      <?php if (empty($videos)): ?>
        <div style="font-weight:900;color:var(--muted);">لا توجد فيديوهات داخل هذه المحاضرة.</div>
      <?php else: ?>
        <?php if ($isLectureOpen): ?>
          <div class="acc-playerShell">
            <div class="acc-playerStage" id="lecturePlayerStage">
              <div class="acc-playerSurface" id="lecturePlayerSurface">
                <div class="acc-playerPlaceholder" id="lecturePlayerPlaceholder">
                  اختر الفيديو من القائمة ثم اضغط <b>ابدأ المشاهدة</b> ليتم تشغيله داخل بلاير المنصة.
                </div>
              </div>
              <div class="acc-playerOverlay">
                <span class="acc-playerOverlay__chip"><?php echo h($studentWatermark); ?></span>
              </div>
            </div>

            <div class="acc-playerToolbar">
              <div class="acc-playerToolbar__meta">
                <div class="acc-playerToolbar__title" id="lecturePlayerTitle">بدون فيديو محدد</div>
                <div class="acc-playerToolbar__sub" id="lecturePlayerSub">اختر فيديو لعرض المدة والعدد المتبقي من المشاهدات.</div>
              </div>

              <div class="acc-playerToolbar__actions">
                <button class="acc-modal-btn acc-modal-btn--primary" type="button" id="lecturePlayerStartBtn">▶️ ابدأ المشاهدة</button>
                <button class="acc-modal-btn acc-modal-btn--ghost" type="button" id="lecturePlayerFullscreenBtn">⛶ تكبير البلاير</button>
              </div>
            </div>

            <div class="acc-playerNotice" id="lecturePlayerNotice">
              ⏱️ يبدأ احتساب المشاهدة من بداية التشغيل ويتم تسجيل مشاهدة واحدة عند الوصول إلى نصف مدة الفيديو المحددة.
            </div>
          </div>
        <?php endif; ?>

        <div class="acc-itemsList acc-itemsList--media">
          <?php foreach ($videos as $v): ?>
            <?php
              $videoRemaining = (int)($v['views_remaining'] ?? 0);
              $videoAllowed = (int)($v['views_allowed'] ?? (int)($v['allowed_views_per_student'] ?? 1));
              $isBlockedVideo = (bool)($v['is_blocked'] ?? false);
              $videoId = (int)($v['id'] ?? 0);
            ?>
            <button
              class="acc-item acc-item--media<?php echo ($selectedVideoId === $videoId ? ' is-active' : ''); ?><?php echo ($isBlockedVideo ? ' is-blocked' : ''); ?>"
              type="button"
              <?php if ($isLectureOpen): ?>
                data-video-select
                data-video-id="<?php echo $videoId; ?>"
                data-video-title="<?php echo h((string)$v['title']); ?>"
                data-duration-minutes="<?php echo (int)($v['duration_minutes'] ?? 0); ?>"
                data-views-allowed="<?php echo $videoAllowed; ?>"
                data-views-used="<?php echo (int)($v['views_used'] ?? 0); ?>"
                data-views-remaining="<?php echo $videoRemaining; ?>"
                data-half-seconds="<?php echo (int)($v['half_watch_seconds'] ?? 30); ?>"
                data-is-blocked="<?php echo $isBlockedVideo ? '1' : '0'; ?>"
                data-video-type="<?php echo h((string)($v['video_type'] ?? '')); ?>"
              <?php else: ?>
                disabled
              <?php endif; ?>
            >
              <div class="acc-item__body">
                <div class="acc-item__title">🎥 <?php echo h((string)$v['title']); ?></div>
                <div class="acc-item__meta">⏱️ <?php echo (int)($v['duration_minutes'] ?? 0); ?> دقيقة</div>
                <?php if ($isLectureOpen): ?>
                  <div class="acc-item__desc">
                    👁️ المشاهدات المستخدمة: <b><?php echo (int)($v['views_used'] ?? 0); ?></b> / <?php echo $videoAllowed; ?>
                    • المتبقي: <b><?php echo $videoRemaining; ?></b>
                  </div>
                  <?php if ($isBlockedVideo): ?>
                    <div class="acc-item__badge acc-item__badge--danger">انتهت عدد المشاهدات</div>
                  <?php else: ?>
                    <div class="acc-item__badge">تشغيل داخل المنصة</div>
                  <?php endif; ?>
                <?php endif; ?>
              </div>

              <?php if ($isLectureOpen): ?>
                <div class="acc-item__lock"><?php echo $isBlockedVideo ? '⛔' : '✅'; ?></div>
              <?php else: ?>
                <div class="acc-item__lock">🔒</div>
              <?php endif; ?>
            </button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="acc-card" aria-label="ملفات PDF للمحاضرة">
      <div class="acc-card__head">
        <h2>📑 ملفات PDF</h2>
      </div>

      <?php if (empty($pdfs)): ?>
        <div style="font-weight:900;color:var(--muted);">لا توجد ملفات PDF داخل هذه المحاضرة.</div>
      <?php else: ?>
        <?php if ($isLectureOpen): ?>
          <div class="acc-pdfShell">
            <div class="acc-pdfViewer">
              <iframe
                id="lecturePdfFrame"
                title="Lecture PDF"
                src="<?php echo $selectedPdfId > 0 ? 'lecture_pdf.php?pdf_id=' . $selectedPdfId . '#toolbar=0&navpanes=0&scrollbar=0' : 'about:blank'; ?>"
                loading="lazy"
              ></iframe>
              <div class="acc-pdfOverlay">
                <span class="acc-pdfOverlay__chip"><?php echo h($studentWatermark); ?></span>
              </div>
            </div>
            <div class="acc-playerNotice">📑 يتم عرض ملف الـ PDF داخل المحاضرة مباشرة بدون الخروج من المنصة.</div>
          </div>
        <?php endif; ?>

        <div class="acc-itemsList acc-itemsList--media">
          <?php foreach ($pdfs as $p): ?>
            <?php $pdfId = (int)($p['id'] ?? 0); ?>
            <button
              class="acc-item acc-item--media<?php echo ($selectedPdfId === $pdfId ? ' is-active' : ''); ?>"
              type="button"
              <?php if ($isLectureOpen): ?>
                data-pdf-select
                data-pdf-id="<?php echo $pdfId; ?>"
                data-pdf-title="<?php echo h((string)$p['title']); ?>"
              <?php else: ?>
                disabled
              <?php endif; ?>
            >
              <div class="acc-item__body">
                <div class="acc-item__title">📑 <?php echo h((string)$p['title']); ?></div>
                <?php if ($isLectureOpen): ?>
                  <div class="acc-item__meta">✅ متاح داخل المحاضرة</div>
                  <div class="acc-item__badge">عرض الملف الآن</div>
                <?php else: ?>
                  <div class="acc-item__meta">🔒 مقفول</div>
                <?php endif; ?>
              </div>

              <?php if ($isLectureOpen): ?>
                <div class="acc-item__lock">✅</div>
              <?php else: ?>
                <div class="acc-item__lock">🔒</div>
              <?php endif; ?>
            </button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

  </div>
</main>

<?php if ($hasFooter): ?>
  <footer class="site-footer" aria-label="Footer">
    <div class="container">
      <div class="footer__grid">
        <div class="footer__col footer__col--left">
          <?php if ($footerLogoUrl): ?>
            <img class="footer__logo" src="<?php echo h($footerLogoUrl); ?>" alt="Logo">
          <?php else: ?>
            <div class="footer__logoFallback" aria-hidden="true"></div>
          <?php endif; ?>
        </div>

        <div class="footer__col footer__col--mid">
          <?php if ($footerSocialTitle !== ''): ?>
            <div class="footer__title"><?php echo h($footerSocialTitle); ?></div>
          <?php endif; ?>

          <?php if (!empty($footerSocials)): ?>
            <ul class="footer__list">
              <?php foreach ($footerSocials as $s): ?>
                <?php
                  $socIconDb = trim((string)($s['icon_path'] ?? ''));
                  $socIconUrl = null;
                  if ($socIconDb !== '') $socIconUrl = '../admin/' . ltrim($socIconDb, '/');
                ?>
                <li class="footer__item">
                  <a class="footer__link" href="<?php echo h((string)$s['url']); ?>" target="_blank" rel="noopener">
                    <span class="footer__ico" aria-hidden="true">
                      <?php if ($socIconUrl): ?>
                        <img class="footer__icoImg" src="<?php echo h($socIconUrl); ?>" alt="">
                      <?php else: ?>
                        <?php echo footer_icon_svg('website'); ?>
                      <?php endif; ?>
                    </span>
                    <span class="footer__lbl"><?php echo h((string)$s['label']); ?></span>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>

        <div class="footer__col footer__col--mid2">
          <?php if ($footerContactTitle !== ''): ?>
            <div class="footer__title"><?php echo h($footerContactTitle); ?></div>
          <?php endif; ?>

          <div class="footer__phones">
            <?php if ($footerPhone1 !== ''): ?><div class="footer__phone"><?php echo h($footerPhone1); ?></div><?php endif; ?>
            <?php if ($footerPhone2 !== ''): ?><div class="footer__phone"><?php echo h($footerPhone2); ?></div><?php endif; ?>
          </div>
        </div>

        <div class="footer__col footer__col--right">
          <?php if ($footerRights !== ''): ?>
            <div class="footer-copy footer-copy--rights"><?php echo h($footerRights); ?></div>
          <?php endif; ?>
          <?php if ($footerDev !== ''): ?>
            <div class="footer-copy footer-copy--dev"><?php echo h($footerDev); ?></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </footer>
<?php endif; ?>

<script src="assets/js/theme.js"></script>

<?php
  $videosJson = json_encode($videosForJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
  if (!is_string($videosJson)) $videosJson = '[]';
?>
<script>
(function(){
  var videos = <?php echo $videosJson; ?>;
  if (!videos.length) {
    document.addEventListener('contextmenu', function(e){ e.preventDefault(); });
    document.addEventListener('mousedown', function(e){ if (e.button === 2) e.preventDefault(); }, true);
    return;
  }

  var videoMap = {};
  videos.forEach(function(video){ videoMap[String(video.id)] = video; });

  var selectedVideoId = <?php echo (int)$selectedVideoId; ?>;
  var surface = document.getElementById('lecturePlayerSurface');
  var placeholder = document.getElementById('lecturePlayerPlaceholder');
  var titleEl = document.getElementById('lecturePlayerTitle');
  var subEl = document.getElementById('lecturePlayerSub');
  var noticeEl = document.getElementById('lecturePlayerNotice');
  var startBtn = document.getElementById('lecturePlayerStartBtn');
  var fullscreenBtn = document.getElementById('lecturePlayerFullscreenBtn');
  var playerStage = document.getElementById('lecturePlayerStage');
  var pdfFrame = document.getElementById('lecturePdfFrame');

  var activeWatchToken = '';
  var countedToken = '';
  var countdownHandle = 0;
  var countdownStartedAt = 0;
  var countdownHalfSeconds = 0;
  var completionInFlight = false;

  function stopCountdown() {
    if (countdownHandle) {
      window.clearInterval(countdownHandle);
      countdownHandle = 0;
    }
    countdownStartedAt = 0;
    countdownHalfSeconds = 0;
    completionInFlight = false;
  }

  function updateNotice(text, isError) {
    if (!noticeEl) return;
    noticeEl.textContent = text;
    noticeEl.style.borderColor = isError ? 'rgba(207,42,55,.35)' : 'rgba(44,123,229,.35)';
    noticeEl.style.background = isError ? 'rgba(207,42,55,.08)' : 'rgba(44,123,229,.08)';
  }

  function renderSelection() {
    var video = videoMap[String(selectedVideoId)] || null;
    document.querySelectorAll('[data-video-select]').forEach(function(btn){
      btn.classList.toggle('is-active', String(btn.getAttribute('data-video-id')) === String(selectedVideoId));
    });

    if (!video) {
      if (titleEl) titleEl.textContent = 'بدون فيديو محدد';
      if (subEl) subEl.textContent = 'اختر فيديو لعرض المدة والعدد المتبقي من المشاهدات.';
      if (startBtn) startBtn.disabled = true;
      return;
    }

    if (titleEl) titleEl.textContent = video.title || 'فيديو المحاضرة';
    if (subEl) {
      subEl.textContent = 'المدة: ' + (parseInt(video.duration_minutes || 0, 10)) + ' دقيقة • المتبقي: ' + (parseInt(video.views_remaining || 0, 10)) + ' من ' + (parseInt(video.views_allowed || 0, 10));
    }
    if (startBtn) startBtn.disabled = !!video.is_blocked;

    if (video.is_blocked) {
      updateNotice('⛔ انتهت عدد المشاهدات المسموحة لهذا الفيديو، ولن يتم تشغيله مرة أخرى.', true);
    } else {
      updateNotice('⏱️ اضغط "ابدأ المشاهدة" لتشغيل الفيديو. يتم احتساب مشاهدة واحدة عند الوصول إلى نصف مدة الفيديو المحددة.', false);
    }
  }

  function escapeHtml(text) {
    return String(text || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function renderPlaceholder(message) {
    stopCountdown();
    activeWatchToken = '';
    countedToken = '';
    if (surface) {
      surface.innerHTML = '<div class="acc-playerPlaceholder">' + message + '</div>';
    }
  }

  function syncVideoStats(videoId, stats) {
    var video = videoMap[String(videoId)];
    if (!video || !stats) return;
    video.views_allowed = parseInt(stats.allowed || video.views_allowed || 1, 10);
    video.views_used = parseInt(stats.used || 0, 10);
    video.views_remaining = parseInt(stats.remaining || 0, 10);
    video.is_blocked = video.views_remaining <= 0;

    var btn = document.querySelector('[data-video-select][data-video-id="' + videoId + '"]');
    if (btn) {
      btn.setAttribute('data-views-allowed', video.views_allowed);
      btn.setAttribute('data-views-used', video.views_used);
      btn.setAttribute('data-views-remaining', video.views_remaining);
      btn.setAttribute('data-is-blocked', video.is_blocked ? '1' : '0');
      btn.classList.toggle('is-blocked', video.is_blocked);

      var desc = btn.querySelector('.acc-item__desc');
      if (desc) {
        desc.innerHTML = '👁️ المشاهدات المستخدمة: <b>' + video.views_used + '</b> / ' + video.views_allowed + ' • المتبقي: <b>' + video.views_remaining + '</b>';
      }

      var badge = btn.querySelector('.acc-item__badge');
      if (badge) {
        badge.textContent = video.is_blocked ? 'انتهت عدد المشاهدات' : 'تشغيل داخل المنصة';
        badge.classList.toggle('acc-item__badge--danger', video.is_blocked);
      }

      var lock = btn.querySelector('.acc-item__lock');
      if (lock) lock.textContent = video.is_blocked ? '⛔' : '✅';
    }

    renderSelection();
  }

  function completeWatchIfReady(force) {
    if (!activeWatchToken || !selectedVideoId || completionInFlight) return;
    if (!force && (!countdownStartedAt || !countdownHalfSeconds)) return;

    var elapsed = countdownStartedAt ? Math.floor((Date.now() - countdownStartedAt) / 1000) : 0;
    if (!force && elapsed < countdownHalfSeconds) return;

    completionInFlight = true;
    var body = new URLSearchParams();
    body.set('action', 'complete');
    body.set('video_id', selectedVideoId);
    body.set('watch_token', activeWatchToken);

    fetch('api/lecture_video_api.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
      body: body.toString()
    }).then(function(res){
      return res.json();
    }).then(function(data){
      completionInFlight = false;
      if (data && data.ok) {
        countedToken = activeWatchToken;
        stopCountdown();
        if (data.stats) syncVideoStats(selectedVideoId, data.stats);
        updateNotice('✅ ' + (data.message || 'تم احتساب المشاهدة بنجاح.'), false);
      } else if (data && data.stats) {
        syncVideoStats(selectedVideoId, data.stats);
        updateNotice('⛔ ' + (data.message || 'انتهت عدد المشاهدات المسموحة.'), true);
      }
    }).catch(function(){
      completionInFlight = false;
    });
  }

  function startCountdown(halfSeconds) {
    stopCountdown();
    countdownStartedAt = Date.now();
    countdownHalfSeconds = Math.max(30, parseInt(halfSeconds || 30, 10));
    countdownHandle = window.setInterval(function(){
      var elapsed = Math.floor((Date.now() - countdownStartedAt) / 1000);
      var remaining = Math.max(0, countdownHalfSeconds - elapsed);
      updateNotice('⏱️ المشاهدة ستُحتسب بعد ' + remaining + ' ثانية من المشاهدة المستمرة داخل المنصة.', false);
      if (remaining <= 0) {
        completeWatchIfReady(true);
      }
    }, 1000);
  }

  function startCurrentVideo() {
    var video = videoMap[String(selectedVideoId)] || null;
    if (!video) {
      renderPlaceholder('من فضلك اختر فيديو من القائمة أولاً.');
      return;
    }
    if (video.is_blocked) {
      renderPlaceholder('انتهت عدد المشاهدات المسموحة لهذا الفيديو.');
      updateNotice('⛔ انتهت عدد المشاهدات المسموحة لهذا الفيديو، ولن يتم تشغيله.', true);
      return;
    }

    if (startBtn) {
      startBtn.disabled = true;
      startBtn.textContent = '⏳ جاري تجهيز الفيديو...';
    }

    var body = new URLSearchParams();
    body.set('action', 'start');
    body.set('video_id', selectedVideoId);

    fetch('api/lecture_video_api.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
      body: body.toString()
    }).then(function(res){
      return res.json();
    }).then(function(data){
      if (startBtn) {
        startBtn.disabled = false;
        startBtn.textContent = '▶️ ابدأ المشاهدة';
      }

      if (!data || !data.ok) {
        renderPlaceholder(escapeHtml((data && data.message) || 'تعذر تشغيل الفيديو داخل المنصة.'));
        if (data && data.stats) syncVideoStats(selectedVideoId, data.stats);
        updateNotice('⛔ ' + ((data && data.message) || 'تعذر تشغيل الفيديو داخل المنصة.'), true);
        return;
      }

      activeWatchToken = data.watch_token || '';
      countedToken = '';
      if (surface) surface.innerHTML = data.player_html || '';
      if (data.stats) syncVideoStats(selectedVideoId, data.stats);
      startCountdown(parseInt(data.half_seconds || video.half_watch_seconds || 30, 10));
      updateNotice('▶️ تم تشغيل الفيديو داخل بلاير المنصة. سيتم احتساب المشاهدة عند الوصول إلى نصف الوقت المحدد.', false);
    }).catch(function(){
      if (startBtn) {
        startBtn.disabled = false;
        startBtn.textContent = '▶️ ابدأ المشاهدة';
      }
      renderPlaceholder('تعذر الاتصال بسيرفر الفيديو الآن. حاول مرة أخرى.');
      updateNotice('❌ حدث خطأ في الاتصال أثناء تجهيز الفيديو.', true);
    });
  }

  document.querySelectorAll('[data-video-select]').forEach(function(btn){
    btn.addEventListener('click', function(){
      var nextId = parseInt(btn.getAttribute('data-video-id') || '0', 10);
      if (!nextId || nextId === selectedVideoId) return;
      completeWatchIfReady(false);
      selectedVideoId = nextId;
      renderPlaceholder('تم اختيار فيديو جديد. اضغط <b>ابدأ المشاهدة</b> لتشغيله داخل المنصة.');
      renderSelection();
    });
  });

  document.querySelectorAll('[data-pdf-select]').forEach(function(btn){
    btn.addEventListener('click', function(){
      var nextId = parseInt(btn.getAttribute('data-pdf-id') || '0', 10);
      if (!nextId || !pdfFrame) return;
      document.querySelectorAll('[data-pdf-select]').forEach(function(other){
        other.classList.toggle('is-active', other === btn);
      });
      pdfFrame.src = 'lecture_pdf.php?pdf_id=' + nextId + '#toolbar=0&navpanes=0&scrollbar=0';
    });
  });

  if (startBtn) {
    startBtn.addEventListener('click', startCurrentVideo);
  }

  if (fullscreenBtn && playerStage) {
    fullscreenBtn.addEventListener('click', function(){
      if (document.fullscreenElement) {
        document.exitFullscreen && document.exitFullscreen();
        return;
      }
      if (playerStage.requestFullscreen) {
        playerStage.requestFullscreen();
      }
    });

    document.addEventListener('fullscreenchange', function(){
      fullscreenBtn.textContent = document.fullscreenElement ? '🡼 إغلاق التكبير' : '⛶ تكبير البلاير';
    });
  }

  document.addEventListener('visibilitychange', function(){
    if (document.visibilityState === 'hidden') completeWatchIfReady(false);
  });

  window.addEventListener('beforeunload', function(){
    if (!activeWatchToken || !countdownStartedAt) return;
    var elapsed = Math.floor((Date.now() - countdownStartedAt) / 1000);
    if (elapsed < countdownHalfSeconds || countedToken === activeWatchToken) return;

    var body = new URLSearchParams();
    body.set('action', 'complete');
    body.set('video_id', selectedVideoId);
    body.set('watch_token', activeWatchToken);
    if (navigator.sendBeacon) {
      navigator.sendBeacon('api/lecture_video_api.php', new Blob([body.toString()], {type: 'application/x-www-form-urlencoded; charset=UTF-8'}));
    }
  });

  document.addEventListener('contextmenu', function(e){ e.preventDefault(); });
  document.addEventListener('mousedown', function(e){ if (e.button === 2) e.preventDefault(); }, true);

  renderPlaceholder('اختر الفيديو من القائمة ثم اضغط <b>ابدأ المشاهدة</b> ليتم تشغيله داخل بلاير المنصة.');
  renderSelection();
})();
</script>

<!-- ✅ Purchase / Code Modals (same as account_course.php) -->
<div id="accModalBackdrop" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.55);align-items:center;justify-content:center;" role="dialog" aria-modal="true">
  <div id="accModalBox" style="background:var(--card-bg,#fff);border-radius:18px;padding:28px 24px;max-width:420px;width:calc(100% - 32px);box-shadow:0 8px 40px rgba(0,0,0,.25);position:relative;font-family:inherit;">
    <button id="accModalClose" style="position:absolute;top:12px;left:12px;background:none;border:none;font-size:1.4em;cursor:pointer;color:var(--muted,#888);" aria-label="إغلاق">✖</button>
    <h3 id="accModalTitle" style="margin:0 0 14px;font-size:1.2em;"></h3>
    <div id="accModalMsg" style="display:none;padding:10px 14px;border-radius:10px;margin-bottom:12px;font-weight:700;"></div>
    <div id="accModalBody"></div>
  </div>
</div>

<script>
(function(){
  var backdrop = document.getElementById('accModalBackdrop');
  var titleEl  = document.getElementById('accModalTitle');
  var msgEl    = document.getElementById('accModalMsg');
  var bodyEl   = document.getElementById('accModalBody');
  var closeBtn = document.getElementById('accModalClose');

  function openModal(title, bodyHtml) {
    titleEl.textContent = title;
    bodyEl.innerHTML = bodyHtml;
    msgEl.style.display = 'none';
    backdrop.style.display = 'flex';
  }
  function closeModal() { backdrop.style.display = 'none'; }
  function showMsg(text, ok) {
    msgEl.textContent = text;
    msgEl.style.display = 'block';
    msgEl.style.background = ok ? '#e9ffe9' : '#ffe9e9';
    msgEl.style.border = '1px solid ' + (ok ? '#8ad08a' : '#d08a8a');
    msgEl.style.color = ok ? '#1a6a1a' : '#a00';
  }
  function setLoading(btn, loading) {
    btn.disabled = loading;
    if (loading) { btn._orig = btn.textContent; btn.textContent = '⏳ جاري التنفيذ...'; }
    else { btn.textContent = btn._orig || btn.textContent; }
  }

  closeBtn.addEventListener('click', closeModal);
  backdrop.addEventListener('click', function(e){ if(e.target===backdrop) closeModal(); });
  document.addEventListener('keydown', function(e){ if(e.key==='Escape' && backdrop.style.display!=='none') closeModal(); });

  function updateWalletPill(newBalance) {
    var pill = document.querySelector('.acc-pill span:last-child');
    if (pill) pill.textContent = parseFloat(newBalance).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',') + ' جنيه';
  }

  var _redeemType = null, _redeemContextId = 0, _redeemLastCode = '';

  window.openRedeemModal = function(type, contextId) {
    _redeemType = type || null;
    _redeemContextId = parseInt(contextId) || 0;
    _redeemLastCode = '';
    openModal('🎫 تفعيل كود اشتراك',
      '<input id="rCodeIn" type="text" placeholder="XXXX-XXXX-XXXX" dir="ltr" style="width:100%;padding:12px;border:1px solid #ccc;border-radius:12px;font-size:1em;box-sizing:border-box;margin-bottom:10px;font-family:inherit;">' +
      '<button id="rCodeBtn" onclick="doRedeemCode()" style="width:100%;padding:12px;border:none;border-radius:12px;background:#111;color:#fff;font-size:1em;font-weight:700;cursor:pointer;font-family:inherit;">✅ تفعيل</button>'
    );
    setTimeout(function(){ var i=document.getElementById('rCodeIn'); if(i) i.focus(); }, 80);
  };

  window.doRedeemCode = async function() {
    var codeIn = document.getElementById('rCodeIn');
    var btn    = document.getElementById('rCodeBtn');
    var code   = (codeIn ? codeIn.value.trim() : '');
    if (!code) { showMsg('من فضلك أدخل الكود.', false); return; }
    _redeemLastCode = code;
    setLoading(btn, true);
    try {
      var fd = new FormData();
      fd.append('code', code);
      if (_redeemType === 'course' && _redeemContextId > 0) fd.append('target_course_id', _redeemContextId);
      if (_redeemType === 'lecture' && _redeemContextId > 0) fd.append('target_lecture_id', _redeemContextId);
      var res  = await fetch('api/redeem_code_api.php', {method:'POST', body:fd});
      var data = await res.json();
      setLoading(btn, false);
      if (data.needs_target && data.target_type === 'course') {
        var opts = '<option value="">-- اختر الكورس --</option>';
        (data.courses || []).forEach(function(c){ opts += '<option value="' + c.id + '">' + c.name + '</option>'; });
        bodyEl.innerHTML =
          '<p style="margin:0 0 8px;font-weight:700;color:#b06000;">🎓 هذا الكود عام — اختر الكورس:</p>' +
          '<select id="rCourseIn" style="width:100%;padding:12px;border:1px solid #ccc;border-radius:12px;font-size:1em;box-sizing:border-box;margin-bottom:10px;font-family:inherit;">' + opts + '</select>' +
          '<button id="rCourseBtn" onclick="doRedeemWithCourse()" style="width:100%;padding:12px;border:none;border-radius:12px;background:#1a7a2a;color:#fff;font-size:1em;font-weight:700;cursor:pointer;font-family:inherit;">✅ تفعيل</button>';
        showMsg(data.message || 'اختر الكورس.', false);
      } else if (data.ok) {
        showMsg('✅ ' + (data.message||'تم التفعيل بنجاح.'), true);
        setTimeout(function(){ closeModal(); location.reload(); }, 1800);
      } else {
        showMsg('❌ ' + (data.message||'حدث خطأ.'), false);
      }
    } catch(e) { setLoading(btn, false); showMsg('❌ خطأ في الاتصال.', false); }
  };

  window.doRedeemWithCourse = async function() {
    var sel = document.getElementById('rCourseIn');
    var btn = document.getElementById('rCourseBtn');
    if (!sel || !sel.value) { showMsg('من فضلك اختر كورساً.', false); return; }
    setLoading(btn, true);
    try {
      var fd = new FormData();
      fd.append('code', _redeemLastCode);
      fd.append('target_course_id', sel.value);
      var res  = await fetch('api/redeem_code_api.php', {method:'POST', body:fd});
      var data = await res.json();
      setLoading(btn, false);
      if (data.ok) { showMsg('✅ ' + (data.message||'تم.'), true); setTimeout(function(){ closeModal(); location.reload(); }, 1800); }
      else showMsg('❌ ' + (data.message||'حدث خطأ.'), false);
    } catch(e) { setLoading(btn, false); showMsg('❌ خطأ في الاتصال.', false); }
  };

  window.openBuyLectureModal = function(lectureId, priceText) {
    openModal('🛒 شراء المحاضرة بالمحفظة',
      '<p style="margin:0 0 10px;font-weight:700;">💰 السعر: <b>' + priceText + '</b></p>' +
      '<p style="margin:0 0 14px;color:var(--muted,#666);font-size:.95em;">سيتم خصم المبلغ من رصيد محفظتك.</p>' +
      '<button id="buyLectureBtn" onclick="doBuyLecture(' + parseInt(lectureId) + ')" style="width:100%;padding:12px;border:none;border-radius:12px;background:#111;color:#fff;font-size:1em;font-weight:700;cursor:pointer;font-family:inherit;">✅ تأكيد الشراء</button>'
    );
  };

  window.doBuyLecture = async function(lectureId) {
    var btn = document.getElementById('buyLectureBtn');
    setLoading(btn, true);
    try {
      var fd = new FormData(); fd.append('lecture_id', lectureId);
      var res  = await fetch('api/buy_lecture_wallet_api.php', {method:'POST', body:fd});
      var data = await res.json();
      setLoading(btn, false);
      if (data.ok) {
        if (data.new_balance !== undefined) updateWalletPill(data.new_balance);
        showMsg('✅ ' + (data.message||'تم الشراء بنجاح.'), true);
        setTimeout(function(){ closeModal(); location.reload(); }, 1800);
      } else {
        showMsg('❌ ' + (data.message||'حدث خطأ.'), false);
      }
    } catch(e) { setLoading(btn, false); showMsg('❌ خطأ في الاتصال.', false); }
  };
})();
</script>

</body>
</html>
