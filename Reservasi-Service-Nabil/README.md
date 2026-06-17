# Reservasi Booking Service

![Go Version](https://img.shields.io/badge/Go-1.21+-00ADD8?style=flat&logo=go)
![Docker](https://img.shields.io/badge/Docker-Enabled-2496ED?style=flat&logo=docker)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-15-4169E1?style=flat&logo=postgresql)
![Redis](https://img.shields.io/badge/Redis-7-DC382D?style=flat&logo=redis)
![GraphQL](https://img.shields.io/badge/GraphQL-Supported-E10098?style=flat&logo=graphql)

Layanan Reservasi API adalah sebuah *backend service* berbasis **Golang** yang menangani logika inti dari sistem pemesanan kamar hotel. Sistem ini dirancang untuk mencegah masalah umum dalam e-commerce seperti *Double Booking* / *Race Condition* melalui pendekatan penguncian dua fase (*Two-Phase Locking*).

---

## Tujuan Sistem
1. **Mencegah Double Booking**: Memastikan satu kamar tidak bisa dipesan oleh dua orang di waktu yang bersamaan.
2. **Ketersediaan Real-time**: Mengelola status kamar secara instan saat pengguna menahan (*hold*) kamar.
3. **Fleksibilitas Data Retrieval**: Menyediakan REST API untuk transaksi dan GraphQL untuk pengambilan data relasional (Nota/Summary) guna mencegah *Over-fetching*.

---

## Arsitektur Microservices
Proyek ini mengadopsi prinsip **Clean Architecture** untuk memastikan pemisahan tanggung jawab yang jelas (*Separation of Concerns*). Meskipun saat ini berjalan sebagai *monolith* di dalam satu repositori, strukturnya sudah disiapkan untuk dipisah menjadi microservice mandiri:

### Penjelasan Layer (Clean Architecture)
- **Delivery**: Menangani protokol komunikasi (REST/Gin & GraphQL/gqlgen). Bertanggung jawab menerjemahkan HTTP Request menjadi format yang dimengerti Usecase.
- **Usecase**: Berisi *Business Logic* murni. Mengatur alur (aturan) pemesanan hotel tanpa peduli dari mana data berasal.
- **Repository**: Menangani komunikasi dengan *database* dan *cache* luar (PostgreSQL & Redis).
- **Domain**: Berisi definisi *struct* (entitas bisnis) dan *interface* yang mengikat semua layer.
- **Infrastructure**: Penghubung (*driver*) ke layanan eksternal (Database Connection, Redis Client, dll).

---

## Teknologi yang Digunakan
- **Bahasa Pemrograman**: Golang (Go 1.21+)
- **Web Framework**: Gin Gonic
- **ORM**: GORM (PostgreSQL Driver)
- **Cache & Distributed Lock**: Redis (go-redis)
- **API Spec**: Swagger (swaggo) & GraphQL (99designs/gqlgen)
- **Containerization**: Docker & Docker Compose

---

## Struktur Folder Project
```text
.
├── cmd/
│   └── app/
│       └── main.go           # Entry point aplikasi
├── configs/
│   └── .env.example          # Template environment variable
├── docs/                     # Dokumentasi Swagger (Auto-generated)
├── internal/
│   ├── delivery/
│   │   ├── graphql/          # Skema, Resolver, dan endpoint GraphQL
│   │   └── rest/             # Handler dan Router REST API (Gin)
│   ├── domain/               # Entitas model dan Interface Usecase/Repo
│   ├── infrastructure/       # Konfigurasi koneksi DB & Redis
│   ├── repository/           # Implementasi Query GORM & Redis Commands
│   └── usecase/              # Logika bisnis reservasi (Hold, Booking)
├── migrations/               # Skema SQL (init_scheme.sql) & Data Awal (seed_data.sql)
├── pkg/
│   └── middleware/           # Middleware Keamanan (API Key Auth)
├── Dockerfile                # Konfigurasi Multi-stage Docker build
└── docker-compose.yml        # Orkestrasi container (App, Postgres, Redis)
```

---

## Workflow Booking & Temporary Hold

Sistem ini menggunakan strategi **Two-Phase Reservation**:

1. **Fase 1: Temporary Hold (Redis)**
   Saat *user* memilih kamar dan mulai mengisi formulir pemesanan, sistem memanggil `POST /api/rooms/:id/hold`. Sistem menggunakan perintah **Redis `SETNX`** dengan TTL 10 Menit. Jika berhasil, kamar terkunci. Jika gagal (kamar sedang di-hold orang lain), sistem menolak permintaan. Hal ini mencegah *Race Condition*.
2. **Fase 2: Permanent Booking (PostgreSQL)**
   Setelah *user* menekan tombol "Bayar/Pesan", sistem memanggil `POST /api/bookings`. Sistem akan **memverifikasi kunci Redis** terlebih dahulu. Jika *user* terbukti pemegang sah kunci tersebut, pesanan akan disimpan permanen ke PostgreSQL dengan status `LOCKED`, dan kunci Redis dilepas agar tidak membebani memori.
3. **Auto-Release (Expire)**
   Jika *user* diam saja dan tidak menyelesaikan formulir dalam 10 menit, Redis secara otomatis menghapus kunci (*auto-expire*), sehingga kamar instan tersedia kembali tanpa perlu *Cron Job* atau *Message Broker*.

---

## Penggunaan Docker dan Docker Compose

Proyek ini telah dikontainerisasi untuk kemudahan *deployment* (Cloud-Native ready). 
- **Dockerfile** menggunakan teknik *Multi-stage Build* (dari `golang:alpine` ke `alpine:latest`) untuk menghasilkan *image* yang sangat ringan, cepat, dan aman.
- **Docker Compose** menghubungkan 3 *container* sekaligus: Aplikasi Golang, PostgreSQL, dan Redis di dalam satu `iae-network` internal yang terisolasi.

---

## Konfigurasi Environment Variable

Ganti nama file `.env.example` menjadi `.env` dan atur variabel berikut:

```env
# Koneksi Database PostgreSQL
DB_HOST=localhost   # Gunakan 'db' jika via Docker Compose
DB_PORT=5432
DB_USER=postgres
DB_PASS=password123
DB_NAME=iae_reservasi

# Koneksi Redis
REDIS_ADDR=localhost:6379 # Gunakan 'redis:6379' jika via Docker Compose

# API Security
IAE_SERVICE_NAME=booking_service
IAE_API_VERSION=v1
IAE_KEY=102022430014 # Wajib disertakan di header HTTP Request

# Application Config
APP_PORT=7070
APP_AUTO_MIGRATE=false # Set 'true' di Docker Compose
APP_AUTO_SEED=false    # Set 'true' di Docker Compose
```

---

## Cara Menjalankan Project

### Opsi A: Menjalankan dengan Docker (Rekomendasi)
Sangat cocok untuk tester dan tim pengembang agar tidak perlu repot *install* PostgreSQL/Redis manual.
```bash
docker-compose up -d --build
```

### Opsi B: Menjalankan secara Lokal (Development)
Pastikan server PostgreSQL dan Redis lokal Anda menyala.
```bash
# Pastikan Anda berada di root folder proyek (Layanan-Reservasi)
# Install dependensi
go mod download

# Menjalankan aplikasi
go run cmd/app/main.go
```
*Sistem akan memberikan prompt interaktif untuk melakukan Migrasi Tabel dan Seed Data saat pertama kali dijalankan lokal.*

---

## Endpoint Utama API

Setiap *request* wajib menyertakan header: `X-IAE-KEY: 102022430014`

### REST API
| Method | Endpoint | Deskripsi |
| :--- | :--- | :--- |
| `POST` | `/rooms/:id/hold` | Menahan sementara kamar (Fase 1) |
| `DELETE`| `/rooms/:id/hold` | Melepas tahanan kamar secara manual |
| `POST` | `/bookings` | Membuat reservasi final (Fase 2) |
| `POST` | `/:id/addons`| Menambah layanan (*Breakfast*, dll) |
| `GET`  | `/:id/summary`| Melihat nota dan detail pesanan |

### GraphQL API
- **Endpoint**: `POST /graphql/v1/summary`
- **Playground UI**: `GET /graphql` (Tanpa Auth)

### Dokumentasi Swagger
Anda dapat mencoba API langsung dari browser melalui Swagger UI:
- **URL**: `http://localhost:7070/swagger/index.html`

---

## Best Practice & Scalability System
- **Graceful Degradation**: Server tetap menyala meskipun RabbitMQ/Message Broker (Fitur Masa Depan) mati, berkat penanganan error infrastruktur yang aman.
- **GraphQL untuk Summary**: Digunakan untuk menyelesaikan masalah `N+1 Query` saat *Front-end* ingin mengambil data Pesanan beserta relasi detail Kamar dan Daftar Add-ons sekaligus dalam satu kali panggilan.
- **Atomic Operations**: Pemanfaatan `SETNX` di Redis memastikan eksekusi bersifat *atomic*, sangat siap menahan beban ribuan klik (*concurrency*) di saat yang bersamaan tanpa kebocoran data kamar.
