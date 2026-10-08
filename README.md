# مِحَكّ — سيرفر Laravel

موقع المطوّرين، لوحة الإدارة، API تطبيق المختبِرين، الدفع بزين كاش وكي كارد، ودفتر مالي مزدوج القيد.

## القواعد المالية (قابلة للتعديل من لوحة الإدارة)

| البند | القيمة الافتراضية |
|---|---|
| سعر التطبيق | 20,000 د.ع |
| عمولة المنصة | 20% ← 4,012 د.ع مع كسور القسمة |
| المقاعد | 14 (12 يشترطها Google + 2 احتياط) |
| حصة المختبِر | 1,142 د.ع (71 يومياً، واليوم الأخير 77) |
| المدة | 16 يوماً (14 لشرط Google + 2 احتياط) |
| الغياب المسموح | يومان، والثالث يُخرج المختبِر |
| حد السحب | 10,000 د.ع (نحو 9 اختبارات) |
| فشل الحملة (أقل من 12 أكملوا) | لا يُدفع لأي مختبِر فيها، والمطوّر يختار: استرداد 20,000 كاملة أو إعادة مجانية |

- المبلغ يُحجز في **ضمان الحملة** لحظة الدفع، ولا يُصرف إلا عند التسوية بعد اليوم الأخير.
- من ينسحب أو يلغي التثبيت أو يتجاوز الغياب يخسر المعلّق. حصته وأيام الغياب تذهب إلى **صندوق الضمان** عند نجاح الحملة.
- رصيد المختبِر افتراضي حتى يطلب السحب وتوافق الإدارة.
- كل حركة مالية تُكتب في `ledger_entries` ومجموع أطرافها صفر. لوحة الإدارة تفحص التوازن، والأمر `php artisan mihak:ledger-check` يعمل يومياً.

القواعد نفسها مكتوبة كدوال خالصة في `app/Domain/CampaignRules.php` ومختبَرة في `tests/Unit/CampaignRulesTest.php`.

## التشغيل على جهازك

المتطلبات: PHP 8.2 أو أحدث مع امتدادات pdo_mysql و mbstring و openssl، و Composer، و MySQL 8 أو MariaDB 10.4+ (مثلاً من XAMPP).

1. أنشئ قاعدة بيانات فارغة باسم `mihak` من phpMyAdmin، أو بهذا الأمر في MySQL:
   ```sql
   CREATE DATABASE mihak CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
2. انسخ ملف الإعدادات (في ويندوز CMD: `copy .env.example .env`) وعدّل فيه `DB_USERNAME` و `DB_PASSWORD` و `ADMIN_PHONE` و `ADMIN_PASSWORD`.
3. شغّل:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed            # ينشئ الجداول وحساب الإدارة من ADMIN_PHONE و ADMIN_PASSWORD
php artisan db:seed --class=DemoSeeder   # اختياري: مطوّر و16 مختبِراً وحملة جاهزة
php artisan serve --host=0.0.0.0 --port=8000
```

افتح http://localhost:8000

- حساب الإدارة: الرقم وكلمة المرور من `.env`
- المطوّر التجريبي: `07700000001` / `password123`
- المختبِرون يسجّلون من الموقع (`/join`) أو من التطبيق، ولهم لوحة في الموقع (`/me`) فيها الرصيد والسجل وطلب السحب. الانضمام للاختبارات والتحقق اليومي من التطبيق فقط، والحساب يُربط بأول هاتف يدخل منه
- خط الموقع يُختار من لوحة الإدارة ← الإعدادات (Cairo افتراضياً)
- رمز التحقق (OTP) **موقوف افتراضياً**: التسجيل يتم مباشرة. تفعّله من لوحة الإدارة ← «رمز التحقق»، ومن هناك تضبط عداد إعادة الإرسال وصلاحية الرمز والحد بالساعة، وترى عدد الرسائل المرسلة. ما دام `SMS_DRIVER=log` تُكتب الرموز في `storage/logs/laravel.log` بدل إرسالها
- مع `MIHAK_FAKE_PAYMENTS=true` تظهر صفحة دفع تجريبية بدل زين كاش وكي كارد

### تجربة دورة 16 يوماً في دقائق

بعد `DemoSeeder` تكون الحملة رقم 1 في مرحلة الاختبار:

```bash
php artisan mihak:demo-day 1                 # يحاكي يوماً يفتح فيه الجميع التطبيق ثم يغلقه
php artisan mihak:demo-day 1 --skip=3        # المختبِر رقم 3 لا يفتح التطبيق هذا اليوم
```

كرره 16 مرة ثم افتح لوحة الإدارة لترى الأرصدة والصندوق والتوازن. لتجربة الفشل، اجعل 3 مختبِرين يتغيبون 3 أيام.

### الاختبارات

الاختبارات تستخدم قاعدة منفصلة اسمها `mihak_test` تُمسح في كل تشغيل، حتى لا تمس بياناتك. أنشئها مرة واحدة:

```sql
CREATE DATABASE mihak_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

ثم:

```bash
php artisan test
```

`tests/Unit` تختبر القواعد المالية، و`tests/Feature/MoneyFlowTest.php` يمر على الدورة كاملة: الدفع، الانضمام، 16 يوماً، التسوية، السحب، الاسترداد، إعادة الاختبار، وAPI الهاتف.

## الجدولة على السيرفر

سطر واحد في crontab:

```
* * * * * cd /var/www/mihak-server && php artisan schedule:run >> /dev/null 2>&1
```

| الأمر | التوقيت | العمل |
|---|---|---|
| `mihak:close-day` | 03:00 بتوقيت بغداد + كل ساعة احتياطاً | يُغلق أيام الاختبار، يحسب المبالغ والغياب، ويسوّي الحملات المنتهية |
| `mihak:maintain` | كل 10 دقائق | يحرر المقاعد غير المؤكدة بعد 24 ساعة، يؤكد الدفعات المعلّقة، يتابع صرف زين كاش |
| `mihak:ledger-check` | 04:00 | يتحقق من توازن الدفتر ويكتب تحذيراً حرجاً في السجل إن وُجد خلل |

اليوم يُغلق الساعة 03:00 وليس منتصف الليل، حتى يصل تقرير من فتح التطبيق آخر الليل.

## النشر للإنتاج

1. سيرفر بـ PHP 8.2+ و MySQL 8 أو PostgreSQL، وشهادة HTTPS (بوابات الدفع ترفض HTTP).
2. في `.env`:
   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://your-domain.iq
   DB_CONNECTION=mysql  (مع بيانات القاعدة)
   MIHAK_FAKE_PAYMENTS=false
   ```
3. `composer install --no-dev --optimize-autoloader && php artisan migrate --force && php artisan db:seed --force`
4. `php artisan config:cache && php artisan route:cache && php artisan view:cache`
5. وجّه جذر الموقع إلى مجلد `public`.

## بوابات الدفع

### زين كاش (Payment Gateway v2)

- بيانات من زين كاش بعد عقد التاجر: `ZAINCASH_CLIENT_ID` و `ZAINCASH_CLIENT_SECRET` و `ZAINCASH_API_KEY` (للتحقق من توقيع الرجوع)، ورابط الإنتاج بدل `pg-api-uat.zaincash.iq`.
- المسار: OAuth2 ← `transaction/init` ← تحويل المطوّر ← رجوعه إلى `/payments/{id}/return` ← استعلام `transaction/inquiry` قبل تسجيل الدفع.
- اطلب من زين كاش تفعيل الإشعارات (webhook) على: `https://your-domain.iq/webhooks/zaincash`
- الاسترداد عبر `transaction/reverse` (كامل) أو `partial-reverse`.
- صرف سحوبات المختبِرين آلياً عبر Cash Disbursement API عند `MIHAK_AUTO_ZAINCASH_PAYOUTS=true` (يحتاج صلاحية disbursement في عقدك).
- توثيق زين كاش لا يعرض أمثلة كاملة للاستجابات، لذلك يقرأ الكود الحقول من أكثر من مسار محتمل. جرّب على بيئة UAT أولاً، وإن اختلف اسم حقل عدّله في `app/Payments/ZainCashGateway.php`.

### كي كارد (Qi Payment Gateway)

- بيانات من البنك المحصّل: `QICARD_TERMINAL_ID` و `QICARD_USERNAME` و `QICARD_PASSWORD`، ورابط الإنتاج بدل `uat-sandbox-3ds-api.qi.iq`.
- المفتاح العام للتحقق من الإشعارات يُطلب من دعم كي كارد، ويوضع في `storage/app/private/qicard-public.pem`.
- رابط الإشعارات يُرسل تلقائياً مع كل دفعة: `https://your-domain.iq/webhooks/qicard`
- كي كارد لا يوفر صرفاً للمحافظ، فسحوبات كي كارد تُحوَّل يدوياً وتُسجَّل برقم الحوالة في لوحة الإدارة.

## API تطبيق المختبِرين

كل الطلبات بعد الدخول تحمل `Authorization: Bearer <token>` و `X-Device-Id`.

| الطريقة | المسار | الوصف |
|---|---|---|
| POST | `/api/auth/register` | تسجيل: name, phone, email (Gmail), password, device_id ← يرسل OTP |
| POST | `/api/auth/verify` | phone, code, device_id ← token |
| POST | `/api/auth/login` | phone, password, device_id ← token |
| GET | `/api/me` | المستخدم، المحفظة، القواعد، مجموعة Google |
| GET | `/api/tasks` | الاختبارات الجارية والمنتظرة مع حالة اليوم |
| GET | `/api/campaigns/available` | اختبارات فيها مقاعد |
| POST | `/api/campaigns/{id}/join` | حجز مقعد |
| POST | `/api/enrollments/{id}/confirm-install` | تأكيد التثبيت |
| POST | `/api/enrollments/{id}/leave` | انسحاب |
| POST | `/api/enrollments/{id}/feedback` | ملاحظة للمطوّر |
| POST | `/api/checks` | تقارير الاستخدام اليومية من الهاتف |
| GET | `/api/history` | السجل |
| POST | `/api/withdrawals` | طلب سحب: amount, method (zaincash/qicard), account |

## قبل الإطلاق

- **مزوّد رسائل SMS حقيقي** (قبل تفعيل رمز التحقق): طبّق `App\Services\Sms\SmsSender` وسجّله في `AppServiceProvider`.
- **عقود التاجر:** مع زين كاش وكي كارد، ثم التجربة على بيئة UAT.
- **صفحات قانونية:** شروط الاستخدام وسياسة الخصوصية، ويجب أن توضح للمختبِر قاعدة «لا دفع إذا لم يكتمل العدد».
- **توثيق هوية المختبِر قبل أول سحب**، ونسخ احتياطي يومي لقاعدة البيانات.
- **Play Integrity API:** إضافة لاحقة مقترحة لكشف المحاكيات والأجهزة الوهمية.
- **مخاطرة يجب معرفتها:** Google قد يرفض طلب الإنتاج إذا رأى أن المختبِرين غير متفاعلين، ولهذا يشترط النظام الاستخدام اليومي ويجمع الملاحظات. لا توجد ضمانة من Google نفسها.
