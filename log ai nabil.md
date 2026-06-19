# AI Log

[19/6/2026 - 16.15] 
Buat file docker-compose.yml untuk menggabungkan Reservasi-Service milik saya dengan layanan milik anggota tim lain (Catalog & Guest Service), sekaligus setup sebuah Nginx API Gateway di port 8000. Pastikan Swagger Reservasi-Service bisa diakses langsung melalui rute /api/reservasi/swagger/ tanpa masalah CORS.

[19/6/2026 - 16.30] 
Swagger-nya sudah muncul, tapi saat saya klik 'Try it out', API-nya gagal merespon karena salah membaca Base URL. Tolong perbaiki swagger.yaml, swagger.json, dan main.go di Reservasi Service agar request dari browser dialihkan dengan benar melewati API Gateway.

[19/6/2026 - 16.45] 
Saat ini AuthMiddleware di Reservasi-Service selalu menolak Token M2M (Machine-to-Machine) dari server SSO Pusat dengan pesan "Unauthorized: Invalid JWT claims" karena tidak mendeteksi field 'email'. Tolong modifikasi middleware tersebut agar memberikan pengecualian (bypass) jika token type-nya adalah m2m.

[19/6/2026 - 17.00] 
Layanan Reservasi gagal mendapatkan Token M2M dari SSO Cloud saat ingin mengirim log SOAP dan pesan RabbitMQ. Terdapat pesan error mengenai NIM dan format request. Tolong pelajari skema SSO yang baru dan perbaiki file sso.go agar request menggunakan application/x-www-form-urlencoded dan menyertakan NIM.

[19/6/2026 - 17.15] 
Tolong buat panduan pengujian menggunakan curl dan Postman untuk mengeksekusi fitur utama (Tahan Kamar dan Buat Pesanan). Lakukan pengujian secara langsung untuk melihat apakah fitur Hold Room di Reservasi sudah berhasil mengambil data ketersediaan kamar dari Catalog-Service dan data identitas dari Guest-Service.

[19/6/2026 - 17.20] 
Pengujian Hold Room gagal karena mengembalikan respon 404 Not Found dari Catalog Service. Coba cek struktur api.php milik Catalog dan Guest. Jika rute internal mereka dilindungi oleh prefix /api/, tolong tambahkan prefix tersebut pada fungsi pemanggil API di service_client.go milik Reservasi.

[19/6/2026 - 17.25] 
Logika Hold Room ternyata masih membaca data dari database lokal (GetRoomByID dan GetGuestByID). Ini melanggar konsep arsitektur microservice! Tolong rombak dan refactor fungsi tersebut di hold_room.go agar sepenuhnya mengandalkan HTTP Request internal ke layanan lain.