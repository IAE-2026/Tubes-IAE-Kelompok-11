# AI Prompting Log — Guest Service
## Integrasi Aplikasi Enterprise | Tugas Besar

**Nama:** Calista Aurelia Putri  
**NIM:** 102022400289  
**Kelas:** SI-48-09  
**Service:** Guest Service - Smart Hospitality  
**AI Tool:** claude.ai

---

## Prompt 1
[19/6/2026]

Tugas besar ini mengharuskan kami untuk mengintegrasikan beberapa service individu yang telah dikembangkan secara mandiri oleh tiap anggota kelompok menjadi satu ekosistem microservices yang berjalan sebagai satu kesatuan.

Service yang saya kerjakan adalah Guest Service (Laravel), yang bertanggung jawab atas manajemen profil tamu dan validasi KTP.

Dalam proses integrasi ini, saya mendapatkan panduan teknis dari AI untuk membantu mengeksekusi beberapa tahapan, mulai dari penyesuaian konfigurasi SSO hingga menyiapkan internal API endpoint yang aman agar service lain (seperti Reservasi Service) bisa memanggil Guest Service dari dalam jaringan Docker tanpa melewati API Gateway publik.

Untuk memenuhi rubrikasi, bantu saya:
1. Menyiapkan endpoint internal (`/internal/`) yang dilindungi shared secret (`X-INTERNAL-KEY`) sebagai pengganti JWT untuk komunikasi antar-service.
2. Menyesuaikan SSO M2M agar request token menyertakan field `nim` sesuai ketentuan terbaru.
3. Mengintegrasikan Guest Service ke dalam `docker-compose.yml` global tim dan konfigurasi `nginx.conf` API Gateway.

---

## Prompt 2
[19/6/2026]

Untuk tahap-tahap berikut: Membuat InternalKeyMiddleware.php, Mendaftarkan Middleware ke Aplikasi Middleware, Menambahkan Route /internal Baru Pada file routes/api.php, Menambahkan Konfigurasi Lingkungan (.env, .env.example, & docker-compose.yml) — apakah ini diambil dari yang di upload teman atau beda?

---

## Prompt 3
[19/6/2026]

Jadi coba list apa saja yang harus saya lakukan berurutan dari yang saya sebutkan sebelum-sebelumnya.

---

## Prompt 4
[19/6/2026]

Ada tambahan ketentuan baru: `network: iae-integration-network`, `INTERNAL_SERVICE_KEY: internal-tim-11-iae`, nama container Calista: `guest-service`. Buat endpoint yang ditambahkan ke middleware internal semua endpoint yang ada di kontrak API ya, ga cuma satu aja. Jadi coba list yang lengkap tahapan yang harus saya lakukan beserta ketentuan-ketentuannya.

---

## Prompt 5
[19/6/2026]

Baik saya ingin lakukan satu-satu dari pertama. Langkah 1 (Penyesuaian SSO M2M).

---

## Prompt 6
[19/6/2026]

Coba periksa apakah sudah benar yang saya masukkan.

---

## Prompt 7
[19/6/2026]

Lanjut tahap kedua: Mendaftarkan Middleware ke Aplikasi.

---

## Prompt 8
[19/6/2026]

Lanjut tahap selanjutnya: Menambahkan Route /internal Baru Pada file routes/api.php.

---

## Prompt 9
[19/6/2026]

Lanjut langkah selanjutnya: Menambahkan Konfigurasi (.env, .env.example, & docker-compose.yml).

---

## Prompt 10
[19/6/2026]

Untuk file hasil git pull yang nginx.conf, isi apa yang bagian guest service?

---

## Prompt 11
[19/6/2026]

Tambahkan konfigurasi untuk service guest-service pada file docker-compose.yml (root) sesuaikan dengan konfigurasi api-gateway.

---

## Prompt 12
[19/6/2026]

docker compose up -d --build menghasilkan error pada reservasi-service karena DNS transient error saat `apk add`. Bagaimana cara memperbaikinya?

---
