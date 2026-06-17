# AI Prompting Log — Guest Service
## BBK2HAB3 - Integrasi Aplikasi Enterprise | Tugas 2

**Nama:** Calista Aurelia Putri  
**NIM:** 102022400289  
**Service:** Guest Service - Smart Hospitality  
**AI Tool:** Claude (claude.ai)

---

## Prompt 1
[14/5/2026]

Saya sedang mengerjakan project mata kuliah Integrasi Aplikasi Enterprise dengan konsep microservice. Setiap anggota tim diminta membuat service dan repository GitHub masing-masing secara terpisah. Saya mendapat bagian Guest Service pada sistem hospitality. Bantu saya menyusun rancangan dan langkah implementasi project ini secara lengkap mulai dari arsitektur service, endpoint API, database, hingga deployment Docker.

---

## Prompt 2
[14/5/2026]

Saya ingin mengubah konfigurasi database Laravel dari SQLite menjadi MySQL dan menambahkan konfigurasi environment variable untuk API Key service.

---

## Prompt 3
[14/5/2026]

Saya membuat migration `create_guests_table`. Bantu saya menentukan struktur tabel guest yang sesuai untuk Guest Service hospitality system.

---

## Prompt 4
[14/5/2026]

Tim project menggunakan UUID sebagai primary key, sedangkan migration saya sebelumnya masih menggunakan integer ID. Bagaimana cara menyesuaikan migration dan model agar mendukung UUID?

---

## Prompt 5
[14/5/2026]

Saya sudah membuat model `Guest`. Tolong bantu konfigurasi model agar mendukung UUID, fillable field, dan mass assignment protection.

---

## Prompt 6
[15/5/2026]

Saya sudah menyelesaikan tahap Swagger documentation dan ingin melanjutkan implementasi Guest Service ke tahap GraphQL.

---

## Prompt 7
[15/5/2026]

Saya ingin menambahkan GraphQL pada Laravel project menggunakan Lighthouse. Tolong bantu proses instalasi dan konfigurasi awalnya.

---

## Prompt 8
[15/5/2026]

Saya sudah menjalankan publish schema Lighthouse. Tolong bantu membuat schema GraphQL untuk Guest Service agar dapat menampilkan data guest.

---

## Prompt 9
[15/5/2026]

Saya ingin menambahkan GraphQL Playground atau GraphiQL untuk testing endpoint GraphQL pada Laravel.

---

## Prompt 10
[15/5/2026]

Setelah menambahkan data guest melalui Swagger, GraphQL berhasil menampilkan data. Tolong jelaskan kelebihan GraphQL dibanding REST API dalam pengambilan field tertentu.

---

## Prompt 11
[15/5/2026]

Saya ingin mulai melakukan implementasi Docker pada project Laravel Guest Service ini. Tolong bantu langkah-langkah setup Docker mulai dari pembuatan `Dockerfile`, `docker-compose.yml`, konfigurasi MySQL container, hingga cara menjalankan project menggunakan Docker Compose.

---

## Prompt 12
[15/5/2026]

Saat menjalankan `docker compose up --build`, Docker Desktop mengalami crash dan muncul error `SIGBUS`. Apa yang kemungkinan menyebabkan masalah ini dan bagaimana langkah troubleshooting yang dapat dilakukan?

---

## Prompt 13
[15/5/2026]

Setelah menjalankan ulang Docker, container Laravel dan MySQL berhasil berjalan. Tolong bantu memastikan service sudah dapat diakses dengan benar.

---

## Prompt 14
[15/5/2026]

Laravel masih mencoba connect ke `127.0.0.1` dan belum terhubung ke database container Docker. Bagaimana cara memperbaiki konfigurasi database pada environment Docker?

---

## Prompt 15
[15/5/2026]

Saya mencoba mengakses endpoint API tanpa API Key dan mendapatkan response 401 Unauthorized. Tolong jelaskan apakah middleware API Key saya sudah bekerja dengan benar.

---
