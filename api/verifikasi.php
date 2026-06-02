<?php
// skl/api/verifikasi.php
// Endpoint publik — dipanggil oleh halaman verifikasi.html
// URL QR Code format: https://domain.com/verifikasi.html?no_skl=SKL-xxx&nisn=xxx
require_once __DIR__ . '/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { jsonResponse(true, null, 'OK'); }

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, null, 'Method tidak didukung.', 405);
}

$no_skl = trim($_GET['no_skl'] ?? '');
$nisn   = trim($_GET['nisn']   ?? '');

if (!$no_skl && !$nisn) {
    jsonResponse(false, null, 'Parameter no_skl atau nisn wajib diisi.', 400);
}

$db = getDB();

// Cari berdasarkan no_skl (utama) atau nisn (fallback)
if ($no_skl) {
    $stmt = $db->prepare("
        SELECT n.*, s.tahun_ajaran, s.tanggal_buka
        FROM skl_nilai n
        JOIN skl_setting s ON s.id = n.skl_setting_id
        WHERE n.no_skl = ? AND s.aktif = 1
        LIMIT 1
    ");
    $stmt->execute([$no_skl]);
} else {
    $stmt = $db->prepare("
        SELECT n.*, s.tahun_ajaran, s.tanggal_buka
        FROM skl_nilai n
        JOIN skl_setting s ON s.id = n.skl_setting_id
        WHERE n.nisn = ? AND s.aktif = 1
        LIMIT 1
    ");
    $stmt->execute([$nisn]);
}

$row = $stmt->fetch();

if (!$row) {
    jsonResponse(false, null, 'Data tidak ditemukan. Dokumen tidak valid atau belum terdaftar.', 404);
}

// Format tanggal lahir
$tglLahirFmt = null;
if ($row['tgl_lahir']) {
    $dt    = date_create($row['tgl_lahir']);
    $bulan = ['Januari','Februari','Maret','April','Mei','Juni',
              'Juli','Agustus','September','Oktober','November','Desember'];
    if ($dt) {
        $tglLahirFmt = $dt->format('d') . ' ' . $bulan[(int)$dt->format('m') - 1] . ' ' . $dt->format('Y');
    }
}

// Hitung rata-rata nilai
$nilaiArr = [
    $row['pai_qh'], $row['pai_aa'], $row['pai_fik'], $row['pai_ski'],
    $row['ppkn'],   $row['bind'],   $row['bar'],     $row['mtk'],
    $row['ipa'],    $row['ips'],    $row['bing'],     $row['sb'],
    $row['pjok'],   $row['prkti'],  $row['mlk1'],
];
$nilaiIsi  = array_filter($nilaiArr, fn($v) => $v !== null && $v !== '');
$rataRata  = count($nilaiIsi) ? round(array_sum($nilaiIsi) / count($nilaiIsi), 2) : null;

// Ambil pengaturan sekolah
$pengRows  = $db->query("SELECT kunci, nilai FROM pengaturan")->fetchAll();
$peng      = [];
foreach ($pengRows as $p) $peng[$p['kunci']] = $p['nilai'];

// Waktu verifikasi
$now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));

// Cek foto
$fotoFile = __DIR__ . '/../uploads/foto/' . $row['nisn'] . '.jpg';
$fotoPng  = __DIR__ . '/../uploads/foto/' . $row['nisn'] . '.png';
$adaFoto  = file_exists($fotoFile) || file_exists($fotoPng);

$statusLabel = match ((int)$row['status_lulus']) {
    1  => 'LULUS',
    2  => 'LULUS BERSYARAT',
    0  => 'TIDAK LULUS',
    default => 'TIDAK DIKETAHUI',
};

jsonResponse(true, [
    'valid'          => true,
    'waktu_verifikasi' => $now->format('d/m/Y H:i:s') . ' WIB',
    'siswa' => [
        'nama'         => $row['nama'],
        'nisn'         => $row['nisn'],
        'kelas'        => $row['kelas'],
        'tempat_lahir' => $row['tempat_lahir'],
        'tgl_lahir'    => $tglLahirFmt,
        'ttl'          => ($row['tempat_lahir'] && $tglLahirFmt)
                            ? $row['tempat_lahir'] . ', ' . $tglLahirFmt
                            : ($tglLahirFmt ?: '-'),
        'no_peserta'   => $row['no_peserta'],
        'no_skl'       => $row['no_skl'],
        'status_lulus' => (int)$row['status_lulus'],
        'status_label' => $statusLabel,
        'tahun_ajaran' => $row['tahun_ajaran'],
        'rata_rata'    => $rataRata,
        'ada_foto'     => $adaFoto,
    ],
    'sekolah' => [
        'nama'           => $peng['nama_sekolah']   ?? 'MTs. Bustanul Ulum',
        'npsn'           => $peng['npsn']            ?? '',
        'kepala_sekolah' => $peng['kepala_sekolah']  ?? '',
        'nip_kepsek'     => $peng['nip_kepsek']      ?? '',
        'kota'           => $peng['kota']            ?? 'Pamekasan',
        'website'        => $peng['website']         ?? '',
        'email'          => $peng['email']           ?? '',
    ],
    'nilai' => [
        ['mapel' => 'Al-Quran Hadits',  'nilai' => $row['pai_qh']],
        ['mapel' => 'Aqidah Akhlak',    'nilai' => $row['pai_aa']],
        ['mapel' => 'Fiqih',            'nilai' => $row['pai_fik']],
        ['mapel' => 'SKI',              'nilai' => $row['pai_ski']],
        ['mapel' => 'PPKn',             'nilai' => $row['ppkn']],
        ['mapel' => 'Bahasa Indonesia', 'nilai' => $row['bind']],
        ['mapel' => 'Bahasa Arab',      'nilai' => $row['bar']],
        ['mapel' => 'Matematika',       'nilai' => $row['mtk']],
        ['mapel' => 'IPA',              'nilai' => $row['ipa']],
        ['mapel' => 'IPS',              'nilai' => $row['ips']],
        ['mapel' => 'Bahasa Inggris',   'nilai' => $row['bing']],
        ['mapel' => 'Seni Budaya',      'nilai' => $row['sb']],
        ['mapel' => 'PJOK',             'nilai' => $row['pjok']],
        ['mapel' => 'Prakarya & TIK',   'nilai' => $row['prkti']],
        ['mapel' => 'Muatan Lokal',     'nilai' => $row['mlk1']],
    ],
]);
