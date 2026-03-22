# 🚗 Couvoit API
### منصة مشاركة الركوب — Laravel 12

<p align="left">
  🌐 <a href="README.md">Français</a> | <a href="README.en.md">English</a> | <strong>العربية</strong>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-12-red?style=for-the-badge&logo=laravel" />
  <img src="https://img.shields.io/badge/PHP-8.2+-blue?style=for-the-badge&logo=php" />
  <img src="https://img.shields.io/badge/PostgreSQL-DB-blue?style=for-the-badge&logo=postgresql" />
  <img src="https://img.shields.io/badge/Auth-JWT-black?style=for-the-badge&logo=jsonwebtokens" />
  <img src="https://img.shields.io/badge/Tests-PHPUnit_11-purple?style=for-the-badge&logo=php" />
  <img src="https://img.shields.io/badge/Architecture-SOLID-black?style=for-the-badge" />
  <img src="https://img.shields.io/badge/Realtime-Reverb-orange?style=for-the-badge" />
</p>

واجهة برمجية REST **موجّهة للإنتاج** لمنصة مشاركة الركوب، مبنية بـ **Laravel 12** و**PHP 8.2+** و**PostgreSQL** ونظام **JWT محلي مخصص**.

يطبّق المشروع **معمارية طبقية نظيفة** مع فصل صارم للمسؤوليات، وسياسات تفويض، ومراسلة فورية عبر Reverb، وتخزين مؤقت منظم، وبريد إلكتروني تعاملي عبر Resend، وتغطية اختبارات شاملة.

> ⚠️ **ملاحظة مهمة**: لا يستخدم هذا التطبيق البادئة الافتراضية `/api` في Laravel. تُكشف نقاط النهاية مباشرةً: `/auth/login` و`/trips` و`/cars` إلخ.

---

<div dir="rtl">

# 📚 جدول المحتويات

- [🏗 المعمارية](#-المعمارية)
- [🧱 التقنيات المستخدمة](#-التقنيات-المستخدمة)
- [📦 النموذج التجاري](#-النموذج-التجاري)
- [🔐 المصادقة](#-المصادقة)
- [💬 المراسلة الفورية](#-المراسلة-الفورية)
- [📧 البريد الإلكتروني التعاملي](#-البريد-الإلكتروني-التعاملي)
- [🚀 التثبيت](#-التثبيت)
- [⚙ الإعداد](#-الإعداد)
- [📖 توثيق الواجهة البرمجية](#-توثيق-الواجهة-البرمجية)
- [🧪 الاختبارات والجودة](#-الاختبارات-والجودة)
- [📌 التخزين المؤقت](#-التخزين-المؤقت)
- [🛡 التفويض](#-التفويض)
- [📍 إنشاء رحلة](#-إنشاء-رحلة)
- [🔄 المهام المجدولة](#-المهام-المجدولة)
- [🌍 النشر](#-النشر)
- [📁 هيكل المشروع](#-هيكل-المشروع)
- [📊 نقاط النهاية](#-نقاط-النهاية)
- [🔄 خارطة الطريق](#-خارطة-الطريق)
- [🔧 استكشاف الأخطاء](#-استكشاف-الأخطاء)
- [👤 المؤلف](#-المؤلف)

---

# 🏗 المعمارية

معمارية طبقية مفصولة بشكل صارم:

```
HTTP
└── Controllers (المتحكمات)
    └── Requests (التحقق)
    └── Resources (التحويل)

Application (التطبيق)
└── Services (تنسيق الأعمال)
    ├── DTOs (تطبيع المدخلات)
    └── Resolvers (حل المراجع)

Domain (النطاق)
└── Models (Eloquent)
└── Policies (التفويض)

Infrastructure (البنية التحتية)
├── Repositories (الواجهات + تطبيقات Eloquent)
├── Support/Cache (RepositoryCacheManager)
├── Security (مُصدر JWT)
└── Clients (OpenRouteService)
```

تُسجَّل روابط Service/Repository في `AppServiceProvider` و`RepositoryProvider`.

### 🔎 المبادئ المطبّقة

- فصل صارم للمسؤوليات (Single Responsibility)
- نمط Repository (واجهات + تطبيقات Eloquent)
- DTOs للتحقق من المدخلات وتطبيعها
- التفويض عبر Policies (مع تجاوز للمسؤول)
- تخزين مؤقت بعلامات (read-through / write-through)
- بث فوري عبر Laravel Reverb
- بريد إلكتروني تعاملي عبر Resend
- توثيق OpenAPI (Swagger / l5-swagger)
- اختبارات وحدة وميزة متينة (PHPUnit 11)
- CI/CD عبر GitHub Actions + SonarQube

---

# 🧱 التقنيات المستخدمة

| التقنية | الاستخدام |
|---------|-----------|
| Laravel 12 | الإطار الرئيسي |
| PHP 8.2+ | اللغة (CI/Docker يستهدف PHP 8.5) |
| PostgreSQL | قاعدة البيانات العلائقية (الإنتاج) |
| SQLite | قاعدة البيانات للاختبارات/CI |
| firebase/php-jwt | إصدار JWT والتحقق منه محلياً |
| laravel/reverb | بث WebSocket الفوري |
| pusher/pusher-php-server | متوافق مع Reverb |
| PHPUnit 11 | اختبارات الوحدة والميزة |
| darkaonline/l5-swagger | توثيق OpenAPI |
| resend/resend-laravel | البريد الإلكتروني التعاملي |
| predis/predis | عميل Redis |
| OpenRouteService | الترميز الجغرافي وحساب المسافة/المدة |
| Laravel Pint | أسلوب الكود |
| SonarQube | جودة الكود (CI) |

---

# 📦 النموذج التجاري

## الكيانات الرئيسية

- **User** — هوية المصادقة
- **Person** — ملف التعريف المرتبط عبر `users.person_id`
- **Car** — المركبة المملوكة لشخص
- **Trip** — رحلة ينشرها سائق
- **Reservation** — حجز راكب لرحلة
- **Conversation / ConversationMessage** — مراسلة خاصة مرتبطة بالرحلات
- **Brand / CarModel / Type / Color** — بيانات كتالوج المركبة
- **Address / City** — البيانات الجغرافية
- **Role** — دور المستخدم (`admin` / `user`)

## قواعد العمل الرئيسية

### 👤 Person / User
- يُنشئ التسجيل `Person` و`User` في آنٍ واحد
- يملك مركبة واحدة (اختياري) عبر `persons.car_id`
- يملك دوراً (`admin` أو `user`)
- يمكن تعطيله (`is_active = false`) — يحجب الوصول عبر الوسيط
- يمكن استعادة الحسابات المحذوفة (soft delete) عند تسجيل الدخول خلال **90 يوماً**
- بعد 90 يوماً: إخفاء الهوية تلقائياً بواسطة أمر `accounts:purge-deleted`

### 🚗 Trip
- `available_seats > 0`
- `distance_km > 0`
- لا يمكن إلغاؤها بعد أن تبدأ
- تدفق الإنشاء: ترميز جغرافي ORS ← حساب المسافة/المدة ← الحفظ + `arrival_time` المشتق

### 📌 Reservation
- مفتاح أساسي مركّب (`person_id + trip_id`)
- لا يستطيع السائق حجز رحلته الخاصة
- لا حجوزات مكررة
- لا حجز زائد (يتم التحقق من المقاعد المتاحة)
- مستحيل على رحلة بدأت بالفعل

### 💬 Conversation
- خيوط نقاش ثنائية حول رحلة
- يمكن للسائق التواصل مع راكب في رحلته
- يمكن للراكب التواصل مع سائق الرحلة

---

# 🔐 المصادقة (JWT المحلي)

تستخدم الواجهة البرمجية نظام مصادقة محلياً مبنياً على JWT (HS256). لا تعتمد على أي خدمة خارجية: توليد الرموز والتحقق منها وتدويرها تتم بالكامل من جانب الخادم.

## 🧩 معمارية المصادقة

| الرمز | المدة | الاستخدام |
|-------|-------|-----------|
| `access_token` | قصير الأمد (مثلاً: ساعة) | الوصول إلى المسارات المحمية |
| `refresh_token` | طويل الأمد (مثلاً: 30 يوماً) | تجديد JWT |

## 🔄 تدفق المصادقة

### 1️⃣ يُسجّل المستخدم أو يُسجّل دخوله عبر:
```
POST /auth/register
POST /auth/login
```

### 2️⃣ الخادم:
- يتحقق من بيانات الاعتماد وحالة `is_active`
- يُشفّر كلمة المرور (bcrypt)
- يُولّد `access_token` JWT موقّع بـ HS256
- يُولّد `refresh_token` عشوائياً (`random_bytes(32)`)
- يُخزّن الرمز المُجزّأ في قاعدة البيانات (`refresh_tokens`)
- يُعيد الرموز بتنسيق JSON **ويضعها** في ملفات تعريف ارتباط HTTP-only آمنة

### 3️⃣ يُرسل العميل JWT عبر:
```
Authorization: Bearer <access_token>
```
أو عبر ملف تعريف الارتباط `access_token` (HTTP-only).

### 4️⃣ الوسيط `jwt` (`LocalJwtAuth`):
- يتحقق من التوقيع (HS256)
- يتحقق من المطالبات `iss` و`aud` و`exp`
- يحلّ `sub` → `User`
- يُخزّن مؤقتاً تعيين الرمز إلى المستخدم (TTL مُوافق لانتهاء صلاحية الرمز)
- يُحمّل `auth()->user()`

---

# 🧾 هيكل JWT

```json
{
  "iss": "couvoit-api",
  "aud": "couvoit-client",
  "iat": 1700000000,
  "exp": 1700000900,
  "sub": "12",
  "role_id": 1,
  "user_id": 1,
  "jti": "a1b2c3d4e5f6..."
}
```

## 🔎 المطالبات المستخدمة

| المطالبة | الوصف |
|----------|-------|
| `iss` | المُصدِر |
| `aud` | الجمهور المستهدف |
| `sub` | معرّف المستخدم الداخلي |
| `exp` | تاريخ الانتهاء |
| `role_id` | دور المستخدم |
| `jti` | معرّف الرمز الفريد |

---

# 🔁 رمز التحديث (التدوير الآمن)

## رمز التحديث:
- يُولَّد عبر `random_bytes(32)`
- تُعاد القيمة الخام فقط إلى العميل
- يُخزَّن **مُجزَّأً** في قاعدة البيانات (`refresh_tokens`) مع تاريخ انتهاء
- يُلغى عند كل تدوير

## 🔄 نقطة النهاية
```
POST /auth/refresh
```

## العملية:
1. التحقق من رمز التحديث المقدَّم
2. إلغاء الرمز المُستخدَم
3. توليد زوج جديد من `access_token` + `refresh_token`

### تحمي هذه الاستراتيجية من:
- سرقة الرمز
- هجمات إعادة التشغيل
- إعادة الاستخدام بعد الاختراق

## 🔒 تسجيل الخروج

```
POST /auth/logout
```
يحذف **جميع** رموز التحديث للمستخدم المصادَق عليه.

---

# 🔒 الأمان

- تشفير كلمة المرور عبر **bcrypt**
- JWT موقَّع بـ **HS256** بسرّ طويل (≥ 32 بايت)
- تدوير رمز التحديث عند كل تجديد
- دعم الإلغاء الكامل (تسجيل الخروج)
- المستخدمون غير النشطين (`is_active = false`) محجوبون بواسطة الوسيط
- وسيط JWT مركزي (`LocalJwtAuth`)
- ملف تعريف ارتباط HTTP-only مع إعدادات `Secure` و`SameSite` قابلة للتكوين

---

# ⚙ الإعداد `.env`

```env
# JWT
JWT_SECRET=base64:...
JWT_ACCESS_TTL=3600
JWT_REFRESH_TTL=2592000
JWT_ISSUER=couvoit-api
JWT_AUDIENCE=couvoit-client

# ملفات تعريف ارتباط المصادقة
AUTH_COOKIE_PATH=/
AUTH_COOKIE_DOMAIN=null
AUTH_COOKIE_SECURE=false
AUTH_COOKIE_SAMESITE=lax
```

---

# 💬 المراسلة الفورية

تُعالَج المراسلة الفورية عبر **Laravel Reverb** (متوافق مع Pusher).

## المعمارية

- البث مُكوَّن في `bootstrap/app.php`
- تفويض القنوات الخاصة في `routes/channels.php`
- الحدث: `App\Events\ChatMessageSent` (يُنفّذ `ShouldBroadcastNow`)
- مصادقة البث مكشوفة عبر `POST /broadcasting/auth-proxy`

## القنوات الخاصة

```
chat.user.{personId}
chat.conversation.{conversationId}
```

## نقاط نهاية المراسلة

| الطريقة | نقطة النهاية | الوصف |
|---------|-------------|-------|
| GET | `/conversations` | قائمة المحادثات |
| GET | `/conversations/{conversation}` | تفاصيل محادثة |
| POST | `/conversations/{conversation}/messages` | إرسال رسالة |
| POST | `/trips/{trip}/contact-driver` | التواصل مع السائق |
| POST | `/my-trips/{trip}/contact-passenger/{person}` | التواصل مع راكب |

> أسماء مستعارة `/chat/conversations...` متاحة أيضاً.

## تشغيل Reverb محلياً

```bash
php artisan reverb:start
```

---

# 📧 البريد الإلكتروني التعاملي

تُرسَل الرسائل الإلكترونية عبر **Resend** مع قوالب قابلة للتكوين.

## الأحداث المغطاة

- إعادة تعيين كلمة المرور
- إنشاء حجز (للراكب والسائق)
- إلغاء حجز (للراكب والسائق)
- إلغاء رحلة (للركاب)

> توثيق متغيرات القوالب متاح في `docs/resend-trip-templates.md`.

> ⚠️ يتم إرسال البريد الإلكتروني **بشكل متزامن** (بدون job في الطابور) بعد الحفظ في قاعدة البيانات.

---

# 🚀 التثبيت

## 1️⃣ استنساخ المشروع

```bash
git clone https://github.com/your-account/couvoit-api.git
cd couvoit-api
```

## 2️⃣ تثبيت الاعتمادات

```bash
composer install
```

## 3️⃣ إعداد البيئة

```bash
cp .env.example .env
php artisan key:generate
```

## 4️⃣ تكوين `.env`

```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:3000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=covoiturage
DB_USERNAME=postgres
DB_PASSWORD=postgres

CACHE_STORE=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

OPENROUTESERVICE_API_KEY=your_ors_key

MAIL_MAILER=resend
RESEND_API_KEY=your_resend_key
```

## 5️⃣ ترحيل قاعدة البيانات

```bash
php artisan migrate
# اختياري:
php artisan db:seed
```

## 6️⃣ تشغيل الخادم

```bash
php artisan serve
```

### الواجهة البرمجية متاحة على:
```
http://localhost:8000
```

### (اختياري) البث الفوري:
```bash
php artisan reverb:start
```

### (اختياري) عامل الطابور:
```bash
php artisan queue:listen --tries=1 --timeout=0
```

---

# 📖 توثيق الواجهة البرمجية

## توليد التوثيق:

```bash
php artisan l5-swagger:generate
```

### واجهة Swagger UI متاحة على:
```
/api/documentation
```

### المواصفة المُولَّدة على:
```
/docs
```

تُفحَص التعليقات التوضيحية من `app/Http/Controllers` و`app/Http/Requests` و`app/Swagger`.

---

# 🧪 الاختبارات والجودة

## تشغيل جميع الاختبارات:

```bash
php artisan test
# أو
composer test
```

## تشغيل اختبار محدد:

```bash
php artisan test --filter=TripServiceTest
php artisan test --filter=ChatControllerTest
```

## توليد تقرير التغطية (Clover):

```bash
php artisan test --coverage-clover=coverage.xml
```

## أسلوب الكود (Laravel Pint):

```bash
./vendor/bin/pint
```

## اتفاقيات الاختبار:

- كل طريقة تتضمن `@throws Throwable`
- استخدام `Model::query()->create()`
- تغطية كاملة: Services وPolicies وRepositories وDTOs وResources وMiddleware وControllers

## المجالات المغطاة:
- تدفقات المصادقة (التسجيل، تسجيل الدخول، التحديث، تسجيل الخروج، إعادة تعيين كلمة المرور)
- وسيط JWT
- نقاط نهاية الدردشة/المحادثات
- الخدمات والمستودعات
- DTOs والموارد
- سياسات التفويض
- سلوك نماذج Eloquent

## CI/CD (GitHub Actions)

يقوم سير العمل `.github/workflows/tests.yml` بـ:
1. تثبيت اعتمادات Composer
2. إعداد `.env` (SQLite)
3. تشغيل الترحيلات
4. تشغيل الاختبارات
5. توليد تغطية الكود
6. تشغيل مسح SonarQube (إذا كانت الأسرار مُكوَّنة)

---

# 📌 التخزين المؤقت

التخزين المؤقت جزء حقيقي من المعمارية، وليس مجرد إعداد افتراضي للإطار.

## المعمارية

- تنسيق مركزي في `app/Support/Cache/RepositoryCacheManager.php`
- علامات مُعرَّفة لـ: persons وcars وbrands وmodels وcities وcolors وtrips وreservations وtypes
- روابط نموذج المسار مُخزَّنة مؤقتاً لـ: `person` و`trip` و`brand` و`car`
- وسيط JWT يُخزّن مؤقتاً تعيين الرمز إلى المستخدم (TTL مُوافق لانتهاء صلاحية الرمز)
- إنشاء الرحلة: استجابات الترميز الجغرافي/التوجيه ORS مُخزَّنة مؤقتاً لـ **24 ساعة**

## أمثلة على المفاتيح:

```
persons:all
person:{id}
cities:{name}:{postal}
trips:all
trip:{id}
```

- TTL الافتراضي: **3600 ثانية**
- إلغاء صلاحية تلقائي عند عمليات الإنشاء/التحديث/الحذف
- Redis مُوصى به في الإنتاج (مطلوب لعلامات التخزين المؤقت)

> ⚠️ يعتمد التطبيق بشكل كبير على علامات التخزين المؤقت — **Redis إلزامي في الإنتاج**.

---

# 🛡 التفويض

## السياسات الرئيسية:

| السياسة | النموذج المُغطَّى |
|---------|-----------------|
| `PersonPolicy` | إدارة الملفات الشخصية والأدوار |
| `CarPolicy` | إدارة المركبات |
| `TripPolicy` | نشر الرحلات وتعديلها وإلغاؤها |

## السلوك الرئيسي:

- **المسؤولون**: تجاوز عبر `before()` على جميع السياسات
- **المستخدمون**: يمكنهم فقط إدارة ملفهم الشخصي ومركبتهم
- **السائقون**: نشر الرحلات مقتصر على السائقين (`canPublishTrip()`)
- **أصحاب الرحلات**: هم فقط من يستطيعون تحديث/إلغاء رحلاتهم
- **مساعدات على `User`**: `isAdmin()` و`isDriver()` و`canPublishTrip()` و`canBookTrip()`

## تجاوز المسؤول:

```php
public function before(Person $user): ?bool
{
    return $user->isAdmin() ? true : null;
}
```

---

# 📍 إنشاء رحلة

1. التحقق عبر DTO
2. التحقق من أن السائق يملك مركبة
3. حل المراجع (Brand وType وModel وColor وAddresses)
4. الترميز الجغرافي لكلا النقطتين عبر ORS (مع تخزين مؤقت 24 ساعة)
5. حساب المسافة والمدة عبر ORS (مع تخزين مؤقت 24 ساعة)
6. حساب `arrival_time` المشتق
7. حفظ الرحلة
8. إعادة النموذج المُحدَّث مع العلاقات

---

# 🔄 المهام المجدولة

مُعلَن عنها في `routes/console.php`:

| الأمر | التكرار | الوصف |
|-------|---------|-------|
| `auth:clear-resets` | كل 15 دقيقة | تنظيف رموز إعادة التعيين المنتهية |
| `accounts:purge-deleted` | يومياً | إخفاء هوية الحسابات المحذوفة منذ أكثر من 90 يوماً |

### التنفيذ اليدوي:

```bash
php artisan accounts:purge-deleted
```

> يجب تشغيل مُجدول Laravel كل دقيقة على خادمك.

---

# 🌍 النشر

## البنية الموصى بها:

- VPS (مثل Hetzner)
- Ubuntu 22.04+
- Apache أو Nginx (يشير إلى `public/`)
- UFW Firewall
- Cloudflare DNS
- SSL Let's Encrypt
- **Redis في الإنتاج** (إلزامي لعلامات التخزين المؤقت)
- Reverb كعملية منفصلة (إذا كان الدردشة الفورية مُفعَّلة)
- مُجدول Laravel (`* * * * * php artisan schedule:run`)

## قائمة تحقق الإنتاج:

- تكوين `APP_URL` و`FRONTEND_URL` بشكل صحيح (روابط مُولَّدة، ملفات تعريف الارتباط)
- `AUTH_COOKIE_SECURE=true` عبر HTTPS
- مراجعة `AUTH_COOKIE_SAMESITE` حسب السياق
- توفير `JWT_SECRET` و`OPENROUTESERVICE_API_KEY` و`RESEND_API_KEY` ومعرّفات قوالب Resend
- توليد ونشر وثائق Swagger حسب الحاجة

## Dockerfile

يُوفَّر `Dockerfile` مبني على `php:8.5-apache` مع تمكين امتدادات PostgreSQL وRedis، وApache مُكوَّن للخدمة من `public/`.

---

# 📁 هيكل المشروع

```
app/
  Console/Commands/         أوامر التشغيل (purge, clear-resets)
  DTOS/                     كائنات بيانات المدخلات (cars, trips...)
  Events/                   أحداث البث (ChatMessageSent)
  Exceptions/               تعيين الاستثناءات → استجابات الواجهة البرمجية
  Http/
    Controllers/            نقاط نهاية الواجهة البرمجية
    Middleware/             وسيط JWT مخصص (LocalJwtAuth)
    Requests/               كائنات التحقق من الطلبات
    Resources/              محوّلات استجابات JSON
  Models/                   نماذج Eloquent
  Policies/                 سياسات التفويض
  Providers/                ServiceProvider, RepositoryProvider, RouteBindings
  Repositories/             الواجهات + تطبيقات Eloquent
  Resolvers/                حل المراجع / العناوين
  Security/                 عقود وتطبيق مُصدر JWT
  Services/                 خدمات التطبيق
  Support/Cache/            إدارة مفاتيح/علامات التخزين المؤقت (RepositoryCacheManager)
  Swagger/                  تعريفات bootstrap لـ OpenAPI
bootstrap/
  app.php                   التوجيه، أسماء مستعارة للوسيط، الاستثناءات
config/                     تكوين الإطار والتكاملات
database/
  factories/
  migrations/
  seeders/
docs/                       ملاحظات تكميلية (قوالب Resend...)
routes/
  api.php                   مسارات الواجهة البرمجية (بدون بادئة /api)
  channels.php              تفويض قنوات البث
  console.php               الأوامر المجدولة
tests/                      اختبارات الوحدة والميزة (PHPUnit 11)
```

---

# 📊 نقاط النهاية

## 🔐 المصادقة — المسارات العامة

| الطريقة | نقطة النهاية | الوصف |
|---------|-------------|-------|
| POST | `/auth/register` | التسجيل |
| POST | `/auth/login` | تسجيل الدخول |
| POST | `/auth/refresh` | تجديد رمز JWT |
| POST | `/auth/logout` | تسجيل الخروج (إلغاء الرموز) |
| GET | `/auth/me` | ملف المستخدم الحالي |
| POST | `/auth/forgot-password` | طلب إعادة تعيين كلمة المرور |
| POST | `/auth/reset-password` | إعادة تعيين كلمة المرور |

## 👤 الأشخاص

| الطريقة | نقطة النهاية | الوصف |
|---------|-------------|-------|
| GET | `/persons` | قائمة المستخدمين |
| GET | `/persons/{person}` | تفاصيل مستخدم |
| GET | `/persons/{person}/trips-driver` | الرحلات كسائق |
| GET | `/persons/{person}/trips-passenger` | الرحلات كراكب |
| POST | `/persons` | إنشاء مستخدم |
| PATCH | `/persons/role` | تحديث الدور |
| PATCH | `/persons/{person}` | تحديث مستخدم |
| DELETE | `/persons/{person}` | حذف مستخدم |

## 🚗 الرحلات

| الطريقة | نقطة النهاية | الوصف |
|---------|-------------|-------|
| GET | `/trips` | قائمة الرحلات |
| GET | `/trips/{trip}` | تفاصيل رحلة |
| GET | `/trips/{trip}/person` | قائمة الركاب |
| POST | `/trips` | إنشاء رحلة |
| PATCH | `/trips/{trip}` | تحديث رحلة |
| PATCH | `/trips/{trip}/cancel` | إلغاء رحلة |
| DELETE | `/trips/{trip}` | حذف رحلة |
| POST | `/trips/{trip}/person` | حجز مقعد |
| DELETE | `/trips/{trip}/reservations` | إلغاء حجز |
| POST | `/trips/{trip}/contact-driver` | التواصل مع السائق |
| POST | `/my-trips/{trip}/contact-passenger/{person}` | التواصل مع راكب |

## 🏷 الماركات والكتالوج

| الطريقة | نقطة النهاية | الوصف |
|---------|-------------|-------|
| GET | `/brands` | قائمة الماركات |
| GET | `/brand/{brand}` | تفاصيل ماركة |

## 🚘 المركبات

| الطريقة | نقطة النهاية | الوصف |
|---------|-------------|-------|
| GET | `/cars` | قائمة المركبات |
| GET | `/cars/{car}` | تفاصيل مركبة |
| GET | `/cars/search` | بحث في الكتالوج |
| POST | `/cars` | إنشاء مركبة |
| PUT | `/cars/{car}` | تحديث كامل |
| DELETE | `/cars/{car}` | حذف مركبة |

## 💬 المحادثات

| الطريقة | نقطة النهاية | الوصف |
|---------|-------------|-------|
| GET | `/conversations` | قائمة المحادثات |
| GET | `/conversations/{conversation}` | تفاصيل محادثة |
| POST | `/conversations/{conversation}/messages` | إرسال رسالة |
| POST | `/broadcasting/auth-proxy` | مصادقة قنوات Reverb الخاصة |

## 🩺 الصحة

| الطريقة | نقطة النهاية | الوصف |
|---------|-------------|-------|
| GET | `/up` | فحص الصحة |
| GET | `/` | Ping (`{"message":"ok"}`) |

## 📌 ملاحظات مهمة

- جميع المسارات المحمية تمر عبر الوسيط `jwt` (`LocalJwtAuth`)
- يُدار التفويض عبر `CarPolicy` و`TripPolicy` و`PersonPolicy`
- يستفيد المسؤولون من تجاوز عبر `before()`
- المستخدمون غير النشطين (`is_active = false`) محجوبون على مستوى الوسيط
- المسارات لا تستخدم البادئة الافتراضية `/api`

---

# 🔧 استكشاف الأخطاء

### `Missing Bearer token`
- أرسل `Authorization: Bearer <access_token>` أو اعتمد على ملف تعريف الارتباط `access_token`
- تحقق من أن الطلب يصل إلى مسار محمي وأن ملفات تعريف الارتباط تُرسَل

### `Token expired` أو أخطاء مصادقة غير متوقعة
- استدعِ `POST /auth/refresh` برمز تحديث صالح
- إذا تغيّر الإعداد: `php artisan config:clear`
- إذا كان التخزين المؤقت غير متسق: `php artisan optimize:clear`

### فشل إنشاء الرحلة بأخطاء ORS
- تحقق من `OPENROUTESERVICE_API_KEY`
- تأكد من أن عناوين الانطلاق والوصول قابلة للترميز الجغرافي بواسطة ORS
- راجع السجلات: `php artisan pail --timeout=0`

### الدردشة الفورية لا تستقبل الأحداث
- تحقق من `BROADCAST_CONNECTION=reverb`
- تأكد من تشغيل `php artisan reverb:start`
- تأكد من مصادقة العميل عبر `POST /broadcasting/auth-proxy`
- تحقق من أن المستخدم المصادَق عليه ينتمي إلى القناة الخاصة المطلوبة

### أخطاء علامات التخزين المؤقت أو القراءات غير المتسقة
- فضّل Redis في بيئات التشغيل (مطلوب للتخزين المؤقت بالعلامات)
- بعد تغيير الإعداد أو برنامج التشغيل:
```bash
php artisan optimize:clear
```

### الرسائل الإلكترونية لا تُرسَل
- تحقق من `MAIL_MAILER=resend` و`RESEND_API_KEY`
- تحقق من معرّفات قوالب Resend في `.env`
- إذا كانت معرّفات القوالب فارغة، يُتجاهَل الإرسال بصمت

### `composer setup` أو `composer dev` يفشل على أوامر npm
- هذا المستودع الخلفي لا يحتوي على `package.json` مُلتزَم به
- استخدم أوامر PHP/Artisan مباشرةً بدلاً من ذلك

---

# 🔄 خارطة الطريق

- Redis في الإنتاج (التكوين الكامل)
- إرسال البريد الإلكتروني عبر Laravel Queue jobs
- معمارية قائمة على الأحداث
- إصدار الواجهة البرمجية (`/v1/...`)
- تحديد معدل الطلبات حسب الدور
- دوكرة كاملة (docker-compose)
- خط CI/CD كامل عبر GitHub Actions
- WebSockets لتحديثات حالة الرحلات الفورية

---

# 👤 المؤلف

### عبيدة حجو
### مطوّر Full Stack

</div>