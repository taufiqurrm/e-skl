<?php
// skl/api/auth.php
require_once __DIR__ . '/database.php';

startSession();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    jsonResponse(true, null, 'OK');
}

$method = $_SERVER['REQUEST_METHOD'];
$action = trim($_GET['action'] ?? '');

// ── GET ?action=me — cek sesi aktif ───────────────────────────
if ($method === 'GET' && $action === 'me') {
    $user = currentUser();
    if ($user) {
        jsonResponse(true, $user);
    } else {
        jsonResponse(false, null, 'Belum login.', 401);
    }
}

// ── POST ?action=login ────────────────────────────────────────
if ($method === 'POST' && $action === 'login') {
    $input    = getInput();
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';

    if (!$username || !$password) {
        jsonResponse(false, null, 'Username dan password wajib diisi.', 400);
    }

    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM admin_users WHERE username = ? AND aktif = 1 LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        // Rate-limit sederhana — catat percobaan gagal
        $db->prepare("UPDATE admin_users SET login_attempts = login_attempts + 1, last_attempt = NOW() WHERE username = ?")
           ->execute([$username]);
        jsonResponse(false, null, 'Username atau password salah.', 401);
    }

    // Reset attempt counter
    $db->prepare("UPDATE admin_users SET login_attempts = 0, last_login = NOW() WHERE id = ?")
       ->execute([$user['id']]);

    // Set session
    session_regenerate_id(true);
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['username']  = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role']      = $user['role'];

    jsonResponse(true, [
        'id'        => $user['id'],
        'username'  => $user['username'],
        'full_name' => $user['full_name'],
        'role'      => $user['role'],
    ], 'Login berhasil.');
}

// ── POST ?action=logout ───────────────────────────────────────
if ($method === 'POST' && $action === 'logout') {
    $_SESSION = [];
    session_destroy();
    jsonResponse(true, null, 'Logout berhasil.');
}

// ── POST ?action=change_name ──────────────────────────────────
if ($method === 'POST' && $action === 'change_name') {
    requireAuth();
    $input    = getInput();
    $fullName = trim($input['full_name'] ?? '');

    if (!$fullName) {
        jsonResponse(false, null, 'Nama tidak boleh kosong.', 400);
    }
    if (strlen($fullName) > 120) {
        jsonResponse(false, null, 'Nama terlalu panjang (maks 120 karakter).', 400);
    }

    $db = getDB();
    $db->prepare("UPDATE admin_users SET full_name = ? WHERE id = ?")
       ->execute([$fullName, $_SESSION['user_id']]);

    $_SESSION['full_name'] = $fullName;

    jsonResponse(true, ['full_name' => $fullName], 'Nama berhasil diubah.');
}

// ── POST ?action=change_password ─────────────────────────────
if ($method === 'POST' && $action === 'change_password') {
    requireAuth();
    $input      = getInput();
    $oldPass    = $input['old_password'] ?? '';
    $newPass    = $input['new_password'] ?? '';

    if (!$oldPass || !$newPass) {
        jsonResponse(false, null, 'Password lama dan baru wajib diisi.', 400);
    }
    if (strlen($newPass) < 6) {
        jsonResponse(false, null, 'Password baru minimal 6 karakter.', 400);
    }

    $db   = getDB();
    $stmt = $db->prepare("SELECT password FROM admin_users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $row  = $stmt->fetch();

    if (!$row || !password_verify($oldPass, $row['password'])) {
        jsonResponse(false, null, 'Password lama tidak sesuai.', 400);
    }

    $db->prepare("UPDATE admin_users SET password = ? WHERE id = ?")
       ->execute([password_hash($newPass, PASSWORD_DEFAULT), $_SESSION['user_id']]);

    jsonResponse(true, null, 'Password berhasil diubah.');
}

jsonResponse(false, null, 'Endpoint tidak ditemukan.', 404);
