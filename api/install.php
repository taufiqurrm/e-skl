<?php
// skl/api/install.php
// ────────────────────────────────────────────────────────────
// Jalankan SATU KALI untuk membuat semua tabel dan akun admin.
// Setelah selesai, HAPUS atau RENAME file ini demi keamanan.
// ────────────────────────────────────────────────────────────

// Konfigurasi (samakan dengan database.php)
define('DB_HOST',    'localhost');
define('DB_NAME',    'skl_db');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

// Akun admin default (GANTI sebelum deploy ke produksi!)
define('ADMIN_USERNAME',  'admin');
define('ADMIN_PASSWORD',  'Admin@1234');
define('ADMIN_FULLNAME',  'Administrator');

header('Content-Type: text/html; charset=UTF-8');

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES); }

try {
    // Buat database jika belum ada
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";charset=".DB_CHARSET,
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `".DB_NAME."` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `".DB_NAME."`");

    // ── Tabel: admin_users ────────────────────────────────────
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_users (
            id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username       VARCHAR(60) NOT NULL UNIQUE,
            password       VARCHAR(255) NOT NULL,
            full_name      VARCHAR(120) NOT NULL,
            role           ENUM('superadmin','admin') NOT NULL DEFAULT 'admin',
            aktif          TINYINT(1) NOT NULL DEFAULT 1,
            login_attempts INT NOT NULL DEFAULT 0,
            last_attempt   DATETIME NULL,
            last_login     DATETIME NULL,
            created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // ── Tabel: pengaturan ─────────────────────────────────────
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pengaturan (
            id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            kunci   VARCHAR(60) NOT NULL UNIQUE,
            nilai   TEXT NOT NULL DEFAULT '',
            updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // ── Tabel: skl_setting ────────────────────────────────────
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS skl_setting (
            id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tahun_ajaran VARCHAR(20) NOT NULL,
            tanggal_buka DATETIME NOT NULL,
            aktif        TINYINT(1) NOT NULL DEFAULT 0,
            created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // ── Tabel: skl_nilai ─────────────────────────────────────
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS skl_nilai (
            id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            skl_setting_id INT UNSIGNED NOT NULL,
            nisn           VARCHAR(20) NOT NULL,
            nama           VARCHAR(120) NOT NULL,
            kelas          VARCHAR(30) NOT NULL DEFAULT 'Kelas 9',
            tempat_lahir   VARCHAR(80) NULL,
            tgl_lahir      DATE NULL,
            no_peserta     VARCHAR(30) NULL,
            no_skl         VARCHAR(50) NULL,
            status_lulus   TINYINT(1) NOT NULL DEFAULT 1,
            -- PAI
            pai_qh  TINYINT UNSIGNED NULL,
            pai_aa  TINYINT UNSIGNED NULL,
            pai_fik TINYINT UNSIGNED NULL,
            pai_ski TINYINT UNSIGNED NULL,
            -- Umum
            ppkn    TINYINT UNSIGNED NULL,
            bind    TINYINT UNSIGNED NULL,
            bar     TINYINT UNSIGNED NULL,
            mtk     TINYINT UNSIGNED NULL,
            ipa     TINYINT UNSIGNED NULL,
            ips     TINYINT UNSIGNED NULL,
            bing    TINYINT UNSIGNED NULL,
            sb      TINYINT UNSIGNED NULL,
            pjok    TINYINT UNSIGNED NULL,
            prkti   TINYINT UNSIGNED NULL,
            mlk1    TINYINT UNSIGNED NULL,

            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_setting_nisn (skl_setting_id, nisn),
            FOREIGN KEY (skl_setting_id) REFERENCES skl_setting(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // ── Tabel: skl_ratelimit ──────────────────────────────────
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS skl_ratelimit (
            ip_addr  VARCHAR(45) NOT NULL PRIMARY KEY,
            attempts INT NOT NULL DEFAULT 0,
            blocked  TINYINT(1) NOT NULL DEFAULT 0,
            last_try DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // ── Insert pengaturan default ─────────────────────────────
    $defaults = [
        'nama_sekolah'   => 'MTs. Bustanul Ulum',
        'npsn'           => '20583489',
        'kepala_sekolah' => 'AGUS SUPARMANTO, S.Pd.',
        'nip_kepsek'     => '-',
        'kota'           => 'Pamekasan',
        'website'        => 'www.mtsbuwaru.sch.id',
        'email'          => 'mtsbuwaru@gmail.com',
        'logo_kiri'      => '',
        'logo_kanan'     => '',
    ];
    $stmt = $pdo->prepare(
        "INSERT IGNORE INTO pengaturan (kunci, nilai) VALUES (?, ?)"
    );
    foreach ($defaults as $k => $v) $stmt->execute([$k, $v]);

    // ── Insert admin default ──────────────────────────────────
    $hash = password_hash(ADMIN_PASSWORD, PASSWORD_DEFAULT);
    $pdo->prepare("
        INSERT IGNORE INTO admin_users (username, password, full_name, role)
        VALUES (?, ?, ?, 'superadmin')
    ")->execute([ADMIN_USERNAME, $hash, ADMIN_FULLNAME]);

    echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8">
    <title>Instalasi SKL</title>
    <style>body{font-family:sans-serif;max-width:600px;margin:60px auto;background:#f8fafc;color:#1e293b;}
    .box{background:#fff;border-radius:12px;padding:32px;box-shadow:0 2px 20px rgba(0,0,0,.08);}
    h2{color:#0b1f3a;} .ok{color:#16a34a;} .warn{color:#d97706;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:14px 18px;margin-top:20px;}
    pre{background:#f1f5f9;padding:12px;border-radius:8px;font-size:13px;}
    a{color:#1d4ed8;}</style></head><body>
    <div class="box">
    <h2>✅ Instalasi Berhasil</h2>
    <p>Semua tabel berhasil dibuat di database <strong>'.h(DB_NAME).'</strong>.</p>
    <h3>Akun Admin Default</h3>
    <pre>Username : '.h(ADMIN_USERNAME).'
Password : '.h(ADMIN_PASSWORD).'</pre>
    <div class="warn">
      ⚠️ <strong>PENTING!</strong><br>
      1. <strong>Ganti password</strong> admin segera setelah login pertama.<br>
      2. <strong>Hapus atau rename</strong> file <code>install.php</code> ini agar tidak bisa diakses ulang.
    </div>
    <p style="margin-top:20px;"><a href="../admin.html">→ Pergi ke Halaman Admin</a></p>
    </div></body></html>';

} catch (PDOException $e) {
    http_response_code(500);
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Error</title>
    <style>body{font-family:sans-serif;max-width:600px;margin:60px auto;}
    .err{background:#fef2f2;border:1px solid #fca5a5;border-radius:8px;padding:20px;}</style></head><body>
    <div class="err"><h2>❌ Instalasi Gagal</h2>
    <p><strong>Error:</strong> '.h($e->getMessage()).'</p>
    <p>Periksa konfigurasi database di bagian atas file <code>install.php</code> dan <code>database.php</code>.</p>
    </div></body></html>';
}
