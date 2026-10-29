# Aditamaland Property

Platform penjualan properti Aditamaland Property ("Harga minimalis, kualitas maksimalis"): katalog publik dengan filter, asisten AI, dan simulasi cicilan, serta area internal admin/sales untuk inventaris, CRM, kunjungan, dan dashboard. Dibangun dengan Laravel 13, Livewire, dan PostgreSQL. Dokumen kebutuhan, arsitektur, dan roadmap disimpan hanya pada lingkungan kerja lokal.

## Prasyarat

- PHP 8.3+ dengan `pdo_pgsql`, Composer 2, Node/npm, dan Docker Compose.
- Terverifikasi 26 Oktober 2026: PHP 8.5.0, Composer 2.10.3, Node 24.22.1, npm 11.19.1, PostgreSQL 16 dalam Docker.
- Bila binari Herd Lite dan NVM tidak ada di PATH shell:

```bash
export PATH="$HOME/.config/herd-lite/bin:$HOME/.nvm/versions/node/v24.22.1/bin:$PATH"
```

## Menjalankan proyek

```bash
docker compose up -d db
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
npm ci
npm run build
php artisan storage:link
php artisan serve
```

Aplikasi di `http://127.0.0.1:8000`, database lokal di `127.0.0.1:55432`. Nilai di `compose.yaml` dan `.env.example` hanya untuk pengembangan lokal; `.env` diabaikan Git.

Pendaftaran publik dinonaktifkan. Buat admin pertama secara interaktif (kata sandi diminta tersembunyi; jangan taruh di argumen shell atau repository):

```bash
php artisan users:create-admin
```

## Fitur dan alamat

| Alamat | Isi | Akses |
|---|---|---|
| `/`, `/properti`, `/properti/{slug}` | Beranda, katalog dengan filter GET, detail | Publik |
| `/staff` | Akun sales, kontak WhatsApp/email publik dan persetujuannya | Admin |
| `/listings` | Inventaris, media, status publikasi/ketersediaan, riwayat status | Admin kelola, sales baca |
| `/prospects`, `/visits` | CRM prospek, aktivitas, tahap, kunjungan | Sales miliknya, admin semua |
| `/dashboard` | Ringkasan per peran | Admin, sales |

Detail properti tersedia dan dipesan menampilkan sales aktif serta simulasi cicilan; properti terjual menyembunyikan keduanya. Tombol WhatsApp hanya muncul setelah admin menyetujui nomor sales. Aturan bisnis lengkap ada di PRD lokal.

## Media dan Cloudflare R2

`PROPERTY_MEDIA_DISK=public` memakai disk lokal; `PROPERTY_MEDIA_DISK=r2` memakai Cloudflare R2. Untuk R2, isi `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_BUCKET`, `R2_ENDPOINT` (endpoint S3 sesuai yurisdiksi bucket), dan `R2_PUBLIC_URL` di `.env`. Bucket development yang sudah diuji: `R2_BUCKET=ai-real-estate-assistant`, `R2_PUBLIC_URL=https://ai-real-estate-assistant-storage.arikmhm.com`. Pakai **Access Key ID** dan **Secret Access Key** dari token R2 berizin Object Read & Write untuk bucket itu, bukan **Token value** Cloudflare API. Setelah mengubah konfigurasi, jalankan `php artisan config:clear` dan mulai ulang server. URL media pada custom domain bersifat publik, termasuk untuk listing draf.

Pratinjau objek yatim, lalu hapus hanya setelah hasilnya diperiksa:

```bash
php artisan listings:prune-media --disk=r2
php artisan listings:prune-media --disk=r2 --delete
```

Aplikasi menerima maksimal 10 MB per foto/denah, tetapi batas PHP bawaan (2 MB per file, 8 MB per request) memotong unggahan lebih dulu. Di Herd Lite, atur `upload_max_filesize=16M` dan `post_max_size=256M` di `$HOME/.config/herd-lite/bin/php.ini`, periksa dengan `php -i | grep -E 'upload_max_filesize|post_max_size'`, lalu mulai ulang server.

## Data demo Kudus

Delapan listing sintetis Kudus (aset dan manifest di `database/demo/kudus/`) membutuhkan `APP_ENV=local` dan `PROPERTY_MEDIA_DISK=r2`:

```bash
php artisan demo:import-kudus --check
php artisan demo:import-kudus --apply
```

`--check` memeriksa manifest, 32 WebP, database, dan koneksi R2 tanpa unggah. `--apply` aman diulang: melanjutkan listing parsial tanpa menggandakan yang lengkap, dan berhenti pada listing yang punya media manual tambahan. Setelah aset sumber berubah, `--apply --replace` mengganti media kedelapan listing.

Tiga akun sales sintetis (email `@example.test`, WhatsApp sintetis `+62800…` yang disetujui tampil publik) dibuat dengan perintah berikut. Kata sandinya tetap untuk development dan tertulis di `app/Console/Commands/SeedKudusSales.php`; perintah menolak berjalan di luar `APP_ENV=local` dan melewati akun yang sudah ada.

```bash
php artisan demo:seed-kudus-sales
```

Setelah listing, sales, dan admin ada, isi riwayat CRM sintetis: ±80 prospek selama enam bulan terakhir, aktivitas, dan kunjungan, termasuk minimal dua kunjungan terjadwal per sales dalam 10 hari ke depan. Seeder ini memundurkan `created_at` listing serta menandai rumah termurah terjual dan rumah berikutnya dipesan. Listing, akun, dan media tidak ditambah. Perintah ini menolak berjalan di luar `APP_ENV=local` atau bila tabel prospek sudah berisi (`php artisan db:seed` setara):

```bash
php artisan demo:seed-crm
```

Tanggal dihitung relatif terhadap waktu seed, jadi agenda dan tindak lanjut menua seiring waktu. Sebelum demo atau mengambil screenshot, segarkan data dengan `--fresh`. Opsi ini menghapus semua prospek, aktivitas, kunjungan, dan riwayat status listing, mengembalikan ketersediaan listing ke tersedia, lalu mengisi ulang. Semuanya dalam satu transaksi.

```bash
php artisan demo:seed-crm --fresh
```

## Asisten AI demo

Widget chat di kanan bawah halaman publik, hanya untuk lingkungan lokal dengan data sintetis. Isi `GEMINI_API_KEY` dan `AI_ASSISTANT_ENABLED=true` di `.env`, jalankan `php artisan config:clear`, lalu mulai ulang server. Model tetap `gemini-3.5-flash-lite` tanpa fallback; batas aplikasi 5 pesan global/menit, 10/IP/jam, 100 global/hari WIB. Jangan buka untuk pengunjung nyata pada tier gratis sebelum keputusan privasi dan kuota dibuat.

## Tes dan pemeriksaan

Buat database uji sekali setelah container PostgreSQL berjalan:

```bash
docker compose exec -T db psql -U real_estate -d real_estate -c 'CREATE DATABASE real_estate_test'
```

`phpunit.xml` memakai `real_estate_test`, bukan database aplikasi. Jalankan Pint, PHPStan, dan PHPUnit, lalu tes kalkulator cicilan:

```bash
composer test
```

```bash
npm run test:calc
```

PHPStan dapat menampilkan peringatan ekstensi turbo pada PHP Herd Lite berbasis musl; pemeriksaannya tetap lulus.

## Referensi Laravel resmi

- [Instalasi Laravel 13](https://laravel.com/docs/13.x/installation)
- [Starter kit dan autentikasi](https://laravel.com/docs/13.x/starter-kits)
- [Konfigurasi database](https://laravel.com/docs/13.x/database)
- [Pengujian](https://laravel.com/docs/13.x/testing)
