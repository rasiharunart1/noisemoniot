# BUKU PANDUAN PENGGUNAAN (USER MANUAL)
## SISTEM MONITORING KEBISINGAN IOT (NOISEMONIOT)

---

### DAFTAR ISI
1. [Pendahuluan](#1-pendahuluan)
2. [Spesifikasi Sistem](#2-spesifikasi-sistem)
3. [Arsitektur Sistem](#3-arsitektur-sistem)
4. [Fitur Utama](#4-fitur-utama)
5. [Panduan Instalasi dan Deployment](#5-panduan-instalasi-dan-deployment)
6. [Panduan Penggunaan Aplikasi](#6-panduan-penggunaan-aplikasi)

---

### 1. Pendahuluan
**NoiseMoniot (Noise Monitor IoT)** adalah sebuah sistem aplikasi berbasis _Internet of Things_ (IoT) dan platform Web yang dirancang secara khusus untuk memonitor, merekam, dan menganalisis tingkat kebisingan (Sound Pressure Level / SPL) di suatu lingkungan. Sistem ini memungkinkan pengguna untuk melakukan pemantauan jarak jauh secara _real-time_, mengatur jadwal rekaman audio otomatis, hingga mengekspor data historis log sensor untuk keperluan analisis lebih lanjut.

Sistem ini beroperasi dengan mengintegrasikan perangkat keras mikrokontroler (node sensor) dengan peladen (server) melalui protokol komunikasi MQTT yang ringan dan cepat.

---

### 2. Spesifikasi Sistem

#### Perangkat Keras (Hardware) Node Sensor:
*   Mikrokontroler: ESP32 atau ESP8266
*   Sensor Suara (Mikrofon): Modul I2S INMP441 (atau Modul MSM261S4030H0)
*   Penyimpanan Lokal: Modul MicroSD Card (Untuk menyimpan file format WAV)
*   Konektivitas: Modul Wi-Fi terintegrasi (2.4 GHz)

#### Perangkat Lunak (Software) Utama Server:
*   **Sistem Operasi:** Linux (Ubuntu / Debian / CentOS)
*   **Web Server:** Nginx atau Apache
*   **Bahasa Pemrograman Backend:** PHP 8.1+ dan Framework Laravel
*   **Bahasa Pemrograman Frontend:** JavaScript, Node.js, NPM, TailwindCSS
*   **Database:** PostgreSQL atau MySQL
*   **Protokol Komunikasi:** MQTT Broker (Misal: HiveMQ, EMQX, atau Mosquitto)
*   **Manajemen Background Process:** Supervisor (Untuk MQTT Listener dan Laravel Scheduler)

---

### 3. Arsitektur Sistem

Arsitektur aplikasi terbagi menjadi tiga komponen utama:
1.  **Node Sensor (ESP32/ESP8266):** Bertugas membaca kondisi akustik melalui I2S mikrofon, menghitung tingkat tekanan suara (SPL) dalam satuan decibel (dB), serta merekam audio mentah ke dalam SD Card dalam format WAV. Data log sensor dikirim ke cloud via protokol MQTT.
2.  **Platform Komunikasi (MQTT Broker):** Perantara jalur distribusi pesan berkecepatan tinggi dengan latensi rendah dari berbagai node sensor menuju peladen utama.
3.  **Peladen Aplikasi Utama (Web Dashboard):** Dibangun menggunakan Laravel. Memiliki _MQTT Listener service_ yang berjalan di _background_ untuk menerima dan menyimpan data sensor ke _database_. Menyediakan antarmuka (UI) bagi pengguna untuk manajemen _device_ dan visualisasi data.

---

### 4. Fitur Utama

1.  **Dashboard Visualisasi Real-Time:** Menampilkan grafik tingkat kebisingan (SPL) dari berbagai perangkat secara _real-time_.
2.  **Manajemen Perangkat (Device Management):** Modul untuk menambah, mengedit parameter kalibrasi (SPL Offset), dan menghadapus _node device_.
3.  **Local Web Standalone:** Konfigurasi awal perangkat keras (kredensial Wi-Fi & MQTT) langsung pada Local UI ESP32/ESP8266 tanpa bergantung pada akses _server_ utama.
4.  **Rekaman Audio Terjadwal (Scheduled Recording):** Penjadwalan perekaman suara dari jarak jauh. Pengguna dapat mengatur otomatisasi kapan _node device_ memulai dan memberhentikan perekaman audio.
5.  **Manajemen File Audio:** Pengguna dapat memutar, mengunduh tunggal, menghapus satuan, dan penghapusan massal (Bulk Delete) terhadap arsip rekaman audio yang dikumpulkan alat.
6.  **Ekspor Log Otomatis:** Fitur _Cron Job / Scheduler_ yang mengekspor data (_Sensor Logs_) harian menjadi format Excel (XLSX) untuk kemudahan pelaporan dan pencatatan eksperimen histeresis.

---

### 5. Panduan Instalasi dan Deployment

Bagi _System Administrator_ atau teknisi IT, berikut langkah _deployment_ pada _production server_:

**A. Pra-Syarat Instalasi**
*   Server Linux siap pakai dengan dependensi web (LEMP/LAMP Stack).
*   _Repository_ Git _source code_ NoiseMoniot.

**B. Langkah Instalasi Berurutan**
1.  **Kloning Repositori:** Tarik pembaruan terakhir dari repository.
    `git pull origin main`
2.  **Install Dependensi:** Konfigurasi komponen PHP dan Frontend.
    `composer install --no-dev --optimize-autoloader`
    `npm install && npm run build`
3.  **Migrasi Database:** Buat struktur dan tabel _database_.
    `php artisan migrate --force`
4.  **Hak Akses:** Penyesuaian izin direktori penyimpanan _cache_ dan _upload_.
    `chmod -R 775 storage bootstrap/cache`
    `chown -R www-data:www-data storage bootstrap/cache`
5.  **Konfigurasi Environment:** Modifikasi `.env` untuk konfigurasi koneksi MQTT Broker dan Database, lalu simpan:
    `php artisan config:cache`
6.  **Manajemen Konfigurasi Supervisor:**
    Gunakan script _setup_ otomatis yang telah disediakan untuk menjalankan _MQTT Listener_ dan penjadwalan.
    `sudo bash setup-supervisor.sh`
    `supervisorctl restart all`

---

### 6. Panduan Penggunaan Aplikasi

#### A. Login Sistem
1.  Buka aplikasi melalui _browser_ dengan menginput alamat URL aplikasi (misal: `https://diklat.mdpower.io`).
2.  Masukkan _Username_ dan _Password_ administrator yang telah terdaftar.
3.  Klik **Login**. Pengguna akan diarahkan ke Dashboard utama.

#### B. Menambahkan dan Konfigurasi Device
1.  Arahkan menu navigasi ke **Device Management**.
2.  Klik **Tambahkan Device Baru**.
3.  Masukkan _Device ID_ (Harus sama dengan ID di dalam kode EPS32/ESP8266), nama lokasi, dan nilai penyesuaian kalibrasi (misal: "SPL Offset: -3.5" jika mikrofon terlalu peka).
4.  Klik **Simpan**.

#### C. Konfigurasi Standalone Node (Local UI)
1.  Nyalakan perangkat mikrokontroler untuk pertama kali.
2.  Gunakan _smartphone_/laptop, hubungkan ke jaringan Access Point (AP) bernama "NoiseMoniot-AP".
3.  Buka web browser dan akses IP lokal bawaan perangkat `192.168.4.1`.
4.  Masukkan kredensial otentikasi mandiri. Konfigurasikan SSID Wi-Fi lokal, kredensial MQTT Cloud, dan _Save_. Alat akan melakukan _restart_ dan otomatis terhubung.

#### D. Membaca Grafik dan Log Kebisingan
1.  Pada Dashboard, pilih perangkat spesifik dari tabel perangkat aktif.
2.  Aplikasi akan menampilkan grafik sebaran tingkat kebisingan dalam rentang jam tertentu.
3.  Pembaruan grafik berjalan secara periodik.

#### E. Menggunakan Fitur Rekam Audio (Audio Recording)
1.  Buka halaman **Detail Device**.
2.  Di layar, terdapat antarmuka **Manual Recording Kontrol**. Klik **Start Recording** untuk memerintahkan sensor merekam ke SD Card-nya secara *remote*.
3.  Klik **Stop Recording** untuk mengakhiri.
4.  Untuk penjadwalan: Masuk ke menu **Schedules**, atur jam awal rekam dan lama durasi rekaman, aplikasi akan mengirimkan instruksi otomatis melalui _Scheduler Background Service_.

#### F. Ekspor Laporan Excel
*   **Otomatis:** Aplikasi menjalankan jadwal _Cron Job Auto Export_ setiap malam yang akan mengarsipkan data sensor ke format `.xlsx`.
*   **Manual:** Admin masuk ke menu **Logs / Reports**, pilih rentang waktu kalender, kemudian klik tombol **Export Data (Excel)** untuk mengunduh log bacaan _Sound Pressure Level_ ke komputer lokal.
