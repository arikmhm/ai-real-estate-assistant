# AI Real Estate Assistant

Platform satu agensi properti untuk katalog publik dan operasi admin/sales. Tahap 1 menambahkan peran, pembatasan akses, dan pengelolaan akun sales pada fondasi Laravel 13, Livewire, dan PostgreSQL. Dokumen kebutuhan, arsitektur, dan roadmap disimpan hanya pada lingkungan kerja lokal.

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

## Tes dan pemeriksaan

Buat database uji **sekali** setelah container PostgreSQL berjalan:

```bash
docker compose exec -T db psql -U real_estate -d real_estate -c 'CREATE DATABASE real_estate_test'
```

`phpunit.xml` mengarah ke `real_estate_test` pada port lokal 55432; tes tidak memakai database aplikasi `real_estate`. Jalankan:

```bash
composer test
```

Perintah ini menjalankan Pint, PHPStan, dan tes PHPUnit. Pemeriksaan terakhir 27 Oktober 2026: 56 tes/160 assertion lulus, build frontend lulus, dan enam migrasi PostgreSQL berstatus `Ran`. PHPStan dapat menampilkan peringatan ekstensi turbo pada PHP Herd Lite berbasis musl; pemeriksaannya tetap lulus.

## Referensi Laravel resmi

- [Instalasi Laravel 13](https://laravel.com/docs/13.x/installation)
- [Starter kit dan autentikasi](https://laravel.com/docs/13.x/starter-kits)
- [Konfigurasi database](https://laravel.com/docs/13.x/database)
- [Pengujian](https://laravel.com/docs/13.x/testing)
