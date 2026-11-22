#!/bin/bash
B="${REPS_BASE_URL:-http://127.0.0.1:8765}"
S="$1"; M="${MYSQL:-mysql}"
q(){ $M ${MYSQL_ARGS:--uroot} -N "${DB_NAME:-reps_v2_db}" -e "$1"; }
pass=0; fail=0
ok(){ if [ "$1" = "1" ]; then echo "  PASS $2"; pass=$((pass+1)); else echo "  FAIL $2"; fail=$((fail+1)); fi; }
csrf(){ grep -o 'name="csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//'; }
get(){ curl -s -c "$S/$1.jar" -b "$S/$1.jar" "$B/$2"; }
yes_if(){ if eval "$1"; then echo 1; fi; }
CAT=$(q "SELECT id FROM categories ORDER BY id LIMIT 1")
newticket(){ local T; T=$(get t_user request_form.php | csrf); curl -s -c "$S/t_user.jar" -b "$S/t_user.jar" -o /dev/null -F "csrf=$T" -F "location_note=$1" -F "contact_person=x" -F "category_id=$CAT" -F "symptoms=x" -F quantity=1 $B/request_form.php; q "SELECT id FROM tickets ORDER BY id DESC LIMIT 1"; }

echo "== manual mode =="
q "UPDATE system_settings SET setting_value='manual' WHERE setting_key='queue_dispatch_mode'"
TID=$(newticket "manual-1")
ok "$(yes_if '[ "$(q "SELECT IFNULL(queue_assigned_to,\"NULL\") FROM tickets WHERE id=$TID")" = "NULL" ]')" "manual: ใบงานใหม่ไม่ถูกจ่ายอัตโนมัติ"
STAFF2=$(q "SELECT user_id FROM users WHERE id_number='t_staff2'")
T=$(get t_admin admin/admin_manage_request.php | csrf)
curl -s -c "$S/t_admin.jar" -b "$S/t_admin.jar" -o /dev/null --data-urlencode "csrf=$T" -d action=assign_ticket -d ticket_id=$TID -d staff_id=$STAFF2 $B/admin/admin_manage_request.php
ok "$(yes_if '[ "$(q "SELECT CONCAT(queue_assigned_to,\"/\",IFNULL(queue_expires_at,\"NULL\")) FROM tickets WHERE id=$TID")" = "$STAFF2/NULL" ]')" "admin มอบหมายให้ staff2 (ไม่มีเวลาหมดเขตในโหมด manual)"
SC=$(q "SELECT score FROM users WHERE user_id=$STAFF2")
T=$(get t_staff2 staff/staff_dashboard.php | csrf)
curl -s -c "$S/t_staff2.jar" -b "$S/t_staff2.jar" -o /dev/null --data-urlencode "csrf=$T" -d action=decline_ticket -d ticket_id=$TID $B/staff/staff_dashboard.php
ok "$(yes_if '[ "$(q "SELECT score FROM users WHERE user_id=$STAFF2")" = "$SC" ] && [ "$(q "SELECT IFNULL(queue_assigned_to,\"NULL\") FROM tickets WHERE id=$TID")" = "NULL" ]')" "manual: ปฏิเสธไม่หักคะแนน คืนเป็นคิวกลาง"
q "UPDATE system_settings SET setting_value='auto' WHERE setting_key='queue_dispatch_mode'"

echo "== admin transitions =="
T=$(get t_admin admin/admin_manage_request.php | csrf)
curl -s -c "$S/t_admin.jar" -b "$S/t_admin.jar" -o /dev/null --data-urlencode "csrf=$T" -d ticket_id=$TID -d status=completed --data-urlencode status_reason=x $B/admin/admin_manage_request.php
ok "$(yes_if '[ "$(q "SELECT status FROM tickets WHERE id=$TID")" = "pending" ]')" "admin ข้ามไป completed ตรง ๆ ไม่ได้ (ต้องผ่านการเซ็น)"
curl -s -c "$S/t_admin.jar" -b "$S/t_admin.jar" -o /dev/null --data-urlencode "csrf=$T" -d ticket_id=$TID -d status=cancelled --data-urlencode "status_reason=ทดสอบยกเลิก" $B/admin/admin_manage_request.php
ok "$(yes_if '[ "$(q "SELECT status FROM tickets WHERE id=$TID")" = "cancelled" ]')" "admin ยกเลิกใบงานได้"

echo "== send back from awaiting_admin_sign (v3: admin return) =="
TID2=$(newticket "sendback-1")
q "UPDATE tickets SET status='awaiting_admin_sign', assigned_to=$STAFF2, accepted_at=NOW(), stage_since=NOW(), resolution_summary='x' WHERE id=$TID2"
T=$(get t_admin "admin/admin_signoff.php?id=$TID2" | csrf)
curl -s -c "$S/t_admin.jar" -b "$S/t_admin.jar" -o /dev/null --data-urlencode "csrf=$T" -d action=return -d ticket_id=$TID2 --data-urlencode "reason=แก้ไขสรุปผลให้ละเอียด" $B/admin/admin_signoff.php
ok "$(yes_if '[ "$(q "SELECT CONCAT(status,\"/\",assigned_to,\"/\",sign_round) FROM tickets WHERE id=$TID2")" = "in_progress/$STAFF2/2" ]')" "ตีกลับงานที่รอรับรองให้สตาฟคนเดิมแก้ไข (รอบเซ็น 2)"

echo "== delete ticket with files =="
TID3=$(q "SELECT id FROM tickets WHERE status='completed' ORDER BY id DESC LIMIT 1")
FILES=$(q "SELECT file_path FROM ticket_attachments WHERE ticket_id=$TID3 UNION ALL SELECT completion_image FROM tickets WHERE id=$TID3 UNION ALL SELECT signature_image FROM ticket_signoffs WHERE ticket_id=$TID3")
NO=$(q "SELECT ticket_no FROM tickets WHERE id=$TID3")
T=$(get t_admin admin/admin_manage_request.php | csrf)
curl -s -c "$S/t_admin.jar" -b "$S/t_admin.jar" -o /dev/null --data-urlencode "csrf=$T" -d action=delete_ticket -d ticket_id=$TID3 -d confirm_ticket_no=$NO $B/admin/admin_manage_request.php
ok "$(yes_if '[ -z "$(q "SELECT id FROM tickets WHERE id=$TID3")" ]')" "ลบใบงานที่เสร็จแล้วได้"
left=0; for f in $FILES; do [ -f "D:/00-Project/reps_project/private_uploads/tickets/$(basename $f)" ] && left=$((left+1)); done
ok "$(yes_if '[ $left = 0 ]')" "ลบไฟล์รูปแนบ/รูปปิดงาน/ลายเซ็นออกจากดิสก์ด้วย ($(echo $FILES | wc -w) ไฟล์)"

echo "== delete user guard =="
UIDS=$(q "SELECT user_id FROM users WHERE id_number='t_staff2'")
T=$(get t_admin admin/admin_manage_user.php | csrf)
curl -s -c "$S/t_admin.jar" -b "$S/t_admin.jar" -o /dev/null --data-urlencode "csrf=$T" -d action=delete_user -d user_id=$UIDS -d confirm_id_number=t_staff2 $B/admin/admin_manage_user.php
ok "$(yes_if '[ -n "$(q "SELECT user_id FROM users WHERE user_id=$UIDS")" ]')" "ลบสตาฟที่มีประวัติคะแนน/งานไม่ได้ (ให้ระงับแทน)"
echo "RESULT: pass=$pass fail=$fail"
