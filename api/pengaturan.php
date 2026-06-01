<?php
// skl/api/pengaturan.php
require_once __DIR__.'/database.php';
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { jsonResponse(true, null, 'OK'); }

$method = $_SERVER['REQUEST_METHOD'];

// GET — ambil semua pengaturan (publik, untuk tampil logo dll)
// Opsional: ?key=foto_nisn_xxxx untuk ambil satu kunci
if ($method === 'GET') {
    $db   = getDB();
    $key  = trim($_GET['key'] ?? '');
    if ($key && strlen($key) <= 80) {
        // Ambil satu key saja (untuk foto siswa)
        $stmt = $db->prepare("SELECT kunci, nilai FROM pengaturan WHERE kunci = ?");
        $stmt->execute([$key]);
        $rows = $stmt->fetchAll();
    } else {
        $rows = $db->query("SELECT kunci, nilai FROM pengaturan")->fetchAll();
    }
    $data = [];
    foreach ($rows as $r) $data[$r['kunci']] = $r['nilai'];
    jsonResponse(true, $data);
}

// POST — update pengaturan (butuh login)
if ($method === 'POST') {
    requireAuth();
    $input = getInput();
    $db    = getDB();
    $stmt  = $db->prepare("INSERT INTO pengaturan (kunci, nilai) VALUES (?,?) ON DUPLICATE KEY UPDATE nilai=VALUES(nilai)");
    foreach ($input as $k => $v) {
        if (is_string($k) && strlen($k) <= 60) $stmt->execute([$k, $v]);
    }
    jsonResponse(true, null, 'Pengaturan disimpan.');
}

jsonResponse(false, null, 'Method tidak didukung.', 405);
