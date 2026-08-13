# SmartEvaluate

ระบบประเมินผลการปฏิบัติราชการ สำหรับสำนักงานสาธารณสุขอำเภอและโรงพยาบาลส่งเสริมสุขภาพตำบล

## ความต้องการของระบบ

- PHP 8.0 ขึ้นไป
- MySQL หรือ MariaDB
- PHP extensions: `pdo_mysql`, `mbstring`, `fileinfo`, `gd`, `xml`, `zip`
- Apache หรือ Web Server ที่รองรับ PHP

## การติดตั้ง

1. อัปโหลดไฟล์ระบบทั้งหมดไปยัง Web Server
2. สร้างฐานข้อมูลที่ใช้ `utf8mb4` แล้วนำเข้า `database.sql`
3. คัดลอก `config.local.example.php` เป็น `config.local.php`
4. กรอกค่าฐานข้อมูลและ `base_path` ให้ตรงกับเซิร์ฟเวอร์
5. ให้ Web Server เขียนข้อมูลลงเฉพาะโฟลเดอร์ `tmp`
6. หากไม่มีโฟลเดอร์ `vendor` ให้รัน:

   ```bash
   composer install --no-dev --optimize-autoloader
   ```

ตัวอย่าง `base_path`:

- ติดตั้งที่ Document Root ใช้ `''`
- ติดตั้งในโฟลเดอร์ `SmartEvaluate` ใช้ `'/SmartEvaluate'`

## ผู้ดูแลระบบครั้งแรก

ระบบไม่มีบัญชีหรือรหัสผ่านเริ่มต้นแบบตายตัว ให้กำหนดค่าต่อไปนี้ใน `config.local.php` เฉพาะการสร้างผู้ดูแลครั้งแรก:

```php
'initial_admin_username' => '',
'initial_admin_password' => '',
```

เมื่อล็อกอินได้แล้วให้ลบสองค่านี้ออกจาก `config.local.php`



## ผู้พัฒนา

สำนักงานสาธารณสุขอำเภอบันนังสตา จังหวัดยะลา
