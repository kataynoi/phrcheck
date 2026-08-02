#!/bin/sh
set -e

# ./src ถูก bind mount จาก Windows host — สิทธิ์ไม่ติดมาด้วย
# สร้างโฟลเดอร์ writable ที่ CI4 ต้องใช้ให้ครบทุกครั้งที่ container start
for d in cache logs session uploads debugbar; do
    mkdir -p "/var/www/html/writable/$d"
done
chmod -R 0777 /var/www/html/writable 2>/dev/null || true

exec "$@"
