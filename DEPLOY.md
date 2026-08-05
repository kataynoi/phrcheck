# การติดตั้งบน Production Server

สำหรับเปิดใช้งานที่ `https://mkho-mis.moph.go.th/phrcheck/`
โดยมี nginx reverse proxy ของเซิร์ฟเวอร์รับหน้า และใช้ URL แบบไม่มี `index.php`

ทำตามลำดับ 1 → 7 ครับ

---

## 1. ตรวจสิ่งที่ต้องมีก่อน

รันบน production server:

```bash
cd /path/to/phrCheck

docker --version && docker compose version   # ต้องมี Docker Compose v2
ls -lh data_sample/data.sql                  # ต้องมี ~7.8 MB ถ้าไม่มีระบบจะสร้างตารางไม่ได้
ss -lntp | grep -E ':8087|:3311'             # ต้องไม่มีอะไรใช้พอร์ตนี้อยู่
```

ถ้าพอร์ตชนกับระบบอื่น เปลี่ยนได้ที่ `NGINX_BIND` / `DB_BIND` ในขั้นตอนถัดไป

---

## 2. สร้างไฟล์ `.env`

`.env` ถูก gitignore ไว้ (มีรหัสผ่านและ LINE Channel Secret) จึง **ไม่ได้ตามมากับ clone** ต้องสร้างเอง:

```bash
cp .env.example .env
nano .env
```

กรอกให้เป็นแบบนี้ — สังเกตว่าเปิดใช้ "ชุดที่ 2 PRODUCTION" และปิดชุด LOCAL:

```ini
DB_NAME=phrcheck_db
DB_USER=phrcheck_user
DB_PASS=<<ตั้งรหัสผ่านใหม่ อย่าใช้ตัวเดียวกับเครื่องทดสอบ>>
DB_ROOT_PASS=<<ตั้งรหัสผ่านใหม่>>

CI_ENVIRONMENT=production
APP_BASE_URL=https://mkho-mis.moph.go.th/phrcheck/
APP_INDEX_PAGE=
LINE_CALLBACK_URL=https://mkho-mis.moph.go.th/phrcheck/login/callback
COOKIE_SECURE=true
TRUSTED_PROXY_IPS=127.0.0.1
NGINX_BIND=127.0.0.1:8087
DB_BIND=127.0.0.1:3311

LINE_CHANNEL_ID=2010937076
LINE_CHANNEL_SECRET=<<Channel Secret>>
ADMIN_LINE_USER_ID=Uf1f5e2e4b22b5b88655d982ed0721ef0
```

จุดที่พลาดกันบ่อย:

| ตั้งค่า | ถ้าตั้งผิดจะเกิดอะไร |
|---|---|
| `CI_ENVIRONMENT=production` | ถ้าเป็น `development` เวลา error หน้าเว็บจะโชว์ stack trace, path ของไฟล์ และ debug toolbar ให้คนนอกเห็น |
| `APP_INDEX_PAGE=` (ว่าง) | ถ้าใส่ `index.php` ลิงก์ทุกอันจะกลายเป็น `/phrcheck/index.php/...` ไม่ตรงกับ Callback URL |
| `COOKIE_SECURE=true` | ถ้าเป็น `false` session cookie จะถูกส่งข้าม http ได้ด้วย |
| `NGINX_BIND=127.0.0.1:8087` | ถ้าเป็น `0.0.0.0` เว็บจะเข้าถึงได้ตรงทางพอร์ต 8087 ข้าม https ไปเลย |
| `DB_BIND=127.0.0.1:3311` | ถ้าเป็น `0.0.0.0` **MySQL จะเปิดออกอินเทอร์เน็ต** ให้ใครก็ได้ลองเดารหัสผ่าน |

ตั้งสิทธิ์ไฟล์ให้อ่านได้เฉพาะเจ้าของ:

```bash
chmod 600 .env
```

---

## 3. Build และ start

```bash
docker compose up -d --build
docker compose ps
```

ครั้งแรก MySQL จะ import `data.sql` (~1 นาที) รอจนเสร็จแล้วตรวจว่าตารางครบ:

```bash
docker compose logs -f db          # รอจนขึ้น "ready for connections" รอบที่สอง
docker compose exec db mysql -u root -p"$DB_ROOT_PASS" phrcheck_db \
  -e "SELECT COUNT(*) FROM hospitals; SELECT COUNT(*) FROM check_statuses;"
```

ต้องได้ **420** และ **6** ถ้าได้ 0 หรือ error แปลว่า `data.sql` ไม่ครบ — ดูหัวข้อแก้ปัญหาท้ายไฟล์

ทดสอบว่าแอปตอบจากในเครื่อง:

```bash
curl -I http://127.0.0.1:8087/login     # ต้องได้ 200
```

**ตรวจว่าโหมดเป็น production จริง** (สำคัญ — ถ้ายังเป็น development หน้า error จะโชว์ path ไฟล์และ stack trace ให้คนนอกเห็น)

วิธีที่ชัดที่สุด ถามระบบตรง ๆ:

```bash
docker compose exec php php spark env
```

ต้องขึ้นว่า `Your environment is currently set as production.`

ตรวจซ้ำจากฝั่งเว็บ — ในโหมด development CodeIgniter จะฝัง debug toolbar ลงในหน้าเว็บ:

```bash
curl -s https://mkho-mis.moph.go.th/phrcheck/login | grep -c debugbar
```

| ผลลัพธ์ | แปลว่า |
|---|---|
| `0` | production ✓ |
| `1` ขึ้นไป | ยังเป็น development ✗ |

> ⚠️ **อย่าใช้หน้า 404 ตัดสิน** — CodeIgniter แสดงหน้า `404 Sorry! Cannot seem to find the page...` เหมือนกันทั้งสองโหมด ไม่ได้บอกอะไรเลย
> ต้องดูจากหน้าปกติ (เช่น `/login`) ว่ามี debug toolbar โผล่มุมล่างขวาหรือไม่

ถ้ายังได้ `development` แปลว่าแก้ `.env` แล้วยังไม่ได้สร้าง container ใหม่ — **`docker compose restart` ไม่พอ** ต้อง:

```bash
docker compose up -d
```

---

## 4. ตั้ง nginx reverse proxy

เอา config จาก [`docker/nginx/reverse-proxy-snippet.conf`](docker/nginx/reverse-proxy-snippet.conf) ไปใส่ใน server block ของ `mkho-mis.moph.go.th`:

```nginx
location /phrcheck/ {
    proxy_pass http://127.0.0.1:8087/;      # <-- / ท้ายบรรทัดนี้ห้ามลืม

    proxy_http_version 1.1;
    proxy_set_header Host              $host;
    proxy_set_header X-Real-IP         $remote_addr;
    proxy_set_header X-Forwarded-For   $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header X-Forwarded-Host  $host;

    client_max_body_size 64M;
    proxy_read_timeout   300s;
    proxy_send_timeout   300s;
}
```

**เครื่องหมาย `/` ท้าย `proxy_pass` สำคัญที่สุดในไฟล์นี้**

- มี `/` → `/phrcheck/login` ถึงแอปเป็น `/login` ✓
- ไม่มี `/` → ถึงแอปเป็น `/phrcheck/login` → **404 ทุกหน้า**

`client_max_body_size 64M` ก็จำเป็น ไม่งั้นอัปโหลด `.xlsx` ไฟล์ใหญ่จะได้ `413 Request Entity Too Large` ตั้งแต่ยังไม่ถึงแอป

```bash
nginx -t && systemctl reload nginx
curl -I https://mkho-mis.moph.go.th/phrcheck/login    # ต้องได้ 200
```

---

## 5. ตั้งค่าที่ LINE Developers Console

เข้า console → เลือก provider → LINE Login channel (ID `2010937076`)

1. **LINE Login settings → Callback URL** เพิ่มบรรทัดนี้ (ใส่หลายอันพร้อมกันได้ เก็บของ localhost ไว้ทดสอบต่อ):

   ```
   https://mkho-mis.moph.go.th/phrcheck/login/callback
   ```

   ต้องตรงกับ `LINE_CALLBACK_URL` ใน `.env` **ทุกตัวอักษร** — ต่างกันแค่ `/` ท้ายก็ไม่ผ่าน

2. **เปลี่ยนสถานะ channel เป็น Published**

   ถ้ายังเป็น *Developing* คนที่ไม่มี role ใน console จะเจอ `400 Bad Request — This channel is now developing status`

   > ⚠️ **publish แล้วย้อนกลับไม่ได้** ถ้าจะทำให้ private อีกต้องลบ channel ทิ้ง
   > ถ้ายังอยากทดสอบวงแคบก่อน ให้ใช้แท็บ **Roles → Invite by email** เพิ่มคนเป็น *Tester* แทน

---

## 6. เข้าใช้งานครั้งแรก

1. เปิด `https://mkho-mis.moph.go.th/phrcheck/`
2. ล็อกอินด้วยบัญชี LINE ที่ตรงกับ `ADMIN_LINE_USER_ID` → ได้สิทธิ์ admin และผ่านอนุมัติอัตโนมัติ
3. กรอกชื่อ-นามสกุล และเลือกหน่วยบริการ
4. คนอื่นที่สมัครเข้ามาจะอยู่สถานะ *รออนุมัติ* จนกว่า admin จะกดอนุมัติที่เมนู **จัดการผู้ใช้**

---

## 7. ตรวจความเรียบร้อยหลังเปิดใช้

```bash
# ต้อง "ไม่" เห็นหน้าเว็บจากภายนอกทางพอร์ต 8087 และต้องต่อ MySQL จากข้างนอกไม่ได้
curl -I http://mkho-mis.moph.go.th:8087/login      # ควรติดต่อไม่ได้
nc -zv mkho-mis.moph.go.th 3311                    # ควรติดต่อไม่ได้

# ต้องไม่มี debug toolbar หลุดออกมา
curl -s https://mkho-mis.moph.go.th/phrcheck/login | grep -c debugbar    # ต้องได้ 0
```

---

## การดูแลหลังติดตั้ง

**ดู log**

```bash
docker compose logs -f php
docker compose exec php tail -f writable/logs/log-$(date +%Y-%m-%d).log
```

**สำรองฐานข้อมูล** (ข้อมูลจริงมีเลขบัตรประชาชน เก็บไฟล์ backup ให้ปลอดภัยด้วย)

```bash
docker compose exec db mysqldump -u root -p"$DB_ROOT_PASS" phrcheck_db \
  | gzip > backup_phrcheck_$(date +%Y%m%d).sql.gz
```

**อัปเดตโค้ด**

```bash
git pull
docker compose up -d --build
```

ถ้าแก้ `src/composer.json` ต้องล้าง volume ของ vendor ด้วย ไม่งั้นยังได้ของเดิม:

```bash
docker compose build php && docker compose rm -fsv php && docker compose up -d php
```

**นำเข้าไฟล์จำนวนมากทาง CLI**

```bash
docker compose cp ไฟล์.xlsx php:/tmp/a.xlsx
docker compose exec php php spark phr:import /tmp/a.xlsx --user 1
```

---

## แก้ปัญหาที่พบบ่อย

| อาการ | สาเหตุ / วิธีแก้ |
|---|---|
| ทุกหน้า 404 หลังผ่าน proxy | ลืม `/` ท้าย `proxy_pass` |
| `400 Bad Request` จาก LINE ตอนกดล็อกอิน | channel ยังเป็น Developing หรือ Callback URL ไม่ตรงกับ `.env` เป๊ะ |
| ล็อกอินแล้วเด้งกลับหน้า login วนไม่จบ | `COOKIE_SECURE=true` แต่เข้าผ่าน http (ต้องเข้าทาง https) |
| CSS/JS ไม่ขึ้น รูปแตก | `APP_BASE_URL` ไม่ตรงกับ URL จริง หรือลืม `/` ปิดท้าย |
| `413 Request Entity Too Large` ตอนอัปโหลด | ลืม `client_max_body_size 64M` ที่ nginx ตัวหน้า |
| `hospitals` มี 0 แถว | `data.sql` ไม่ครบตอน start ครั้งแรก — MySQL รัน initdb แค่ตอน volume ว่างเท่านั้น ต้อง `docker compose down -v` แล้ว `up -d` ใหม่ (**ลบข้อมูลทั้งหมด** สำรองก่อน) |
| `Class "..." not found` หลังอัปเดต | vendor volume ค้างของเดิม ใช้คำสั่ง `rm -fsv php` ด้านบน |
