<?php

// คัดลอกไฟล์นี้เป็น config.local.php บนเซิร์ฟเวอร์ แล้วกรอกค่าจริง
return [
    // ใช้ '' เมื่อติดตั้งที่ Document Root หรือ '/evaluations' เมื่อติดตั้งในโฟลเดอร์ย่อย
    'base_path' => '/evaluations',
    'db_host' => 'localhost',
    'db_port' => 3306,
    'db_name' => 'ชื่อฐานข้อมูล',
    'db_user' => 'ชื่อผู้ใช้ฐานข้อมูล',
    'db_password' => 'รหัสผ่านฐานข้อมูล',
];
