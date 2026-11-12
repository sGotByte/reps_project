# REPS — Project Context

เอกสารนี้สรุปโครงสร้างและการทำงานจาก source code ใน repository ปัจจุบัน เพื่อช่วยให้ผู้พัฒนาทำงานต่อได้เร็วขึ้น ไม่ใช่คู่มือผู้ใช้หรือเอกสารติดตั้งฉบับ production

## ภาพรวม

REPS (Repair and Equipment Problem System) เป็นเว็บแอปสำหรับแจ้งซ่อมและติดตามงานอุปกรณ์คอมพิวเตอร์/IT ของสถานศึกษา พัฒนาด้วย PHP แบบ server-rendered, MySQLi และ MariaDB/MySQL ไม่มี frontend framework หรือ routing layer แยก แต่ละไฟล์ PHP ทำหน้าที่เป็นหน้าเว็บ/endpoint โดยตรง

ผู้ใช้ทั่วไปสมัครและเข้าสู่ระบบเพื่อสร้างคำขอ ดูงานที่ยังเปิด แก้ไข/ยกเลิกคำขอที่ยังรอรับเรื่อง ดูประวัติ และแก้ไขโปรไฟล์ ผู้ดูแลจัดการงาน หมวดหมู่ และบัญชีผู้ใช้ ดูสถิติ/ประวัติ และดาวน์โหลดรายงาน PDF

## โครงสร้างสำคัญ

| Path | หน้าที่ |
|---|---|
| `index.php` | หน้าแนะนำบริการ ขั้นตอนการแจ้งซ่อม FAQ และลิงก์เข้าใช้ระบบ; ถ้ามี session จะตรวจบัญชีก่อนแสดงทางลัดไปยังหน้าตาม role |
| `login.php`, `register.php`, `logout.php` | เข้าสู่ระบบ สมัคร และออกจากระบบ |
| `forgot_password.php`, `reset_password.php` | ขออีเมลตั้งรหัสผ่านใหม่และใช้ token ที่มีอายุ/ใช้ครั้งเดียว |
| `dashboard.php` | พื้นที่ผู้แจ้ง: รายการงานที่ยังเปิด ค้นหา/กรอง/แบ่งหน้า ดู timeline แก้ไขหรือยกเลิกงาน |
| `request_form.php` | สร้างใบแจ้งซ่อม พร้อมแนบรูปและส่งการแจ้งเตือน LINE หลังบันทึก |
| `history.php`, `profile.php` | ประวัติใบงานทั้งหมดและแก้ไขข้อมูลโปรไฟล์ |
| `admin/admin_dashboard.php` | ภาพรวมจำนวนงาน KPI และแนวโน้มช่วงวัน/เดือน/ปี |
| `admin/admin_manage_request.php` | จัดการสถานะงาน เพิ่ม/แก้ไข/ลบหมวดหมู่ และลบใบงาน |
| `admin/admin_manage_user.php`, `admin/admin_edit_user.php` | ค้นหา แก้ไข role/สถานะ/ข้อมูล และลบบัญชี |
| `admin/admin_reports.php`, `admin/admin_report_pdf.php` | รายงานตามช่วงวันที่และส่งออก PDF ด้วย mPDF |
| `admin/admin_notifications.php`, `admin/admin_history.php` | งานด่วน/งานรอรับเรื่อง/กิจกรรมล่าสุด และประวัติการเปลี่ยนสถานะ |
| `admin/admin_header.php` | โครง UI ร่วมของหน้าผู้ดูแลและ navigation |
| `admin/line_notify.php` | helper สำหรับส่ง push message ไป LINE Messaging API; ถูก include จากฟอร์มแจ้งซ่อม |
| `admin/ticket_attachment.php` | อ่านไฟล์แนบผ่าน endpoint ที่ต้องผ่าน admin authentication |
| `db_connect.php` | โหลดค่าตั้งค่าจาก environment/ไฟล์ `.env`, เริ่ม session, ตั้ง timezone และเชื่อม MySQL |
| `includes/` | helper สำหรับ auth, CSRF, rate limit, registration, private storage, SMTP และข้อมูลรายงาน |
| `database/scema.sql` | SQL dump สำหรับ schema และข้อมูลตั้งต้น/ตัวอย่าง (ชื่อไฟล์สะกด `scema`) |
| `assets/css/`, `assets/images/`, `assets/vendor/` | CSS, โลโก้, Font Awesome และ vendor assets |
| `vendor/` | Composer dependencies ที่มีใน working tree; mPDF ใช้สร้าง PDF |

ไม่พบ root `composer.json`, package manifest, test suite หรือ migration framework ในไฟล์ที่ตรวจพบ การพัฒนา UI ใช้ PHP/HTML/CSS/JavaScript โดยตรง; บางหน้าดึง Tailwind จาก CDN และหน้าแรกมี JavaScript สำหรับ animation/scroll behavior

## ผู้ใช้และการยืนยันตัวตน

- บัญชีมี `user_type` เป็น `student`, `teacher` หรือ `staff`; ส่วน `role` ใช้ `requester` หรือ `admin` และมี `status` เป็น `active`/`disabled`
- สมัครผ่าน `register.php` ตรวจข้อมูลฝั่ง server, CSRF, แผนกที่มีจริง, รูปแบบอีเมล/เบอร์โทร, รหัสผ่านขั้นต่ำ 8 ตัวอักษร และป้องกัน `id_number`/อีเมลซ้ำ จากนั้นบันทึก password hash ด้วย `password_hash()`
- Login ค้นด้วย `id_number`; รับเฉพาะบัญชี active ที่ role ถูกต้อง และตรวจรหัสผ่านด้วย `password_verify()`. เมื่อสำเร็จ regenerate session ID และเก็บ user ID, role, ชื่อ และ hash ปัจจุบันของรหัสผ่านใน session
- `includes/user_auth.php` ตรวจสถานะบัญชีและเทียบ password hash ใน session กับฐานข้อมูลทุกครั้ง จึงทำให้ session เก่าหมดผลเมื่อบัญชีถูกปิดหรือเปลี่ยนรหัสผ่าน และ redirect ไปยังพื้นที่ตาม role
- `includes/admin_auth.php` จำกัดหน้าผู้ดูแลเฉพาะ admin active และตรวจ hash เช่นเดียวกัน
- `includes/csrf.php` สร้าง CSRF token แบบสุ่มใน session; ฟอร์ม POST หลักตรวจ token ก่อนทำรายการ
- `includes/login_rate_limit.php` จัดเก็บ timestamp ของความพยายาม login ในไฟล์ JSON ที่ temp directory โดยใช้ key hash แยก IP และบัญชี+IP จำกัดในหน้าต่าง 15 นาที
- Session cookie ตั้ง HttpOnly, SameSite และอายุ 7 วันจาก `db_connect.php`; ใช้ Secure เมื่อ HTTPS เปิดใช้งาน

## Flow ของใบแจ้งซ่อม

1. ผู้ใช้ส่ง `request_form.php` โดยต้อง login; ฟอร์มเลือกหมวดหมู่ สถานที่ รายละเอียด อาการย่อย (ถ้ามี) ระดับความเร่งด่วน และรูปได้สูงสุด 3 รูป
2. Server ตรวจ category, ข้อความและความเร่งด่วนซ้ำอีกครั้ง รูปต้องเป็น JPEG/PNG ตาม MIME จริงและขนาดไม่เกิน 5 MB ต่อรูป
3. ระบบสร้างเลขงานรูปแบบ `LB-ปี-รหัสสุ่ม`, บันทึก ticket สถานะ `pending`, ย้ายไฟล์ไปยัง private storage, บันทึก attachment และสร้างแถวเริ่มต้นใน `ticket_logs` ภายใน transaction เดียวกัน หากล้มเหลวจะ rollback และพยายามลบไฟล์ที่ย้ายแล้ว
4. หลัง commit จะพยายามส่ง LINE push notification (Flex; ถ้า LINE ตอบ HTTP 400 จะ fallback เป็นข้อความธรรมดา) การส่งล้มเหลวไม่ย้อนการสร้าง ticket แต่ถูก log และแสดงคำเตือนแก่ผู้ใช้
5. ผู้แจ้งเห็นเฉพาะใบงานตนเองใน `dashboard.php`; แสดงเฉพาะ `pending`/`in_progress`, มีตัวกรอง/ค้นหา/แบ่งหน้า และ timeline จาก `ticket_logs`. แก้ไขได้เฉพาะงาน `pending`; ยกเลิกได้เมื่อยัง `pending` และยังไม่ได้มอบหมาย โดยบันทึกเหตุผลและ log ภายใน transaction
6. ผู้ดูแลเปลี่ยนสถานะตามลำดับ `pending → in_progress → completed` หรือยกเลิกจาก `pending`/`in_progress`; งาน `completed`/`cancelled` ปิดแล้วและเปลี่ยนต่อไม่ได้ การเปลี่ยนสถานะบันทึกผู้กระทำและเวลาใน `ticket_logs`; รับงานจะกำหนด `assigned_to`/`accepted_at`, ปิดงานต้องมี resolution summary, ยกเลิกต้องมี cancel reason
7. `history.php` แสดงงานทุกสถานะของผู้ใช้ พร้อมตัวกรองและ timeline; `admin_history.php` แสดงกิจกรรมเปลี่ยนสถานะล่าสุดสูงสุด 250 แถว

## แบบจำลองข้อมูล

ตารางหลักใน `database/scema.sql`:

- `users`: ข้อมูลบัญชี ประเภทผู้ใช้ role สถานะ และ `department_id`; email และ `id_number` เป็น unique
- `department`: รายการแผนก/สังกัด
- `categories`: หมวดหมู่งานซ่อม
- `tickets`: ใบงาน ผู้แจ้ง หมวดหมู่ สถานที่ อาการ ระดับเร่งด่วน สถานะ ผู้รับผิดชอบ ข้อสรุป/เหตุผล และเวลาสร้าง/รับงาน/ปิดงาน; `ticket_no` เป็น unique
- `ticket_attachments`: metadata/relative path ของรูปแนบ; ลบตาม ticket
- `ticket_logs`: ประวัติเปลี่ยนสถานะ ผู้กระทำ เวลา และ source (`web` เป็นค่าเริ่มต้น)
- `password_reset_tokens`: hash ของ reset token, วันหมดอายุและวันใช้; ผูกกับ user และลบ cascade เมื่อ user ถูกลบ

ค่าความเร่งด่วน: `urgent`, `normal`, `low`; สถานะ: `pending`, `in_progress`, `completed`, `cancelled`. มี foreign keys ระหว่าง users/department, tickets/users/categories, attachments/tickets, logs/tickets/users และ reset tokens/users โปรดตรวจ SQL dump ก่อนใช้กับฐานข้อมูลจริง เพราะไฟล์มีข้อมูล seed/ตัวอย่างติดมาด้วย ไม่ควรใช้เป็น migration สำหรับฐานข้อมูล production โดยไม่ตรวจข้อมูลก่อน

## หน้าผู้ดูแล

- Dashboard สรุปงานทั้งหมด งานค้าง งานด่วน ปิดงานวันนี้ เวลาเฉลี่ยก่อนรับงาน สัดส่วนหมวดหมู่ และแนวโน้ม 7 วัน/12 เดือน/5 ปี
- จัดการคำขอรองรับตัวกรองสถานะ, การเปลี่ยนสถานะตาม transition, เพิ่ม/แก้ไข/ลบหมวดหมู่ และลบ ticket หลังยืนยัน ticket number
- ลบผู้ใช้ต้องยืนยันรหัสประจำตัว ห้ามลบบัญชีตัวเอง/บัญชีที่มีงานหรือ log อ้างอิง และห้ามลด role/ปิด/ลบ admin active คนสุดท้าย; การเปลี่ยนบัญชีผู้ดูแลใช้ MySQL named lock `reps_active_admin_guard`
- รายงาน filter ช่วงวันที่ มีสรุปสถานะ หมวดหมู่ รายวัน เวลาเฉลี่ย และรายการสูงสุด 100 งาน; PDF ใช้ mPDF และ temp directory เฉพาะ request
- Notifications เป็นหน้ารวมงานด่วน งาน pending ที่ไม่ด่วน และ log ล่าสุด ไม่ใช่ระบบ polling/realtime

## ไฟล์แนบและการแจ้งเตือน

- `includes/private_storage.php` เลือก path จาก `TICKET_UPLOAD_DIR`; ค่าเริ่มต้นคือ temp storage นอก document root หากจัดเก็บใต้ document root ต้องเปิด `TICKET_UPLOAD_ALLOW_IN_DOCROOT=true` และมี `tickets/.htaccess` ที่ deny access
- ไฟล์ถูกตั้ง permission จำกัด; ผู้ดูแลอ่านผ่าน `admin/ticket_attachment.php` ซึ่งตรวจสิทธิ์, ตรวจชื่อไฟล์ด้วย allowlist และตรวจ MIME ก่อนส่งกลับ
- `admin/line_notify.php` ใช้ cURL และ TLS ตรวจ certificate กับ `api.line.me`; ใช้ `LINE_CHANNEL_ACCESS_TOKEN` และ `LINE_TARGET_ID`
- รีเซ็ตรหัสผ่านใช้ Gmail SMTP STARTTLS ผ่าน `includes/gmail_smtp.php`; token สุ่ม 32 bytes เก็บเฉพาะ SHA-256, ใช้ได้ 1 ชั่วโมง/ครั้งเดียว และจำกัดคำขอ 3 ครั้งต่อ user ต่อชั่วโมง
- `APP_BASE_URL` ใช้ประกอบลิงก์ reset และลิงก์กลับไปหน้า admin ใน LINE message; ต้องเป็น URL HTTP(S) ที่เชื่อถือได้

## การตั้งค่าและการรันในเครื่อง

`db_connect.php` อ่าน environment ของ process ก่อน และหากไม่มีค่าจะอ่าน `.env` ที่ parent directory ก่อน project directory (อ่านไฟล์แรกที่พบ) ควรเก็บ `.env` ไว้นอก web root/ไม่ส่งเข้าระบบควบคุมเวอร์ชัน

ค่าที่ใช้:

| Variable | ใช้สำหรับ |
|---|---|
| `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASSWORD`, `DB_NAME` | เชื่อมต่อ MySQL/MariaDB; port default `3306` |
| `APP_TIMEZONE` | timezone ของ PHP/MySQL session; default `Asia/Bangkok` |
| `APP_DISPLAY_ERRORS` | เปิดแสดง PHP errors; default ปิด |
| `APP_BASE_URL` | URL หลักของเว็บสำหรับลิงก์ reset password และ LINE |
| `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM` | Gmail SMTP login/App Password และ sender |
| `LINE_CHANNEL_ACCESS_TOKEN`, `LINE_TARGET_ID` | LINE Messaging API push |
| `TICKET_UPLOAD_DIR` | directory ฐานสำหรับ private รูปแนบ; default ใช้ temp directory |
| `TICKET_UPLOAD_ALLOW_IN_DOCROOT` | อนุญาตเก็บใต้ document root เฉพาะเมื่อมี `.htaccess` ป้องกัน |

เตรียมฐานข้อมูล MySQL/MariaDB ตาม schema ที่ตรวจแล้วและกำหนด environment ข้างต้น จาก root directory สามารถใช้ PHP built-in server สำหรับ local development ได้ เช่น `php -S 127.0.0.1:8000`; ต้องมี PHP อย่างน้อย 8.1 (มี enum/mixed types ใน source), MySQLi/mysqlnd, `mbstring`, `fileinfo`; `openssl` จำเป็นกับ SMTP และ `curl` จำเป็นกับ LINE หากใช้ PDF ต้องมี `vendor/autoload.php` และ mPDF ใน `vendor/`. การใช้งานจริงควรผ่าน Apache/Nginx ที่รองรับกฎป้องกันไฟล์ตาม `.htaccess` หรือกำหนดการป้องกันเทียบเท่า

## ข้อควรรู้ในการแก้ไข

- ใช้ prepared statements สำหรับ input เข้า SQL และ escape output ด้วย `htmlspecialchars()` ตาม pattern ของแต่ละหน้า
- Auth include ของหน้า requester มีค่าเริ่มต้นต้องเป็น role `requester`; หน้า admin ต้อง include `includes/admin_auth.php`
- การเปลี่ยนสถานะควรอัปเดต ticket และเพิ่ม log ให้สอดคล้องกัน; ตรวจ transaction/locking ที่มีอยู่ก่อนแก้ flow
- ระวัง schema, PHP และ data dump ใช้เวลาต่างกันได้; `db_connect.php` ตั้ง timezone เริ่มต้นเป็น `Asia/Bangkok` และตั้ง MySQL session timezone จาก offset ปัจจุบัน
- `.htaccess` ปิด directory listing และ block ไฟล์ dot, SQL/Markdown/config รวมถึง directory ภายในหลายรายการ; อย่าลบหรือทำให้กฎเหล่านี้อ่อนลงโดยไม่แทนด้วย config ของ web server
- `assets/js/home-3d.js` และ `assets/vendor/three/three.min.js` มีอยู่ใน tree แต่ไม่พบการอ้างอิงจากไฟล์ PHP; หน้าแรกวาด background/animation ด้วย inline JavaScript ใน `index.php`
- ไม่มี automated tests หรือคำสั่ง build/test ใน root ที่พบ; หลังแก้ควรอย่างน้อยตรวจ syntax PHP ของไฟล์ที่เปลี่ยนและทดสอบ flow ที่เกี่ยวข้องบนฐานข้อมูลทดสอบ
