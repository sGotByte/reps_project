#!/bin/bash
# รูปวัสดุในสต็อก (อัปโหลด เปลี่ยน ลบ สิทธิ์เปิดไฟล์) + ตัวเปิดรูปแบบ modal โหลดทุกหน้า
B="${REPS_BASE_URL:-http://127.0.0.1:8765}"
S="$1"; M="${MYSQL:-mysql}"
PW='Test1234!'
q(){ $M ${MYSQL_ARGS:--uroot} -N "${DB_NAME:-reps_v2_db}" -e "$1"; }
pass=0; fail=0
ok(){ if [ "$1" = "1" ]; then echo "  PASS $2"; pass=$((pass+1)); else echo "  FAIL $2"; fail=$((fail+1)); fi; }
csrf(){ grep -o 'name="csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//'; }
login(){ local jar="$S/$1.jar"; rm -f "$jar"; local t; t=$(curl -s -c "$jar" -b "$jar" $B/login.php | csrf); curl -s -c "$jar" -b "$jar" -o /dev/null --data-urlencode "csrf=$t" --data-urlencode "username=$1" --data-urlencode "password=$2" $B/login.php; }
get(){ curl -s -c "$S/$1.jar" -b "$S/$1.jar" "$B/$2"; }
code(){ curl -s -o /dev/null -w "%{http_code}" -b "$S/$1.jar" "$B/$2"; }
yes_if(){ if eval "$1"; then echo 1; fi; }
# save <who> <item_id> <name> [extra -F args]
save(){ local who=$1 id=$2 name=$3; shift 3; local t; t=$(get $who staff/inventory.php | csrf); curl -s -c "$S/$who.jar" -b "$S/$who.jar" -o /dev/null -F "csrf=$t" -F action=save_item -F item_id=$id -F "name=$name" -F unit=ชิ้น -F min_stock=1 -F stock_qty=3 "$@" "$B/staff/inventory.php"; }
img(){ q "SELECT IFNULL(image_path,'') FROM inventory_items WHERE id=$1"; }
# path จริงของไฟล์ที่เก็บใน private storage
fexists(){ [ -n "$1" ] && [ "$(${PHP:-php} -r 'require "db_connect.php"; require "includes/private_storage.php"; $p = app_private_file_abs($argv[1]); echo $p !== null && is_file($p) ? 1 : 0;' "$1" 2>/dev/null)" = "1" ]; }

for u in t_admin t_staff t_user; do login $u "$PW" >/dev/null; done
NAME="E2E รูปวัสดุ $$"

echo "== A. เพิ่มวัสดุพร้อมรูป =="
save t_admin 0 "$NAME" -F "item_image=@$S/photo.jpg;type=image/jpeg"
ID=$(q "SELECT id FROM inventory_items WHERE name='$NAME'")
P1=$(img $ID)
ok "$(yes_if '[ -n "$ID" ] && [[ "$P1" == ticket-private/inv_* ]]')" "เพิ่มวัสดุใหม่พร้อมรูป"
ok "$(yes_if 'fexists "$P1"')" "ไฟล์รูปถูกเก็บใน private storage"
ok "$(yes_if 'get t_admin staff/inventory.php | grep -q "file.php?k=inv&amp;i=$ID"')" "ตารางยอดคงเหลือมีปุ่มดูรูป"
ok "$(yes_if '[ "$(code t_admin "file.php?k=inv&i=$ID")" = "200" ]')" "ครูเปิดรูปวัสดุได้"
ok "$(yes_if '[ "$(code t_staff "file.php?k=inv&i=$ID")" = "200" ]')" "สตาฟเปิดรูปวัสดุได้"
ok "$(yes_if '[ "$(code t_user "file.php?k=inv&i=$ID")" = "404" ]')" "ผู้แจ้งเปิดรูปวัสดุไม่ได้"
save t_admin 0 "$NAME-fake" -F "item_image=@$S/fake.jpg;type=image/jpeg"
ok "$(yes_if '[ -z "$(q "SELECT id FROM inventory_items WHERE name=\"$NAME-fake\"")" ]')" "ไฟล์ปลอม -> ไม่เพิ่มวัสดุ"
save t_staff 0 "$NAME-staff" -F "item_image=@$S/photo.jpg;type=image/jpeg"
ok "$(yes_if '[ -z "$(q "SELECT id FROM inventory_items WHERE name=\"$NAME-staff\"")" ]')" "สตาฟเพิ่มวัสดุไม่ได้"

echo "== B. เปลี่ยน / ลบรูป =="
save t_admin $ID "$NAME"
ok "$(yes_if '[ "$(img $ID)" = "$P1" ]')" "แก้ข้อมูลโดยไม่แนบรูป -> รูปเดิมอยู่"
save t_admin $ID "$NAME" -F "item_image=@$S/photo.jpg;type=image/jpeg"
P2=$(img $ID)
ok "$(yes_if '[ -n "$P2" ] && [ "$P2" != "$P1" ] && ! fexists "$P1" && fexists "$P2"')" "เปลี่ยนรูป -> ไฟล์เก่าถูกลบ"
save t_admin $ID "$NAME" -F remove_image=1
ok "$(yes_if '[ -z "$(img $ID)" ] && ! fexists "$P2"')" "ลบรูป -> ล้างค่าและลบไฟล์"
ok "$(yes_if '[ "$(code t_admin "file.php?k=inv&i=$ID")" = "404" ]')" "วัสดุที่ไม่มีรูป -> 404"

echo "== C. ตัวเปิดรูปแบบ modal =="
ok "$(yes_if 'get t_admin staff/inventory.php | grep -q "assets/js/image-viewer.js"')" "หน้าสต็อกโหลดตัวเปิดรูป"
ok "$(yes_if 'get t_user dashboard.php | grep -q "assets/js/image-viewer.js"')" "หน้าผู้แจ้งโหลดตัวเปิดรูป"
ok "$(yes_if '[ "$(curl -s -o /dev/null -w "%{http_code}" "$B/assets/js/image-viewer.js")" = "200" ]')" "ไฟล์ image-viewer.js เปิดได้"

q "DELETE FROM inventory_movements WHERE item_id=$ID; DELETE FROM inventory_items WHERE id=$ID"
echo "RESULT: pass=$pass fail=$fail"
