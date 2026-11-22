#!/bin/bash
# REPS · ชุดทดสอบ end-to-end (HTTP จริง + ตรวจฐานข้อมูล) — ใช้กับเครื่องพัฒนาเท่านั้น
#
#   set -a; . /path/to/reps_v2_db_test.env; set +a
#   bash tests/e2e/run.sh
#
# สคริปต์จะ: สำรองฐานข้อมูลและโฟลเดอร์รูป -> เตรียมบัญชี t_* -> ลบข้อมูลจำลอง -> รันทุกชุด -> คืนฐานข้อมูลและรูปเดิมเสมอ
# เลือกชุดเฉพาะได้ด้วย E2E_SUITES=01_core.sh,04_site_custom.sh (ไม่กำหนด = รันครบ)
# Required: DB_HOST/PORT/NAME/USER/PASSWORD, MYSQL_DEFAULTS_FILE, TICKET_UPLOAD_DIR, REPS_E2E=1
set -u
set -o pipefail
cd "$(dirname "$0")/../.."
ROOT="$(pwd)"
export REPS_ROOT="$ROOT"
export PHP="${PHP:-php}" MYSQL="${MYSQL:-mysql}" MYSQLDUMP="${MYSQLDUMP:-mysqldump}"
export REPS_BASE_URL="${REPS_BASE_URL:-http://127.0.0.1:8765}"

for key in DB_HOST DB_PORT DB_NAME DB_USER DB_PASSWORD MYSQL_DEFAULTS_FILE TICKET_UPLOAD_DIR REPS_E2E; do
  if [ -z "${!key:-}" ]; then echo "ปฏิเสธ: ต้องกำหนด $key สำหรับฐานข้อมูลทดสอบที่แยกไว้"; exit 1; fi
done
if [ "$DB_HOST" != "127.0.0.1" ] || [ "$DB_PORT" != "3307" ] || [ "$DB_NAME" != "reps_v2_db_test" ] || [ "$DB_USER" != "reps_e2e" ] || [ "$REPS_E2E" != "1" ]; then
  echo "ปฏิเสธ: E2E อนุญาตเฉพาะ 127.0.0.1:3307/reps_v2_db_test ด้วยบัญชีทดสอบ"
  exit 1
fi
if [ ! -f "$MYSQL_DEFAULTS_FILE" ] || [ "$(stat -c '%a' "$MYSQL_DEFAULTS_FILE" 2>/dev/null)" != "600" ]; then
  echo "ปฏิเสธ: MYSQL_DEFAULTS_FILE ต้องเป็นไฟล์สิทธิ์ 600"
  exit 1
fi

UPLOAD_ROOT="$(realpath -m -- "$TICKET_UPLOAD_DIR")"
case "$UPLOAD_ROOT" in
  "$ROOT"|"$ROOT"/*) echo "ปฏิเสธ: upload path ต้องอยู่นอก document root"; exit 1 ;;
esac
if [ "$(basename "$UPLOAD_ROOT")" != "reps_e2e_uploads" ] || [ ! -f "$UPLOAD_ROOT/.reps-e2e-upload-root" ] || [ "$(cat "$UPLOAD_ROOT/.reps-e2e-upload-root" 2>/dev/null)" != "REPS_E2E_UPLOADS_V1" ] || [ ! -d "$UPLOAD_ROOT/tickets" ]; then
  echo "ปฏิเสธ: upload path ไม่ใช่ไดเรกทอรี E2E ที่เตรียมไว้"
  exit 1
fi

MYSQL_BASE=("$MYSQL" "--defaults-file=$MYSQL_DEFAULTS_FILE" --protocol=tcp --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USER")
MYSQLDUMP_BASE=("$MYSQLDUMP" "--defaults-file=$MYSQL_DEFAULTS_FILE" --protocol=tcp --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USER")
export DB_HOST DB_PORT DB_NAME DB_USER DB_PASSWORD TICKET_UPLOAD_DIR REPS_E2E
export MYSQL_ARGS="--defaults-file=$MYSQL_DEFAULTS_FILE --protocol=tcp --host=$DB_HOST --port=$DB_PORT --user=$DB_USER"
export MAIL_TRANSPORT=smtp MAIL_FROM=disabled MAIL_USERNAME=disabled MAIL_PASSWORD=disabled BREVO_API_KEY=disabled
export LINE_CHANNEL_ACCESS_TOKEN=disabled LINE_TARGET_ID=disabled

if [ "$REPS_BASE_URL" != "http://127.0.0.1:8765" ]; then
  echo "ปฏิเสธ: E2E web server ต้องใช้ http://127.0.0.1:8765"
  exit 1
fi

CLI_IDENTITY="$("${MYSQL_BASE[@]}" --batch --skip-column-names "$DB_NAME" -e 'SELECT CONCAT_WS("|", DATABASE(), @@hostname, @@port, @@server_id)' 2>/dev/null)"
PHP_IDENTITY="$("$PHP" -r '$GLOBALS["APP_SKIP_SESSION"]=true; require "db_connect.php"; $r=$conn->query("SELECT CONCAT_WS(\"|\", DATABASE(), @@hostname, @@port, @@server_id) AS identity_value"); if (!$r) { exit(2); } echo $r->fetch_assoc()["identity_value"];' 2>/dev/null)"
if [ -z "$CLI_IDENTITY" ] || [ "$CLI_IDENTITY" != "$PHP_IDENTITY" ]; then
  echo "ปฏิเสธ: PHP และ MySQL CLI ไม่ได้เชื่อมต่อฐานข้อมูลเดียวกัน"
  exit 1
fi

WORK="$(mktemp -d)"
# บน Windows (Git Bash) curl/php เป็นโปรแกรม Windows อ่าน path แบบ /tmp ไม่ได้ จึงแปลงเป็น C:/... ก่อน
command -v cygpath >/dev/null 2>&1 && WORK="$(cygpath -m "$WORK")"
cp tests/e2e/fixtures/* "$WORK/"
UPLOADS="$($PHP -r '$GLOBALS["APP_SKIP_SESSION"]=true; require "db_connect.php"; require "includes/private_storage.php"; echo app_ticket_upload_dir();' 2>/dev/null)"
UPLOADS_REAL="$(realpath -m -- "$UPLOADS")"
if [ "$UPLOADS_REAL" != "$UPLOAD_ROOT/tickets" ] || [ -L "$UPLOADS" ] || [ ! -d "$UPLOADS" ]; then
  echo "ปฏิเสธ: PHP upload path ไม่ตรงกับไดเรกทอรี E2E ที่เตรียมไว้"
  rm -rf "$WORK"
  exit 1
fi

echo "สำรองฐานข้อมูลทดสอบ $DB_NAME และรูปใน $UPLOADS"
"${MYSQLDUMP_BASE[@]}" --single-transaction --routines --skip-dump-date "$DB_NAME" > "$WORK/db_backup.sql" && [ -s "$WORK/db_backup.sql" ] || { echo "สำรองฐานข้อมูลไม่สำเร็จ"; rm -rf "$WORK"; exit 1; }
mkdir "$WORK/uploads_backup"
upload_manifest() { (cd "$1" && find . -type f -print0 | sort -z | xargs -0 -r sha256sum); }
upload_manifest "$UPLOADS" > "$WORK/uploads.before.sha256"
cp -a "$UPLOADS/." "$WORK/uploads_backup/" || { echo "สำรองรูปไม่สำเร็จ หยุดก่อนแก้ข้อมูล"; rm -rf "$WORK"; exit 1; }
upload_manifest "$WORK/uploads_backup" > "$WORK/uploads.backup.sha256"
if ! cmp -s "$WORK/uploads.before.sha256" "$WORK/uploads.backup.sha256"; then echo "สำรองรูปไม่ครบ หยุดก่อนแก้ข้อมูล"; rm -rf "$WORK"; exit 1; fi

db_fingerprint() { "${MYSQLDUMP_BASE[@]}" --single-transaction --skip-dump-date --skip-comments --compact --order-by-primary "$DB_NAME" | sha256sum | cut -d' ' -f1; }
BEFORE_DB_FINGERPRINT="$(db_fingerprint)"
[ -n "$BEFORE_DB_FINGERPRINT" ] || { echo "คำนวณ fingerprint ฐานข้อมูลก่อนทดสอบไม่ได้"; rm -rf "$WORK"; exit 1; }
count_rows() { "${MYSQL_BASE[@]}" --batch --skip-column-names "$DB_NAME" -e "SELECT CONCAT((SELECT COUNT(*) FROM tickets),'/',(SELECT COUNT(*) FROM users),'/',(SELECT COUNT(*) FROM ticket_logs))" 2>/dev/null | tr -d '\r'; }
BEFORE_COUNTS="$(count_rows)"
[ -n "$BEFORE_COUNTS" ] || { echo "อ่านจำนวนแถวก่อนทดสอบไม่ได้ หยุดก่อนแก้ข้อมูล"; rm -rf "$WORK"; exit 1; }

RESTORED=0
SERVER_PID=""
restore() {
  [ "$RESTORED" = 1 ] && return
  RESTORED=1
  if [ -n "$SERVER_PID" ]; then
    kill "$SERVER_PID" 2>/dev/null || true
    wait "$SERVER_PID" 2>/dev/null || true
    SERVER_PID=""
  fi
  echo "คืนฐานข้อมูลและรูปเดิม"
  if ! "${MYSQL_BASE[@]}" "$DB_NAME" < "$WORK/db_backup.sql"; then
    echo "!! คืนฐานข้อมูลทดสอบไม่สำเร็จ — เก็บไฟล์สำรองไว้ที่ $WORK/db_backup.sql"
    return 1
  fi
  AFTER_COUNTS="$(count_rows)"
  AFTER_DB_FINGERPRINT="$(db_fingerprint)"
  if [ "$AFTER_COUNTS" != "$BEFORE_COUNTS" ] || [ "$AFTER_DB_FINGERPRINT" != "$BEFORE_DB_FINGERPRINT" ]; then
    echo "!! ตรวจฐานข้อมูลหลังคืนไม่ผ่าน — เก็บไฟล์สำรองไว้ที่ $WORK"
    return 1
  fi
  echo "ตรวจฐานข้อมูลหลังคืนครบ ($AFTER_COUNTS)"
  if [ "$(realpath -m -- "$UPLOADS")" != "$UPLOAD_ROOT/tickets" ] || [ -L "$UPLOADS" ] || [ "$(cat "$UPLOAD_ROOT/.reps-e2e-upload-root" 2>/dev/null)" != "REPS_E2E_UPLOADS_V1" ]; then
    echo "!! upload path เปลี่ยนไปจากไดเรกทอรี E2E — ไม่ลบหรือคืนไฟล์"
    return 1
  fi
  rm -rf "$UPLOADS"
  mkdir -m 700 "$UPLOADS"
  cp -a "$WORK/uploads_backup/." "$UPLOADS/"
  upload_manifest "$UPLOADS" > "$WORK/uploads.restored.sha256"
  if ! cmp -s "$WORK/uploads.before.sha256" "$WORK/uploads.restored.sha256"; then
    echo "!! ตรวจ SHA-256 หลังคืนรูปไม่ผ่าน — เก็บไฟล์สำรองไว้ที่ $WORK"
    return 1
  fi
  rm -rf "$WORK"
}
trap restore EXIT
trap 'exit 130' INT TERM

"$PHP" -S 127.0.0.1:8765 -t "$ROOT" > "$WORK/php-server.log" 2>&1 &
SERVER_PID=$!
READY=0
for _ in $(seq 1 30); do
  if ! kill -0 "$SERVER_PID" 2>/dev/null; then break; fi
  if grep -q 'Development Server' "$WORK/php-server.log" && curl -fsS -o /dev/null "$REPS_BASE_URL/login.php"; then
    READY=1
    break
  fi
  sleep 1
done
if [ "$READY" != 1 ]; then
  echo "เว็บทดสอบภายในไม่พร้อม — ดูบันทึกที่ $WORK/php-server.log"
  exit 1
fi

$PHP tests/e2e/setup_accounts.php || { echo "!! เตรียมบัญชีทดสอบไม่สำเร็จ หยุดก่อนรันชุดทดสอบ"; exit 1; }
$PHP -d display_errors=1 database/seeds/seed_demo.php --clean >/dev/null || { echo "!! ล้างข้อมูลจำลองไม่สำเร็จ (ข้อความ error ด้านบน) หยุดก่อนรันชุดทดสอบ"; exit 1; }

TOTAL_FAIL=0
SUITES=(tests/e2e/[0-9][0-9]_*.sh)
SELECTED_SUITES=()
if [ -n "${E2E_SUITES:-}" ]; then
  IFS=, read -r -a REQUESTED_SUITES <<< "$E2E_SUITES"
  for requested in "${REQUESTED_SUITES[@]}"; do
    if [[ ! "$requested" =~ ^[0-9][0-9]_[a-z0-9_]+\.sh$ ]] || [ ! -f "tests/e2e/$requested" ]; then
      echo "ปฏิเสธ: ไม่รู้จัก E2E suite: $requested"
      exit 1
    fi
    SELECTED_SUITES+=("tests/e2e/$requested")
  done
else
  SELECTED_SUITES=("${SUITES[@]}")
fi

for suite in "${SELECTED_SUITES[@]}"; do
  echo "== $(basename "$suite")"
  OUT="$(bash "$suite" "$WORK" 2>&1)"
  if [ -n "${E2E_SUITES:-}" ]; then
    printf '%s\n' "$OUT"
  else
    echo "$OUT" | grep -E "FAIL|RESULT"
  fi
  F=$(echo "$OUT" | grep -o 'fail=[0-9]*' | tail -1 | cut -d= -f2)
  TOTAL_FAIL=$((TOTAL_FAIL + ${F:-1}))
  if [ "${F:-1}" -gt 0 ] && [ -s "$WORK/php-server.log" ]; then
    echo "-- PHP server errors for $(basename "$suite")"
    grep -Ei 'fatal error|uncaught|warning|mysqli|SQLSTATE' "$WORK/php-server.log" | tail -n 30 || true
  fi
done
echo "รวมไม่ผ่าน: $TOTAL_FAIL"
[ "$TOTAL_FAIL" -eq 0 ]
