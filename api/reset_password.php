<?php
// reset_password.php - taruh di folder skl/api/ lalu akses via browser SEKALI SAJA
// Hapus file ini setelah berhasil!

require_once __DIR__ . '/database.php';

$newPassword = 'admin123';
$hash = password_hash($newPassword, PASSWORD_DEFAULT);

$db = getDB();
$db->prepare("UPDATE admin_users SET password=?, login_attempts=0, aktif=1 WHERE username='admin'")->execute([$hash]);

echo '<h2>✅ Password berhasil direset!</h2>';
echo '<p>Username: <strong>admin</strong></p>';
echo '<p>Password baru: <strong>' . $newPassword . '</strong></p>';
echo '<p style="color:red;"><strong>Hapus file ini sekarang!</strong> Akses: <a href="../admin.html">Admin Panel</a></p>';
?>