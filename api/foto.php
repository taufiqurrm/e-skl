<?php
// skl/api/foto.php
require_once __DIR__ . '/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { jsonResponse(true, null, 'OK'); }

define('FOTO_DIR', __DIR__ . '/../uploads/foto/');
define('FOTO_URL', '../uploads/foto/');

$method = $_SERVER['REQUEST_METHOD'];
$nisn   = preg_replace('/[^0-9]/', '', trim($_GET['nisn'] ?? ''));
$action = trim($_GET['action'] ?? '');

// ── GET ?action=test ──────────────────────────────────────────
if ($method === 'GET' && $action === 'test') {
    requireAuth();
    $dir      = FOTO_DIR;
    $exists   = is_dir($dir);
    $created  = false;
    if (!$exists) $created = @mkdir($dir, 0755, true);
    $writable = is_dir($dir) && is_writable($dir);
    $testWrite = false;
    if ($writable) {
        $tf = $dir . '_test.txt';
        $testWrite = @file_put_contents($tf, 'ok') !== false;
        if ($testWrite) @unlink($tf);
    }
    jsonResponse(true, [
        'foto_dir'      => $dir,
        'dir_exists'    => is_dir($dir),
        'dir_writable'  => $writable,
        'dir_created'   => $created,
        'write_test'    => $testWrite,
        'php_version'   => PHP_VERSION,
        'post_max_size' => ini_get('post_max_size'),
        'upload_max'    => ini_get('upload_max_filesize'),
    ]);
}

// Buat folder otomatis jika belum ada
if (!is_dir(FOTO_DIR)) @mkdir(FOTO_DIR, 0755, true);

// ── GET ?nisn=xxx ─────────────────────────────────────────────
if ($method === 'GET') {
    if (!$nisn) jsonResponse(false, null, 'NISN wajib diisi.', 400);
    $file = findFoto($nisn);
    if (!$file) jsonResponse(false, null, 'Foto tidak ditemukan.', 404);
    $ext  = pathinfo($file, PATHINFO_EXTENSION);
    $mime = $ext === 'png' ? 'image/png' : 'image/jpeg';
    $b64  = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($file));
    jsonResponse(true, ['nisn' => $nisn, 'url' => FOTO_URL . basename($file), 'data' => $b64]);
}

// ── POST — upload (admin only) ────────────────────────────────
if ($method === 'POST') {
    requireAuth();
    if (!$nisn) jsonResponse(false, null, 'NISN wajib diisi.', 400);

    $binary = null;
    $ext    = 'jpg';

    // CARA 1: multipart file upload ($_FILES)
    if (!empty($_FILES['foto']['tmp_name'])) {
        $tmp  = $_FILES['foto']['tmp_name'];
        $err  = $_FILES['foto']['error'];
        if ($err !== UPLOAD_ERR_OK) {
            jsonResponse(false, null, 'Upload error code: ' . $err, 400);
        }
        if ($_FILES['foto']['size'] > 600 * 1024) {
            jsonResponse(false, null, 'Ukuran foto maks 600KB.', 400);
        }
        $binary = file_get_contents($tmp);
        $sig    = substr($binary, 0, 4);
        $ext    = ($sig === "\x89PNG") ? 'png' : 'jpg';

    // CARA 2: JSON body dengan field "data" = base64
    } else {
        $raw_body = file_get_contents('php://input');
        if (!$raw_body) {
            jsonResponse(false, null, 'Body kosong. post_max_size=' . ini_get('post_max_size'), 400);
        }
        $input = json_decode($raw_body, true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($input['data'])) {
            jsonResponse(false, null, 'JSON tidak valid: ' . json_last_error_msg(), 400);
        }

        $dataUri = $input['data'];
        $b64data = (strpos($dataUri, ',') !== false)
            ? explode(',', $dataUri, 2)[1]
            : $dataUri;
        $b64data = preg_replace('/\s+/', '', $b64data);

        $binary = base64_decode($b64data, true);
        if ($binary === false || strlen($binary) < 100) {
            jsonResponse(false, null, 'Decode base64 gagal.', 400);
        }

        $sig = substr($binary, 0, 4);
        if ($sig === "\x89PNG") {
            $ext = 'png';
        } elseif (substr($sig, 0, 2) === "\xFF\xD8") {
            $ext = 'jpg';
        } elseif (strpos($dataUri, 'png') !== false) {
            $ext = 'png';
        } else {
            $ext = 'jpg';
        }
    }

    if (!$binary) jsonResponse(false, null, 'Tidak ada data foto.', 400);

    if (!is_dir(FOTO_DIR)) {
        jsonResponse(false, null, 'Folder uploads/foto/ tidak ada. Buat dulu di server.', 500);
    }
    if (!is_writable(FOTO_DIR)) {
        jsonResponse(false, null, 'Folder uploads/foto/ tidak bisa ditulis. chmod 755 uploads/foto', 500);
    }

    hapusFotoLama($nisn);

    $filename = $nisn . '.' . $ext;
    $path     = FOTO_DIR . $filename;

    if (file_put_contents($path, $binary) === false) {
        jsonResponse(false, null, 'file_put_contents gagal.', 500);
    }

    jsonResponse(true, [
        'nisn' => $nisn,
        'url'  => FOTO_URL . $filename,
        'size' => strlen($binary),
    ], 'Foto berhasil disimpan.');
}

// ── DELETE ────────────────────────────────────────────────────
if ($method === 'DELETE') {
    requireAuth();
    if (!$nisn) jsonResponse(false, null, 'NISN wajib diisi.', 400);
    hapusFotoLama($nisn);
    jsonResponse(true, null, 'Foto dihapus.');
}

jsonResponse(false, null, 'Method tidak didukung.', 405);

function findFoto(string $nisn): ?string {
    foreach (['jpg', 'jpeg', 'png'] as $ext) {
        $p = FOTO_DIR . $nisn . '.' . $ext;
        if (file_exists($p)) return $p;
    }
    // Fallback: cek format lama foto_{nisn} jika ada
    foreach (['jpg', 'jpeg', 'png'] as $ext) {
        $p = FOTO_DIR . 'foto_' . $nisn . '.' . $ext;
        if (file_exists($p)) return $p;
    }
    return null;
}
function hapusFotoLama(string $nisn): void {
    foreach (['jpg', 'jpeg', 'png'] as $ext) {
        foreach ([$nisn . '.' . $ext, 'foto_' . $nisn . '.' . $ext] as $name) {
            $p = FOTO_DIR . $name;
            if (file_exists($p)) @unlink($p);
        }
    }
}
