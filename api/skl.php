<?php
// skl/api/skl.php — Public cek + Admin CRUD
require_once __DIR__.'/database.php';
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { jsonResponse(true, null, 'OK'); }

$method = $_SERVER['REQUEST_METHOD'];
$action = trim($_GET['action'] ?? '');
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

// ── ENDPOINT PUBLIK ───────────────────────────────────────────

if ($method === 'GET' && $action === 'setting_publik') {
    $db  = getDB();
    $row = $db->query("SELECT tahun_ajaran, tanggal_buka, aktif FROM skl_setting WHERE aktif=1 LIMIT 1")->fetch();
    if (!$row) jsonResponse(true, ['aktif'=>false,'sudah_buka'=>false]);
    jsonResponse(true, [
        'aktif'        => (bool)$row['aktif'],
        'sudah_buka'   => (function($tgl) {
            $tz  = new DateTimeZone('Asia/Jakarta');
            $buka = new DateTime($tgl, $tz);
            $now  = new DateTime('now', $tz);
            return $now >= $buka;
        })($row['tanggal_buka']),
        'tahun_ajaran' => $row['tahun_ajaran'],
        'tanggal_buka' => $row['tanggal_buka'],
    ]);
}

if ($method === 'GET' && $action === 'cek') {
    cekHasil();
}

// ── ENDPOINT ADMIN (butuh login) ──────────────────────────────

requireAuth();
$db = getDB();

// ── SETTING ──────────────────────────────────────────────────

if ($method === 'GET' && $action === 'setting') {
    $row = $db->query("SELECT * FROM skl_setting ORDER BY id DESC LIMIT 1")->fetch();
    jsonResponse(true, $row ?: null);
}

if ($method === 'POST' && $action === 'setting') {
    $input       = getInput();
    $tahunAjaran = trim($input['tahun_ajaran'] ?? '');
    $tanggalBuka = trim($input['tanggal_buka'] ?? '');
    $aktif       = (int)($input['aktif'] ?? 0);
    if (!$tahunAjaran || !$tanggalBuka) jsonResponse(false, null, 'Tahun ajaran dan tanggal buka wajib diisi.', 400);

    if ($aktif) $db->exec("UPDATE skl_setting SET aktif=0");

    $existing = $db->query("SELECT id FROM skl_setting ORDER BY id DESC LIMIT 1")->fetch();
    if ($existing) {
        $db->prepare("UPDATE skl_setting SET tahun_ajaran=?,tanggal_buka=?,aktif=? WHERE id=?")
           ->execute([$tahunAjaran, $tanggalBuka, $aktif, $existing['id']]);
        $settingId = $existing['id'];
    } else {
        $db->prepare("INSERT INTO skl_setting (tahun_ajaran,tanggal_buka,aktif) VALUES (?,?,?)")
           ->execute([$tahunAjaran, $tanggalBuka, $aktif]);
        $settingId = $db->lastInsertId();
    }
    jsonResponse(true, ['id'=>$settingId], 'Setting disimpan.');
}

// ── SISWA LIST ────────────────────────────────────────────────

if ($method === 'GET' && $action === 'list') {
    $setting = getActiveSetting($db);
    if (!$setting) jsonResponse(true, ['items'=>[],'total'=>0]);

    $q      = '%'.trim($_GET['q'] ?? '').'%';
    $page   = max(1,(int)($_GET['page'] ?? 1));
    $limit  = max(1,min(100,(int)($_GET['limit'] ?? 10)));
    $offset = ($page-1)*$limit;

    $total = $db->prepare("SELECT COUNT(*) FROM skl_nilai WHERE skl_setting_id=? AND (nama LIKE ? OR nisn LIKE ?)");
    $total->execute([$setting['id'],$q,$q]);

    $stmt = $db->prepare("SELECT * FROM skl_nilai WHERE skl_setting_id=? AND (nama LIKE ? OR nisn LIKE ?) ORDER BY nama LIMIT $limit OFFSET $offset");
    $stmt->execute([$setting['id'],$q,$q]);

    jsonResponse(true, ['items'=>$stmt->fetchAll(),'total'=>(int)$total->fetchColumn(),'setting'=>$setting]);
}

// ── SISWA GET by ID ───────────────────────────────────────────

if ($method === 'GET' && $action === 'get' && $id) {
    $stmt = $db->prepare("SELECT * FROM skl_nilai WHERE id=? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) jsonResponse(false, null, 'Data tidak ditemukan.', 404);
    jsonResponse(true, $row);
}

// ── SISWA IMPORT (Excel) ──────────────────────────────────────

if ($method === 'POST' && $action === 'import') {
    $input     = getInput();
    $rows      = $input['rows'] ?? [];
    $settingId = (int)($input['setting_id'] ?? 0);

    if (!$settingId) {
        $setting = getActiveSetting($db);
        if (!$setting) jsonResponse(false, null, 'Belum ada setting aktif. Buat setting terlebih dahulu.', 400);
        $settingId = $setting['id'];
    }
    if (empty($rows)) jsonResponse(false, null, 'Tidak ada data untuk diimport.', 400);

    $berhasil = 0; $gagal = 0; $errors = [];
    $stmt = $db->prepare("
        INSERT INTO skl_nilai (skl_setting_id,nisn,nama,kelas,tempat_lahir,tgl_lahir,no_peserta,no_skl,status_lulus,pai_qh,pai_aa,pai_fik,pai_ski,ppkn,bind,bar,mtk,ipa,ips,bing,sb,pjok,prkti,mlk1)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
          nama=VALUES(nama),kelas=VALUES(kelas),tempat_lahir=VALUES(tempat_lahir),
          tgl_lahir=VALUES(tgl_lahir),no_peserta=VALUES(no_peserta),no_skl=VALUES(no_skl),
          status_lulus=VALUES(status_lulus),
          pai_qh=VALUES(pai_qh),pai_aa=VALUES(pai_aa),pai_fik=VALUES(pai_fik),pai_ski=VALUES(pai_ski),
          ppkn=VALUES(ppkn),bind=VALUES(bind),bar=VALUES(bar),mtk=VALUES(mtk),
          ipa=VALUES(ipa),ips=VALUES(ips),bing=VALUES(bing),sb=VALUES(sb),
          pjok=VALUES(pjok),prkti=VALUES(prkti),mlk1=VALUES(mlk1)
    ");

    $db->beginTransaction();
    try {
        foreach ($rows as $r) {
            $nisn = trim($r['nisn'] ?? '');
            $nama = strtoupper(trim($r['nama'] ?? ''));
            if (!$nisn || !$nama) { $gagal++; continue; }
            $n = fn($k) => isset($r[$k]) && $r[$k] !== '' ? (int)$r[$k] : null;
            $s = fn($k) => isset($r[$k]) && $r[$k] !== '' ? trim($r[$k]) : null;
            // Normalisasi tanggal lahir ke format Y-m-d
            $tglLahir = null;
            if (!empty($r['tgl_lahir'])) {
                $tglRaw = trim($r['tgl_lahir']);
                // Coba parse berbagai format
                $parsed = date_create_from_format('Y-m-d', $tglRaw)
                    ?: date_create_from_format('d/m/Y', $tglRaw)
                    ?: date_create_from_format('d-m-Y', $tglRaw)
                    ?: date_create($tglRaw);
                if ($parsed) $tglLahir = $parsed->format('Y-m-d');
            }
            try {
                $stmt->execute([$settingId,$nisn,$nama,
                    trim($r['kelas'] ?? 'Kelas 9'),
                    $s('tempat_lahir'), $tglLahir, $s('no_peserta'), $s('no_skl'),
                    (int)($r['status_lulus'] ?? 1),
                    $n('pai_qh'),$n('pai_aa'),$n('pai_fik'),$n('pai_ski'),
                    $n('ppkn'),$n('bind'),$n('bar'),$n('mtk'),
                    $n('ipa'),$n('ips'),$n('bing'),$n('sb'),$n('pjok'),$n('prkti'),$n('mlk1')]);
                $berhasil++;
            } catch (\Exception $e) { $gagal++; $errors[] = "NISN $nisn: ".$e->getMessage(); }
        }
        $db->commit();
    } catch (\Exception $e) { $db->rollBack(); jsonResponse(false, null, 'Import gagal: '.$e->getMessage(), 500); }

    jsonResponse(true, ['berhasil'=>$berhasil,'gagal'=>$gagal,'errors'=>array_slice($errors,0,10)],
        "$berhasil data berhasil diimport".($gagal?", $gagal gagal":''));
}

// ── SISWA CREATE (manual) ─────────────────────────────────────

if ($method === 'POST' && $action === 'create') {
    $input = getInput();
    $setting = getActiveSetting($db);
    if (!$setting) jsonResponse(false, null, 'Belum ada setting aktif.', 400);

    $nisn = trim($input['nisn'] ?? '');
    $nama = strtoupper(trim($input['nama'] ?? ''));
    if (!$nisn || !$nama) jsonResponse(false, null, 'NISN dan nama wajib diisi.', 400);

    $n = fn($k) => isset($input[$k]) && $input[$k] !== '' ? (int)$input[$k] : null;
    $s = fn($k) => isset($input[$k]) && $input[$k] !== '' ? trim($input[$k]) : null;
    $tglLahir = null;
    if (!empty($input['tgl_lahir'])) {
        $parsed = date_create_from_format('Y-m-d', trim($input['tgl_lahir']));
        if ($parsed) $tglLahir = $parsed->format('Y-m-d');
    }
    try {
        $db->prepare("INSERT INTO skl_nilai (skl_setting_id,nisn,nama,kelas,tempat_lahir,tgl_lahir,no_peserta,no_skl,status_lulus,pai_qh,pai_aa,pai_fik,pai_ski,ppkn,bind,bar,mtk,ipa,ips,bing,sb,pjok,prkti,mlk1) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
           ->execute([$setting['id'],$nisn,$nama,
               trim($input['kelas'] ?? 'Kelas 9'),
               $s('tempat_lahir'), $tglLahir, $s('no_peserta'), $s('no_skl'),
               (int)($input['status_lulus'] ?? 1),
               $n('pai_qh'),$n('pai_aa'),$n('pai_fik'),$n('pai_ski'),
               $n('ppkn'),$n('bind'),$n('bar'),$n('mtk'),
               $n('ipa'),$n('ips'),$n('bing'),$n('sb'),$n('pjok'),$n('prkti'),$n('mlk1')]);
        jsonResponse(true, ['id'=>(int)$db->lastInsertId()], 'Data siswa berhasil ditambahkan.');
    } catch (\Exception $e) {
        jsonResponse(false, null, 'NISN sudah terdaftar.', 409);
    }
}

// ── SISWA UPDATE ──────────────────────────────────────────────

if ($method === 'PUT' && $id) {
    $input = getInput();
    $n = fn($k) => isset($input[$k]) && $input[$k] !== '' ? (int)$input[$k] : null;
    $s = fn($k) => isset($input[$k]) && $input[$k] !== '' ? trim($input[$k]) : null;
    $tglLahir = null;
    if (!empty($input['tgl_lahir'])) {
        $parsed = date_create_from_format('Y-m-d', trim($input['tgl_lahir']));
        if ($parsed) $tglLahir = $parsed->format('Y-m-d');
    }
    $db->prepare("UPDATE skl_nilai SET nisn=?,nama=?,kelas=?,tempat_lahir=?,tgl_lahir=?,no_peserta=?,no_skl=?,status_lulus=?,pai_qh=?,pai_aa=?,pai_fik=?,pai_ski=?,ppkn=?,bind=?,bar=?,mtk=?,ipa=?,ips=?,bing=?,sb=?,pjok=?,prkti=?,mlk1=? WHERE id=?")
       ->execute([
           trim($input['nisn'] ?? ''), strtoupper(trim($input['nama'] ?? '')),
           trim($input['kelas'] ?? 'Kelas 9'),
           $s('tempat_lahir'), $tglLahir, $s('no_peserta'), $s('no_skl'),
           (int)($input['status_lulus'] ?? 1),
           $n('pai_qh'),$n('pai_aa'),$n('pai_fik'),$n('pai_ski'),
           $n('ppkn'),$n('bind'),$n('bar'),$n('mtk'),
           $n('ipa'),$n('ips'),$n('bing'),$n('sb'),$n('pjok'),$n('prkti'),$n('mlk1'),
           $id
       ]);
    jsonResponse(true, null, 'Data siswa berhasil diupdate.');
}

// ── SISWA DELETE ──────────────────────────────────────────────

if ($method === 'DELETE' && $id) {
    $db->prepare("DELETE FROM skl_nilai WHERE id=?")->execute([$id]);
    jsonResponse(true, null, 'Data siswa berhasil dihapus.');
}

// ── DELETE ALL (reset) ────────────────────────────────────────

if ($method === 'DELETE' && $action === 'reset') {
    $setting = getActiveSetting($db);
    if (!$setting) jsonResponse(false, null, 'Tidak ada setting aktif.');
    $db->prepare("DELETE FROM skl_nilai WHERE skl_setting_id=?")->execute([$setting['id']]);
    jsonResponse(true, null, 'Semua data nilai berhasil dihapus.');
}

jsonResponse(false, null, 'Endpoint tidak ditemukan.', 404);

// ── HELPERS ───────────────────────────────────────────────────

function getActiveSetting(PDO $db): ?array {
    return $db->query("SELECT * FROM skl_setting WHERE aktif=1 LIMIT 1")->fetch() ?: null;
}

function cekHasil(): void {
    $db   = getDB();
    $nisn = trim($_GET['nisn'] ?? '');
    $ip   = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown')[0];

    if (!$nisn) jsonResponse(false, null, 'NISN wajib diisi.', 400);

    // Rate limit
    $rl = $db->prepare("SELECT attempts,blocked,last_try FROM skl_ratelimit WHERE ip_addr=?");
    $rl->execute([$ip]);
    $rlRow = $rl->fetch();
    if ($rlRow) {
        $menit = (time() - strtotime($rlRow['last_try'])) / 60;
        if ($rlRow['blocked'] && $menit >= 15) {
            $db->prepare("DELETE FROM skl_ratelimit WHERE ip_addr=?")->execute([$ip]);
            $rlRow = null;
        } elseif ($rlRow['blocked']) {
            jsonResponse(false, null, 'Terlalu banyak percobaan. Coba lagi dalam '.(int)(15-$menit+1).' menit.', 429);
        }
    }

    // Cek waktu buka di server
    $setting = $db->query("SELECT * FROM skl_setting WHERE aktif=1 LIMIT 1")->fetch();
    if (!$setting) jsonResponse(false, null, 'Pengumuman belum tersedia.', 404);
    $tzWib   = new DateTimeZone('Asia/Jakarta');
    $nowWib  = new DateTime('now', $tzWib);
    $tglBuka = new DateTime($setting['tanggal_buka'], $tzWib);
    if ($nowWib < $tglBuka) jsonResponse(false, null, 'Pengumuman belum dibuka.', 403);

    // Cari siswa
    $stmt = $db->prepare("SELECT * FROM skl_nilai WHERE skl_setting_id=? AND nisn=? LIMIT 1");
    $stmt->execute([$setting['id'], $nisn]);
    $row = $stmt->fetch();

    if (!$row) {
        $db->prepare("INSERT INTO skl_ratelimit (ip_addr,attempts) VALUES (?,1) ON DUPLICATE KEY UPDATE attempts=attempts+1, blocked=IF(attempts+1>=5,1,0), last_try=NOW()")
           ->execute([$ip]);
        jsonResponse(false, null, 'Data tidak ditemukan. Pastikan NISN benar.', 404);
    }

    $db->prepare("DELETE FROM skl_ratelimit WHERE ip_addr=?")->execute([$ip]);

    // Format tanggal lahir untuk tampil
    $tglLahirFmt = null;
    if ($row['tgl_lahir']) {
        $dt = date_create($row['tgl_lahir']);
        if ($dt) {
            $bulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
            $tglLahirFmt = $dt->format('d') . ' ' . $bulan[(int)$dt->format('m')-1] . ' ' . $dt->format('Y');
        }
    }

    jsonResponse(true, [
        'nama'           => $row['nama'],
        'nisn'           => $row['nisn'],
        'kelas'          => $row['kelas'],
        'tempat_lahir'   => $row['tempat_lahir'],
        'tgl_lahir'      => $row['tgl_lahir'],
        'tgl_lahir_fmt'  => $tglLahirFmt,
        'ttl'            => $row['tempat_lahir'] && $tglLahirFmt
                              ? $row['tempat_lahir'] . ', ' . $tglLahirFmt
                              : ($tglLahirFmt ?: '-'),
        'no_peserta'     => $row['no_peserta'],
        'no_skl'         => $row['no_skl'],
        'status_lulus'   => (int)$row['status_lulus'],
        'tahun_ajaran'   => $setting['tahun_ajaran'],
        'nilai'          => [
            ['mapel'=>'Al-Quran Hadits',  'nilai'=>$row['pai_qh']],
            ['mapel'=>'Aqidah Akhlak',    'nilai'=>$row['pai_aa']],
            ['mapel'=>'Fiqih',            'nilai'=>$row['pai_fik']],
            ['mapel'=>'SKI',              'nilai'=>$row['pai_ski']],
            ['mapel'=>'PPKn',             'nilai'=>$row['ppkn']],
            ['mapel'=>'Bahasa Indonesia', 'nilai'=>$row['bind']],
            ['mapel'=>'Bahasa Arab',      'nilai'=>$row['bar']],
            ['mapel'=>'Matematika',       'nilai'=>$row['mtk']],
            ['mapel'=>'IPA',              'nilai'=>$row['ipa']],
            ['mapel'=>'IPS',              'nilai'=>$row['ips']],
            ['mapel'=>'Bahasa Inggris',   'nilai'=>$row['bing']],
            ['mapel'=>'Seni Budaya',      'nilai'=>$row['sb']],
            ['mapel'=>'PJOK',             'nilai'=>$row['pjok']],
            ['mapel'=>'Prakarya & TIK',   'nilai'=>$row['prkti']],
            ['mapel'=>'Muatan Lokal',     'nilai'=>$row['mlk1']],
        ],
    ]);
}
