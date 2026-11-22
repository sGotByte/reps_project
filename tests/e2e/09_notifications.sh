#!/bin/bash
# กระดิ่งแจ้งเตือน: กดอ่านทีละรายการแล้วหายและตัวเลขลดลง · อ่านทั้งหมด · เรื่องใหม่/เปลี่ยนขั้นตอนแจ้งใหม่ · แยกต่อผู้ใช้
B="${REPS_BASE_URL:-http://127.0.0.1:8765}"
S="$1"; M="${MYSQL:-mysql}"
PW='Test1234!'
q(){ $M ${MYSQL_ARGS:--uroot} -N "${DB_NAME:-reps_v2_db}" -e "$1"; }
pass=0; fail=0
ok(){ if [ "$1" = "1" ]; then echo "  PASS $2"; pass=$((pass+1)); else echo "  FAIL $2"; fail=$((fail+1)); fi; }
csrf(){ grep -o 'name="csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//'; }
login(){ local jar="$S/$1.jar"; rm -f "$jar"; local t; t=$(curl -s -c "$jar" -b "$jar" $B/login.php | csrf); curl -s -c "$jar" -b "$jar" -o /dev/null --data-urlencode "csrf=$t" --data-urlencode "username=$1" --data-urlencode "password=$2" $B/login.php; }
get(){ curl -s -c "$S/$1.jar" -b "$S/$1.jar" "$B/$2"; }
yes_if(){ if eval "$1"; then echo 1; fi; }
# page <who> -> หน้าหลักของบทบาทนั้น
page(){ case $1 in t_admin) get t_admin admin/admin_dashboard.php;; t_staff) get t_staff staff/staff_dashboard.php;; *) get $1 dashboard.php;; esac; }
badge(){ page $1 | grep -o 'data-badge="[0-9]*"' | head -1 | grep -o '[0-9]*'; }
keys(){ page $1 | grep -o 'data-notif-key="[^"]*"' | sed 's/.*="//;s/"$//'; }
ncsrf(){ page $1 | grep -o 'data-csrf="[a-f0-9]*"' | head -1 | sed 's/.*="//;s/"$//'; }
seen(){ local who=$1; shift; curl -s -o /dev/null -w "%{http_code}" -c "$S/$who.jar" -b "$S/$who.jar" --data-urlencode "csrf=$(ncsrf $who)" "$@" "$B/notifications_seen.php"; }

for u in t_admin t_staff t_user; do login $u "$PW" >/dev/null; done
UID_A=$(q "SELECT user_id FROM users WHERE id_number='t_admin'"); UID_S=$(q "SELECT user_id FROM users WHERE id_number='t_staff'"); UID_U=$(q "SELECT user_id FROM users WHERE id_number='t_user'")
q "DELETE FROM notification_reads WHERE user_id IN ($UID_A, $UID_S, $UID_U)"
CAT=$(q "SELECT id FROM categories ORDER BY id LIMIT 1")
# ทำให้มีงานด่วนรอรับอย่างน้อย 3 ใบ
for i in 1 2 3; do q "INSERT INTO tickets (ticket_no, requester_id, category_id, location_note, contact_person, symptom, urgency, status, created_at) VALUES (CONCAT('NT-', FLOOR(RAND()*100000000)), $UID_U, $CAT, 'ห้องทดสอบแจ้งเตือน', 'x', 'ทดสอบ', 'urgent', 'pending', NOW())"; done

echo "== A. อ่านทีละรายการ (ครู) =="
N0=$(badge t_admin)
K1=$(keys t_admin | grep '^urgent:' | head -1)
ok "$(yes_if '[ "${N0:-0}" -ge 3 ] && [ -n "$K1" ]')" "มีการแจ้งเตือนหลายรายการ (${N0})"
ok "$(yes_if '[ "$(seen t_admin -d "key=$K1")" = "200" ]')" "กดดู 1 รายการ -> บันทึกว่าอ่านแล้ว"
N1=$(badge t_admin)
ok "$(yes_if '[ "$N1" = "$((N0 - 1))" ]')" "ตัวเลขลดลง 1 ($N0 -> $N1)"
ok "$(yes_if '! keys t_admin | grep -qx "$K1"')" "รายการที่กดแล้วหายจากกระดิ่ง"
seen t_admin -d "key=$K1" >/dev/null
ok "$(yes_if '[ "$(badge t_admin)" = "$N1" ]')" "กดรายการเดิมซ้ำ ตัวเลขไม่ลดซ้ำ"

echo "== B. ความปลอดภัยของ endpoint =="
ok "$(yes_if '[ "$(seen t_admin -d "key=<script>")" = "400" ]')" "key รูปแบบไม่ถูกต้อง -> 400"
ok "$(yes_if '[ "$(curl -s -o /dev/null -w "%{http_code}" -b "$S/t_admin.jar" -d csrf=bad -d key=urgent:1 "$B/notifications_seen.php")" = "403" ]')" "CSRF ไม่ถูกต้อง -> 403"
ok "$(yes_if '[ "$(curl -s -o /dev/null -w "%{http_code}" -b "$S/t_admin.jar" "$B/notifications_seen.php")" = "405" ]')" "GET -> 405"
ok "$(yes_if '[ "$(curl -s -o /dev/null -w "%{http_code}" -d key=urgent:1 "$B/notifications_seen.php")" = "303" ]')" "ไม่ได้ล็อกอิน -> ไปหน้าเข้าสู่ระบบ"

echo "== C. แยกต่อผู้ใช้ =="
SK=$(keys t_staff | grep '^job:' | head -1)
ok "$(yes_if '[ -n "$SK" ]')" "สตาฟเห็นงานด่วนในกระดิ่งของตัวเอง"
ok "$(yes_if '[ "$(q "SELECT COUNT(*) FROM notification_reads WHERE user_id=$UID_S")" = "0" ]')" "ครูอ่านแล้ว ไม่ทำให้ของสตาฟหาย"

echo "== D. อ่านทั้งหมด และเรื่องใหม่แจ้งอีก =="
ok "$(yes_if '[ "$(seen t_admin -d all=1)" = "200" ]')" "กดอ่านทั้งหมด"
ok "$(yes_if '[ "$(badge t_admin)" = "0" ] && [ -z "$(keys t_admin)" ]')" "อ่านทั้งหมดแล้ว ไม่มีตัวเลขและรายการ (รวมรายการที่ไม่ได้แสดงในกระดิ่ง)"
ok "$(yes_if 'page t_admin | grep -q "ไม่มีการแจ้งเตือนใหม่"')" "แสดงข้อความไม่มีการแจ้งเตือนใหม่"
q "INSERT INTO tickets (ticket_no, requester_id, category_id, location_note, contact_person, symptom, urgency, status, created_at) VALUES (CONCAT('NT-', FLOOR(RAND()*100000000)), $UID_U, $CAT, 'ห้องทดสอบแจ้งเตือน', 'x', 'ทดสอบ', 'urgent', 'pending', NOW())"
ok "$(yes_if '[ "$(badge t_admin)" = "1" ]')" "มีงานด่วนใหม่ -> แจ้งเตือนใหม่ 1 รายการ"
STK=$(keys t_admin | head -1)
TID=$(echo "$STK" | cut -d: -f2)
SID=$(keys t_staff | grep "^job:$TID:" | head -1)
seen t_staff -d "key=$SID" >/dev/null
ok "$(yes_if '! keys t_staff | grep -q "^job:$TID:"')" "สตาฟกดดูงานใหม่แล้วหาย"
q "UPDATE tickets SET queue_assigned_to=$UID_S, queue_expires_at=NOW() + INTERVAL 1 HOUR WHERE id=$TID"
ok "$(yes_if 'keys t_staff | grep -q "^job:$TID:$UID_S$"')" "งานเดิมเปลี่ยนเป็นถึงคิวสตาฟ -> แจ้งใหม่อีกครั้ง"

echo "== E. ผู้แจ้ง =="
seen t_user -d all=1 >/dev/null
U0=$(badge t_user)
UT=$(q "SELECT MAX(id) FROM tickets WHERE requester_id=$UID_U")
q "INSERT INTO ticket_logs (ticket_id, old_status, new_status, action_by, created_at) VALUES ($UT, 'pending', 'accepted', $UID_S, NOW()), ($UT, 'accepted', 'in_progress', $UID_S, NOW())"
ok "$(yes_if '[ "$(badge t_user)" = "$((U0 + 2))" ]')" "ความคืบหน้าใบงาน 2 เรื่อง -> ผู้แจ้งเห็น 2 รายการ"
UK=$(keys t_user | grep '^log:' | head -1)
seen t_user -d "key=$UK" >/dev/null
ok "$(yes_if '[ "$(badge t_user)" = "$((U0 + 1))" ] && ! keys t_user | grep -qx "$UK"')" "ผู้แจ้งกดดู 1 รายการ -> เหลือ 1"
ok "$(yes_if '[ "$(seen t_user -d "key=urgent:$UT")" = "200" ] && [ "$(badge t_user)" = "$((U0 + 1))" ]')" "ส่ง key ของคนอื่นมา ไม่กระทบรายการของตัวเอง"

q "DELETE FROM ticket_logs WHERE ticket_id IN (SELECT id FROM (SELECT id FROM tickets WHERE location_note='ห้องทดสอบแจ้งเตือน') x); DELETE FROM tickets WHERE location_note='ห้องทดสอบแจ้งเตือน'; DELETE FROM notification_reads WHERE user_id IN ($UID_A, $UID_S, $UID_U)"
echo "RESULT: pass=$pass fail=$fail"
