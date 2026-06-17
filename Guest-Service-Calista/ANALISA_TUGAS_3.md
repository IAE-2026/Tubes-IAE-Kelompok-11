# Analisis Justifikasi Transaksi Kritis Pada Guest Service

**Nama:** Calista Aurelia Putri  
**NIM:** 102022400289   
**Kelas:** SI-48-09

Pada Guest Service yang saya kerjakan, terdapat tiga endpoint yang berkaitan dengan pengelolaan data tamu hotel, yaitu:
1. GET /{guestId} digunakan untuk mengambil data profil tamu berdasarkan ID yang diberikan, dan endpoint ini hanya melakukan pembacaan data.
2. POST /profile digunakan untuk menyimpan atau memperbarui data diri tamu, seperti nama, email, nomor KTP, dan nomor telepon.
3. POST /validate-ktp digunakan untuk mengecek apakah nomor KTP tertentu sudah terdaftar di sistem atau belum, dan endpoint ini hanya melakukan pembacaan data.

Sebelum menentukan transaksi kritis pada service ini, perlu saya pahami terlebih dahulu apa yang dimaksud dengan transaksi kritis. Transaksi kritis merupakan suatu proses atau operasi yang apabila mengalami kegagalan saat dijalankan dapat menyebabkan data menjadi tidak lengkap, tidak konsisten, atau bahkan menimbulkan dampak yang cukup besar terhadap proses bisnis. Akibatnya, proses bisnis yang bergantung pada data tersebut juga dapat terganggu. Oleh karena itu, transaksi kritis memerlukan penanganan khusus agar setiap proses dapat berjalan secara utuh. 

Berdasarkan pemahaman tersebut, transaksi yang saya pilih sebagai transaksi kritis adalah endpoint POST /profile. Endpoint ini berfungsi untuk menyimpan data diri tamu ke dalam sistem. Alasan utama pemilihan endpoint ini adalah karena POST /profile merupakan satu-satunya endpoint pada Guest Service yang melakukan penulisan data ke database melalui operasi updateOrCreate pada tabel guests. Data yang disimpan juga termasuk data pribadi tamu, seperti nama, email, nomor KTP, dan nomor telepon. Karena data tersebut bersifat penting, proses penyimpanannya harus dilakukan dengan hati-hati agar tidak menimbulkan masalah di kemudian hari. Apabila proses penyimpanan gagal tanpa penanganan yang tepat, data dapat menjadi tidak lengkap atau tidak konsisten antara database lokal dengan sistem audit yang digunakan. Kondisi tersebut sesuai dengan pengertian transaksi kritis yang harus menjaga konsistensi data.

Selain itu, endpoint POST /profile juga dipilih sebagai transaksi kritis karena prosesnya perlu melakukan pencatatan aktivitas ke sistem audit melalui SOAP. Setiap perubahan data perlu memiliki jejak audit yang jelas agar dapat ditelusuri apabila diperlukan atau terjadi masalah. Jika data berhasil tersimpan tetapi proses audit gagal dilakukan, maka akan muncul ketidaksesuaian antara data yang ada di sistem dengan catatan yang seharusnya tersimpan. Hal ini dapat menyulitkan proses pelacakan perubahan data pada masa mendatang.

Alasan lainnya adalah karena hasil dari transaksi ini akan digunakan oleh service lain dalam sistem. Setelah data berhasil disimpan dan proses audit selesai dilakukan, Guest Service akan mengirimkan informasi tersebut melalui RabbitMQ yang akan dimanfaatkan oleh service lain. Dengan adanya proses ini, service lain tidak perlu terus-menerus melakukan pengecekan terhadap Guest Service untuk mengetahui apakah terdapat data tamu baru.

Berdasarkan ketiga alasan tersebut, dapat disimpulkan bahwa endpoint POST /profile merupakan transaksi yang paling kritis pada Guest Service. Endpoint ini tidak hanya melibatkan penyimpanan data pribadi tamu ke database, tetapi juga mencakup proses audit dan distribusi informasi ke service lain.
