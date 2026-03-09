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
    SELECT id, title, duration_minutes
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
        <a class="acc-btn acc-btn--ghost" href="redeem.php">🎫 تفعيل كود</a>

        <?php if ($isLectureOpen): ?>
          <span class="pill">✅ لديك صلاحية مشاهدة المحاضرة</span>
        <?php else: ?>
          <?php if (!$isCourseEnrolled): ?>
            <form method="post" action="buy_lecture_wallet.php">
              <input type="hidden" name="lecture_id" value="<?php echo (int)$lectureId; ?>">
              <button class="acc-btn" type="submit">🛒 شراء المحاضرة بالمحفظة</button>
            </form>
          <?php else: ?>
            <span class="pill">✅ أنت مشترك في الكورس، يجب أن تكون المحاضرة مفتوحة</span>
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
        <div class="acc-itemsList">
          <?php foreach ($videos as $v): ?>
            <div class="acc-item">
              <div class="acc-item__title">🎥 <?php echo h((string)$v['title']); ?></div>
              <div class="acc-item__meta">⏱️ <?php echo (int)($v['duration_minutes'] ?? 0); ?> دقيقة</div>

              <?php if ($isLectureOpen): ?>
                <div class="acc-item__lock">✅</div>
              <?php else: ?>
                <div class="acc-item__lock">🔒</div>
              <?php endif; ?>
            </div>
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
        <div class="acc-itemsList">
          <?php foreach ($pdfs as $p): ?>
            <div class="acc-item">
              <div class="acc-item__title">📑 <?php echo h((string)$p['title']); ?></div>

              <?php if ($isLectureOpen): ?>
                <div class="acc-item__meta">✅ متاح</div>
                <div class="acc-item__lock">✅</div>
              <?php else: ?>
                <div class="acc-item__meta">🔒 مقفول</div>
                <div class="acc-item__lock">🔒</div>
              <?php endif; ?>
            </div>
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
</body>
</html>