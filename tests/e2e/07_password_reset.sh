#!/bin/bash
# ครูรีเซ็ตรหัสผ่านให้ผู้ใช้/สตาฟ: รหัสชั่วคราว (บังคับเปลี่ยนตอนล็อกอิน) และส่งลิงก์ทางอีเมล · สิทธิ์และ session
B="${REPS_BASE_URL:-http://127.0.0.1:8765}"
S="$1"; M="${MYSQL:-mysql}"
PW='Test1234!'
q(){ $M ${MYSQL_ARGS:--uroot} -N "${DB_NAME:-reps_v2_db}" -e "$1"; }
pass=0; fail=0
ok(){ if [ "$1" = "1" ]; then echo "  PASS $2"; pass=$((pass+1)); else echo "  FAIL $2"; fail=$((fail+1)); fi; }
csrf(){ grep -o 'name="csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//'; }
login(){ local jar="$S/$1.jar"; rm -f "$jar"; local t; t=$(curl -s -c "$jar" -b "$jar" $B/login.php | csrf); curl -s -c "$jar" -b "$jar" -o /dev/null -w "%{redirect_url}" --data-urlencode "csrf=$t" --data-urlencode "username=$1" --data-urlencode "password=$2" $B/login.php; }
get(){ curl -s -c "$S/$1.jar" -b "$S/$1.jar" "$B/$2"; }
code(){ curl -s -o /dev/null -w "%{http_code}" -b "$S/$1.jar" "$B/$2"; }
loc(){ curl -s -o /dev/null -w "%{redirect_url}" -b "$S/$1.jar" "$B/$2"; }
yes_if(){ if eval "$1"; then echo 1; fi; }
# reset <action> <user_id>  (ครู t_admin) → คืนหน้าแก้ไขผู้ใช้หลัง redirect
reset(){ local t; t=$(get t_admin "admin/admin_edit_user.php?user_id=$2" | csrf); curl -s -L -c "$S/t_admin.jar" -b "$S/t_admin.jar" --data-urlencode "csrf=$t" -d action=$1 -d user_id=$2 "$B/admin/admin_edit_user.php"; }
mc(){ q "SELECT must_change_password FROM users WHERE user_id=$1"; }

for u in t_admin t_staff t_user; do login $u "$PW" >/dev/null; done
UID_A=$(q "SELECT user_id FROM users WHERE id_number='t_admin'"); UID_S=$(q "SELECT user_id FROM users WHERE id_number='t_staff'"); UID_U=$(q "SELECT user_id FROM users WHERE id_number='t_user'")
OTHER_ADMIN=$(q "SELECT user_id FROM users WHERE role='admin' AND user_id<>$UID_A ORDER BY user_id LIMIT 1")

echo "== A. หน้าแก้ไขผู้ใช้ =="
ok "$(yes_if 'get t_admin "admin/admin_edit_user.php?user_id=$UID_U" | grep -q "ตั้งรหัสผ่านชั่วคราว"')" "หน้าแก้ไขผู้ใช้มีปุ่มรีเซ็ตรหัสผ่าน"
ok "$(yes_if '! get t_admin "admin/admin_edit_user.php?user_id=$UID_A" | grep -q "value=\"reset_password_temp\""')" "บัญชีตัวเองไม่มีปุ่มรีเซ็ต (ไปหน้าโปรไฟล์)"

echo "== B. รหัสชั่วคราว =="
PAGE=$(reset reset_password_temp $UID_U)
TEMP=$(printf '%s' "$PAGE" | grep -o 'class="pw-temp-value[^>]*>[^<]*' | sed 's/.*>//')
ok "$(yes_if '[[ "$TEMP" =~ ^[A-Za-z2-9]{4}-[A-Za-z2-9]{4}$ ]]')" "แสดงรหัสชั่วคราวให้ครูหลังรีเซ็ต"
ok "$(yes_if '[ "$(mc $UID_U)" = "1" ]')" "ตั้งสถานะต้องเปลี่ยนรหัสผ่าน"
ok "$(yes_if '! get t_admin "admin/admin_edit_user.php?user_id=$UID_U" | grep -q "$TEMP"')" "รหัสชั่วคราวแสดงครั้งเดียว (โหลดหน้าใหม่ไม่เห็นอีก)"
ok "$(yes_if '! q "SELECT password FROM users WHERE user_id=$UID_U" | grep -q "$TEMP"')" "ไม่เก็บรหัสชั่วคราวเป็นข้อความในฐานข้อมูล"
ok "$(yes_if '[ "$(q "SELECT COUNT(*) FROM user_audit_logs WHERE target_user_id=$UID_U AND changed_by=$UID_A AND action_detail LIKE \"%รหัสชั่วคราว%\"")" -ge 1 ]')" "บันทึก audit ว่าใครรีเซ็ต"
ok "$(yes_if '[ "$(loc t_user dashboard.php)" != "" ] && [ "$(code t_user dashboard.php)" = "303" ]')" "session เดิมของผู้ใช้หลุดทันที"
login t_user "$PW" >/dev/null
ok "$(yes_if '[ "$(code t_user dashboard.php)" = "303" ] && loc t_user dashboard.php | grep -q login.php')" "รหัสเดิมเข้าสู่ระบบไม่ได้แล้ว"
login t_user "$TEMP" >/dev/null
ok "$(yes_if 'loc t_user dashboard.php | grep -q change_password.php')" "เข้าด้วยรหัสชั่วคราว -> ถูกส่งไปตั้งรหัสใหม่"
ok "$(yes_if 'loc t_user history.php | grep -q change_password.php')" "หน้าอื่นก็ถูกส่งไปตั้งรหัสใหม่ก่อน"
ok "$(yes_if '[ "$(curl -s -o /dev/null -w "%{http_code}" -b "$S/t_user.jar" -d x=1 "$B/notifications_seen.php")" = "403" ]')" "POST ไปหน้าอื่นระหว่างรอตั้งรหัสถูกปฏิเสธ"
cp_post(){ local t; t=$(get t_user change_password.php | csrf); curl -s -c "$S/t_user.jar" -b "$S/t_user.jar" -o /dev/null -w "%{redirect_url}" --data-urlencode "csrf=$t" --data-urlencode "password=$1" --data-urlencode "confirm_password=$2" "$B/change_password.php"; }
cp_post short short >/dev/null
ok "$(yes_if '[ "$(mc $UID_U)" = "1" ]')" "รหัสสั้นกว่า 8 ตัวถูกปฏิเสธ"
cp_post "$TEMP" "$TEMP" >/dev/null
ok "$(yes_if '[ "$(mc $UID_U)" = "1" ]')" "ใช้รหัสชั่วคราวซ้ำไม่ได้"
cp_post 'NewPass123!' 'Mismatch123!' >/dev/null
ok "$(yes_if '[ "$(mc $UID_U)" = "1" ]')" "รหัสยืนยันไม่ตรงถูกปฏิเสธ"
R=$(cp_post 'NewPass123!' 'NewPass123!')
ok "$(yes_if '[ "$(mc $UID_U)" = "0" ] && printf "%s" "$R" | grep -q dashboard.php')" "ตั้งรหัสใหม่สำเร็จ -> ไปหน้าหลักของตัวเอง"
ok "$(yes_if '[ "$(code t_user dashboard.php)" = "200" ]')" "ใช้งานต่อได้โดยไม่ต้องล็อกอินใหม่"
ok "$(yes_if 'loc t_user change_password.php | grep -q dashboard.php')" "ตั้งรหัสแล้วเปิดหน้าตั้งรหัสอีกไม่ได้"
login t_user 'NewPass123!' >/dev/null
ok "$(yes_if '[ "$(code t_user dashboard.php)" = "200" ]')" "เข้าสู่ระบบด้วยรหัสใหม่ได้"

echo "== C. สตาฟ =="
PAGE=$(reset reset_password_temp $UID_S)
TEMP_S=$(printf '%s' "$PAGE" | grep -o 'class="pw-temp-value[^>]*>[^<]*' | sed 's/.*>//')
login t_staff "$TEMP_S" >/dev/null
ok "$(yes_if 'loc t_staff staff/staff_dashboard.php | grep -q "/change_password.php"')" "สตาฟที่ถูกรีเซ็ตถูกส่งไปตั้งรหัสใหม่ (จากโฟลเดอร์ staff)"
ok "$(yes_if '[ "$(code t_staff "staff/staff_dashboard.php?partial=1")" = "403" ]')" "polling ระหว่างรอตั้งรหัสถูกปฏิเสธ (ไม่ redirect เป็น HTML)"

echo "== D. สิทธิ์ =="
H=$(q "SELECT password FROM users WHERE user_id=$UID_A")
reset reset_password_temp $UID_A >/dev/null
ok "$(yes_if '[ "$(q "SELECT password FROM users WHERE user_id=$UID_A")" = "$H" ]')" "ครูรีเซ็ตรหัสของตัวเองผ่านหน้านี้ไม่ได้"
if [ -n "$OTHER_ADMIN" ]; then
  H2=$(q "SELECT password FROM users WHERE user_id=$OTHER_ADMIN")
  reset reset_password_temp $OTHER_ADMIN >/dev/null
  ok "$(yes_if '[ "$(q "SELECT password FROM users WHERE user_id=$OTHER_ADMIN")" = "$H2" ] && [ "$(mc $OTHER_ADMIN)" = "0" ]')" "รีเซ็ตรหัสของครูคนอื่นไม่ได้"
fi
login t_user 'NewPass123!' >/dev/null
H3=$(q "SELECT password FROM users WHERE user_id=$UID_S")
T=$(get t_user profile.php | csrf)
curl -s -b "$S/t_user.jar" -o /dev/null --data-urlencode "csrf=$T" -d action=reset_password_temp -d user_id=$UID_S "$B/admin/admin_edit_user.php"
ok "$(yes_if '[ "$(q "SELECT password FROM users WHERE user_id=$UID_S")" = "$H3" ]')" "ผู้ใช้ทั่วไปเรียกรีเซ็ตไม่ได้"
curl -s -b "$S/t_admin.jar" -o /dev/null -d action=reset_password_temp -d user_id=$UID_U -d csrf=bad "$B/admin/admin_edit_user.php"
ok "$(yes_if '[ "$(mc $UID_U)" = "0" ]')" "CSRF ไม่ถูกต้อง -> ไม่รีเซ็ต"

echo "== E. ส่งลิงก์ทางอีเมล (เครื่องทดสอบปิดระบบอีเมล) =="
N0=$(q "SELECT COUNT(*) FROM password_reset_tokens WHERE user_id=$UID_U AND used_at IS NULL")
PAGE=$(reset reset_password_link $UID_U)
ok "$(yes_if '[ "$(q "SELECT COUNT(*) FROM password_reset_tokens WHERE user_id=$UID_U AND used_at IS NULL")" = "0" ]')" "ส่งอีเมลไม่สำเร็จ -> ลิงก์ที่สร้างถูกยกเลิก ไม่ค้างใช้ได้"
ok "$(yes_if 'printf "%s" "$PAGE" | grep -q "ใช้วิธีรหัสชั่วคราวแทน"')" "แจ้งครูให้ใช้วิธีรหัสชั่วคราวแทน"
ok "$(yes_if '[ "$(mc $UID_U)" = "0" ]')" "ส่งลิงก์ไม่เปลี่ยนรหัสเดิมและไม่บังคับเปลี่ยน"

echo "== F. ลิงก์รีเซ็ตล้างสถานะต้องเปลี่ยนรหัส =="
reset reset_password_temp $UID_U >/dev/null
TK=$(printf 'f%.0s' {1..64})
q "INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES ($UID_U, SHA2('$TK', 256), NOW() + INTERVAL 1 HOUR)"
J="$S/anon.jar"; rm -f "$J"
T=$(curl -s -c "$J" -b "$J" "$B/reset_password.php?token=$TK" | csrf)
curl -s -c "$J" -b "$J" -o /dev/null --data-urlencode "csrf=$T" --data-urlencode "token=$TK" --data-urlencode "password=$PW" --data-urlencode "confirm_password=$PW" "$B/reset_password.php"
ok "$(yes_if '[ "$(mc $UID_U)" = "0" ]')" "ตั้งรหัสด้วยลิงก์อีเมล -> ไม่ต้องเปลี่ยนรหัสชั่วคราวอีก"
login t_user "$PW" >/dev/null
ok "$(yes_if '[ "$(code t_user dashboard.php)" = "200" ]')" "เข้าสู่ระบบด้วยรหัสที่ตั้งจากลิงก์ได้"

# คืนรหัสผ่าน Test1234! (ใช้ hash ของ t_admin ที่ไม่ถูกรีเซ็ต) ให้ชุดทดสอบถัดไปล็อกอินได้
q "UPDATE users u JOIN (SELECT password FROM users WHERE id_number='t_admin') a SET u.password = a.password, u.must_change_password = 0 WHERE u.id_number IN ('t_staff','t_user')"

echo "RESULT: pass=$pass fail=$fail"
