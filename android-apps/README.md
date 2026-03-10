# Android WebView Apps

تم إضافة مشروعين Android Studio جاهزين لاستخراج ملفي APK:

- `./android-admin`
- `./android-student`

## قبل استخراج APK

### 1) تعديل اسم التطبيق والرابط

اسم التطبيق يتم تعديله من:

- `android-admin/app/src/main/res/values/strings.xml`
- `android-student/app/src/main/res/values/strings.xml`

أما الرابط وباقي بيانات الإصدار فتُعدَّل من ملف `gradle.properties` داخل كل مشروع:

- `APP_START_URL`
- `APP_ID`
- `VERSION_CODE`
- `VERSION_NAME`

### 2) تعديل اللوجو/الأيقونة

استبدل الملفين التاليين بالصورة التي تريدها مع الاحتفاظ بنفس الاسم:

- `android-admin/app/src/main/res/drawable/admin_app_logo.png`
- `android-student/app/src/main/res/drawable/student_app_logo.jpg`

### 3) في حال كان موقعك يعمل بدون HTTPS

القيمة الافتراضية آمنة وهي:

- `USES_CLEARTEXT_TRAFFIC=false`

إذا كان السيرفر يعمل على `http://` فقط، يمكنك تغييرها إلى:

- `USES_CLEARTEXT_TRAFFIC=true`

## المزايا المضافة

- كل مشروع عبارة عن Android WebView مستقل.
- تطبيق الطالب يحتوي على `FLAG_SECURE` لمنع screenshot وscreen recording داخل التطبيق.
- تم تفعيل JavaScript وDOM Storage ودعم اختيار الملفات داخل WebView.

## بناء APK من Android Studio

1. افتح `android-admin` أو `android-student` في Android Studio.
2. انتظر حتى يتم عمل Gradle Sync.
3. من القائمة:
   - `Build > Build Bundle(s) / APK(s) > Build APK(s)`
4. سيظهر مسار ملف APK بعد انتهاء البناء.
