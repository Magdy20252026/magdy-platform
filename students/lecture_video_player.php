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

$studentId = (int)($_SESSION['student_id'] ?? 0);
$videoId = (int)($_GET['video_id'] ?? 0);
if ($studentId <= 0 || $videoId <= 0) {
  header('Location: account.php?page=platform_courses');
  exit;
}

$video = student_get_video_row($pdo, $videoId); // defined in students/inc/access_control.php
if (!$video) {
  header('Location: account.php?page=platform_courses');
  exit;
}

$lectureId = (int)($video['lecture_id'] ?? 0);
if (!student_has_lecture_access($pdo, $studentId, $lectureId)) {
  header('Location: account.php?page=platform_courses');
  exit;
}

$stmt = $pdo->prepare("
  SELECT s.full_name, s.wallet_balance, s.barcode
  FROM students s
  WHERE s.id=?
  LIMIT 1
");
$stmt->execute([$studentId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
if (!$student) {
  header('Location: logout.php');
  exit;
}

$stmt = $pdo->prepare("
  SELECT l.id, l.name, c.id AS course_id, c.name AS course_name
  FROM lectures l
  INNER JOIN courses c ON c.id = l.course_id
  WHERE l.id=?
  LIMIT 1
");
$stmt->execute([$lectureId]);
$lecture = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
if (!$lecture) {
  header('Location: account.php?page=platform_courses');
  exit;
}

student_video_views_ensure_table($pdo);
$stats = student_get_video_watch_stats($pdo, $studentId, $videoId, $video);
$halfSeconds = student_video_half_watch_seconds((int)($video['duration_minutes'] ?? 0));

$row = get_platform_settings_row($pdo);
$platformName = trim((string)($row['platform_name'] ?? 'منصتي التعليمية'));
if ($platformName === '') $platformName = 'منصتي التعليمية';
$logoDb = trim((string)($row['platform_logo'] ?? ''));
$logoUrl = $logoDb !== '' ? '../admin/' . ltrim($logoDb, '/') : null;

$studentName = (string)($student['full_name'] ?? ($_SESSION['student_name'] ?? ''));
$wallet = (float)($student['wallet_balance'] ?? 0);
$studentCode = trim((string)($student['barcode'] ?? ''));
if ($studentCode === '') $studentCode = 'STD-' . $studentId;
$studentWatermark = $studentCode . ' • ' . $studentName;

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
  <link rel="stylesheet" href="assets/css/account.css?v=<?php echo h($cssVer); ?>">
  <link rel="stylesheet" href="assets/css/account-lecture.css?v=<?php echo h($lecCssVer); ?>">

  <style>
    .pill{padding:10px 12px;border:1px solid #ddd;border-radius:14px;font-weight:900}
    .acc-modal-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:10px 14px;border:2px solid transparent;border-radius:12px;font-weight:700;cursor:pointer;font-family:inherit;font-size:1em;text-decoration:none}
    .acc-modal-btn--primary{background:#111;color:#fff}
    .acc-modal-btn--ghost{background:var(--page-bg);border-color:var(--border);color:var(--text)}
  </style>

  <title>مشغل المحاضرة - <?php echo h((string)($video['title'] ?? 'فيديو المحاضرة')); ?></title>
</head>
<body>

<header class="acc-topbar" role="banner">
  <div class="container">
    <div class="acc-topbar__bar">
      <div class="acc-topbar__right">
        <a class="acc-brand" href="account_lecture.php?lecture_id=<?php echo (int)$lectureId; ?>" aria-label="العودة إلى صفحة المحاضرة">
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
        <a class="acc-btn acc-btn--ghost" href="account_lecture.php?lecture_id=<?php echo (int)$lectureId; ?>">⬅️ رجوع</a>

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

<main class="acc-viewerPage">
  <div class="container">
    <section class="acc-card acc-viewerHero" aria-label="بيانات الفيديو">
      <div class="acc-viewerHero__top">
        <div class="acc-viewerHero__meta">
          <h1 class="acc-viewerHero__title">🎥 <?php echo h((string)($video['title'] ?? 'فيديو المحاضرة')); ?></h1>
          <div class="acc-viewerHero__sub">
            المحاضرة: <b><?php echo h((string)($lecture['name'] ?? '')); ?></b>
            <br>
            الكورس: <b><?php echo h((string)($lecture['course_name'] ?? '')); ?></b>
          </div>
        </div>

      </div>

      <div class="acc-viewerStats">
        <span class="pill">⏱️ المدة: <b><?php echo (int)($video['duration_minutes'] ?? 0); ?> دقيقة</b></span>
        <span class="pill">👁️ المستخدم: <b id="videoViewsUsed"><?php echo (int)($stats['used'] ?? 0); ?></b> / <b id="videoViewsAllowed"><?php echo (int)($stats['allowed'] ?? 1); ?></b></span>
        <span class="pill">🟢 المتبقي: <b id="videoViewsRemaining"><?php echo (int)($stats['remaining'] ?? 0); ?></b></span>
      </div>
    </section>

    <section class="acc-card acc-viewerFrameShell" aria-label="مشغل فيديو المحاضرة">
      <div class="acc-playerStage" id="lecturePlayerStage">
        <div class="acc-playerSurface" id="lecturePlayerSurface">
          <div class="acc-playerPlaceholder" id="lecturePlayerPlaceholder">
            <?php if (!empty($stats['blocked'])): ?>
              ⛔ انتهت عدد المشاهدات المسموحة لهذا الفيديو، ولن يتم تشغيله مرة أخرى.
            <?php else: ?>
              يتم تجهيز الفيديو داخل مشغل المنصة تلقائيًا، ويمكنك تشغيله من زر التشغيل داخل الفيديو نفسه.
            <?php endif; ?>
          </div>
        </div>
        <div class="acc-playerOverlay">
          <span class="acc-playerOverlay__chip"><?php echo h($studentWatermark); ?></span>
        </div>
        <div class="acc-playerProtectionMask" aria-hidden="true"></div>
        <div class="acc-platformControls" id="lecturePlayerControls" hidden>
          <div class="acc-platformControls__group acc-platformControls__group--actions">
            <button class="acc-modal-btn acc-modal-btn--primary acc-platformControls__iconBtn" type="button" id="lecturePlayerCtrlPlayPause" aria-label="تشغيل أو إيقاف الفيديو" disabled>تشغيل ▶️</button>
            <button class="acc-modal-btn acc-modal-btn--ghost acc-platformControls__iconBtn" type="button" id="lecturePlayerCtrlSeekBack" aria-label="إرجاع عشر ثواني" disabled>رجوع 10 ث ⏪</button>
            <button class="acc-modal-btn acc-modal-btn--ghost acc-platformControls__iconBtn" type="button" id="lecturePlayerCtrlSeekForward" aria-label="تقديم عشر ثواني" disabled>تقديم 10 ث ⏩</button>
            <button class="acc-modal-btn acc-modal-btn--ghost acc-platformControls__iconBtn" type="button" id="lecturePlayerCtrlFullscreen" aria-label="تكبير المشغل" disabled>تكبير ⛶</button>
          </div>
          <div class="acc-platformControls__group acc-platformControls__group--timeline">
            <span class="acc-platformControls__label">الوقت</span>
            <input class="acc-platformControls__timeline" type="range" id="lecturePlayerCtrlTimeline" min="0" max="0" step="1" value="0" aria-label="التحكم في وقت الفيديو" disabled>
            <span class="acc-platformControls__time" id="lecturePlayerCtrlTime">00:00 / 00:00</span>
          </div>
          <div class="acc-platformControls__group acc-platformControls__group--audio">
            <span class="acc-platformControls__label">الصوت</span>
            <input class="acc-platformControls__range" type="range" id="lecturePlayerCtrlVolume" min="0" max="100" step="1" value="100" aria-label="مستوى الصوت" disabled>
          </div>
          <div class="acc-platformControls__group">
            <label class="acc-platformControls__label" for="lecturePlayerCtrlQuality">الجودة</label>
            <select class="acc-platformControls__select" id="lecturePlayerCtrlQuality" aria-label="جودة الفيديو" disabled>
              <option value="auto">تلقائي</option>
            </select>
          </div>
          <div class="acc-platformControls__group">
            <label class="acc-platformControls__label" for="lecturePlayerCtrlSpeed">السرعة</label>
            <select class="acc-platformControls__select" id="lecturePlayerCtrlSpeed" aria-label="سرعة التشغيل" disabled>
              <option value="1">1x</option>
            </select>
          </div>
        </div>
      </div>

      <div class="acc-playerNotice" id="lecturePlayerNotice">
        <?php if (!empty($stats['blocked'])): ?>
          ⛔ لا يمكن تشغيل هذا الفيديو لأن عدد المشاهدات المسموحة انتهى.
        <?php else: ?>
          🔒 هذه الصفحة محمية: تم تعطيل الكليك اليمين واختصارات أدوات المطور، مع طبقة حماية مائية على الفيديو وإرجاع تلقائي عند محاولات كشف أو نسخ المحتوى.
        <?php endif; ?>
      </div>
    </section>
  </div>
</main>

<script src="assets/js/theme.js"></script>
<script>
(function(){
  var videoId = <?php echo (int)$videoId; ?>;
  var lectureId = <?php echo (int)$lectureId; ?>;
  var videoState = {
    id: videoId,
    title: <?php echo json_encode((string)($video['title'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    durationMinutes: <?php echo (int)($video['duration_minutes'] ?? 0); ?>,
    viewsAllowed: <?php echo (int)($stats['allowed'] ?? 1); ?>,
    viewsUsed: <?php echo (int)($stats['used'] ?? 0); ?>,
    viewsRemaining: <?php echo (int)($stats['remaining'] ?? 0); ?>,
    videoType: <?php echo json_encode((string)($video['video_type'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    isBlocked: <?php echo !empty($stats['blocked']) ? 'true' : 'false'; ?>,
    halfSeconds: <?php echo (int)$halfSeconds; ?>
  };

  var surface = document.getElementById('lecturePlayerSurface');
  var noticeEl = document.getElementById('lecturePlayerNotice');
  var startBtn = null;
  var fullscreenBtn = document.getElementById('lecturePlayerCtrlFullscreen');
  var playerStage = document.getElementById('lecturePlayerStage');
  var viewsAllowedEl = document.getElementById('videoViewsAllowed');
  var viewsUsedEl = document.getElementById('videoViewsUsed');
  var viewsRemainingEl = document.getElementById('videoViewsRemaining');
  var halfSecondsEl = null;
  var platformControls = document.getElementById('lecturePlayerControls');
  var ctrlPlayPauseBtn = document.getElementById('lecturePlayerCtrlPlayPause');
  var ctrlSeekBackBtn = document.getElementById('lecturePlayerCtrlSeekBack');
  var ctrlSeekForwardBtn = document.getElementById('lecturePlayerCtrlSeekForward');
  var ctrlFullscreenBtn = document.getElementById('lecturePlayerCtrlFullscreen');
  var ctrlTimelineInput = document.getElementById('lecturePlayerCtrlTimeline');
  var ctrlTimeLabel = document.getElementById('lecturePlayerCtrlTime');
  var ctrlVolumeInput = document.getElementById('lecturePlayerCtrlVolume');
  var ctrlQualitySelect = document.getElementById('lecturePlayerCtrlQuality');
  var ctrlSpeedSelect = document.getElementById('lecturePlayerCtrlSpeed');

  var activeWatchToken = '';
  var countedToken = '';
  var heartbeatHandle = 0;
  var progressHandle = 0;
  var progressBaseSeconds = 0;
  var progressBaseStartedAt = 0;
  var requestInFlight = false;
  var protectedPageClosed = false;
  var devtoolsDetectionStrikes = 0;
  var controlsHideHandle = 0;
  var youtubePlayer = null;
  var youtubeApiReadyPromise = null;
  var youtubeTimeHandle = 0;
  var timelineDragging = false;
  var maxReachedSeconds = 0;
  var playbackBootstrapped = false;
  var startRequestInFlight = false;
  // tuned for typical browser UI gaps so docked DevTools detection triggers before playback continues
  const devtoolsWidthGapThreshold = 160;
  const devtoolsHeightGapThreshold = 140;
  const devtoolsStrikeThreshold = 2;
  const devtoolsCheckIntervalMs = 400;
  const fallbackHalfSeconds = 30;
  const seekDeltaSeconds = 10;
  const immersiveControlsDelayMs = 1800;
  const youtubeStatePlaying = 1;

  function ensureValidHalfSeconds(nextValue) {
    return Math.max(5, parseInt(nextValue || videoState.halfSeconds || fallbackHalfSeconds, 10));
  }

  function updateNotice(text, isError) {
    if (!noticeEl) return;
    noticeEl.textContent = text;
    noticeEl.style.borderColor = isError ? 'rgba(207,42,55,.35)' : 'rgba(44,123,229,.35)';
    noticeEl.style.background = isError ? 'rgba(207,42,55,.08)' : 'rgba(44,123,229,.08)';
  }

  function renderPlaceholder(message) {
    if (!surface) return;
    surface.innerHTML = '<div class="acc-playerPlaceholder">' + message + '</div>';
    if (playerStage && playerStage.classList) playerStage.classList.remove('acc-playerStage--platformControls');
    if (platformControls) platformControls.hidden = true;
  }

  function mountPlayerHtml(html) {
    if (!surface) return Promise.resolve();

    surface.innerHTML = '';
    if (!html) return Promise.resolve();

    var host = document.createElement('div');
    host.className = 'acc-playerEmbedHost';
    host.innerHTML = html;
    surface.appendChild(host);

    var scripts = Array.prototype.slice.call(host.querySelectorAll('script'));
    return scripts.reduce(function(chain, oldScript){
      return chain.then(function(){
        return new Promise(function(resolve){
          if (!oldScript.parentNode) {
            resolve();
            return;
          }

          var newScript = document.createElement('script');
          Array.prototype.slice.call(oldScript.attributes).forEach(function(attr){
            var attrName = String(attr.name || '').toLowerCase();
            if (
              attrName === 'src' ||
              attrName === 'type' ||
              attrName === 'async' ||
              attrName === 'defer' ||
              attrName === 'id' ||
              attrName.indexOf('data-') === 0
            ) {
              newScript.setAttribute(attr.name, attr.value);
            }
          });

          if (newScript.src) {
            newScript.async = false;
            newScript.onload = resolve;
            newScript.onerror = resolve;
          } else {
            newScript.text = oldScript.text || oldScript.textContent || '';
            resolve();
          }

          oldScript.parentNode.replaceChild(newScript, oldScript);
        });
      });
    }, Promise.resolve());
  }

  function setPlatformControlsEnabled(enabled) {
    [ctrlPlayPauseBtn, ctrlSeekBackBtn, ctrlSeekForwardBtn, ctrlFullscreenBtn, ctrlTimelineInput, ctrlVolumeInput, ctrlQualitySelect, ctrlSpeedSelect].forEach(function(el){
      if (el) el.disabled = !enabled;
    });
  }

  function setPlayPauseLabel(isPlaying) {
    if (!ctrlPlayPauseBtn) return;
    ctrlPlayPauseBtn.textContent = isPlaying ? 'إيقاف ⏸️' : 'تشغيل ▶️';
  }

  function setPlatformControlsVisible(visible) {
    if (platformControls) platformControls.hidden = !visible;
    if (playerStage && playerStage.classList) {
      if (visible) playerStage.classList.add('acc-playerStage--platformControls');
      else playerStage.classList.remove('acc-playerStage--platformControls');
      if (!visible) {
        playerStage.classList.remove('acc-playerStage--controlsVisible');
      }
    }
    if (visible) toggleImmersiveControlsVisibility(true);
    else clearControlsHideTimer();
  }

  function clearControlsHideTimer() {
    if (controlsHideHandle) {
      window.clearTimeout(controlsHideHandle);
      controlsHideHandle = 0;
    }
  }

  function toggleImmersiveControlsVisibility(forceVisible) {
    if (!playerStage || !platformControls || platformControls.hidden) return;
    var isFullscreen = !!document.fullscreenElement;
    if (!isFullscreen) {
      clearControlsHideTimer();
      playerStage.classList.remove('acc-playerStage--immersive');
      playerStage.classList.add('acc-playerStage--controlsVisible');
      return;
    }

    playerStage.classList.add('acc-playerStage--immersive');
    if (forceVisible) playerStage.classList.add('acc-playerStage--controlsVisible');
    clearControlsHideTimer();
    controlsHideHandle = window.setTimeout(function(){
      if (!document.fullscreenElement || !playerStage) return;
      playerStage.classList.remove('acc-playerStage--controlsVisible');
    }, immersiveControlsDelayMs);
  }

  function formatClock(seconds) {
    var totalSeconds = Math.max(0, Math.floor(parseFloat(seconds) || 0));
    var hours = Math.floor(totalSeconds / 3600);
    var minutes = Math.floor((totalSeconds % 3600) / 60);
    var secs = totalSeconds % 60;
    var zeroPad = function(n){ return n < 10 ? '0' + n : String(n); };
    if (hours > 0) return hours + ':' + zeroPad(minutes) + ':' + zeroPad(secs);
    return zeroPad(minutes) + ':' + zeroPad(secs);
  }

  function stopYoutubeTimeTicker() {
    if (youtubeTimeHandle) {
      window.clearInterval(youtubeTimeHandle);
      youtubeTimeHandle = 0;
    }
  }

  function refreshYoutubeTimeControl(force) {
    var current = 0;
    var duration = 0;
    if (youtubePlayer) {
      try { current = youtubePlayer.getCurrentTime() || 0; } catch(e) {}
      try { duration = youtubePlayer.getDuration() || 0; } catch(e) {}
    }

    if (!isFinite(current)) current = 0;
    if (!isFinite(duration) || duration < 0) duration = 0;
    current = Math.max(0, current);
    if (duration > 0) current = Math.min(current, duration);
    maxReachedSeconds = Math.max(maxReachedSeconds, current);

    if (!ctrlTimelineInput || !ctrlTimeLabel) return;

    ctrlTimelineInput.max = String(Math.max(0, Math.floor(duration)));
    if (!timelineDragging || force) {
      ctrlTimelineInput.value = String(Math.floor(current));
    }

    var displayedCurrent = force ? Math.floor(ctrlTimelineInput.value || 0) : Math.floor(current);
    if (!isFinite(displayedCurrent)) displayedCurrent = 0;
    if (duration > 0) displayedCurrent = Math.min(displayedCurrent, Math.floor(duration));
    ctrlTimeLabel.textContent = formatClock(displayedCurrent) + ' / ' + formatClock(duration);
    ctrlTimelineInput.setAttribute('aria-valuetext', ctrlTimeLabel.textContent);
  }

  function loadYoutubeApi() {
    if (window.YT && window.YT.Player) return Promise.resolve();
    if (youtubeApiReadyPromise) return youtubeApiReadyPromise;

    youtubeApiReadyPromise = new Promise(function(resolve){
      var previous = window.onYouTubeIframeAPIReady;
      window.onYouTubeIframeAPIReady = function(){
        if (typeof previous === 'function') previous();
        resolve();
      };

      var exists = document.querySelector('script[src*="youtube.com/iframe_api"]');
      if (exists) return;
      var script = document.createElement('script');
      script.src = 'https://www.youtube.com/iframe_api';
      script.async = true;
      document.head.appendChild(script);
    });

    return youtubeApiReadyPromise;
  }

  function refreshYoutubeQualityOptions() {
    if (!youtubePlayer || !ctrlQualitySelect) return;
    var levels = [];
    try { levels = youtubePlayer.getAvailableQualityLevels() || []; } catch(e) {}

    ctrlQualitySelect.innerHTML = '<option value="auto">تلقائي</option>';
    levels.forEach(function(level){
      var opt = document.createElement('option');
      opt.value = level;
      opt.textContent = level;
      ctrlQualitySelect.appendChild(opt);
    });

    var current = '';
    try { current = youtubePlayer.getPlaybackQuality() || 'auto'; } catch(e) {}
    if (current !== '' && ctrlQualitySelect.querySelector('option[value="' + current + '"]')) {
      ctrlQualitySelect.value = current;
    } else {
      ctrlQualitySelect.value = 'auto';
    }
  }

  function refreshYoutubeSpeedOptions() {
    if (!youtubePlayer || !ctrlSpeedSelect) return;
    var rates = [];
    try { rates = youtubePlayer.getAvailablePlaybackRates() || [1]; } catch(e) { rates = [1]; }

    ctrlSpeedSelect.innerHTML = '';
    rates.forEach(function(rate){
      var val = String(rate);
      var opt = document.createElement('option');
      opt.value = val;
      opt.textContent = val + 'x';
      ctrlSpeedSelect.appendChild(opt);
    });

    var currentRate = 1;
    try { currentRate = youtubePlayer.getPlaybackRate() || 1; } catch(e) {}
    ctrlSpeedSelect.value = String(currentRate);
  }

  function refreshYoutubeVolumeControl() {
    if (!youtubePlayer || !ctrlVolumeInput) return;
    var currentVolume = 100;
    var isMuted = false;
    try { currentVolume = youtubePlayer.getVolume(); } catch(e) {}
    try { isMuted = !!youtubePlayer.isMuted(); } catch(e) {}
    if (!isFinite(currentVolume)) currentVolume = 100;
    var normalizedVolume = isMuted ? 0 : Math.max(0, Math.min(100, Math.round(currentVolume)));
    ctrlVolumeInput.value = String(normalizedVolume);
    ctrlVolumeInput.setAttribute('aria-valuetext', normalizedVolume + '%');
  }

  function initYoutubePlatformControls(frame) {
    if (!frame) {
      setPlatformControlsVisible(false);
      setPlatformControlsEnabled(false);
      return;
    }

    frame.id = frame.id || 'lectureVideoFrame';
    loadYoutubeApi().then(function(){
      if (!window.YT || !window.YT.Player) return;
      youtubePlayer = new window.YT.Player(frame.id, {
        events: {
          onReady: function(){
            setPlatformControlsVisible(true);
            setPlatformControlsEnabled(true);
            setPlayPauseLabel(false);
            refreshYoutubeQualityOptions();
            refreshYoutubeSpeedOptions();
            refreshYoutubeVolumeControl();
            refreshYoutubeTimeControl(false);
            stopYoutubeTimeTicker();
            youtubeTimeHandle = window.setInterval(function(){
              refreshYoutubeTimeControl(false);
            }, 500);
          },
          onStateChange: function(event){
            setPlayPauseLabel(event && event.data === youtubeStatePlaying);
            refreshYoutubeTimeControl(false);
          }
        }
      });
    }).catch(function(){
      setPlatformControlsVisible(false);
      setPlatformControlsEnabled(false);
      stopYoutubeTimeTicker();
    });
  }

  function syncStats(stats) {
    if (!stats) return;
    videoState.viewsAllowed = parseInt(stats.allowed || videoState.viewsAllowed || 1, 10);
    videoState.viewsUsed = parseInt(stats.used || 0, 10);
    videoState.viewsRemaining = parseInt(stats.remaining || 0, 10);
    videoState.isBlocked = videoState.viewsRemaining <= 0;

    if (viewsAllowedEl) viewsAllowedEl.textContent = videoState.viewsAllowed;
    if (viewsUsedEl) viewsUsedEl.textContent = videoState.viewsUsed;
    if (viewsRemainingEl) viewsRemainingEl.textContent = videoState.viewsRemaining;

    if (videoState.isBlocked && fullscreenBtn && !document.fullscreenElement) fullscreenBtn.disabled = true;
  }

  function stopProgressTimers() {
    if (heartbeatHandle) {
      window.clearInterval(heartbeatHandle);
      heartbeatHandle = 0;
    }
    if (progressHandle) {
      window.clearInterval(progressHandle);
      progressHandle = 0;
    }
    requestInFlight = false;
  }

  function currentWatchedSeconds() {
    if (!progressBaseStartedAt) return progressBaseSeconds;
    return progressBaseSeconds + Math.max(0, Math.floor((Date.now() - progressBaseStartedAt) / 1000));
  }

  function resetProgress(seconds) {
    progressBaseSeconds = Math.max(0, parseInt(seconds || 0, 10));
    progressBaseStartedAt = Date.now();
  }

  function startNoticeTicker() {
    if (progressHandle) window.clearInterval(progressHandle);
    progressHandle = window.setInterval(function(){
      if (!activeWatchToken || countedToken === activeWatchToken) return;
      var remaining = Math.max(0, videoState.halfSeconds - currentWatchedSeconds());
      if (remaining <= 0) {
        window.clearInterval(progressHandle);
        progressHandle = 0;
      }
    }, 1000);
  }

  function sendProgress(action) {
    if (!activeWatchToken || requestInFlight || protectedPageClosed) return;

    requestInFlight = true;
    var body = new URLSearchParams();
    body.set('action', action || 'heartbeat');
    body.set('video_id', videoId);
    body.set('watch_token', activeWatchToken);

    fetch('api/lecture_video_api.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
      body: body.toString()
    }).then(function(res){
      return res.json();
    }).then(function(data){
      requestInFlight = false;
      if (!data) return;

      if (data.stats) syncStats(data.stats);
      if (typeof data.half_seconds !== 'undefined') {
        videoState.halfSeconds = ensureValidHalfSeconds(data.half_seconds);
        if (halfSecondsEl) halfSecondsEl.textContent = videoState.halfSeconds;
      }
      if (typeof data.watched_seconds !== 'undefined') {
        resetProgress(parseInt(data.watched_seconds || 0, 10));
      }

      if (data.counted) {
        countedToken = activeWatchToken;
        stopProgressTimers();
        updateNotice('✅ ' + (data.message || 'تم احتساب مشاهدة الفيديو بنجاح.'), false);
        return;
      }

      if (data.ok === false && action !== 'heartbeat') {
        updateNotice('⛔ ' + (data.message || 'لم يكتمل زمن المشاهدة المطلوب بعد.'), true);
        return;
      }
    }).catch(function(){
      requestInFlight = false;
    });
  }

  function startBackgroundTracking(initialWatchedSeconds) {
    stopProgressTimers();
    resetProgress(initialWatchedSeconds);
    startNoticeTicker();
    heartbeatHandle = window.setInterval(function(){
      sendProgress('heartbeat');
    }, 10000);
  }

  function startPlayback() {
    if (videoState.isBlocked) {
      updateNotice('⛔ انتهت عدد المشاهدات المسموحة لهذا الفيديو.', true);
      return;
    }
    if (startRequestInFlight || playbackBootstrapped) {
      return;
    }
    startRequestInFlight = true;

    renderPlaceholder('⏳ جاري تجهيز الفيديو داخل مشغل المنصة...');

    var body = new URLSearchParams();
    body.set('action', 'start');
    body.set('video_id', videoId);

    fetch('api/lecture_video_api.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
      body: body.toString()
    }).then(function(res){
      return res.json();
    }).then(function(data){
      startRequestInFlight = false;

      if (!data || !data.ok) {
        renderPlaceholder('❌ تعذر تشغيل الفيديو داخل بلاير المنصة.');
        if (data && data.stats) syncStats(data.stats);
        updateNotice('⛔ ' + ((data && data.message) || 'تعذر تشغيل الفيديو داخل بلاير المنصة.'), true);
        return;
      }

      activeWatchToken = data.watch_token || '';
      countedToken = '';
      maxReachedSeconds = Math.max(0, parseInt(data.watched_seconds || 0, 10));
      playbackBootstrapped = true;
      if (typeof data.half_seconds !== 'undefined') {
        videoState.halfSeconds = ensureValidHalfSeconds(data.half_seconds);
        if (halfSecondsEl) halfSecondsEl.textContent = videoState.halfSeconds;
      }
      if (data.video && typeof data.video.video_type !== 'undefined') {
        videoState.videoType = String(data.video.video_type || '');
      }

      mountPlayerHtml(data.player_html || '').then(function(){
        if (data.stats) syncStats(data.stats);
        var mountedFrame = surface ? surface.querySelector('iframe') : null;
        var isYoutube = mountedFrame && /youtube(?:-nocookie)?\.com/i.test(String(mountedFrame.src || ''));
        if (isYoutube || String(videoState.videoType || '').toLowerCase() === 'youtube') {
          initYoutubePlatformControls(mountedFrame);
        } else {
          youtubePlayer = null;
          stopYoutubeTimeTicker();
          setPlatformControlsVisible(false);
          setPlatformControlsEnabled(false);
        }
        startBackgroundTracking(parseInt(data.watched_seconds || 0, 10));
        updateNotice('✅ تم تجهيز الفيديو. شغّل الفيديو من زر التشغيل داخل المشغل.', false);
      });
    }).catch(function(){
      startRequestInFlight = false;
      renderPlaceholder('❌ حدث خطأ أثناء الاتصال بالسيرفر.');
      updateNotice('❌ حدث خطأ أثناء تجهيز المشغل.', true);
    });
  }

  function closeProtectedPage(reason) {
    if (protectedPageClosed) return;
    protectedPageClosed = true;
    stopProgressTimers();
    updateNotice(reason, true);
    renderPlaceholder(reason);
    if (fullscreenBtn) fullscreenBtn.disabled = true;

    if (document.fullscreenElement && document.exitFullscreen) {
      document.exitFullscreen().catch(function(){});
    }

    window.location.replace('account_lecture.php?lecture_id=' + lectureId);
  }

  if (fullscreenBtn && playerStage) {
    var toggleFullscreen = function(){
      if (document.fullscreenElement) {
        if (document.exitFullscreen) document.exitFullscreen();
        return;
      }
      if (playerStage.requestFullscreen) playerStage.requestFullscreen();
    };

    fullscreenBtn.addEventListener('click', toggleFullscreen);
    if (ctrlFullscreenBtn) ctrlFullscreenBtn.addEventListener('click', toggleFullscreen);

    document.addEventListener('fullscreenchange', function(){
      fullscreenBtn.textContent = document.fullscreenElement ? 'إغلاق التكبير 🡼' : 'تكبير ⛶';
      toggleImmersiveControlsVisibility(true);
    });
  }

  if (playerStage) {
    ['mousemove', 'touchstart', 'touchmove', 'pointerdown'].forEach(function(evt){
      playerStage.addEventListener(evt, function(){
        toggleImmersiveControlsVisibility(true);
      }, {passive:true});
    });
  }

  if (ctrlPlayPauseBtn) {
    ctrlPlayPauseBtn.addEventListener('click', function(){
      if (!youtubePlayer) return;
      var state = -1;
      try { state = youtubePlayer.getPlayerState(); } catch(e) {}
      if (state === youtubeStatePlaying) {
        youtubePlayer.pauseVideo();
      } else {
        youtubePlayer.playVideo();
      }
    });
  }

  function seekYoutubeBy(deltaSeconds) {
    if (!youtubePlayer) return;
    var duration = 0;
    var current = 0;
    try { duration = youtubePlayer.getDuration() || 0; } catch(e) {}
    try { current = youtubePlayer.getCurrentTime() || 0; } catch(e) {}
    if (!isFinite(duration) || duration < 0) duration = 0;
    if (!isFinite(current) || current < 0) current = 0;
    var nextTime = current + (parseFloat(deltaSeconds) || 0);
    if (duration > 0) nextTime = Math.min(nextTime, duration);
    nextTime = Math.max(0, nextTime);
    youtubePlayer.seekTo(nextTime, true);
    refreshYoutubeTimeControl(true);
  }

  if (ctrlSeekBackBtn) {
    ctrlSeekBackBtn.addEventListener('click', function(){
      seekYoutubeBy(-seekDeltaSeconds);
    });
  }

  if (ctrlSeekForwardBtn) {
    ctrlSeekForwardBtn.addEventListener('click', function(){
      seekYoutubeBy(seekDeltaSeconds);
    });
  }

  if (ctrlQualitySelect) {
    ctrlQualitySelect.addEventListener('change', function(){
      if (!youtubePlayer) return;
      var nextQuality = String(ctrlQualitySelect.value || 'auto');
      if (nextQuality === 'auto') {
        return;
      }
      youtubePlayer.setPlaybackQuality(nextQuality);
    });
  }

  if (ctrlSpeedSelect) {
    ctrlSpeedSelect.addEventListener('change', function(){
      if (!youtubePlayer) return;
      var nextRate = parseFloat(ctrlSpeedSelect.value || '1');
      if (!isFinite(nextRate) || nextRate <= 0) nextRate = 1;
      youtubePlayer.setPlaybackRate(nextRate);
    });
  }

  if (ctrlVolumeInput) {
    ctrlVolumeInput.addEventListener('input', function(){
      if (!youtubePlayer) return;
      var nextVolume = parseInt(ctrlVolumeInput.value || '100', 10);
      if (!isFinite(nextVolume)) nextVolume = 100;
      nextVolume = Math.max(0, Math.min(100, nextVolume));
      ctrlVolumeInput.setAttribute('aria-valuetext', nextVolume + '%');
      if (nextVolume === 0) {
        youtubePlayer.mute();
      } else {
        youtubePlayer.unMute();
        youtubePlayer.setVolume(nextVolume);
      }
    });
  }

  if (ctrlTimelineInput) {
    ctrlTimelineInput.addEventListener('input', function(){
      if (!youtubePlayer) return;
      timelineDragging = true;
      var nextTime = parseInt(ctrlTimelineInput.value || '0', 10);
      if (!isFinite(nextTime)) nextTime = 0;
      var duration = 0;
      try { duration = youtubePlayer.getDuration() || 0; } catch(e) {}
      if (isFinite(duration) && duration > 0) nextTime = Math.min(nextTime, Math.floor(duration));
      nextTime = Math.max(0, nextTime);
      youtubePlayer.seekTo(nextTime, true);
      refreshYoutubeTimeControl(true);
    });

    var endTimelineInteraction = function(){
      timelineDragging = false;
      refreshYoutubeTimeControl(false);
    };
    ctrlTimelineInput.addEventListener('change', endTimelineInteraction);
    ctrlTimelineInput.addEventListener('mouseup', endTimelineInteraction);
    ctrlTimelineInput.addEventListener('touchend', endTimelineInteraction);
    ctrlTimelineInput.addEventListener('keyup', function(e){
      var key = String(e.key || '').toLowerCase();
      if (key === 'arrowleft' || key === 'arrowright' || key === 'home' || key === 'end') {
        endTimelineInteraction();
      }
    });
  }

  document.addEventListener('keydown', function(e){
    if (!youtubePlayer || !platformControls || platformControls.hidden) return;
    var target = e.target;
    if (target && target.tagName) {
      var tagName = String(target.tagName).toUpperCase();
      if (tagName === 'INPUT' || tagName === 'TEXTAREA' || tagName === 'SELECT') return;
    }

    var key = String(e.key || '').toLowerCase();
    if (key === ' ' || key === 'k') {
      e.preventDefault();
      if (ctrlPlayPauseBtn) ctrlPlayPauseBtn.click();
      return;
    }
    if (key === 'f') {
      e.preventDefault();
      if (ctrlFullscreenBtn) ctrlFullscreenBtn.click();
      return;
    }
    if (key === 'arrowleft' || key === 'j') {
      e.preventDefault();
      seekYoutubeBy(-seekDeltaSeconds);
      return;
    }
    if (key === 'arrowright' || key === 'l') {
      e.preventDefault();
      seekYoutubeBy(seekDeltaSeconds);
    }
  });

  if (!videoState.isBlocked) {
    startPlayback();
  }

  document.addEventListener('contextmenu', function(e){ e.preventDefault(); });
  document.addEventListener('mousedown', function(e){ if (e.button === 2) e.preventDefault(); }, true);
  document.addEventListener('keydown', function(e){
    var key = String(e.key || '').toLowerCase();
    var blocked =
      key === 'f12' ||
      (e.ctrlKey && e.shiftKey && (key === 'i' || key === 'j' || key === 'c')) ||
      (e.ctrlKey && key === 'u');
    if (blocked) {
      e.preventDefault();
      closeProtectedPage('⛔ تم الرجوع إلى صفحة تفاصيل المحاضرة لحماية المحتوى عند محاولة فتح أدوات المطور.');
    }
  }, true);

  document.addEventListener('keyup', function(e){
    var key = String(e.key || '').toLowerCase();
    if (key === 'printscreen') {
      closeProtectedPage('⛔ تم إيقاف المشاهدة عند محاولة لقطة شاشة لحماية محتوى الفيديو.');
    }
  }, true);

  document.addEventListener('visibilitychange', function(){
    if (document.visibilityState === 'hidden') {
      sendProgress('heartbeat');
    }
  });

  window.addEventListener('beforeunload', function(){
    if (!activeWatchToken || countedToken === activeWatchToken) return;
    var body = new URLSearchParams();
    body.set('action', 'complete');
    body.set('video_id', videoId);
    body.set('watch_token', activeWatchToken);
    if (navigator.sendBeacon) {
      navigator.sendBeacon('api/lecture_video_api.php', new Blob([body.toString()], {type: 'application/x-www-form-urlencoded; charset=UTF-8'}));
    }
  });

  window.setInterval(function(){
    if (protectedPageClosed) return;
    if (document.hidden) {
      devtoolsDetectionStrikes = 0;
      return;
    }

    var widthGap = Math.abs(window.outerWidth - window.innerWidth);
    var heightGap = Math.abs(window.outerHeight - window.innerHeight);
    var devtoolsOpen =
      widthGap > devtoolsWidthGapThreshold ||
      heightGap > devtoolsHeightGapThreshold;

    if (devtoolsOpen) {
      devtoolsDetectionStrikes++;
    } else {
      devtoolsDetectionStrikes = 0;
    }

    if (devtoolsDetectionStrikes >= devtoolsStrikeThreshold) {
      closeProtectedPage('⛔ تم اكتشاف فتح أدوات المطور، وتم الرجوع إلى صفحة تفاصيل المحاضرة لحماية الفيديو.');
    }
  }, devtoolsCheckIntervalMs);
})();
</script>
</body>
</html>
