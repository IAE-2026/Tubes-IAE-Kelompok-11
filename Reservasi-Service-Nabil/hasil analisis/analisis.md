# Analisis Transaksi Kritis Layanan Reservasi (Booking Service)

## 1. Identifikasi Transaksi Kritis

Pada layanan reservasi ini terdapat beberapa endpoint dengan fungsi yang berbeda:

* `POST /rooms/:id/hold` → Menahan kamar sementara menggunakan Redis dengan TTL 10 menit.
* `DELETE /rooms/:id/hold` → Melepas hold kamar.
* `POST /bookings` → Membuat pesanan awal (Create Booking).
* `POST /bookings/:id/addons` → Menambahkan layanan tambahan pada booking yang sudah ada.
* `GET /bookings/:id/summary` → Menampilkan ringkasan atau nota transaksi.
* `POST /graphql/v1/summary` → Mengambil detail booking beserta relasi datanya.

Dari seluruh endpoint tersebut, saya memilih **POST /bookings (Create Booking)** sebagai transaksi yang paling kritis.

## 2. Mengapa Create Booking Paling Kritis?

### 2.1 Mengubah Status Data Secara Permanen dan Berkaitan dengan Transaksi Inti

Sebelum endpoint ini dijalankan, kamar masih berstatus **hold** sementara di Redis akibat `POST /rooms/:id/hold`. Ketika `POST /bookings` berhasil diproses, sistem membuat data booking baru di DB PostgreSQL dengan status **LOCKED** dan mulai melakukan perhitungan total biaya final. Jika terjadi kesalahan pada tahap ini, misalnya booking ganda atau perhitungan tidak sesuai, maka dampaknya langsung memengaruhi integritas data transaksi, dan ini sangat berbahaya untuk bisnis tersebut.

### 2.2 Melibatkan Hampir Semua Komponen Sistem

Dibandingkan endpoint lain yang biasanya hanya melakukan transaksi sederhana, endpoint Create Booking memiliki alur yang lebih kompleks karena melibatkan hampir seluruh komponen sistem, yaitu:

* Middleware: validasi header dan JWT
* Redis: pengecekan hold dan idempotency
* PostgreSQL: penyimpanan data booking
* Cloud SOAP: pengiriman audit transaksi
* Cloud RabbitMQ: broadcast event ke layanan lain

Karena banyak komponen yang saling terhubung, endpoint ini menjadi inti transaksi sistem.

### 2.3 Rentan terhadap Race Condition dan Double Booking

Pada kondisi banyak pengguna melakukan reservasi secara bersamaan, endpoint ini menjadi bagian yang paling sensitif. Untuk mengatasi risiko tersebut, sistem menerapkan:

* **Idempotency Key** untuk mencegah request yang sama diproses lebih dari satu kali.
* **Validasi hold room** agar hanya pengguna yang memegang kunci lock sebelumnya yang dapat melanjutkan proses booking.


### 2.4 Memenuhi Kebutuhan Audit dan Integrasi Sistem

Setelah transaksi berhasil disimpan, sistem mengirim data transaksi ke service SOAP dalam bentuk XML untuk mendapatkan ReceiptNumber sebagai bukti audit. Setelah itu sistem juga mengirim event ke RabbitMQ (sebagai komunikasi asynchronous) agar layanan lain tau ada booking baru.

## 3. Skema Role Lokal

Sistem ini tidak menggunakan role dari JWT secara langsung, tetapi melakukan pemetaan ke role lokal. Alurnya:

1. Middleware memverifikasi JWT.
2. Email pengguna diambil dari payload JWT.
3. Sistem melakukan pencarian ke tabel `users` di DB PostgreSQL.
4. Role yang ditemukan di DB kemudian disimpan pada context request dan digunakan untuk otorisasi.

Role yang ada:

* **Guest**: melakukan hold kamar, membuat booking, dan melihat summary.
* **Admin**: memiliki akses penuh termasuk fitur manajemen dan migrasi data.

## 4. Alur Transaksi Create Booking

### Fase 1: Autentikasi dan Otorisasi

Sistem memvalidasi `X-IAE-KEY`, memverifikasi JWT menggunakan JWKS dari Redis, kemudian mencocokkan email ke database lokal untuk mendapatkan role user.

### Fase 2: Eksekusi Transaksi

Sistem melakukan pengecekan idempotency untuk mencegah duplikasi request dan memvalidasi hold kamar. Setelah itu sistem melakukan pengecekan status kamar di DB PostgreSQL untuk memastikan kamar masih berstatus `AVAILABLE`. Jika valid, sistem mengubah status kamar menjadi `LOCKED` (`UpdateRoomStatus`) agar tidak terjadi double booking, lalu menyimpan data booking ke DB PostgreSQL.

Untuk menjaga konsistensi database, jika proses `INSERT booking` gagal dilakukan maka sistem menjalankan mekanisme rollback yang mengembalikan status kamar menjadi `AVAILABLE` kembali.

### Fase 3: Audit dan Event

Setelah transaksi berhasil disimpan:

* Sistem mengirim audit trail ke SOAP service lalu menerima `receipt_number`.
* `receipt_number` disimpan kembali ke DB PostgreSQL.
* Sistem melakukan publish event `BookingCreated` ke Cloud RabbitMQ.
* Hold sementara pada Redis dilepas (Fungsi ReleaseRoom) karena kontrol reservasi sudah beralih ke data booking permanen ke database utama.

Untuk meningkatkan kestabilan integrasis sistem, sistem juga memiliki mekanisme **Retry Worker** (outbox pattern) yang menangani kegagalan pengiriman ke SOAP atau RabbitMQ. Request yang gagal akan disimpan sementara di Redis queue dan dicoba kembali secara berkala tanpa mengganggu transaksi utama.

## 5. Kesimpulan

Saya memilih **POST /bookings (Create Booking)** sebagai transaksi paling kritis karena endpoint ini menjadi inti perubahan data dalam sistem. Endpoint ini melibatkan banyak komponen, memiliki risiko seperti double booking, serta memiliki kebutuhan audit dan integrasi dengan layanan eksternal.
