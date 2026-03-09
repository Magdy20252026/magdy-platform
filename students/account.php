<?php
require __DIR__ . '/../admin/inc/db.php';
require_once __DIR__ . '/inc/platform_settings.php';
require __DIR__ . '/inc/student_auth.php';

no_cache_headers();
student_require_login();

if (!function_exists('h')) {
  function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
}

function normalize_phone(string $p): string {
  $p = trim((string)$p);
  return (string)preg_replace('/[^\d\+]/', '', $p);
}

function is_arabic_name_3plus(string $name): bool {
  $name = trim((string)preg_replace('/\s+/u', ' ', $name));
  if ($name === '') return false;
  if (!preg_match('/^[\p{Arabic}\s]+$/u', $name)) return false;
  $parts = array_values(array_filter(explode(' ', $name), fn($p) => trim($p) !== ''));
  return count($parts) >= 3;
}

function fmt_dt(?string $dt): string {
  $dt = trim((string)$dt);
  if ($dt === '') return '';
  return $dt;
}

/* محافظات مصر */
$governorates = [
  'القاهرة','الجيزة','الإسكندرية','الدقهلية','البحر الأحمر','البحيرة','الفيوم','الغربية',
  'الإسماعيلية','المنوفية','المنيا','القليوبية','الوادي الجديد','السويس','اسوان','اسيوط',
  'بني سويف','بورسعيد','دمياط','الشرقية','جنوب سيناء','كفر الشيخ','مطروح','الأقصر',
  'قنا','شمال سيناء','سوهاج'
];

/* platform settings */
$row = get_platform_settings_row($pdo);
$platformName = trim((string)($row['platform_name'] ?? 'منصتي التعليمية'));
if ($platformName === '') $platformName = 'منصتي التعليمية';

$logoDb = trim((string)($row['platform_logo'] ?? ''));
$logoUrl = null;
if ($logoDb !== '') $logoUrl = '../admin/' . ltrim($logoDb, '/');

/* footer data */
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

/* current student */
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
$studentStatus = (string)($student['status'] ?? 'اونلاين');
$isOnline = ($studentStatus === 'اونلاين');

/* ✅ Auto-enroll free courses so they appear in "كورساتك" */
try {
  $pdo->prepare("
    INSERT IGNORE INTO student_course_enrollments (student_id, course_id, access_type)
    SELECT ?, c.id, 'free'
    FROM courses c
    WHERE c.access_type = 'free'
  ")->execute([$studentId]);
} catch (Throwable $e) { /* non-fatal */ }

/* navigation */
$page = (string)($_GET['page'] ?? 'home');
$allowedPages = ['home','settings','platform_courses','my_courses'];
if (!in_array($page, $allowedPages, true)) $page = 'home';

/* sidebar items */
$sidebar = [
  ['key'=>'home', 'label'=>'الصفحه الرئيسية', 'icon'=>'🏠', 'href'=>'account.php?page=home'],

  ['key'=>'platform_courses', 'label'=>'كورسات المنصة', 'icon'=>'📚', 'href'=>'account.php?page=platform_courses'],
  ['key'=>'my_courses', 'label'=>'كورساتك', 'icon'=>'🎓', 'href'=>'account.php?page=my_courses'],

  ['key'=>'assignments', 'label'=>'الواجبات', 'icon'=>'📝', 'href'=>'#', 'disabled'=>true],
  ['key'=>'exams', 'label'=>'الامتحانات', 'icon'=>'🧠', 'href'=>'#', 'disabled'=>true],
  ['key'=>'notifications', 'label'=>'اشعارات الطلاب', 'icon'=>'🔔', 'href'=>'#', 'disabled'=>true],
  ['key'=>'facebook', 'label'=>'فيسبوك المنصة', 'icon'=>'📘', 'href'=>'#', 'disabled'=>true],
  ['key'=>'chat', 'label'=>'شات', 'icon'=>'💬', 'href'=>'#', 'disabled'=>true],
  ['key'=>'wallet', 'label'=>'المحفظة', 'icon'=>'💳', 'href'=>'#', 'disabled'=>true],

  ['key'=>'settings', 'label'=>'إعدادات الحساب', 'icon'=>'⚙️', 'href'=>'account.php?page=settings'],

  ['key'=>'logout', 'label'=>'تسجيل الخروج', 'icon'=>'🚪', 'href'=>'logout.php', 'danger'=>true],
];

/* grades list for settings */
$gradesList = [];
try {
  $gradesList = $pdo->query("SELECT id, name FROM grades WHERE is_active=1 ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) { $gradesList = []; }

/* handle settings update */
$success = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'update_profile') {
  $fullName = trim((string)($_POST['full_name'] ?? ''));
  $governorate = trim((string)($_POST['governorate'] ?? ''));
  $studentPhone = normalize_phone((string)($_POST['student_phone'] ?? ''));
  $parentPhone = normalize_phone((string)($_POST['parent_phone'] ?? ''));
  $gradeId = (int)($_POST['grade_id'] ?? 0);

  $newPass = (string)($_POST['new_password'] ?? '');
  $newPass2 = (string)($_POST['new_password2'] ?? '');

  if (!is_arabic_name_3plus($fullName)) {
    $error = 'اسم الطالب يجب أن يكون ثلاثي (3 كلمات أو أكثر) وباللغة العربية.';
  } elseif ($studentPhone === '') {
    $error = 'رقم الهاتف مطلوب.';
  } elseif ($governorate === '' || !in_array($governorate, $governorates, true)) {
    $error = 'من فضلك اختر المحافظة.';
  } elseif ($gradeId <= 0) {
    $error = 'من فضلك اختر الصف الدراسي.';
  } elseif ($newPass !== '' && $newPass !== $newPass2) {
    $error = 'كلمة السر الجديدة وتأكيدها غير متطابقين.';
  } else {
    try {
      $chk = $pdo->prepare("SELECT id FROM grades WHERE id=? AND is_active=1 LIMIT 1");
      $chk->execute([$gradeId]);
      if (!$chk->fetch()) $error = 'الصف الدراسي غير موجود.';
    } catch (Throwable $e) {
      $error = 'حدث خطأ أثناء التحقق من الصف الدراسي.';
    }
  }

  if (!$error) {
    try {
      $stmt = $pdo->prepare("SELECT id FROM students WHERE student_phone=? AND id<>? LIMIT 1");
      $stmt->execute([$studentPhone, $studentId]);
      if ($stmt->fetch()) $error = 'رقم الهاتف مسجل لطالب آخر.';
    } catch (Throwable $e) {
      $error = 'تعذر التحقق من رقم الهاتف.';
    }
  }

  if (!$error) {
    try {
      if ($newPass !== '') {
        $hash = password_hash($newPass, PASSWORD_DEFAULT);
        $up = $pdo->prepare("
          UPDATE students
          SET full_name=?,
              governorate=?,
              student_phone=?,
              parent_phone=?,
              grade_id=?,
              password_hash=?,
              password_plain=?
          WHERE id=?
        ");
        $up->execute([
          $fullName,
          $governorate,
          $studentPhone,
          ($parentPhone !== '' ? $parentPhone : null),
          $gradeId,
          $hash,
          $newPass,
          $studentId
        ]);
      } else {
        $up = $pdo->prepare("
          UPDATE students
          SET full_name=?,
              governorate=?,
              student_phone=?,
              parent_phone=?,
              grade_id=?
          WHERE id=?
        ");
        $up->execute([
          $fullName,
          $governorate,
          $studentPhone,
          ($parentPhone !== '' ? $parentPhone : null),
          $gradeId,
          $studentId
        ]);
      }

      $_SESSION['student_name'] = $fullName;

      header('Location: account.php?page=settings&saved=1');
      exit;
    } catch (Throwable $e) {
      $error = 'تعذر حفظ بيانات الحساب.';
    }
  }
}

if (isset($_GET['saved'])) $success = 'تم حفظ بيانات الحساب بنجاح.';

/* =========================
   Platform courses (NOT enrolled)
   ========================= */
$platformCourses = [];
try {
  if ($isOnline) {
    $stmt = $pdo->prepare("
      SELECT
        c.*,
        gr.name AS grade_name
      FROM courses c
      INNER JOIN grades gr ON gr.id = c.grade_id
      LEFT JOIN student_course_enrollments e
        ON e.course_id = c.id AND e.student_id = ?
      WHERE e.id IS NULL
        AND c.access_type != 'attendance'
      ORDER BY c.id DESC
    ");
  } else {
    $stmt = $pdo->prepare("
      SELECT
        c.*,
        gr.name AS grade_name
      FROM courses c
      INNER JOIN grades gr ON gr.id = c.grade_id
      LEFT JOIN student_course_enrollments e
        ON e.course_id = c.id AND e.student_id = ?
      WHERE e.id IS NULL
      ORDER BY c.id DESC
    ");
  }
  $stmt->execute([$studentId]);
  $platformCourses = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  $platformCourses = [];
}

/* =========================
   ✅ My courses (enrolled)
   ========================= */
$myCourses = [];
try {
  $stmt = $pdo->prepare("
    SELECT
      c.*,
      gr.name AS grade_name,
      e.access_type AS enroll_access_type,
      e.created_at AS enrolled_at
    FROM student_course_enrollments e
    INNER JOIN courses c ON c.id = e.course_id
    INNER JOIN grades gr ON gr.id = c.grade_id
    WHERE e.student_id = ?
    ORDER BY e.id DESC
  ");
  $stmt->execute([$studentId]);
  $myCourses = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  $myCourses = [];
}

/* ✅ NEW: attach last content update per course (lecture/video/pdf created_at) */
$courseLastUpdateMap = [];
$allCoursesForMap = array_merge($platformCourses, $myCourses);

if (!empty($allCoursesForMap)) {
  $courseIds = array_values(array_unique(array_map(fn($c) => (int)($c['id'] ?? 0), $allCoursesForMap)));
  $courseIds = array_values(array_filter($courseIds, fn($id) => $id > 0));

  if (!empty($courseIds)) {
    $placeholders = implode(',', array_fill(0, count($courseIds), '?'));
    try {
      $stmt = $pdo->prepare("
        SELECT course_id, MAX(dt) AS last_dt
        FROM (
          SELECT course_id, created_at AS dt FROM lectures WHERE course_id IN ($placeholders)
          UNION ALL
          SELECT course_id, created_at AS dt FROM videos  WHERE course_id IN ($placeholders)
          UNION ALL
          SELECT course_id, created_at AS dt FROM pdfs   WHERE course_id IN ($placeholders)
        ) x
        GROUP BY course_id
      ");
      $stmt->execute(array_merge($courseIds, $courseIds, $courseIds));
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
      foreach ($rows as $r) {
        $cid = (int)($r['course_id'] ?? 0);
        if ($cid > 0) $courseLastUpdateMap[$cid] = (string)($r['last_dt'] ?? '');
      }
    } catch (Throwable $e) {
      $courseLastUpdateMap = [];
    }
  }
}

/* ✅ Compute real stats now that courses lists are loaded */
$stats = [
  ['label' => 'كورسات المنصة', 'value' => count($platformCourses), 'icon' => '📚'],
  ['label' => 'كورساتك',       'value' => count($myCourses),        'icon' => '🎓'],
  ['label' => 'رصيد المحفظة',  'value' => number_format($wallet, 2), 'icon' => '💳'],
];

// count total lectures available in enrolled courses
$totalLectures = 0;
$totalVideos   = 0;
$totalPdfs     = 0;
if (!empty($myCourses)) {
  $enrolledCourseIds = array_values(array_filter(
    array_map(fn($c) => (int)($c['id'] ?? 0), $myCourses),
    fn($id) => $id > 0
  ));
  if (!empty($enrolledCourseIds)) {
    $ph = implode(',', array_fill(0, count($enrolledCourseIds), '?'));
    try {
      $s = $pdo->prepare("SELECT COUNT(*) FROM lectures WHERE course_id IN ($ph)");
      $s->execute($enrolledCourseIds);
      $totalLectures = (int)$s->fetchColumn();

      $s = $pdo->prepare("SELECT COUNT(*) FROM videos WHERE course_id IN ($ph)");
      $s->execute($enrolledCourseIds);
      $totalVideos = (int)$s->fetchColumn();

      $s = $pdo->prepare("SELECT COUNT(*) FROM pdfs WHERE course_id IN ($ph)");
      $s->execute($enrolledCourseIds);
      $totalPdfs = (int)$s->fetchColumn();
    } catch (Throwable $e) {}
  }
}
$stats[] = ['label' => 'المحاضرات المتاحة لك', 'value' => $totalLectures, 'icon' => '🧑‍🏫'];
$stats[] = ['label' => 'الفيديوهات المتاحة لك', 'value' => $totalVideos,   'icon' => '🎥'];
$stats[] = ['label' => 'ملفات PDF المتاحة لك',  'value' => $totalPdfs,     'icon' => '📑'];

/* ✅ cache-bust for account.css */
$cssVer = (string)@filemtime(__DIR__ . '/assets/css/account.css');
if ($cssVer === '' || $cssVer === '0') $cssVer = (string)time();
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

  <style>
    .acc-topbar{ position: static !important; top: auto !important; }
    @media (max-width: 980px){
      :root{ --acc-topbar-h: 118px !important; }
      .acc-topbar__bar{ flex-wrap: wrap !important; gap:10px !important; align-items: stretch !important; }
      .acc-topbar__right{ width:100% !important; justify-content: space-between !important; order:1 !important; }
      .acc-topbar__left{ width:100% !important; justify-content: space-between !important; order:2 !important; gap:8px !important; }
      .acc-layout{ grid-template-columns: 1fr !important; }
      .acc-sidebar{
        position: fixed !important;
        top: calc(var(--acc-topbar-h) + 10px) !important;
        right: 16px !important;
        left: 16px !important;
        height: auto !important;
        max-height: 72vh !important;
        overflow:auto !important;
        opacity: 0 !important;
        pointer-events:none !important;
        transform: translateY(-10px) !important;
        transition: .18s ease !important;
        z-index: 9997 !important;
      }
      .acc-sidebar.is-open{ opacity:1 !important; pointer-events:auto !important; transform: translateY(0) !important; }
      .acc-stats__grid{ grid-template-columns: repeat(2, minmax(0,1fr)) !important; }
      .acc-grid{ grid-template-columns: 1fr !important; }
    }
    @media (max-width: 560px){
      .acc-stats__grid{ grid-template-columns: 1fr !important; }
      .acc-brand__name{ display:none !important; }
    }
    .acc-actionsRow{display:flex;gap:10px;flex-wrap:wrap;margin:10px 0}
    .acc-btnx{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:12px;font-weight:900;text-decoration:none;cursor:pointer;border:none;font-family:inherit;font-size:1em}
    .acc-btnx--solid{background:#111;color:#fff}
    .acc-btnx--ghost{background:transparent;border:2px solid #111;color:#111}
    /* Stats grid */
    .acc-stats{margin:20px 0}
    .acc-stats__grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
    .acc-stat{background:var(--card-bg,#fff);border:1px solid var(--border,#e2e8f0);border-radius:16px;padding:18px 14px;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,.05)}
    .acc-stat__ico{font-size:2em;margin-bottom:6px}
    .acc-stat__val{font-size:1.7em;font-weight:900;color:var(--accent,#0b63ce)}
    .acc-stat__lbl{font-size:.85em;color:var(--muted,#666);margin-top:4px;font-weight:700}
  </style>

  <title>حساب الطالب - <?php echo h($platformName); ?></title>
</head>
<body>

<header class="acc-topbar" role="banner">
  <div class="container">
    <div class="acc-topbar__bar">

      <div class="acc-topbar__right">
        <a class="acc-brand" href="account.php?page=home" aria-label="<?php echo h($platformName); ?>">
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
        <button class="acc-burger" id="accBurger" type="button" aria-label="فتح القائمة">☰</button>

        <div class="acc-student" title="<?php echo h($studentName); ?>">
          <span aria-hidden="true">👤</span>
          <span class="acc-student__name"><?php echo h($studentName); ?></span>
        </div>

        <div class="acc-pill" title="رصيد المحفظة">
          <span aria-hidden="true">💳</span>
          <span><?php echo number_format($wallet, 2); ?> جنيه</span>
        </div>

        <button class="acc-bell" type="button" id="btnBell" aria-label="الإشعارات" title="الإشعارات">
          🔔
          <span class="acc-bell__badge" id="bellBadge" style="display:none;">0</span>
        </button>
      </div>

    </div>
  </div>
</header>

<div class="acc-backdrop" id="accBackdrop" aria-hidden="true"></div>

<div class="acc-notifs" id="notifsBox" aria-hidden="true">
  <div class="acc-notifs__head">
    <div class="acc-notifs__title">🔔 إشعارات الصف</div>
    <button class="acc-notifs__close" type="button" id="closeNotifs">✖</button>
  </div>
  <div class="acc-notifs__body" id="notifsBody">
    <div class="acc-notifs__loading">جارٍ التحميل...</div>
  </div>
</div>

<div class="acc-layout">
  <aside class="acc-sidebar" id="accSidebar" aria-label="القائمة الجانبية">
    <nav class="acc-nav">
      <?php foreach ($sidebar as $it): ?>
        <?php
          $isActive = false;
          if (($it['key'] ?? '') === 'home' && $page === 'home') $isActive = true;
          if (($it['key'] ?? '') === 'settings' && $page === 'settings') $isActive = true;
          if (($it['key'] ?? '') === 'platform_courses' && $page === 'platform_courses') $isActive = true;
          if (($it['key'] ?? '') === 'my_courses' && $page === 'my_courses') $isActive = true;

          $cls = 'acc-nav__item';
          if ($isActive) $cls .= ' is-active';
          if (!empty($it['danger'])) $cls .= ' is-danger';
          if (!empty($it['disabled'])) $cls .= ' is-disabled';
        ?>

        <?php if (!empty($it['disabled'])): ?>
          <span class="<?php echo $cls; ?>" title="يبرمج لاحقًا">
            <span aria-hidden="true"><?php echo h((string)$it['icon']); ?></span>
            <span class="acc-nav__lbl"><?php echo h((string)$it['label']); ?></span>
            <span class="acc-nav__soon">قريبًا</span>
          </span>
        <?php else: ?>
          <a class="<?php echo $cls; ?>" href="<?php echo h((string)$it['href']); ?>">
            <span aria-hidden="true"><?php echo h((string)$it['icon']); ?></span>
            <span class="acc-nav__lbl"><?php echo h((string)$it['label']); ?></span>
          </a>
        <?php endif; ?>
      <?php endforeach; ?>
    </nav>
  </aside>

  <main class="acc-main">
    <div class="container">

      <?php if ($success): ?>
        <div class="acc-alert acc-alert--success" role="alert"><?php echo h($success); ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="acc-alert acc-alert--error" role="alert"><?php echo h($error); ?></div>
      <?php endif; ?>

      <?php if ($page === 'home'): ?>
        <section class="acc-hero">
          <h1>👋 أهلاً <?php echo h($studentName); ?></h1>
          <p style="margin-top:6px;color:var(--muted);font-weight:700;">مرحباً بك في حسابك على المنصة.</p>
        </section>

        <section class="acc-stats" aria-label="إحصائيات">
          <div class="acc-stats__grid">
            <?php foreach ($stats as $st): ?>
              <div class="acc-stat">
                <div class="acc-stat__ico" aria-hidden="true"><?php echo h((string)$st['icon']); ?></div>
                <div class="acc-stat__val"><?php echo is_numeric($st['value']) ? (int)$st['value'] : h((string)$st['value']); ?></div>
                <div class="acc-stat__lbl"><?php echo h((string)$st['label']); ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </section>

      <?php elseif ($page === 'platform_courses'): ?>
        <section class="acc-card" aria-label="كورسات المنصة">
          <div class="acc-card__head">
            <h2>📚 كورسات المنصة</h2>
            <p>هنا تظهر الكورسات التي لست مشتركًا فيها.</p>
          </div>

          <?php if (empty($platformCourses)): ?>
            <div style="font-weight:900;color:var(--muted);line-height:1.9;">
              لا توجد كورسات متاحة حالياً (أو أنت مشترك في كل الكورسات).
            </div>
          <?php else: ?>
            <div class="acc-courses-grid">
              <?php foreach ($platformCourses as $c): ?>
                <?php
                  $accessType = (string)($c['access_type'] ?? 'attendance');
                  $buyType = (string)($c['buy_type'] ?? 'none');

                  $isFree = ($accessType === 'free');
                  $isBuy = ($accessType === 'buy');
                  $isDiscount = ($isBuy && $buyType === 'discount');

                  $priceBase = $c['price_base'];
                  $priceDiscount = $c['price_discount'];
                  $discountEnd = (string)($c['discount_end'] ?? '');

                  $imgDb = trim((string)($c['image_path'] ?? ''));
                  $imgUrl = null;
                  if ($imgDb !== '') $imgUrl = '../admin/' . ltrim($imgDb, '/');

                  $details = trim((string)($c['details'] ?? ''));
                  $courseLast = (string)($courseLastUpdateMap[(int)$c['id']] ?? '');
                ?>

                <article class="acc-course">
                  <div class="acc-course__cover">
                    <?php if ($imgUrl): ?>
                      <img class="acc-course__img" src="<?php echo h($imgUrl); ?>" alt="<?php echo h((string)$c['name']); ?>">
                    <?php else: ?>
                      <div class="acc-course__imgFallback">📚</div>
                    <?php endif; ?>
                  </div>

                  <div class="acc-course__body">
                    <div class="acc-course__head">
                      <div class="acc-course__title"><?php echo h((string)$c['name']); ?></div>
                      <div class="acc-course__grade">🏫 <?php echo h((string)$c['grade_name']); ?></div>
                    </div>

                    <div class="acc-course__details">
                      <?php if ($details !== ''): ?>
                        <?php echo nl2br(h($details)); ?>
                      <?php else: ?>
                        <span style="color:var(--muted);font-weight:900;">بدون تفاصيل.</span>
                      <?php endif; ?>
                    </div>

                    <div class="acc-course__meta">
                      <div class="acc-metaRow">
                        🧩 آخر تحديث داخل الكورس:
                        <span><?php echo h($courseLast !== '' ? $courseLast : 'لا يوجد محتوى بعد'); ?></span>
                      </div>
                    </div>

                    <div class="acc-course__pricing">
                      <?php if ($isFree): ?>
                        <span class="acc-badge acc-badge--free">🆓 مجاني</span>
                      <?php elseif ($isBuy): ?>
                        <span class="acc-badge acc-badge--buy">🛒 شراء</span>

                        <?php if ($isDiscount): ?>
                          <div class="acc-price">
                            <span class="acc-price__label">قبل الخصم:</span>
                            <span class="acc-price__val acc-price__val--before"><?php echo h((string)$priceBase); ?> جنيه</span>
                          </div>
                          <div class="acc-price">
                            <span class="acc-price__label">بعد الخصم:</span>
                            <span class="acc-price__val acc-price__val--after"><?php echo h((string)$priceDiscount); ?> جنيه</span>
                          </div>
                          <?php if ($discountEnd !== ''): ?>
                            <div class="acc-price acc-price--muted">⏳ حتى <?php echo h($discountEnd); ?></div>
                          <?php endif; ?>
                        <?php else: ?>
                          <div class="acc-price">
                            <span class="acc-price__label">السعر:</span>
                            <span class="acc-price__val acc-price__val--after"><?php echo h((string)$priceBase); ?> جنيه</span>
                          </div>
                        <?php endif; ?>

                      <?php else: ?>
                        <span class="acc-badge acc-badge--att">✅ بالحضور</span>
                      <?php endif; ?>
                    </div>

                    <div class="acc-course__actions">
                      <a class="acc-btn acc-btn--ghost" href="account_course.php?course_id=<?php echo (int)$c['id']; ?>">📑 تفاصيل الكورس</a>
                      <a class="acc-btn" href="account_course.php?course_id=<?php echo (int)$c['id']; ?>">🛒 شراء / تفعيل</a>
                    </div>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>

      <?php elseif ($page === 'my_courses'): ?>
        <section class="acc-card" aria-label="كورساتك">
          <div class="acc-card__head">
            <h2>🎓 كورساتك</h2>
            <p>هنا تظهر الكورسات التي أنت مشترك فيها.</p>

          </div>

          <?php if (empty($myCourses)): ?>
            <div style="font-weight:900;color:var(--muted);line-height:1.9;">
              أنت غير مشترك في أي كورس حتى الآن.
            </div>
          <?php else: ?>
            <div class="acc-courses-grid">
              <?php foreach ($myCourses as $c): ?>
                <?php
                  $imgDb = trim((string)($c['image_path'] ?? ''));
                  $imgUrl = null;
                  if ($imgDb !== '') $imgUrl = '../admin/' . ltrim($imgDb, '/');

                  $details = trim((string)($c['details'] ?? ''));
                  $courseLast = (string)($courseLastUpdateMap[(int)$c['id']] ?? '');
                  $enrollType = (string)($c['enroll_access_type'] ?? '');
                  $enrolledAt = (string)($c['enrolled_at'] ?? '');
                ?>

                <article class="acc-course">
                  <div class="acc-course__cover">
                    <?php if ($imgUrl): ?>
                      <img class="acc-course__img" src="<?php echo h($imgUrl); ?>" alt="<?php echo h((string)$c['name']); ?>">
                    <?php else: ?>
                      <div class="acc-course__imgFallback">🎓</div>
                    <?php endif; ?>
                  </div>

                  <div class="acc-course__body">
                    <div class="acc-course__head">
                      <div class="acc-course__title"><?php echo h((string)$c['name']); ?></div>
                      <div class="acc-course__grade">🏫 <?php echo h((string)$c['grade_name']); ?></div>
                    </div>

                    <div class="acc-course__meta">
                      <div class="acc-metaRow">✅ نوع الاشتراك: <b><?php echo h($enrollType); ?></b></div>
                      <?php if ($enrolledAt !== ''): ?>
                        <div class="acc-metaRow">🗓️ تاريخ الاشتراك: <span><?php echo h($enrolledAt); ?></span></div>
                      <?php endif; ?>
                      <div class="acc-metaRow">🧩 آخر تحديث داخل الكورس: <span><?php echo h($courseLast !== '' ? $courseLast : '—'); ?></span></div>
                    </div>

                    <div class="acc-course__details">
                      <?php if ($details !== ''): ?>
                        <?php echo nl2br(h($details)); ?>
                      <?php else: ?>
                        <span style="color:var(--muted);font-weight:900;">بدون تفاصيل.</span>
                      <?php endif; ?>
                    </div>

                    <div class="acc-course__actions">
                      <a class="acc-btn" href="account_course.php?course_id=<?php echo (int)$c['id']; ?>">▶️ دخول الكورس</a>
                    </div>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>

      <?php elseif ($page === 'settings'): ?>
        <!-- settings unchanged -->
        <section class="acc-card" aria-label="إعدادات الحساب">
          <div class="acc-card__head">
            <h2>⚙️ إعدادات الحساب</h2>
            <p>يمكنك تعديل بياناتك هنا.</p>
          </div>

          <form method="post" class="acc-form" autocomplete="off">
            <input type="hidden" name="action" value="update_profile">

            <div class="acc-grid">
              <label class="acc-field">
                <span class="acc-label">اسم الطالب</span>
                <input class="acc-input" name="full_name" required value="<?php echo h((string)$student['full_name']); ?>" placeholder="مثال: محمد أحمد علي">
              </label>

              <label class="acc-field">
                <span class="acc-label">المحافظة</span>
                <select class="acc-input" name="governorate" required>
                  <option value="">— اختر المحافظة —</option>
                  <?php foreach ($governorates as $gov): ?>
                    <option value="<?php echo h($gov); ?>" <?php echo ((string)$student['governorate'] === $gov) ? 'selected' : ''; ?>>
                      <?php echo h($gov); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </label>

              <label class="acc-field">
                <span class="acc-label">رقم هاتف الطالب</span>
                <input class="acc-input" name="student_phone" required inputmode="numeric" pattern="[0-9]*"
                       value="<?php echo h((string)$student['student_phone']); ?>" placeholder="010xxxxxxxx">
              </label>

              <label class="acc-field">
                <span class="acc-label">رقم هاتف ولي الأمر</span>
                <input class="acc-input" name="parent_phone" inputmode="numeric" pattern="[0-9]*"
                       value="<?php echo h((string)($student['parent_phone'] ?? '')); ?>" placeholder="010xxxxxxxx">
              </label>

              <label class="acc-field">
                <span class="acc-label">الصف الدراسي</span>
                <select class="acc-input" name="grade_id" required>
                  <option value="0">— اختر الصف —</option>
                  <?php foreach ($gradesList as $g): ?>
                    <option value="<?php echo (int)$g['id']; ?>" <?php echo ((int)$student['grade_id'] === (int)$g['id']) ? 'selected' : ''; ?>>
                      <?php echo h((string)$g['name']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </label>

              <label class="acc-field">
                <span class="acc-label">كلمة سر جديدة</span>
                <input class="acc-input" type="password" name="new_password" placeholder="••••••••">
              </label>

              <label class="acc-field">
                <span class="acc-label">تأكيد كلمة السر الجديدة</span>
                <input class="acc-input" type="password" name="new_password2" placeholder="••••••••">
              </label>
            </div>

            <div class="acc-actions">
              <button class="acc-btn" type="submit">💾 حفظ التغييرات</button>
              <a class="acc-btn acc-btn--ghost" href="account.php?page=settings">إلغاء</a>
            </div>
          </form>
        </section>
      <?php endif; ?>

    </div>
  </main>
</div>

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
<script>
(function(){
  const burger = document.getElementById('accBurger');
  const sidebar = document.getElementById('accSidebar');
  const backdrop = document.getElementById('accBackdrop');

  function isMobile(){ return window.matchMedia && window.matchMedia('(max-width: 980px)').matches; }

  function openSidebar(){
    if (!isMobile()) return;
    sidebar.classList.add('is-open');
    backdrop.classList.add('is-open');
    backdrop.setAttribute('aria-hidden','false');
  }
  function closeSidebar(){
    sidebar.classList.remove('is-open');
    backdrop.classList.remove('is-open');
    backdrop.setAttribute('aria-hidden','true');
  }

  burger && burger.addEventListener('click', (e) => {
    e.preventDefault();
    if (sidebar.classList.contains('is-open')) closeSidebar();
    else openSidebar();
  });

  backdrop && backdrop.addEventListener('click', (e) => {
    e.preventDefault();
    closeSidebar();
  });

  window.addEventListener('resize', () => {
    if (!isMobile()) closeSidebar();
  });

  // Notifications
  const btnBell = document.getElementById('btnBell');
  const notifsBox = document.getElementById('notifsBox');
  const closeBtn = document.getElementById('closeNotifs');
  const body = document.getElementById('notifsBody');
  const badge = document.getElementById('bellBadge');

  let opened = false;
  let lastUnreadCount = 0;

  function renderBadge(){
    if (!badge) return;
    if (opened) { badge.style.display = 'none'; return; }

    if (lastUnreadCount > 0) {
      badge.style.display = '';
      badge.textContent = String(lastUnreadCount);
    } else {
      badge.style.display = 'none';
      badge.textContent = '0';
    }
  }

  function openNotifs(){
    opened = true;
    notifsBox.classList.add('is-open');
    notifsBox.setAttribute('aria-hidden','false');
    renderBadge();
  }
  function closeNotifs(){
    opened = false;
    notifsBox.classList.remove('is-open');
    notifsBox.setAttribute('aria-hidden','true');
    renderBadge();
  }

  function escapeHtml(s){
    return (s||'').toString()
      .replaceAll('&','&amp;')
      .replaceAll('<','&lt;')
      .replaceAll('>','&gt;')
      .replaceAll('"','&quot;')
      .replaceAll("'","&#039;");
  }

  async function loadNotifs(markRead){
    try{
      body.innerHTML = '<div class="acc-notifs__loading">جارٍ التحميل...</div>';

      const url = markRead ? 'account_notifications_api.php?mark_read=1' : 'account_notifications_api.php';
      const res = await fetch(url, { credentials:'same-origin' });
      const data = await res.json();
      if (!data || !data.ok) throw new Error('api_error');

      lastUnreadCount = Math.max(0, parseInt(data.unread_count || 0, 10) || 0);
      renderBadge();

      const items = Array.isArray(data.items) ? data.items : [];
      if (!items.length) {
        body.innerHTML = '<div class="acc-notifs__empty">لا توجد إشعارات حالياً.</div>';
        return;
      }

      body.innerHTML = items.map(it => {
        const title = (it.title || '').toString();
        const text = (it.body || '').toString();
        const dt = (it.created_at || '').toString();
        return `
          <div class="acc-notif">
            <div class="acc-notif__title">${escapeHtml(title)}</div>
            <div class="acc-notif__body">${escapeHtml(text)}</div>
            <div class="acc-notif__time">${escapeHtml(dt)}</div>
          </div>
        `;
      }).join('');
    }catch(e){
      body.innerHTML = '<div class="acc-notifs__err">تعذر تحميل الإشعارات.</div>';
      lastUnreadCount = 0;
      renderBadge();
    }
  }

  btnBell && btnBell.addEventListener('click', async () => {
    if (!opened) {
      openNotifs();
      await loadNotifs(true);
    } else {
      closeNotifs();
      await loadNotifs(false);
    }
  });

  closeBtn && closeBtn.addEventListener('click', async () => {
    closeNotifs();
    await loadNotifs(false);
  });

  document.addEventListener('click', (e) => {
    const t = e.target;
    if (!t) return;
    if (opened) {
      const inside = notifsBox.contains(t) || (btnBell && btnBell.contains(t));
      if (!inside) closeNotifs();
    }
  });

  loadNotifs(false);

  window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      closeSidebar();
      closeNotifs();
    }
  });
})();
</script>

<!-- ✅ Redeem Code Modal -->
<div id="redeemModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.55);align-items:center;justify-content:center;" role="dialog" aria-modal="true" aria-label="تفعيل كود">
  <div style="background:var(--card-bg,#fff);border-radius:18px;padding:28px 24px;max-width:420px;width:calc(100% - 32px);box-shadow:0 8px 40px rgba(0,0,0,.25);position:relative;font-family:inherit;">
    <button onclick="closeRedeemModal()" style="position:absolute;top:12px;left:12px;background:none;border:none;font-size:1.4em;cursor:pointer;color:var(--muted,#888);" aria-label="إغلاق">✖</button>
    <h3 style="margin:0 0 14px;font-size:1.2em;">🎫 تفعيل كود اشتراك</h3>

    <div id="redeemMsg" style="display:none;padding:10px 14px;border-radius:10px;margin-bottom:12px;font-weight:700;"></div>

    <div id="redeemCodeStep">
      <input id="redeemCodeInput" type="text" placeholder="XXXX-XXXX-XXXX" style="width:100%;padding:12px;border:1px solid #ccc;border-radius:12px;font-size:1em;box-sizing:border-box;margin-bottom:10px;font-family:inherit;" dir="ltr">
      <button onclick="submitRedeemCode()" style="width:100%;padding:12px;border:none;border-radius:12px;background:#111;color:#fff;font-size:1em;font-weight:700;cursor:pointer;font-family:inherit;">✅ تفعيل</button>
    </div>

    <div id="redeemCourseStep" style="display:none;">
      <p style="margin:0 0 8px;font-weight:700;color:#b06000;">🎓 هذا الكود عام — اختر الكورس الذي تريد فتحه:</p>
      <select id="redeemCourseSelect" style="width:100%;padding:12px;border:1px solid #ccc;border-radius:12px;font-size:1em;box-sizing:border-box;margin-bottom:10px;font-family:inherit;">
        <option value="">-- اختر الكورس --</option>
      </select>
      <button onclick="submitRedeemWithCourse()" style="width:100%;padding:12px;border:none;border-radius:12px;background:#1a7a2a;color:#fff;font-size:1em;font-weight:700;cursor:pointer;font-family:inherit;">✅ تفعيل الكورس</button>
    </div>
  </div>
</div>

<script>
(function(){
  var modal = document.getElementById('redeemModal');
  var codeInput = document.getElementById('redeemCodeInput');
  var msgBox = document.getElementById('redeemMsg');
  var codeStep = document.getElementById('redeemCodeStep');
  var courseStep = document.getElementById('redeemCourseStep');
  var courseSelect = document.getElementById('redeemCourseSelect');
  var lastCode = '';

  window.openRedeemModal = function() {
    modal.style.display = 'flex';
    codeInput.value = '';
    lastCode = '';
    hideMsg();
    showStep('code');
    setTimeout(function(){ codeInput.focus(); }, 80);
  };
  window.closeRedeemModal = function() {
    modal.style.display = 'none';
  };

  modal.addEventListener('click', function(e){ if (e.target === modal) closeRedeemModal(); });

  function showMsg(text, ok) {
    msgBox.textContent = text;
    msgBox.style.display = 'block';
    msgBox.style.background = ok ? '#e9ffe9' : '#ffe9e9';
    msgBox.style.border = '1px solid ' + (ok ? '#8ad08a' : '#d08a8a');
    msgBox.style.color = ok ? '#1a6a1a' : '#a00';
  }
  function hideMsg() {
    msgBox.style.display = 'none';
    msgBox.textContent = '';
  }
  function showStep(step) {
    codeStep.style.display = step === 'code' ? 'block' : 'none';
    courseStep.style.display = step === 'course' ? 'block' : 'none';
  }

  window.submitRedeemCode = async function() {
    var code = codeInput.value.trim();
    if (!code) { showMsg('من فضلك أدخل الكود.', false); return; }
    lastCode = code;
    hideMsg();
    codeStep.querySelector('button').disabled = true;
    codeStep.querySelector('button').textContent = '⏳ جاري التفعيل...';

    try {
      var fd = new FormData();
      fd.append('code', code);
      var res = await fetch('api/redeem_code_api.php', {method:'POST', body:fd});
      var data = await res.json();

      codeStep.querySelector('button').disabled = false;
      codeStep.querySelector('button').textContent = '✅ تفعيل';

      if (data.needs_target && data.target_type === 'course') {
        // Show course picker
        courseSelect.innerHTML = '<option value="">-- اختر الكورس --</option>';
        (data.courses || []).forEach(function(c){
          var o = document.createElement('option');
          o.value = c.id;
          o.textContent = c.name;
          courseSelect.appendChild(o);
        });
        showStep('course');
        showMsg(data.message || 'اختر الكورس المراد فتحه.', false);
      } else if (data.ok) {
        showMsg('✅ ' + (data.message || 'تم التفعيل بنجاح.'), true);
        showStep('code');
        setTimeout(function(){ closeRedeemModal(); location.reload(); }, 1800);
      } else {
        showMsg('❌ ' + (data.message || 'حدث خطأ.'), false);
      }
    } catch(e) {
      codeStep.querySelector('button').disabled = false;
      codeStep.querySelector('button').textContent = '✅ تفعيل';
      showMsg('❌ حدث خطأ في الاتصال، حاول مرة أخرى.', false);
    }
  };

  window.submitRedeemWithCourse = async function() {
    var courseId = courseSelect.value;
    if (!courseId) { showMsg('من فضلك اختر كورساً.', false); return; }
    hideMsg();
    courseStep.querySelector('button').disabled = true;
    courseStep.querySelector('button').textContent = '⏳ جاري التفعيل...';

    try {
      var fd = new FormData();
      fd.append('code', lastCode);
      fd.append('target_course_id', courseId);
      var res = await fetch('api/redeem_code_api.php', {method:'POST', body:fd});
      var data = await res.json();

      courseStep.querySelector('button').disabled = false;
      courseStep.querySelector('button').textContent = '✅ تفعيل الكورس';

      if (data.ok) {
        showMsg('✅ ' + (data.message || 'تم التفعيل بنجاح.'), true);
        setTimeout(function(){ closeRedeemModal(); location.reload(); }, 1800);
      } else {
        showMsg('❌ ' + (data.message || 'حدث خطأ.'), false);
      }
    } catch(e) {
      courseStep.querySelector('button').disabled = false;
      courseStep.querySelector('button').textContent = '✅ تفعيل الكورس';
      showMsg('❌ حدث خطأ في الاتصال.', false);
    }
  };

  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape' && modal.style.display !== 'none') closeRedeemModal();
  });
})();
</script>

</body>
</html>
