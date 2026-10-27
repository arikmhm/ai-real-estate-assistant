# AI Real Estate Assistant

Platform satu agensi properti untuk katalog publik dan operasi admin/sales. Tahap 1 menambahkan peran serta akun staf; tahap 2 menambahkan inventaris, media, dan katalog properti pada Laravel 13, Livewire, dan PostgreSQL. Dokumen kebutuhan, arsitektur, dan roadmap disimpan hanya pada lingkungan kerja lokal.

## Prasyarat

- PHP 8.3+ dengan `pdo_pgsql`, Composer 2, Node/npm, dan Docker Compose.
- Contoh lingkungan yang terverifikasi pada 26 Oktober 2026: PHP 8.5.0, Composer 2.10.3, Node 24.22.1, npm 11.19.1, PostgreSQL 16 dalam Docker.
- Di mesin pemeriksaan, binari Herd Lite dan NVM tidak masuk PATH shell Codex. Tambahkan ke PATH bila kondisinya sama:

```bash
export PATH="$HOME/.config/herd-lite/bin:$HOME/.nvm/versions/node/v24.22.1/bin:$PATH"
```

## Menjalankan proyek

Jalankan dari root proyek:

```bash
docker compose up -d db
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
npm ci
npm run build
php artisan serve
```

Aplikasi terbuka pada `http://127.0.0.1:8000`. Database lokal di `127.0.0.1:55432`; nilai di `compose.yaml` dan `.env.example` hanya untuk pengembangan lokal. Sesuaikan `.env` jika port atau kredensial lokal berubah. File `.env` diabaikan Git.

Pendaftaran publik dinonaktifkan. Buat admin pertama dari terminal interaktif setelah migrasi:

```bash
php artisan users:create-admin
```

Perintah meminta nama, email unik, dan kata sandi secara tersembunyi. Jangan memasukkan kata sandi ke argumen shell atau file repository. Admin masuk melalui `/login`, lalu mengelola sales pada `/staff`. Saat membuat sales, admin mengisi nomor WhatsApp dalam format internasional (misalnya `+628...`) dan kata sandi sementara yang disampaikan di luar aplikasi. Email kontak publik opsional. Akun nonaktif tidak dapat masuk; penonaktifan tidak menghapus akun. Sales dapat mengubah kata sandi dan pengaturan keamanan, sedangkan nama, email, dan kontaknya dikelola admin. Verifikasi email Fortify tetap tersedia tetapi bukan syarat akses akun yang dibuat admin, agar setup lokal tidak bergantung pada layanan email.

Inventaris ada di `/listings`: admin membuat dan mengelola listing, sedangkan sales dapat membacanya. Beranda `/`, katalog `/properti`, dan detail `/properti/{slug}` dapat dibuka tanpa akun. Katalog menampilkan listing yang diterbitkan dan tersedia; detail listing yang diterbitkan tetap terbuka saat statusnya dipesan atau terjual.

Untuk melihat media pada lingkungan lokal, jalankan perintah berikut sekali setelah setup:

```bash
php artisan storage:link
```

Unggahan listing memakai `PROPERTY_MEDIA_DISK=public` untuk disk lokal atau `PROPERTY_MEDIA_DISK=r2` untuk Cloudflare R2. Pada `.env` lingkungan R2, isi `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_BUCKET`, `R2_ENDPOINT` (endpoint S3 sesuai yurisdiksi bucket), dan `R2_PUBLIC_URL`. Untuk bucket development yang sudah diuji, gunakan `R2_BUCKET=ai-real-estate-assistant` dan `R2_PUBLIC_URL=https://ai-real-estate-assistant-storage.arikmhm.com`. Gunakan **Access Key ID** dan **Secret Access Key** dari token R2 dengan izin Object Read & Write untuk bucket ini; **Token value** Cloudflare API tidak dipakai Laravel. Simpan kredensial hanya di `.env`, jalankan `php artisan config:clear`, lalu mulai ulang server Laravel setelah konfigurasi berubah. Objek otomatis memakai prefix `listings/{id}/`, sehingga folder tidak perlu dibuat. URL media pada custom domain bersifat publik, termasuk untuk listing yang masih draf.

Pratinjau objek yatim dengan `php artisan listings:prune-media --disk=public` atau `php artisan listings:prune-media --disk=r2`; gunakan opsi `--delete` hanya setelah hasil pratinjau diperiksa. Pada 27 Oktober 2026, unggah foto/denah, URL custom domain, penggantian, penghapusan, dan pratinjau pemangkasan disk R2 development berhasil diuji memakai listing sintetis.

Untuk unggah melalui browser, batas PHP harus mencakup ukuran file dan jumlah seluruh file dalam satu pilihan. Di lingkungan development Herd Lite yang diuji pada 27 Oktober 2026, file `$HOME/.config/herd-lite/bin/php.ini` memakai `upload_max_filesize=16M` dan `post_max_size=256M`; aturan aplikasi tetap maksimal 10 MB per foto/denah. Periksa runtime dengan `$HOME/.config/herd-lite/bin/php --ini` dan `$HOME/.config/herd-lite/bin/php -i | grep -E 'upload_max_filesize|post_max_size'`, lalu mulai ulang server Laravel setelah mengubah `php.ini`. Sebelum penyesuaian, PHP membatasi 2 MB per file dan 8 MB per request sehingga upload browser gagal sebelum mencapai R2. Foto sintetis 3,27 MB berhasil diunggah dari editor admin ke R2 dan URL publiknya merespons HTTP 200 setelah penyesuaian.

Untuk mengisi katalog development dengan delapan listing sintetis Kudus, pastikan `.env` memakai `APP_ENV=local` dan `PROPERTY_MEDIA_DISK=r2` beserta konfigurasi R2 di atas. Jalankan pemeriksaan tanpa perubahan, lalu impor:

```bash
php artisan demo:import-kudus --check
php artisan demo:import-kudus --apply
```

Perintah `--check` memeriksa manifest, 32 WebP, database, dan koneksi R2 tanpa unggah. `--apply` menyimpan tiap listing sebagai draf, mengunggah tiga foto dan satu denah, lalu menerbitkannya. Jalankan `--apply` lagi untuk melanjutkan listing parsial tanpa menggandakan atau mengubah listing yang lengkap, termasuk status terjual. Bila sebuah listing memiliki media tambahan manual, impor berhenti untuk listing itu agar media tidak terhapus. Setelah mengubah aset sumber, gunakan `php artisan demo:import-kudus --apply --replace` untuk mengganti media kedelapan listing melalui layanan media aplikasi; tinjau perubahan manual terlebih dahulu. Aset dan manifest ada di `database/demo/kudus/`. Dataset ini hanya untuk development; URL gambar pada custom domain R2 dapat diakses publik.

## Tes dan pemeriksaan

Buat database uji **sekali** setelah container PostgreSQL berjalan:

```bash
docker compose exec -T db psql -U real_estate -d real_estate -c 'CREATE DATABASE real_estate_test'
```

`phpunit.xml` mengarah ke `real_estate_test` pada port lokal 55432; tes tidak memakai database aplikasi `real_estate`. Jalankan:

```bash
composer test
```

Perintah ini menjalankan Pint, PHPStan, dan tes PHPUnit. Pemeriksaan tahap 2 pada 27 Oktober 2026: 87 tes/319 assertion lulus, build frontend lulus, dan tujuh migrasi PostgreSQL berstatus `Ran`. Pemeriksaan data demo Kudus pada tanggal yang sama: 97 tes/405 assertion lulus; impor menghasilkan 8 listing terbit, 24 foto, 8 denah, dan 32 objek R2 WebP. Perintah pratinjau `php artisan listings:prune-media --disk=public` juga berhasil dan menemukan 0 objek yatim. PHPStan dapat menampilkan peringatan ekstensi turbo pada PHP Herd Lite berbasis musl; pemeriksaannya tetap lulus.

## Referensi Laravel resmi

- [Instalasi Laravel 13](https://laravel.com/docs/13.x/installation)
- [Starter kit dan autentikasi](https://laravel.com/docs/13.x/starter-kits)
- [Konfigurasi database](https://laravel.com/docs/13.x/database)
- [Pengujian](https://laravel.com/docs/13.x/testing)
