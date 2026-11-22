#!/bin/bash
B="${REPS_BASE_URL:-http://127.0.0.1:8765}"
S="$1"; M="${MYSQL:-mysql}"
PHP="${PHP:-php}"
PW='Test1234!'
q(){ $M ${MYSQL_ARGS:--uroot} -N "${DB_NAME:-reps_v2_db}" -e "$1"; }
pass=0; fail=0
ok(){ if [ "$1" = "1" ]; then echo "  PASS $2"; pass=$((pass+1)); else echo "  FAIL $2"; fail=$((fail+1)); fi; }
csrf(){ grep -o 'name="csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//'; }
login(){ local jar="$S/$1.jar"; rm -f "$jar"; local t; t=$(curl -s -c "$jar" -b "$jar" $B/login.php | csrf); curl -s -c "$jar" -b "$jar" -o /dev/null -w "%{redirect_url}" --data-urlencode "csrf=$t" --data-urlencode "username=$1" --data-urlencode "password=$2" $B/login.php; }
get(){ curl -s -c "$S/$1.jar" -b "$S/$1.jar" "$B/$2"; }
code(){ curl -s -o /dev/null -w "%{http_code}" -c "$S/$1.jar" -b "$S/$1.jar" "$B/$2"; }
yes_if(){ if eval "$1"; then echo 1; fi; }

echo "== 1. register -> pending =="
q "DELETE FROM users WHERE id_number IN ('t_new','t_new_long_username_x')"
rm -f "$S/anon.jar"; T=$(curl -s -c "$S/anon.jar" -b "$S/anon.jar" $B/register.php | csrf)
R=$(curl -s -c "$S/anon.jar" -b "$S/anon.jar" -o /dev/null -w "%{redirect_url}" --data-urlencode "csrf=$T" -d user_type=student -d id_number=t_new --data-urlencode first_name=ใหม่ --data-urlencode last_name=ทดสอบ -d department_id=2 -d phone_number=0812345678 -d email=t_new@example.invalid --data-urlencode "password=$PW" --data-urlencode "confirm_password=$PW" $B/register.php)
ok "$(yes_if '[ "$(q "SELECT status FROM users WHERE id_number=\"t_new\"")" = "pending" ]')" "สมัครแล้วสถานะ pending (redirect=$R)"
T=$(curl -s -c "$S/anon.jar" -b "$S/anon.jar" $B/register.php | csrf)
curl -s -c "$S/anon.jar" -b "$S/anon.jar" -o /dev/null --data-urlencode "csrf=$T" -d user_type=student -d id_number=t_new_long_username_x -d first_name=a -d last_name=b -d department_id=2 -d phone_number=0812345679 -d email=t_long@example.invalid --data-urlencode "password=$PW" --data-urlencode "confirm_password=$PW" $B/register.php
ok "$(yes_if '[ -z "$(q "SELECT 1 FROM users WHERE id_number LIKE \"t_new_long%\"")" ]')" "username ยาวเกิน 20 ถูกปฏิเสธ"
rm -f "$S/pn.jar"; T=$(curl -s -c "$S/pn.jar" -b "$S/pn.jar" $B/login.php | csrf)
BODY=$(curl -s -c "$S/pn.jar" -b "$S/pn.jar" --data-urlencode "csrf=$T" -d username=t_new --data-urlencode "password=$PW" $B/login.php)
ok "$(yes_if 'echo "$BODY" | grep -q "รอผู้ดูแลระบบอนุมัติ"')" "ล็อกอินบัญชี pending ได้ข้อความรออนุมัติ"

echo "== 2. admin approve =="
R=$(login t_admin "$PW"); ok "$(yes_if '[ "${R##*/}" = "admin_dashboard.php" ]')" "admin login -> $R"
NEWID=$(q "SELECT user_id FROM users WHERE id_number='t_new'")
T=$(get t_admin admin/admin_manage_user.php | csrf)
curl -s -c "$S/t_admin.jar" -b "$S/t_admin.jar" -o /dev/null --data-urlencode "csrf=$T" -d action=approve_user -d user_id=$NEWID $B/admin/admin_manage_user.php
ok "$(yes_if '[ "$(q "SELECT CONCAT(status,\"/\",approved_by IS NOT NULL) FROM users WHERE user_id=$NEWID")" = "active/1" ]')" "อนุมัติแล้ว active + approved_by"
ok "$(yes_if '[ "$(q "SELECT COUNT(*) FROM user_audit_logs WHERE target_user_id=$NEWID")" -ge 1 ]')" "บันทึก audit log การอนุมัติ"

echo "== 3. admin_edit_user on normal user =="
UID_=$(q "SELECT user_id FROM users WHERE id_number='t_user'")
T=$(get t_admin "admin/admin_edit_user.php?user_id=$UID_" | csrf)
curl -s -c "$S/t_admin.jar" -b "$S/t_admin.jar" -o /dev/null --data-urlencode "csrf=$T" -d action=update_user -d user_id=$UID_ -d id_number=t_user -d user_type=student --data-urlencode first_name=T-แก้ไขแล้ว --data-urlencode last_name=ทดสอบ -d email=t_user@example.invalid -d phone_number=0800000000 -d department_id=2 -d role=user $B/admin/admin_edit_user.php
ok "$(yes_if '[ "$(q "SELECT CONCAT(first_name,\"/\",role) FROM users WHERE user_id=$UID_")" = "T-แก้ไขแล้ว/user" ]')" "แก้ข้อมูล user สำเร็จ role ยังเป็น user"

echo "== 4. user creates urgent ticket with photo =="
R=$(login t_user "$PW"); ok "$(yes_if '[ "${R##*/}" = "dashboard.php" ]')" "user login -> $R"
FORM=$(get t_user request_form.php)
STEP_COUNT=$(printf '%s' "$FORM" | grep -o 'data-step="[1-4]"' | wc -l | tr -d ' ')
ok "$(yes_if '[ "$STEP_COUNT" = "4" ]')" "Request Wizard แสดง 4 ขั้นตอน"
T=$(printf '%s' "$FORM" | csrf)
CAT=$(q "SELECT id FROM categories ORDER BY id LIMIT 1")
TICKET_COUNT_BEFORE=$(q "SELECT COUNT(*) FROM tickets")
curl -s -c "$S/t_user.jar" -b "$S/t_user.jar" -o "$S/invalid_secondary.html" -F "csrf=$T" -F "location_note=ห้องทดสอบ" -F "category_id=$CAT" -F "symptoms=เปิดไม่ติด" -F quantity=1 -F "secondary_contact_name=ผู้ติดต่อสำรอง" -F secondary_contact_phone=invalid $B/request_form.php
ok "$(yes_if 'grep -q "เบอร์โทรผู้ติดต่อสำรองไม่ถูกต้อง" "$S/invalid_secondary.html" && [ "$(q "SELECT COUNT(*) FROM tickets")" = "$TICKET_COUNT_BEFORE" ]')" "ปฏิเสธเบอร์ผู้ติดต่อสำรองที่ไม่ถูกต้อง"
R=$(curl -s -c "$S/t_user.jar" -b "$S/t_user.jar" -o /dev/null -w "%{redirect_url}" -F "csrf=$T" -F "location_note=ห้องทดสอบ <script>alert(1)</script>" -F "contact_person=ผู้ทดสอบ" -F "primary_contact_name=ปลอมชื่อผู้แจ้ง" -F "category_id=$CAT" -F "symptoms=เปิดไม่ติด" -F "device_detail=Dell 3080" -F quantity=2 -F urgent=1 -F "urgency_reason=ใช้สอบ" -F coordinator_teacher=ครูเอ -F coordinator_phone=0811111111 -F secondary_contact_name=ผู้ติดต่อสำรอง -F secondary_contact_phone=0812345678 -F secondary_contact_email=backup@example.invalid -F "attachments[]=@$S/photo.jpg;type=image/jpeg" $B/request_form.php)
TID=$(q "SELECT id FROM tickets ORDER BY id DESC LIMIT 1")
ok "$(yes_if '[ -n "$TID" ] && [ "${R##*/}" = "dashboard.php?created=1" ]')" "สร้างใบงาน #$TID redirect=$R"
ok "$(yes_if '[ "$(q "SELECT CONCAT(urgency,\"/\",device_detail,\"/\",coordinator_phone,\"/\",status) FROM tickets WHERE id=$TID")" = "urgent/Dell 3080/0811111111/pending" ]')" "บันทึกฟิลด์ใหม่ครบ"
ok "$(yes_if '[ "$(q "SELECT IF(t.primary_contact_name=CONCAT(u.first_name,\" \",u.last_name) AND t.primary_contact_phone=u.phone_number AND t.primary_contact_email=u.email,1,0) FROM tickets t JOIN users u ON u.user_id=t.requester_id WHERE t.id=$TID")" = "1" ]')" "ผู้แจ้งเป็นผู้ติดต่อหลักและเก็บข้อมูลบัญชีเป็น snapshot"
ok "$(yes_if '[ "$(q "SELECT CONCAT(secondary_contact_name,\"/\",secondary_contact_phone,\"/\",secondary_contact_email) FROM tickets WHERE id=$TID")" = "ผู้ติดต่อสำรอง/0812345678/backup@example.invalid" ]')" "บันทึกผู้ติดต่อสำรอง"
ok "$(yes_if '[ "$(q "SELECT COUNT(*) FROM ticket_contact_history WHERE ticket_id=$TID")" = "2" ] && [ "$(q "SELECT COUNT(*) FROM ticket_contact_history WHERE ticket_id=$TID AND contact_type=\"secondary\" AND contact_phone=\"0812345678\"")" = "1" ]')" "เก็บ snapshot ผู้ติดต่อหลักและผู้ติดต่อสำรองในประวัติ"
ok "$(yes_if '[ "$(q "SELECT u.id_number FROM tickets t JOIN users u ON u.user_id=t.queue_assigned_to WHERE t.id=$TID")" = "t_staff" ]')" "จ่ายคิวให้ t_staff (queue_order 1)"
AID=$(q "SELECT id FROM ticket_attachments WHERE ticket_id=$TID LIMIT 1")
ok "$(yes_if '[ -n "$AID" ]')" "บันทึกรูปแนบ id=$AID"
ok "$(yes_if 'get t_user dashboard.php | grep -q "คุณเป็นคิวที่"')" "dashboard แสดงลำดับคิว"
T=$(get t_user request_form.php | csrf)
curl -s -c "$S/t_user.jar" -b "$S/t_user.jar" -o "$S/fake_resp.html" -F "csrf=$T" -F "location_note=x" -F "contact_person=x" -F "category_id=$CAT" -F "symptoms=x" -F quantity=1 -F "attachments[]=@$S/fake.jpg;type=image/jpeg" $B/request_form.php
ok "$(yes_if 'grep -q "JPG, PNG หรือ WEBP" "$S/fake_resp.html"')" "ปฏิเสธไฟล์ปลอมที่ตั้งชื่อ .jpg"
ok "$(yes_if '[ "$(code t_user "file.php?t=$TID&a=$AID")" = "200" ]')" "ผู้แจ้งเปิดรูปแนบตัวเองได้"
login t_new "$PW" >/dev/null
ok "$(yes_if '[ "$(code t_new "file.php?t=$TID&a=$AID")" = "404" ]')" "ผู้ใช้อื่นเปิดรูปแนบไม่ได้"

echo "== 5. queue: staff2 cannot steal, staff1 declines =="
login t_staff "$PW" >/dev/null; login t_staff2 "$PW" >/dev/null
T=$(get t_staff2 staff/staff_dashboard.php | csrf)
curl -s -c "$S/t_staff2.jar" -b "$S/t_staff2.jar" -o /dev/null --data-urlencode "csrf=$T" -d action=accept_ticket -d ticket_id=$TID $B/staff/staff_dashboard.php
ok "$(yes_if '[ "$(q "SELECT status FROM tickets WHERE id=$TID")" = "pending" ]')" "staff2 รับงานที่เป็นคิวของ staff1 ไม่ได้"
ok "$(yes_if 'get t_staff staff/staff_dashboard.php | grep -q "&lt;script&gt;alert(1)&lt;/script&gt;"')" "ชื่อสถานที่ถูก escape"
ok "$(yes_if '! get t_staff staff/staff_dashboard.php | grep -q "<script>alert(1)"')" "ไม่มี script ดิบในหน้า staff"
S1=$(q "SELECT score FROM users WHERE id_number='t_staff'")
T=$(get t_staff staff/staff_dashboard.php | csrf)
curl -s -c "$S/t_staff.jar" -b "$S/t_staff.jar" -o /dev/null --data-urlencode "csrf=$T" -d action=decline_ticket -d ticket_id=$TID $B/staff/staff_dashboard.php
S1b=$(q "SELECT score FROM users WHERE id_number='t_staff'")
ok "$(yes_if '[ $((S1 - S1b)) = 5 ]')" "ปฏิเสธแล้วหัก 5 คะแนน ($S1 -> $S1b)"
ok "$(yes_if '[ "$(q "SELECT u.id_number FROM tickets t JOIN users u ON u.user_id=t.queue_assigned_to WHERE t.id=$TID")" = "t_staff2" ]')" "คิวย้ายไป t_staff2"
T=$(get t_staff staff/staff_dashboard.php | csrf)
curl -s -c "$S/t_staff.jar" -b "$S/t_staff.jar" -o /dev/null --data-urlencode "csrf=$T" -d action=decline_ticket -d ticket_id=$TID $B/staff/staff_dashboard.php
ok "$(yes_if '[ "$(q "SELECT score FROM users WHERE id_number=\"t_staff\"")" = "$S1b" ]')" "ปฏิเสธงานที่ไม่ใช่คิวตัวเอง ไม่ถูกหัก"

echo "== 6. queue expiry: no ping-pong =="
q "UPDATE tickets SET queue_expires_at = NOW() - INTERVAL 1 MINUTE WHERE id=$TID"
S2=$(q "SELECT score FROM users WHERE id_number='t_staff2'")
code t_staff2 staff/staff_dashboard.php >/dev/null
S2b=$(q "SELECT score FROM users WHERE id_number='t_staff2'")
ok "$(yes_if '[ $((S2 - S2b)) = 5 ]')" "staff2 หมดเวลาโดนหัก 5"
ok "$(yes_if '[ "$(q "SELECT IFNULL(queue_assigned_to,\"NULL\") FROM tickets WHERE id=$TID")" = "NULL" ]')" "ข้ามครบทุกคนแล้ว -> คิวกลาง ไม่วนหักซ้ำ"

echo "== 7. v3: accept -> intake checklist -> requester signs -> repair + recheck (stock) =="
T=$(get t_staff2 staff/staff_dashboard.php | csrf)
R=$(curl -s -c "$S/t_staff2.jar" -b "$S/t_staff2.jar" -o /dev/null -w "%{redirect_url}" --data-urlencode "csrf=$T" -d action=accept_ticket -d ticket_id=$TID $B/staff/staff_dashboard.php)
ok "$(yes_if '[ "$(q "SELECT status FROM tickets WHERE id=$TID")" = "accepted" ]')" "staff2 รับงานจากคิวกลาง -> accepted"
ok "$(yes_if 'echo "$R" | grep -q "job.php?id=$TID"')" "รับงานแล้วพาไปหน้าเช็คลิสต์ ($R)"
ok "$(yes_if '[ "$(q "SELECT COUNT(*) FROM ticket_logs WHERE ticket_id=$TID AND new_status=\"accepted\"")" = "1" ]')" "บันทึก ticket_logs ตอนรับงาน"
SIG=$(cat "$S/sig.txt")
jobpost(){ local who=$1; shift; local t; t=$(get $who "staff/job.php?id=$TID" | csrf); curl -s -c "$S/$who.jar" -b "$S/$who.jar" -o /dev/null -F "csrf=$t" -F ticket_id=$TID "$@" "$B/staff/job.php?id=$TID"; }
signpost(){ local who=$1; shift; local t; t=$(get $who "sign.php?id=$TID" | csrf); curl -s -c "$S/$who.jar" -b "$S/$who.jar" -o /dev/null --data-urlencode "csrf=$t" -d ticket_id=$TID "$@" "$B/sign.php?id=$TID"; }
# ห้ามเริ่มซ่อม/ตรวจซ้ำก่อนบันทึกเช็คลิสต์
jobpost t_staff2 -F action=submit_recheck -F "repair_method=x" -F "resolution_summary=x" -F "completion_image=@$S/photo.jpg;type=image/jpeg"
ok "$(yes_if '[ "$(q "SELECT status FROM tickets WHERE id=$TID")" = "accepted" ]')" "ข้ามขั้นเช็คลิสต์ไม่ได้"
# สตาฟที่ไม่อยู่ในทีมบันทึกเช็คลิสต์ไม่ได้ (IDOR)
jobpost t_staff -F action=save_intake -F "it_name[]=โน้ตบุ๊ก" -F "it_type[]=main" -F "it_qty[]=1" -F "it_cond[]=normal"
ok "$(yes_if '[ "$(q "SELECT COUNT(*) FROM ticket_item_checks WHERE ticket_id=$TID")" = "0" ]')" "สตาฟนอกทีมบันทึกเช็คลิสต์ไม่ได้"
# ต้องมีอุปกรณ์หลัก
jobpost t_staff2 -F action=save_intake -F "it_name[]=สายชาร์จ" -F "it_type[]=accessory" -F "it_qty[]=1" -F "it_cond[]=normal"
ok "$(yes_if '[ "$(q "SELECT COUNT(*) FROM ticket_item_checks WHERE ticket_id=$TID")" = "0" ]')" "ไม่มีอุปกรณ์หลัก -> บันทึกไม่ได้"
jobpost t_staff2 -F action=save_intake -F "it_name[]=โน้ตบุ๊ก Dell" -F "it_type[]=main" -F "it_serial[]=7440-001" -F "it_qty[]=1" -F "it_cond[]=scratched" -F "it_note[]=มีรอยมุมขวา" \
  -F "it_name[]=สายชาร์จ" -F "it_type[]=accessory" -F "it_serial[]=" -F "it_qty[]=1" -F "it_cond[]=normal" -F "it_note[]=" \
  -F "it_name[]=เมาส์" -F "it_type[]=accessory" -F "it_serial[]=" -F "it_qty[]=0" -F "it_cond[]=normal" -F "it_note[]=" \
  -F "it_photo[0]=@$S/photo.jpg;type=image/jpeg" -F "intake_photo=@$S/photo.jpg;type=image/jpeg" -F "intake_note=รับที่ห้องช่าง"
ok "$(yes_if '[ "$(q "SELECT status FROM tickets WHERE id=$TID")" = "awaiting_intake_sign" ]')" "บันทึกเช็คลิสต์ -> awaiting_intake_sign"
ok "$(yes_if '[ "$(q "SELECT COUNT(*) FROM ticket_item_checks WHERE ticket_id=$TID")" = "3" ] && [ -n "$(q "SELECT photo_in FROM ticket_item_checks WHERE ticket_id=$TID AND item_name=\"โน้ตบุ๊ก Dell\"")" ]')" "เช็คลิสต์ 3 รายการ + รูปรายการ"
ok "$(yes_if '[ "$(q "SELECT COUNT(*) FROM email_logs WHERE ticket_id=$TID AND template_key=\"intake_recorded\"")" = "1" ]')" "อีเมลสรุปเช็คลิสต์ถึงผู้แจ้ง (บันทึก email_logs)"
jobpost t_staff2 -F action=save_intake -F "it_name[]=ของเพิ่ม" -F "it_type[]=main" -F "it_qty[]=1" -F "it_cond[]=normal"
ok "$(yes_if '[ "$(q "SELECT COUNT(*) FROM ticket_item_checks WHERE ticket_id=$TID")" = "3" ]')" "เช็คลิสต์บันทึกแล้วแก้/เพิ่มย้อนหลังไม่ได้"
CHK=$(q "SELECT id FROM ticket_item_checks WHERE ticket_id=$TID AND item_name='โน้ตบุ๊ก Dell'")
ok "$(yes_if '[ "$(code t_user "file.php?t=$TID&k=item_in&i=$CHK")" = "200" ] && [ "$(code t_user "file.php?t=$TID&k=intake")" = "200" ]')" "ผู้แจ้งเปิดรูปเช็คลิสต์ได้"
ok "$(yes_if '[ "$(code t_new "file.php?t=$TID&k=intake")" = "404" ]')" "คนอื่นเปิดรูปเช็คลิสต์ไม่ได้"
# ผู้แจ้งคนอื่นเซ็นแทนไม่ได้ / ผู้แจ้งเซ็นรับคืนข้ามขั้นไม่ได้
ok "$(yes_if '[ "$(code t_new "sign.php?id=$TID")" = "404" ]')" "ผู้ใช้อื่นเปิดหน้าเซ็นของใบงานนี้ไม่ได้"
signpost t_user -d action=sign_return -d sig_method=draw --data-urlencode "sig_data=$SIG"
ok "$(yes_if '[ "$(q "SELECT status FROM tickets WHERE id=$TID")" = "awaiting_intake_sign" ]')" "ผู้แจ้งเซ็นรับคืนข้ามขั้นไม่ได้"
signpost t_user -d action=sign_intake -d sig_method=saved
ok "$(yes_if '[ "$(q "SELECT status FROM tickets WHERE id=$TID")" = "awaiting_intake_sign" ]')" "เลือกลายเซ็นที่บันทึกไว้แต่ยังไม่มี -> ไม่ผ่าน"
signpost t_user -d action=sign_intake -d sig_method=draw --data-urlencode "sig_data=$SIG" --data-urlencode "sign_note=รายการถูกต้อง"
ok "$(yes_if '[ "$(q "SELECT status FROM tickets WHERE id=$TID")" = "in_progress" ]')" "ผู้แจ้งเซ็นรับทราบ -> in_progress"
ok "$(yes_if '[ "$(q "SELECT CONCAT(signer_role,\"/\",stage,\"/\",method) FROM ticket_signoffs WHERE ticket_id=$TID")" = "requester/intake/draw" ]')" "บันทึกลายเซ็นตอนรับ (requester/intake)"

ITEM=$(q "SELECT id FROM inventory_items ORDER BY id LIMIT 1"); STK=$(q "SELECT stock_qty FROM inventory_items WHERE id=$ITEM")
C1=$(q "SELECT id FROM ticket_item_checks WHERE ticket_id=$TID ORDER BY sort_order LIMIT 1 OFFSET 0")
C2=$(q "SELECT id FROM ticket_item_checks WHERE ticket_id=$TID ORDER BY sort_order LIMIT 1 OFFSET 1")
C3=$(q "SELECT id FROM ticket_item_checks WHERE ticket_id=$TID ORDER BY sort_order LIMIT 1 OFFSET 2")
RC="-F rc_result[$C1]=returned -F rc_qty[$C1]=1 -F rc_result[$C2]=returned -F rc_qty[$C2]=1 -F rc_result[$C3]=returned -F rc_qty[$C3]=0"
jobpost t_staff2 -F action=submit_recheck -F "repair_method=เปลี่ยนสาย" -F "resolution_summary=ใช้ได้" -F "item_id[]=$ITEM" -F "item_qty[]=$((STK+1))" $RC -F "completion_image=@$S/photo.jpg;type=image/jpeg"
ok "$(yes_if '[ "$(q "SELECT status FROM tickets WHERE id=$TID")" = "in_progress" ] && [ "$(q "SELECT stock_qty FROM inventory_items WHERE id=$ITEM")" = "$STK" ]')" "เบิกเกินสต็อก -> ส่งไม่ได้ สต็อกไม่เปลี่ยน"
ok "$(yes_if '[ "$(q "SELECT COUNT(*) FROM ticket_item_checks WHERE ticket_id=$TID AND result_out IS NOT NULL")" = "0" ]')" "ล้มแล้ว rollback ผลตรวจซ้ำด้วย"
jobpost t_staff2 -F action=submit_recheck -F "repair_method=เปลี่ยนสาย" -F "resolution_summary=ใช้ได้" -F "item_id[]=$ITEM" -F "item_qty[]=1" -F "item_note[]=ทดสอบ" $RC -F "completion_image=@$S/photo.jpg;type=image/jpeg"
ok "$(yes_if '[ "$(q "SELECT status FROM tickets WHERE id=$TID")" = "inspected_ready" ]')" "ซ่อมเสร็จ + ตรวจซ้ำครบ -> inspected_ready"
ok "$(yes_if '[ "$(q "SELECT has_discrepancy FROM tickets WHERE id=$TID")" = "0" ]')" "ตรวจครบถ้วน ไม่มีข้อแตกต่าง"
ok "$(yes_if '[ "$(q "SELECT stock_qty FROM inventory_items WHERE id=$ITEM")" = "$((STK-1))" ]')" "ตัดสต็อก 1"
ok "$(yes_if '[ "$(q "SELECT COUNT(*) FROM inventory_movements WHERE ticket_id=$TID AND movement_type=\"withdraw\"")" = "1" ]')" "บันทึก inventory_movements"
CI=$(q "SELECT completion_image FROM tickets WHERE id=$TID")
DIM=$($PHP "$S/imgwidth.php" "$CI")
ok "$(yes_if '[ "$DIM" = "1600" ]')" "รูปปิดงานถูกย่อเป็นกว้าง 1600px (เดิม 2400) $CI"
ok "$(yes_if '[ "$(q "SELECT COUNT(*) FROM email_logs WHERE ticket_id=$TID AND template_key=\"recheck_done\"")" = "1" ]')" "อีเมลผลตรวจซ้ำถึงผู้แจ้ง"
SC=$(q "SELECT score FROM users WHERE id_number='t_staff2'")
jobpost t_staff2 -F action=submit_recheck -F "repair_method=x" -F "resolution_summary=x" -F "item_id[]=$ITEM" -F "item_qty[]=1" $RC -F "completion_image=@$S/photo.jpg;type=image/jpeg"
ok "$(yes_if '[ "$(q "SELECT stock_qty FROM inventory_items WHERE id=$ITEM")" = "$((STK-1))" ]')" "ส่งผลซ่อมซ้ำ: ไม่ตัดสต็อกซ้ำ"

echo "== 8. v3: 3-party sign-off (staff -> requester -> admin) =="
T=$(get t_admin "admin/admin_signoff.php?id=$TID" | csrf)
curl -s -c "$S/t_admin.jar" -b "$S/t_admin.jar" -o /dev/null --data-urlencode "csrf=$T" -d action=sign -d ticket_id=$TID -d confirm=1 -d sig_method=draw --data-urlencode "sig_data=$SIG" $B/admin/admin_signoff.php
ok "$(yes_if '[ "$(q "SELECT status FROM tickets WHERE id=$TID")" = "inspected_ready" ]')" "ครูเซ็นข้ามสตาฟ/ผู้แจ้งไม่ได้"
jobpost t_staff2 -F action=staff_sign -F sig_method=draw --form-string "sig_data=$SIG" -F "sign_note=ส่งคืนที่ห้อง"
ok "$(yes_if '[ "$(q "SELECT status FROM tickets WHERE id=$TID")" = "awaiting_requester_sign" ]')" "สตาฟเซ็นส่งมอบ -> awaiting_requester_sign"
ok "$(yes_if '[ "$(q "SELECT COUNT(*) FROM email_logs WHERE ticket_id=$TID AND template_key=\"requester_turn\"")" = "1" ]')" "อีเมลแจ้งผู้แจ้งว่าถึงคิวเซ็น"
signpost t_user -d action=sign_return -d sig_method=draw --data-urlencode "sig_data=$SIG"
ok "$(yes_if '[ "$(q "SELECT status FROM tickets WHERE id=$TID")" = "awaiting_admin_sign" ]')" "ผู้แจ้งเซ็นรับคืน -> awaiting_admin_sign"
ok "$(yes_if '[ "$(q "SELECT COUNT(*) FROM email_logs WHERE ticket_id=$TID AND template_key=\"requester_signed\"")" -ge 1 ]')" "อีเมลแจ้งครูว่ามีใบงานรอรับรอง"
T=$(get t_admin "admin/admin_signoff.php?id=$TID" | csrf)
curl -s -c "$S/t_admin.jar" -b "$S/t_admin.jar" -o /dev/null --data-urlencode "csrf=$T" -d action=sign -d ticket_id=$TID -d confirm=1 -d sig_method=draw --data-urlencode "sig_data=$SIG" --data-urlencode note=ตรวจแล้ว $B/admin/admin_signoff.php
ok "$(yes_if '[ "$(q "SELECT status FROM tickets WHERE id=$TID")" = "completed" ]')" "ครูเซ็นรับรอง -> completed"
ok "$(yes_if '[ "$(q "SELECT GROUP_CONCAT(signer_role ORDER BY id) FROM ticket_signoffs WHERE ticket_id=$TID AND stage=\"return\" AND ip<>\"\" AND signature_image IS NOT NULL")" = "staff,requester,admin" ]')" "ลายเซ็นส่งคืนครบ 3 ฝ่ายตามลำดับ"
ok "$(yes_if '[ $(( $(q "SELECT score FROM users WHERE id_number=\"t_staff2\"") - SC )) = 0 ]')" "ปิดงานไม่ได้คะแนนอัตโนมัติ (กติกาใหม่: เริ่ม 100 หัก/ครูให้เท่านั้น)"
ok "$(yes_if '[ "$(q "SELECT status FROM email_logs WHERE ticket_id=$TID AND template_key=\"completed\" LIMIT 1")" = "failed" ]')" "บันทึก email_logs (failed เพราะปิด SMTP ตอนทดสอบ) และไม่ทำให้ปิดงานล้ม"
ok "$(yes_if '[ "$(code t_user "certificate.php?id=$TID")" = "200" ]')" "ผู้แจ้งเปิดใบรับรองได้"
ok "$(yes_if 'get t_user "certificate.php?id=$TID" | grep -q "สตาฟผู้รับงาน" && get t_user "certificate.php?id=$TID" | grep -q "โน้ตบุ๊ก Dell"')" "ใบรับรองมีลายเซ็น 3 ฝ่ายและเช็คลิสต์"
ok "$(yes_if '[ "$(code t_new "certificate.php?id=$TID")" = "404" ]')" "คนอื่นเปิดใบรับรองไม่ได้"
GID=$(q "SELECT id FROM ticket_signoffs WHERE ticket_id=$TID AND signer_role='staff' LIMIT 1")
ok "$(yes_if '[ "$(code t_user "file.php?t=$TID&g=$GID")" = "200" ] && [ "$(code t_user "file.php?t=$TID&k=signature")" = "200" ]')" "เปิดรูปลายเซ็นแต่ละขั้นได้"
ok "$(yes_if '[ "$(code t_user "file.php?t=$TID&k=completion")" = "200" ]')" "ผู้แจ้งเปิดรูปหลังซ่อมได้"

echo "== 9. pages per role =="
for p in admin/admin_checklists.php "staff/job.php?id=$TID" admin/admin_dashboard.php admin/admin_manage_request.php admin/admin_manage_user.php admin/admin_signoff.php admin/admin_settings.php admin/admin_reports.php admin/admin_notifications.php admin/admin_history.php admin/admin_war_room.php admin/admin_teacher_report.php staff/inventory.php staff/staff_dashboard.php "admin/admin_edit_user.php?user_id=$UID_" request_form.php profile.php "certificate.php?id=$TID"; do c=$(code t_admin "$p"); ok "$(yes_if '[ "$c" = "200" ]')" "admin $p -> $c"; done
for p in staff/staff_dashboard.php staff/inventory.php admin/admin_war_room.php request_form.php profile.php; do c=$(code t_staff "$p"); ok "$(yes_if '[ "$c" = "200" ]')" "staff $p -> $c"; done
for p in admin/admin_dashboard.php admin/admin_teacher_report.php admin/admin_settings.php admin/admin_signoff.php; do c=$(code t_staff "$p"); ok "$(yes_if '[ "$c" != "200" ]')" "staff ถูกกันจาก $p -> $c"; done
for p in dashboard.php history.php request_form.php profile.php index.php; do c=$(code t_user "$p"); ok "$(yes_if '[ "$c" = "200" ]')" "user $p -> $c"; done
for p in staff/staff_dashboard.php staff/inventory.php admin/admin_war_room.php admin/admin_dashboard.php; do c=$(code t_user "$p"); ok "$(yes_if '[ "$c" != "200" ]')" "user ถูกกันจาก $p -> $c"; done
get t_user index.php >/dev/null
ok "$(yes_if '[ "$(code t_user dashboard.php)" = "200" ]')" "เปิดหน้าแรกแล้วไม่หลุดจากระบบ (บั๊กเดิม)"

echo "== 10. settings + ขอบคุณ Lab boy =="
T=$(get t_admin admin/admin_settings.php | csrf)
curl -s -c "$S/t_admin.jar" -b "$S/t_admin.jar" -o /dev/null -F "csrf=$T" -F "site_name=REPS - ระบบแจ้งซ่อมอุปกรณ์คอมพิวเตอร์" -F theme_color=default -F primary_color= -F "homepage_intro=ยินดีต้อนรับ <b>x</b>" -F "homepage_announcement=ทดสอบประกาศ" -F homepage_show_announcement=1 -F homepage_show_steps=1 -F homepage_show_faq=1 -F footer_text= -F queue_dispatch_mode=auto -F queue_timeout_minutes=30 -F queue_penalty_points=5 -F queue_work_start= -F queue_work_end= -F academic_year_start_month=5 -F thanks_enabled=1 -F thanks_max_amount=5000 -F thanks_days=30 -F wallboard_enabled=1 -F requester_sign_skip_days=3 -F intake_sign_skip_hours=24 -F sign_reminder_days=1 -F warroom_refresh_seconds=30 -F stale_warn_hours=24 -F stale_critical_hours=72 $B/admin/admin_settings.php
H=$(curl -s $B/index.php)
ok "$(yes_if 'echo "$H" | grep -q "ยินดีต้อนรับ &lt;b&gt;x&lt;/b&gt;"')" "ข้อความหน้าแรกถูก escape"
ok "$(yes_if 'echo "$H" | grep -q "ทดสอบประกาศ"')" "แสดงประกาศหน้าแรก"
ok "$(yes_if '[ "$(curl -s -o /dev/null -w "%{http_code}" $B/donate.php)" = "301" ] && [ "$(q "SELECT setting_value FROM system_settings WHERE setting_key=\"thanks_enabled\"")" = "1" ]')" "หน้า donate เดิมส่งต่อไปหน้าแรก และเปิดระบบขอบคุณ Lab boy ได้"
ok "$(yes_if '[ "$(curl -s -o /dev/null -w "%{http_code}" "$B/cron_queue.php?token=abc")" = "403" ]')" "cron_queue ปิดเมื่อไม่มี token"
echo; echo "RESULT: pass=$pass fail=$fail"
