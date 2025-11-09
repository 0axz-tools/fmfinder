Simple Email Extractor

📋 Deskripsi
Script PHP ini adalah alat untuk mengekstrak alamat email dari kumpulan website secara otomatis dan paralel menggunakan multi-threading.
# Pastikan PHP terinstall
php --version

# Berikan permission executable (jika di Linux/Mac)
chmod +x main.php

Buat file teks berisi list website (satu website per baris):
text
example.com
google.com
github.com
companywebsite.org

# Dengan 10 threads (default)
php main.php websites.txt

# Dengan 30 threads
php main.php websites.txt 30

# Dengan file split (seperti xaa, xab, etc)
php main.php xaa

⚙️ Parameter
file_list (wajib): File teks berisi list website

threads (opsional): Jumlah thread paralel (default: 10)

📁 Output
Hasil: Disimpan di result.txt (email yang berhasil ditemukan)

File input: Otomatis terhapus setelah diproses

Progress: Ditampilkan real-time di console

🔧 Fungsi & Fitur
 Ekstraksi Email Otomatis
Scan halaman website utama

Scan halaman contact/kontak yang umum

Ekstraksi pattern email dari HTML

Multi-threading
Proses multiple website secara paralel

Configurable thread count

Delay antar batch untuk menghindari block

Validasi Email Cerdas
Filter email yang tidak valid:

Domain contoh (example.com, test.com)

Ekstensi file (jpg, png, pdf, dll)

Pattern asset (@2x.png, -300x300.jpg)

Local part mencurigakan (user, test, demo)

TLD tidak valid
URL Generation Otomatis
Mencoba berbagai path contact umum:

text
/
/contact
/contact-us  
/about
/support
/help
/dan-lainnya
Manajemen File Otomatis
Hapus website yang sudah diproses dari file input

Simpan hasil terurut dan unique

Backup-safe dengan file locking

===================================
    SIMPLE EMAIL EXTRACTOR
===================================
Memulai ekstraksi email...
File: websites.txt
Threads: 10
Output: result.txt

Total website: 150
Memproses...

Batch 1/15: ✓.✓.✓✓..✓
Batch 2/15: .✓.✓✓.✓..
...

Selesai! Hasil disimpan di: result.txt
File websites.txt telah dihapus.
