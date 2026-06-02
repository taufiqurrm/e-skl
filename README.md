# 📋 Rangkuman Aplikasi SKL (Surat Keterangan Lulus)
**MTs. Bustanul Ulum — Waru, Pamekasan**

---

## 🏗️ Struktur Aplikasi

```
skl/
├── index.html           ← Halaman publik (siswa)
├── admin.html           ← Panel admin
├── verifikasi.html      ← Halaman verifikasi TTE (akses via QR Code)
├── .htaccess            ← Konfigurasi keamanan & redirect Apache
└── api/
    ├── database.php     ← Koneksi database & helper
    ├── auth.php         ← Autentikasi admin
    ├── skl.php          ← CRUD data siswa & pengumuman
    ├── pengaturan.php   ← Pengaturan data sekolah
    ├── foto.php         ← Upload & manajemen foto siswa
    ├── verifikasi.php   ← API publik verifikasi keaslian SKL
    ├── install.php      ← Installer (hapus setelah dipakai)
    └── reset_password.php ← Reset password darurat
```

---

## 🌐 Halaman Publik (`index.html`)

Halaman ini diakses oleh **siswa** untuk mengecek hasil kelulusan.

### Fitur-fitur:

| Fitur | Keterangan |
|-------|-----------|
| **Cek Kelulusan** | Siswa memasukkan NISN untuk melihat status kelulusan |
| **Status Lulus** | Tampil banner hijau dengan tulisan **LULUS** dan ikon 🎓 |
| **Status Lulus Bersyarat** | Tampil banner oranye dengan tulisan **LULUS BERSYARAT** dan ikon 📝 |
| **Status Tidak Lulus** | Tampil banner merah dengan tulisan **TIDAK LULUS** dan ikon 📋 |
| **Data Identitas** | Menampilkan nama, NISN, kelas, no. peserta ujian, dan no. SKL |
| **Tabel Nilai** | Menampilkan nilai semua mata pelajaran beserta rata-rata (hanya untuk status Lulus) |
| **Download PDF SKL** | Mengunduh Surat Keterangan Lulus dalam format PDF lengkap dengan kop surat, identitas, nilai, tanda tangan kepala madrasah, dan QR Code verifikasi (hanya untuk status Lulus) |
| **Countdown Timer** | Menghitung mundur waktu pembukaan pengumuman jika belum saatnya dibuka |
| **Rate Limiting** | Membatasi maksimal 5 percobaan salah per IP selama 15 menit untuk mencegah penyalahgunaan |
| **Foto Siswa** | Foto siswa tampil di banner status dan ikut tercetak di PDF SKL |
| **QR Code di PDF** | Kode QR mengarah ke `verifikasi.html?no_skl=...` untuk verifikasi keaslian SKL secara online |

---

## 🔏 Halaman Verifikasi TTE (`verifikasi.html`)

Halaman ini diakses oleh **siapa saja** dengan memindai QR Code yang tercetak di PDF SKL.

### Alur verifikasi:

```
QR Code di PDF dipindai
        ↓
verifikasi.html?no_skl=SKL-xxx   ← halaman tampilan
        ↓  fetch ke
api/verifikasi.php?no_skl=xxx    ← query ke database
        ↓
Tampilkan data & status keaslian dokumen
```

### Fitur-fitur:

| Fitur | Keterangan |
|-------|-----------|
| **Banner Status** | Menampilkan nama, foto, NISN, dan status kelulusan dengan warna sesuai status |
| **Stamp Terverifikasi** | Konfirmasi dokumen terdaftar di sistem resmi sekolah beserta waktu verifikasi WIB |
| **Identitas Siswa** | Nama, NISN, kelas, TTL, no. peserta, no. SKL, dan tahun ajaran |
| **Info Sekolah** | Nama madrasah, NPSN, kepala sekolah, website, dan email |
| **Kompatibel QR Lama** | `.htaccess` meredirect URL QR Code lama (`api/skl.php?action=cek&nisn=...`) ke halaman verifikasi secara otomatis |
| **Parameter** | Mendukung `?no_skl=` (utama) dan `?nisn=` (fallback) |

> Halaman ini **tidak menampilkan transkrip nilai** — hanya menampilkan identitas dan status kelulusan untuk keperluan verifikasi keaslian dokumen.

---

## 🔐 Panel Admin (`admin.html`)

Halaman ini hanya dapat diakses oleh **admin/kepala madrasah** setelah login.

---

### 1. 📊 Dashboard

Menampilkan ringkasan statistik data siswa secara real-time dalam 5 kartu:

| Kartu | Warna | Keterangan |
|-------|-------|-----------|
| **Total Siswa** | Hijau navy | Jumlah seluruh siswa yang terdaftar |
| **Lulus** | Hijau tua | Jumlah siswa dengan status Lulus |
| **Lulus Bersyarat** | Oranye | Jumlah siswa dengan status Lulus Bersyarat |
| **Tidak Lulus** | Merah | Jumlah siswa dengan status Tidak Lulus |
| **Kelulusan %** | Kuning emas | Persentase kelulusan (hitung dari status Lulus saja) |

Selain statistik, dashboard juga menampilkan **Status Pengumuman** berisi tahun ajaran, tanggal pembukaan, dan status aktif/nonaktif.

---

### 2. 👨‍🎓 Data Siswa

Halaman manajemen data nilai siswa.

| Fitur | Keterangan |
|-------|-----------|
| **Tambah Manual** | Menambah data siswa satu per satu melalui form modal |
| **Edit Data** | Mengubah semua data siswa termasuk nilai dan identitas |
| **Hapus Siswa** | Menghapus data siswa satu per satu |
| **Hapus Semua** | Menghapus seluruh data nilai sekaligus (dengan konfirmasi ganda) |
| **Pencarian** | Cari siswa berdasarkan nama atau NISN secara real-time |
| **Paginasi** | Tampilkan 10 / 25 / 50 data per halaman |
| **Toggle Status** | Klik badge status di tabel untuk mengubah status lulus (rotasi: Lulus → Bersyarat → Tidak Lulus → Lulus) |
| **Rata-rata Nilai** | Dihitung otomatis dari semua nilai mata pelajaran yang terisi |

**Data yang dikelola per siswa:**
- Identitas: NISN, Nama, Kelas, Tempat/Tanggal Lahir, No. Peserta Ujian, No. SKL
- Status Kelulusan: Lulus (1) / Lulus Bersyarat (2) / Tidak Lulus (0)
- Nilai 15 mata pelajaran: Al-Quran Hadits, Aqidah Akhlak, Fiqih, SKI, PPKn, Bahasa Indonesia, Bahasa Arab, Matematika, IPA, IPS, Bahasa Inggris, Seni Budaya, PJOK, Prakarya & TIK, Muatan Lokal
- Foto siswa (JPG/PNG, maks 600KB)

---

### 3. 📥 Import Excel

Mengimpor data nilai banyak siswa sekaligus dari file Excel (.xlsx).

| Fitur | Keterangan |
|-------|-----------|
| **Unduh Template** | Mengunduh file Excel template siap isi beserta sheet petunjuk |
| **Upload File** | Drag & drop atau klik untuk memilih file Excel |
| **Auto-deteksi Kolom** | Sistem otomatis mengenali nama kolom meskipun tidak persis sama (asal mengandung kata kunci seperti "arab", "ipa", "indonesia", dll) |
| **Preview Data** | Menampilkan preview tabel data sebelum disimpan ke database |
| **Upsert** | Jika NISN sudah ada, data lama diperbarui otomatis (tidak duplikat) |
| **Normalisasi** | Nama siswa otomatis diubah menjadi HURUF KAPITAL, tanggal lahir dinormalisasi ke format Y-m-d |

---

### 4. 📷 Foto Siswa

Manajemen foto siswa yang akan tampil di halaman hasil dan PDF SKL.

| Fitur | Keterangan |
|-------|-----------|
| **Upload Satu Foto** | Upload foto per siswa langsung dari kartu foto di grid |
| **Upload Massal** | Pilih banyak file sekaligus — sistem mencocokkan nama file dengan NISN siswa otomatis |
| **Progress Bar** | Menampilkan progress upload massal secara real-time |
| **Ganti Foto** | Upload foto baru untuk menggantikan foto lama |
| **Hapus Foto** | Menghapus foto siswa |
| **Pencarian** | Cari siswa berdasarkan nama atau NISN di grid foto |
| **Indikator** | Kartu siswa yang sudah punya foto ditandai centang hijau (✓) |

> Format nama file untuk upload massal: **NISN.jpg** (contoh: `0103926213.jpg`)

---

### 5. ⚙️ Pengaturan

Dibagi menjadi dua bagian:

**Pengaturan Pengumuman:**
| Fitur | Keterangan |
|-------|-----------|
| **Tahun Ajaran** | Mengatur tahun ajaran yang aktif (contoh: 2024/2025) |
| **Tanggal & Jam Buka** | Menentukan kapan pengumuman bisa diakses siswa |
| **Toggle Aktif/Nonaktif** | Mengaktifkan atau menonaktifkan pengumuman |

**Data Sekolah:**
| Fitur | Keterangan |
|-------|-----------|
| **Nama Sekolah** | Nama madrasah (tampil di kop surat PDF dan halaman verifikasi) |
| **NPSN** | Nomor Pokok Sekolah Nasional |
| **Kepala Sekolah** | Nama kepala madrasah (tampil di tanda tangan PDF dan halaman verifikasi) |
| **NIP** | NIP kepala madrasah |
| **Kota** | Kota untuk tanggal surat (contoh: Pamekasan) |
| **Website & Email** | Informasi kontak sekolah (tampil di halaman verifikasi) |
| **Logo Kiri & Kanan** | Upload atau URL logo untuk kop surat SKL — bisa upload file langsung (konversi ke base64 otomatis) |

---

### 6. 👤 Profil Admin

| Fitur | Keterangan |
|-------|-----------|
| **Edit Nama** | Mengubah nama tampilan admin |
| **Ganti Password** | Mengubah password dengan verifikasi password lama |
| **Logout** | Keluar dari sesi admin |

---

## 📄 PDF Surat Keterangan Lulus

PDF yang dihasilkan terdiri dari **2 halaman**:

**Halaman 1 — Surat Keterangan Lulus:**
- Kop surat lengkap (logo Kemenag, logo madrasah, nama, alamat, garis kop)
- Judul dan nomor SKL
- Paragraf pernyataan kelulusan
- Identitas lengkap siswa
- Kotak foto 3×4 siswa
- Tanda tangan kepala madrasah + QR Code verifikasi (mengarah ke `verifikasi.html?no_skl=...`)

**Halaman 2 — Transkrip Nilai:**
- Kop surat
- Identitas siswa + tanggal kelulusan
- Tabel nilai lengkap dengan kolom **Angka** dan **Huruf** (konversi otomatis)
- Pengelompokan Kelompok A dan Kelompok B
- Baris rata-rata nilai
- Tanda tangan kepala madrasah + QR Code

---

## 🔒 Keamanan

| Aspek | Implementasi |
|-------|-------------|
| **Password** | Di-hash dengan bcrypt (`password_hash`) |
| **Session** | Cookie `HttpOnly` + `SameSite=Lax`, lifetime 24 jam |
| **SQL Injection** | Semua query menggunakan Prepared Statements (PDO) |
| **Rate Limiting** | Maks 5 percobaan salah per IP / 15 menit di halaman publik |
| **CORS** | Header CORS dikontrol di `database.php` |
| **Directory Listing** | Dinonaktifkan via `.htaccess` (`Options -Indexes`) |
| **Security Headers** | `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` |
| **Gzip & Cache** | Kompresi dan cache aset statis via `.htaccess` |
| **Redirect Cerdas** | `.htaccess` meredirect QR Code lama ke `verifikasi.html`, hanya untuk akses browser (bukan fetch/AJAX) |

---

## 🛠️ Persyaratan Server

| Komponen | Minimum |
|----------|---------|
| PHP | 8.0+ |
| MySQL | 5.7+ / MariaDB 10.3+ |
| Apache | mod_rewrite aktif |
| Ekstensi PHP | `pdo`, `pdo_mysql`, `json`, `session` |

---

## 📊 Struktur Database

| Tabel | Fungsi |
|-------|--------|
| `admin_users` | Data akun admin beserta role dan riwayat login |
| `pengaturan` | Konfigurasi data sekolah (key-value) |
| `skl_setting` | Pengaturan tahun ajaran dan jadwal pengumuman |
| `skl_nilai` | Data siswa dan nilai semua mata pelajaran |
| `skl_ratelimit` | Pencatatan percobaan akses untuk rate limiting |

---

## 🚀 Panduan Deploy

1. Upload semua file ke server hosting (folder `skl/` atau root domain)
2. Buka `api/install.php` di browser untuk membuat tabel dan akun admin default
3. **Hapus `api/install.php`** setelah instalasi selesai
4. Login ke `admin.html` dengan akun default, lalu **ganti password segera**
5. Isi data sekolah di menu **Pengaturan**
6. Import data siswa via **Import Excel** atau tambah manual
7. Upload foto siswa via menu **Foto Siswa**
8. Aktifkan pengumuman di **Pengaturan → Pengumuman**

> Pastikan folder `uploads/foto/` dapat ditulis oleh server (`chmod 755`).

---

*Sistem SKL — MTs. Bustanul Ulum, Waru, Pamekasan · 2026*
