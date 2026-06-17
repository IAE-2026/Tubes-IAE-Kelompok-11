# Analisis Tugas 3 — Integrasi Aplikasi Enterprise

> **Nama:** Muhammad Izaz Naufal
> **NIM:** 102022400306
> **Mata Kuliah:** Integrasi Aplikasi Enterprise (IAE)
> **Layanan:** Catalog Service — Fitur *Tambah Katalog Kamar*

---
1. **Penjelasan Transaksi Bisnis**
Dalam rancangan sistem informasi yang berskala besar, fitur untuk menambahkan informasi kamar tidak hanya sekadar memasukkan data ke dalam database lokal (seperti CRUD pada umumnya). Informasi kamar berfungsi sebagai Master Data yang menjadi dasar untuk semua layanan lainnya. Oleh karena itu, langkah penyimpanannya memerlukan dua fase integrasi yang sangat penting: pencatatan di server utama (melalui SOAP) dan penyebaran data kepada layanan lainnya (dengan memanfaatkan RabbitMQ).

**1.1 Mengapa Wajib Tercatat di SOAP Audit?**

Integrasi ke server SOAP Audit pusat dilakukan untuk memenuhi standar operasional perusahaan melalui tiga alasan utama:

1. Kepatuhan dan Verifikasi Data: Setiap penambahan informasi mengenai aset (kamar) harus diumumkan ke sistem yang utama untuk dilakukan verifikasi. Sebagai bukti, sistem utama akan memberikan Nomor Resi (IAE-LOG-XXXX). Resi ini berfungsi sebagai bukti resmi bahwa informasi kamar tersebut sah dan telah diterima oleh sistem perusahaan. 

2. Sinkronisasi dengan Sistem Lama: Dalam konteks Perusahaan, server utama seringkali masih beroperasi dengan sistem lama yang hanya mendukung protokol komunikasi berbasis XML. Aplikasi Laravel kita yang lebih mutakhir berfungsi sebagai jembatan untuk mengatasi perbedaan teknologi ini agar alur pertukaran data tetap berjalan dengan baik. 

3. Membangun Jejak Audit: Pencatatan dalam sistem SOAP berperan sebagai catatan yang tidak hilang. Jika di masa yang akan datang terjadi perbedaan data (seperti perubahan harga atau status ruangan), jejak audit yang ada di server pusat ini akan berfungsi sebagai referensi yang benar untuk penyelidikan. 

**1.2 Mengapa Menggunakan RabbitMQ?**

Jika SOAP digunakan untuk pelaporan ke pusat, RabbitMQ digunakan sebagai jembatan komunikasi antar-layanan (microservices) di dalam ekosistem aplikasi.

1. Pemrosesan yang Cepat (Asynchronous): Tanpa menggunakan RabbitMQ, aplikasi kita perlu mengirimkan pemberitahuan secara terpisah ke layanan lain (seperti layanan Notifikasi atau Booking). Ini akan menyebabkan aplikasi menjadi sangat lambat (bottleneck). Dengan adanya RabbitMQ, aplikasi kita hanya perlu mengeluarkan satu pesan ke Message Broker, kemudian dapat segera menyelesaikan tugas yang ada. Proses pengiriman pesan ke layanan lain akan berlangsung di belakang layar tanpa menambah beban pada server Katalog. 

2. Sistem yang Saling Terpisah: Konsep ini memperjelas pemisahan ketergantungan antara berbagai layanan. Layanan Katalog tidak perlu menyadari keadaan apakah layanan Pemesanan sedang berjalan atau tidak. Apabila layanan Pemesanan mengalami gangguan, pesan akan tetap terjaga secara aman dalam antrean RabbitMQ dan akan diproses segera setelah layanan tersebut pulih. 

3.  Efisiensi Beban API (Transfer Status Berbasis Event): Ketika kita mengirimkan pesan ke RabbitMQ, kita dengan sengaja mengirimkan seluruh informasi tentang kamar (ID, nama, lokasi, deskripsi, dan harga). Tujuannya adalah agar layanan yang mendapatkan pesan ini tidak perlu melakukan beberapa permintaan HTTP ke Catalog Service hanya untuk menanyakan rincian harga kamar. Ini sangat membantu dalam mengurangi penggunaan bandwidth dan meringankan beban server. 