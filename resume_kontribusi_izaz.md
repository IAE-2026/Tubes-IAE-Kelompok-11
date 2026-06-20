RESUME KONTRIBUSI IZAZ PADA PROYEK TUBES IAE

Berikut adalah daftar kontribusi nyata yang saya lakukan berdasarkan catatan log commit git.

• Saya menginisiasi dan membangun layanan utama Catalog Service pada commit 1a8c681. Saya mengunggah 99 berkas awal termasuk model database Room, Addon, Bookmark, dan User. Saya juga membangun sistem autentikasi SSO serta integrasi SOAP dan RabbitMQ.
• Saya mengonfigurasi API Gateway Nginx pada commit 205f1be. Saya menambahkan 81 baris konfigurasi perutean agar pengguna bisa mengakses Catalog Service dari luar sistem.
• Saya menyelaraskan port layanan pada commit bc5c7bd. Saya mengubah setelan port kontainer dan gateway menjadi port 8002 agar komunikasi antar layanan berjalan lancar.
• Saya menyesuaikan konfigurasi Docker pada commit 3bad80f. Saya memperbarui berkas docker-compose.yml untuk mengatur variabel lingkungan database secara terpusat.
• Saya memperbaiki dan mengunggah setelan Swagger UI pada commit 5195366. Saya memperbarui rute dokumentasi menjadi api/catalog-openapi untuk mengatasi masalah cache browser. Saya juga memastikan pengguna bisa menguji seluruh API Room dan Addon secara langsung lewat gateway.

Aksi praktis yang bisa saya terapkan langsung:
• Saya menguji semua API katalog secara langsung pada alamat http://localhost:8000/api/catalog/documentation.
• Saya memantau pengiriman pesan antrean karena saya sudah menghubungkan penerbit RabbitMQ secara langsung dengan aksi pembuatan kamar.
