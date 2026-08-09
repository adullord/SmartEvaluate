<?php

// คัดลอกไฟล์นี้เป็น config.local.php บนเซิร์ฟเวอร์ แล้วกรอกค่าจริง
return [
    // ใช้ '' เมื่อติดตั้งที่ Document Root หรือ '/SmartEvaluate' เมื่อติดตั้งในโฟลเดอร์ย่อย
    'base_path' => '/SmartEvaluate',
    'db_host' => 'localhost',
    'db_port' => 3306,
    'db_name' => '',
    'db_user' => '',
    'db_password' => '',

    // ใช้เฉพาะการสร้างแอดมินครั้งแรก ต้องตั้งรหัสผ่านสุ่มอย่างน้อย 12 ตัวอักษร
    // เมื่อล็อกอินได้แล้วให้ลบ 2 ค่านี้ออกจาก config.local.php
    'initial_admin_username' => '',
    'initial_admin_password' => '',
];
