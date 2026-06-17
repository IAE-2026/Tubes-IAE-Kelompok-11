# AI Log

Rekap jurnal proses pengembangan dan interaksi dengan AI.

[15/5/2026 - 00.18] 
Buatkan wrapper response pada folder internal/domain/ untuk RestAPI agar response sistem konsisten.

[15/5/2026 - 00.36] 
buat konfigurasi untuk koneksi PostgreSQL ( buat di internal/infrastructure/postgres.go). kemudian gunakan grom untuk mempermudah pemetaan model data ke tabel PostgreSQL. (untuk grom sudah saya instal di project ini)

[15/5/2026 - 00.40]
buat konfigurasi untuk koneksi Redis ( buat di internal/infrastructure/redis.go). gunakan go-redis untuk berinteraksi dengan layanan Redis lokal (sudah saya instal)

[15/5/2026 - 00.57]
Buat implementasi middleware authentication di Gin untuk memvalidasi header X-IAE-KEY dengan value wajib 102022430014. Jika key tidak valid atau tidak ada, return response unauthorized. Selain itu, saya sudah membuat global response wrapper/helper agar seluruh response API memiliki format konsisten dengan field status, message, data, dan meta untuk seluruh endpoint sukses maupun error pada file respons.go

[15/5/2026 - 01.02]
lakukan konfigurasi pada file main.go agar menggunakan configuration environment dari file .env, dan juga daftarkan middleware authentication yang sudah dibuat

[15/5/2026 - 01.12]
implementasikan layer Delivery (REST Handlers) dan Usecase pada layanan Booking Service menggunakan framework gin ini dengan pola Clean Architecture, di mana saya memerlukan beberapa endpoint utama sesuai kontrak tim pada file kontrak API.md. pastikan seluruh respon mematuhi standar Integration Contract dengan wrapper JSON yang terdiri dari field status, message, data, dan meta (berisi informasi service name dan api version) , perhatikan tetep gunakan grom untuk manajemen data di PostgreSQL, serta terapkan pengamanan header X-IAE-KEY menggunakan nilai "102022430014"

[15/5/2026 - 01.24]
Tambahkan anotasi pada setiap handler Gin dan agar Swagger UI bisa diakses menggunakan swag init

[15/5/2026 - 09.58]
Saya ingin tiap server utama dijalankan sistem akan meminta konfirmasi admin yang menjalankan main.go. apakah ingin migrate ulang tabel ke database? y/N. kemudian apakah ingin memasukkan data di seed_data.sql juga? y/N. kemudian skenario lain adalah admin bisa melewatkan migrasi ulang tabel tapi bisa memasukkan data di seed_data.sql. untuk migrasi bisa menggunakan /migrate dan untuk seed bisa menggunakan /seed

[15/5/2026 - 11.50]
Saya ingin melakukan implementasi Fase 1 penguncian sementara (Hold Room) menggunakan Redis TTL (10 mnt) untuk mencegah double booking/race condition saat user menekan tombol booking. tambahkan endpoint POST /rooms/:id/hold dan DELETE /rooms/:id/hold, serta perbarui logika pembuatan pesanan agar memverifikasi kunci di redis sebelum menyimpan ke database utama postgreSQL.

[15/5/2026 - 15.36]
Buatkan skema, model, resolver, dan endpoint GraphQL untuk mengambil data detail booking beserta relasinya ( seperti rincian kamar, rincian tamu, add-ons). sesuaikan juga dg struktur database yang di GORM dan pastikan berjalan di jalur POST /graphql/v1/summary.

[15/5/2026 - 15.32]
Buat konfigurasi dockerfile multi-stage menggunakan golang 1.22-alpine dan alpine latest, serta docker-compose.yml untuk menghubungkan aplikasi dengan PostgreSQL 15 dan Redis 7 dalam satu network internal.


file kredensial yang saya cantumkan pada .kredensial yang didalamnya terdapat konfigurasi SSO, SOAP, dan RabbitMQ. file tersebut akan saya gunakan untuk integrasi dengan sistem pusat.

[11/6/2026 - 17.17]
Tambahkan variabel environment baru di .env dan perbarui file konfigurasi di internal/infrastructure/. Tambahkan URL untuk SSO_URL=https://iae-sso.virtualfri.id dan API_KEY=KEY-MHS-25. Kemudian, buat sebuah helper atau service M2M di layer infrastructure untuk melakukan HTTP POST ke /api/v1/auth/token menggunakan payload JSON {"api_key": "KEY-MHS-25"}. Fungsi ini harus mengembalikan Bearer JWT, dan cache token tersebut di Redis dengan TTL menyesuaikan expired token agar sistem tidak terus-menerus memanggil API dari server cloud.

[11/6/2026 - 22.35]
Perbarui middleware autentikasi Gin yang sudah ada. Selain mengecek header X-IAE-KEY, tambahkan pengecekan Authorization: Bearer <token>. Buat fungsi untuk mengambil Public Key (RS256) dari GET https://iae-sso.virtualfri.id/api/v1/auth/jwks dan cache JWKS tersebut di Redis selama 24 jam. Gunakan library golang-jwt/jwt dan JWKS tersebut untuk memverifikasi keaslian JWT klien. Jika valid, ekstrak claims email-nya, lalu lakukan query menggunakan GORM ke tabel users untuk memvalidasi role lokalnya. Lempar identitas user ke Gin context.

[11/6/2026 - 22.43]
Buat file soap_client.go di internal/infrastructure/. Buat fungsi HTTP POST ke /soap/v1/audit. Gunakan M2M Token dari helper yang dibuat sebelumnya di header Authorization. Fungsi ini menerima payload JSON transaksi, lalu membungkusnya ke dalam format XML Envelope yang memiliki tag <iae:TeamID>TEAM-25</iae:TeamID>, <iae:ActivityName>BookingCreated</iae:ActivityName>, dan <iae:LogContent><![CDATA[ ...JSON... ]]></iae:LogContent>. Lakukan unmarshal pada respons XML dari server cloud untuk mengekstrak dan mengembalikan nilai dari tag <iae:ReceiptNumber>

[12/6/2026 - 13.23]
Buat file rabbitmq_client.go di internal/infrastructure/. Buat fungsi untuk melakukan HTTP POST ke /api/v1/messages/publish. Gunakan M2M Token di header. Fungsi ini menerima struct event (misal: booking_id, status: LOCKED, timestamp), merubahnya menjadi JSON, dan menembaknya ke API gateway server cloud tersebut untuk mempublikasikan event ke iae.central.exchange secara asynchronous

[12/6/2026 - 14.10]
Perbarui model entitas Booking di GORM dengan menambahkan kolom receipt_number (tipe string/varchar, nullable). Selanjutnya, orkestrasikan semuanya di file Usecase CreateBooking. Urutan logikanya: 1) Cek Hold Room di Redis, 2) INSERT data pesanan ke PostgreSQL, 3) Panggil soap_client untuk audit dan dapatkan nomor resi, 4) UPDATE receipt_number di database, 5) Panggil rabbitmq_client untuk broadcast event

[12/6/2026 - 14.37]
Terapkan resiliensi (Retry Queue/Outbox Pattern) pada Usecase CreateBooking. Jika panggilan ke soap_client atau rabbitmq_client mengalami timeout atau gagal (HTTP 500 dari server cloud), jangan gagalkan transaksi / jangan kembalikan error ke klien. Tangkap error tersebut, lalu gunakan Redis List (LPUSH retry:soap atau LPUSH retry:rabbitmq) untuk menyimpan payload yang gagal. Tetap kembalikan respons HTTP 201 Created ke pengguna dengan wrapper standar. Sisipkan goroutine sederhana yang berjalan di background untuk membaca antrean Redis tersebut dan mencoba mengirim ulang secara berkala

[12/6/2026 - 20.48]
Perbarui seluruh dokumentasi dan testing pada swagger dengan update yang sudah dilakukan

