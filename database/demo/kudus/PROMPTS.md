# Prompt set aset inventaris Kudus

Semua aset dibuat dengan **built-in image_gen** pada 27 Oktober 2026, lalu dikonversi ke WebP sRGB 1536 × 1024. Gambar pertama tiap listing menjadi referensi untuk foto sudut kedua, foto detail, dan denah bangunan. Untuk denah tanah, iterasi akhir memakai prompt diagram sederhana tanpa referensi gambar karena versi fotorealistis terlalu besar dan kurang jelas sebagai diagram.

## Kerangka prompt

- **Foto utama:** `Use case: photorealistic-natural`; foto properti fiktif di Kabupaten Kudus, horizontal 3:2, sudut pandang manusia, cahaya tropis alami, lingkungan hunian setempat, material dan sedikit tanda pemakaian yang wajar. Tanpa orang, papan iklan, teks, logo, watermark, atau klaim alamat tepat.
- **Foto sudut kedua:** gunakan foto utama sebagai `Image 1`; ambil sudut lebih dekat/berbeda sambil mempertahankan bentuk atap, warna, bukaan, pagar, tanaman, dan orientasi parkir/batas lahan.
- **Foto detail:** gunakan foto utama sebagai `Image 1`; rumah menampilkan ruang keluarga yang selaras dengan fasad, kos menampilkan satu kamar contoh sederhana, tanah menampilkan sudut balik bidang yang sama. Hindari interior mewah dan skala yang tidak masuk akal.
- **Denah rumah/kos:** `Use case: infographic-diagram`; pandangan atas 2D, ruang dan kamar mandi sesuai manifest, sirkulasi serta pintu masuk masuk akal, halaman/parkir sesuai foto. Tanpa teks, angka, dimensi, atau klaim gambar ukur.
- **Denah tanah:** `Use case: infographic-diagram`; diagram bidang datar dengan blok warna sederhana, akses jalan dan penanda batas mengikuti foto. Tanpa bangunan, teks, angka, dimensi, atau klaim survei.

## Ciri visual dan sudut per listing

| Key | Acuan bentuk dan material | Sudut kedua | Detail / denah |
|---|---|---|---|
| kds01 | Rumah satu lantai, dinding putih gading, atap genteng terakota, pintu jati gelap, dua jendela hitam sempit, pohon kamboja, carport kanan | Teras dan pintu dari kiri depan | Ruang keluarga; denah 3 kamar, 2 mandi, carport kanan |
| kds02 | Rumah dua lantai abu-abu muda, atap gelap, kisi kayu vertikal, balkon kecil, carport kiri | Fasad kanan depan lebih dekat | Ruang keluarga dan tangga; denah dua lantai 4 kamar, 3 mandi |
| kds03 | Rumah kompak hijau pucat, genteng cokelat, jendela putih, pagar rendah putih, teras kecil | Teras dan parkir motor dari kanan depan | Ruang keluarga kecil; denah 2 kamar, 1 mandi |
| kds04 | Rumah satu lantai krem, genteng merah karat, pintu hijau gelap, beranda panjang, halaman rumput/kerikil, carport kanan | Beranda dari halaman | Ruang keluarga; denah 3 kamar, 2 mandi, halaman depan |
| kds05 | Bidang persegi panjang 180 m², rumput dan tanah merah, jalan paving depan, dua patok, dinding sisi kanan, rumpun pisang | Sudut kiri depan menuju belakang | Sudut balik menuju jalan; diagram bidang/akses sederhana |
| kds06 | Bidang 320 m², dinding bata kiri, jalan aspal dan selokan depan, patok putih, pohon kecil kanan belakang | Dari dalam bidang menuju sisi kanan | Dari belakang menuju jalan; diagram bidang/akses sederhana |
| kds07 | Kos dua lantai krem, genteng cokelat, koridor terbuka, pagar besi gelap, aksen tangga biru abu-abu, pelataran motor | Koridor lantai bawah | Kamar contoh; denah 6 kamar per lantai, masing-masing kamar mandi |
| kds08 | Kos dua lantai pasir pucat, atap metal hijau tua, tangga luar, pagar balkon hitam, bugenvil dekat masuk | Koridor dari pelataran | Kamar contoh; denah 4 kamar per lantai, masing-masing kamar mandi |

**Pemeriksaan visual:** foto antarsudut dibandingkan sebagai kelompok; rumah tidak berubah jumlah lantai, warna utama, dan posisi carport. Denah rumah dan kos dihitung manual terhadap jumlah kamar di manifest. Denah tanah bersifat ilustrasi dan tidak menyatakan batas legal.

## Prompt final per berkas

Setiap baris berikut dipakai bersama aturan global pada **Kerangka prompt**. `Image 1` berarti `01-front.webp` listing yang sama sebagai referensi visual. Seluruhnya meminta komposisi horizontal 3:2, tanpa teks/logo/watermark. Untuk denah, ini adalah ilustrasi tata ruang sintetis, bukan gambar kerja atau dokumen ukur.

| Berkas | Prompt khusus |
|---|---|
| kds01/01-front.webp | Foto properti fiktif satu lantai di Kota Kudus; dinding putih gading, genteng terakota, pintu jati gelap, dua jendela hitam ramping, pohon kamboja, carport kanan; jalan lingkungan dan cahaya pagi tropis alami. |
| kds01/02-angle.webp | Image 1; pandangan lebih dekat dari kiri depan ke teras dan pintu; pertahankan atap, pohon kamboja, carport kanan dan warna dinding. |
| kds01/03-detail.webp | Image 1; ruang keluarga sederhana yang masuk akal untuk rumah ini, palet putih gading dan kayu, cahaya dari jendela; skala ruang tidak mewah. |
| kds01/04-floorplan.webp | Image 1; denah pandangan atas rumah satu lantai dengan tiga kamar tidur, dua kamar mandi, ruang keluarga, dapur, teras dan carport kanan; akses ruang masuk akal. |
| kds02/01-front.webp | Foto properti fiktif dua lantai di Jati, Kudus; dinding abu-abu muda, atap gelap, kisi kayu vertikal, balkon kecil, carport kiri; fasad sederhana dalam lingkungan hunian Jawa Tengah. |
| kds02/02-angle.webp | Image 1; sudut kanan depan lebih dekat; posisi balkon, kisi kayu, carport kiri, dan garis atap tetap sama. |
| kds02/03-detail.webp | Image 1; ruang keluarga sederhana dengan tangga menuju lantai atas, cahaya alami, bahan dan warna sesuai fasad, skala realistis. |
| kds02/04-floorplan.webp | Image 1; denah dua lantai total empat kamar tidur dan tiga kamar mandi, ruang keluarga, tangga, dapur dan carport kiri; pembagian ruang per lantai jelas. |
| kds03/01-front.webp | Foto rumah fiktif kompak satu lantai di Bae, Kudus; dinding hijau pucat, genteng cokelat, jendela putih, pagar rendah putih, teras kecil dan tempat parkir motor. |
| kds03/02-angle.webp | Image 1; pandangan dari kanan depan ke teras dan parkir motor; pertahankan genteng cokelat, dinding hijau pucat, pagar rendah dan proporsi rumah kecil. |
| kds03/03-detail.webp | Image 1; ruang keluarga kecil dan sederhana, cahaya alami, material sesuai rumah kompak; jangan buat ruang terlalu luas. |
| kds03/04-floorplan.webp | Image 1; denah satu lantai dua kamar tidur, satu kamar mandi, ruang keluarga, dapur dan teras kecil; sesuai luas bangunan 68 m² secara ilustratif. |
| kds04/01-front.webp | Foto rumah fiktif satu lantai di Mejobo, Kudus; dinding krem, genteng merah karat, pintu hijau gelap, beranda panjang, halaman rumput dan kerikil, carport kanan. |
| kds04/02-angle.webp | Image 1; pandangan dari halaman menuju beranda; pertahankan pintu hijau, genteng merah karat, dinding krem, carport kanan dan tanaman depan. |
| kds04/03-detail.webp | Image 1; ruang keluarga yang sederhana, tenang dan terang, warna krem serta aksen hijau lembut; jangan tampilkan interior mewah. |
| kds04/04-floorplan.webp | Image 1; denah satu lantai tiga kamar tidur, dua kamar mandi, ruang keluarga, dapur, beranda panjang, halaman depan dan carport kanan. |
| kds05/01-front.webp | Foto bidang tanah kosong fiktif sekitar 180 m² di Kaliwungu, Kudus; bentuk relatif persegi panjang, rumput dan tanah merah, jalan paving di depan, dua patok, dinding sisi kanan dan rumpun pisang. |
| kds05/02-angle.webp | Image 1; sudut dari kiri depan memandang ke belakang bidang yang sama; pertahankan jalan paving, patok, dinding kanan, rumpun pisang dan bentuk bidang. |
| kds05/03-detail.webp | Image 1; sudut balik dari dalam bidang menuju akses jalan; pertahankan tanaman, patok, dinding kanan dan permukaan rumput/tanah merah. |
| kds05/04-floorplan.webp | Diagram 2D sederhana bidang tanah persegi panjang kosong, jalan paving pada sisi depan, dua penanda batas, dinding pada sisi kanan dan tanaman di belakang; ilustrasi akses tanpa ukuran legal. |
| kds06/01-front.webp | Foto bidang tanah pekarangan fiktif sekitar 320 m² di Jekulo, Kudus; dinding bata kiri, jalan aspal dan selokan depan, patok putih, pohon kecil kanan belakang, rumput bercampur tanah. |
| kds06/02-angle.webp | Image 1; pandangan dari dalam lahan ke sisi kanan; pertahankan dinding bata kiri, akses jalan/selokan depan, patok dan pohon kecil. |
| kds06/03-detail.webp | Image 1; pandangan dari bagian belakang menuju jalan aspal; pertahankan bentuk lahan, dinding bata, selokan, patok dan pohon kecil yang sama. |
| kds06/04-floorplan.webp | Diagram 2D sederhana bidang tanah kosong, jalan aspal dan selokan pada sisi depan, dinding bata di kiri, patok putih, pohon kecil kanan belakang; hanya ilustrasi bidang dan akses. |
| kds07/01-front.webp | Foto bangunan kos fiktif dua lantai di Kota Kudus; dinding krem, genteng cokelat, koridor terbuka, pagar besi gelap, tangga beraksen biru abu-abu, pelataran parkir motor. |
| kds07/02-angle.webp | Image 1; pandangan koridor lantai bawah; pertahankan warna krem, pagar gelap, susunan pintu, tangga dan halaman motor; detail pakai sehari-hari yang wajar. |
| kds07/03-detail.webp | Image 1; satu contoh kamar kos sederhana dan bersih dengan kamar mandi privat; material dan pencahayaan selaras bangunan, ukuran kamar realistis. |
| kds07/04-floorplan.webp | Image 1; denah dua lantai kos, enam kamar di lantai satu dan enam di lantai dua, masing-masing dengan kamar mandi; koridor terbuka, tangga dan halaman parkir. |
| kds08/01-front.webp | Foto bangunan kos fiktif dua lantai di Bae, Kudus; dinding pasir pucat, atap metal hijau tua, tangga luar, pagar balkon hitam, bugenvil di dekat pintu masuk. |
| kds08/02-angle.webp | Image 1; pandangan koridor dari pelataran; pertahankan atap hijau tua, dinding pasir pucat, pagar hitam, tangga luar, bugenvil dan susunan pintu. |
| kds08/03-detail.webp | Image 1; satu contoh kamar kos sederhana dengan kamar mandi privat, warna dan material yang sesuai bangunan; skala kamar realistis. |
| kds08/04-floorplan.webp | Image 1; denah dua lantai kos, empat kamar di lantai satu dan empat di lantai dua, masing-masing dengan kamar mandi; tangga luar, koridor dan halaman depan. |
