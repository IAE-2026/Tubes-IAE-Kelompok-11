17/06/2026

Tugas kami adalah menggabungkan (merger) beberapa mini-service individu menjadi satu ekosistem microservices yang terintegrasi. Salah satu service yang sudah siap adalah "Catalog Service" (menggunakan Laravel & GraphQL) yang telah lulus uji Central Infrastructure Compliance (berhasil melakukan login SSO -> kirim SOAP Audit -> broadcast ke RabbitMQ).

Berikut adalah syarat kelulusan dan kriteria rubrik Tugas Besar kami yang harus dipenuhi:
1. **API Gateway & Routing Hub (Bobot 20%):** Seluruh service harus dibungkus di belakang API Gateway (Nginx/Kong). Tidak boleh ada service internal yang bisa diakses langsung dari luar (bypass gateway).
2. **End-to-End Core Business Flow (Bobot 25%):** Harus ada alur transaksi mulus lintas service secara internal (misalnya, Catalog Service dipanggil oleh Service B via REST/GraphQL tanpa intervensi manual).
3. **Akuntabilitas Git (Bobot 30% individual):** Harus ada kolaborasi menggunakan repositori bersama di GitHub/GitLab untuk melacak kontribusi[cite: 1].
4. **Luaran:** Sebuah repositori gabungan berisi arsitektur container Docker, konfigurasi API Gateway, dan kode program terintegrasi[cite: 1].

Tolong buatkan panduan teknis langkah demi langkah untuk mengeksekusi proyek ini, yang mencakup:
1. **Rancangan Arsitektur Docker Compose:** Buatkan template `docker-compose.yml` untuk menggabungkan 3 layanan (termasuk database masing-masing) dan 1 API Gateway. Pastikan jaringan internalnya aman sehingga klien hanya bisa mengakses via Gateway.
2. **Konfigurasi API Gateway (Nginx):** Buatkan template konfigurasi `nginx.conf` sederhana untuk merouting request API dari luar ke service-service internal yang spesifik (misalnya `/api/catalog` diarahkan ke Catalog Service).
3. **Strategi Internal Communication:** Berikan contoh cara amannya agar satu Laravel service bisa memanggil service Laravel lainnya di dalam satu jaringan Docker yang sama menggunakan HTTP Client.
4. **Strategi Git Collaboration:** Berikan saran alur kerja Git (branching model) yang baik agar kontribusi setiap anggota tetap rapi, tercatat (log commit terlihat), dan tidak saling menimpa pekerjaan orang lain.

Gunakan bahasa yang mudah dipahami namun tetap profesional, cocok untuk diterapkan oleh mahasiswa yang sedang merancang arsitektur sistem enterprise.