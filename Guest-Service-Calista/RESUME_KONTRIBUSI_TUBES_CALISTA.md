RESUME KONTRIBUSI CALISTA PADA PROYEK TUBES IAE

Berikut kontribusi yang saya kerjakan pada proyek Tugas Besar IAE :

Pembuatan dan upload Guest Service :
Saya membuat Guest Service dari awal. Fitur dan komponen yang saya kerjakan dan upload yaitu :
• Model database Guest, User, dan Role.
• GuestController untuk pengelolaan profil tamu dan validasi KTP.
• ApiKeyMiddleware dan SsoJwtMiddleware untuk autentikasi JWT.
• SsoIntegrationService untuk autentikasi Machine to Machine (M2M) ke SSO pusat.
• SoapLoggingService untuk mengirim audit trail ke server SOAP.
• RabbitMqPublisherService untuk mengirim event ke message broker.
• Dockerfile, docker-compose.yml, dan migrasi database.
• Dokumentasi API menggunakan Swagger.

Integrasi Guest Service :
Saya juga menyesuaikan Guest Service agar dapat terhubung dengan service lain dalam sistem integrasi kelompok. Perubahan yang saya lakukan antara lain:
• Menambahkan InternalKeyMiddleware.php untuk memvalidasi header X-INTERNAL-KEY pada komunikasi antar service.
• Menambahkan alias internal.key pada bootstrap/app.php.
• Menambahkan tiga endpoint internal pada routes/api.php: GET /internal/{guestId}, POST /internal/profile, POST /internal/validate-ktp
• Menambahkan field NIM pada request token SSO M2M di SsoIntegrationService.
• Menambahkan variabel INTERNAL_SERVICE_KEY, CENTRAL_SSO_TOKEN_URL, dan STUDENT_NIM pada .env.example dan docker-compose.yml.
• Menambahkan konfigurasi proxy_pass pada nginx.conf untuk mengarahkan request /api/guest/ ke container guest-service.

Kontribusi pada Docker Compose Utama :
Saya menambahkan konfigurasi guest-service dan guest-db pada file docker-compose.yml utama kelompok. Konfigurasi tersebut adalah :
• Environment variable untuk Guest Service.
• Jaringan iae-integration-network.
• Volume guest_mysql_data untuk penyimpanan database.
• Konfigurasi agar aplikasi Laravel berjalan pada port 8001.
