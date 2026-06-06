# Panduan Lengkap Mobile HR-BCP

Dokumen ini menjelaskan cara:
1. Mendaftar akun mobile HR-BCP
2. Login aplikasi mobile
3. Download APK
4. Daftar wajah native
5. Absensi native (masuk/pulang)
6. Lihat rekap absensi
7. Lihat slip gaji

## A. Persiapan

Sebelum mulai, pastikan:
1. HP Android terhubung internet.
2. GPS/Location aktif.
3. Kamera HP berfungsi baik.
4. Data karyawan Anda sudah ada di sistem HR-BCP.
5. Anda tahu entitas perusahaan tempat Anda terdaftar.

## B. Download APK Mobile

1. Buka halaman login mobile HR-BCP (`/m/login`).
2. Klik link **Download APK Mobile**.
3. Install file `mobile_hr-bcp.apk`.
4. Jika muncul peringatan, izinkan install dari sumber tersebut.

### Screenshot yang diambil:
1. Halaman login mobile.
2. Posisi link **Download APK Mobile**.
3. Proses install APK di Android.

## C. Registrasi Akun (Pertama Kali)

1. Di halaman login mobile, klik **Belum punya akun? Register**.
2. Pilih **Entitas Perusahaan**.
3. Isi **Email** aktif.
4. Pada **Connect Employee**, ketik beberapa huruf nama/NIK lalu pilih data yang sesuai.
5. Isi **Password**.
6. Isi **Konfirmasi Password**.
7. Klik tombol **Daftar**.

Setelah berhasil, sistem menampilkan pesan bahwa akun menunggu aktivasi Administrator.

### Screenshot yang diambil:
1. Halaman register awal.
2. Pilih entitas perusahaan.
3. Pencarian employee pada field Connect Employee.
4. Form terisi lengkap sebelum submit.
5. Feedback sukses: menunggu aktivasi Administrator.

## D. Aktivasi oleh Admin

Penting:
1. User belum bisa login sebelum diaktivasi.
2. Admin mengaktifkan akun dari menu **User Management** di aplikasi web utama.

Jika belum aktif, login akan gagal dengan pesan akun menunggu aktivasi admin.

### Screenshot yang diambil:
1. Pesan gagal login karena akun belum aktif.
2. (Opsional admin) tampilan status user di User Management.

## E. Login Aplikasi Mobile

1. Buka aplikasi Mobile HR-BCP.
2. Isi **Email**.
3. Isi **Password**.
4. Klik **Masuk**.

### Screenshot yang diambil:
1. Form login terisi.
2. Berhasil masuk ke beranda mobile.

## F. Daftar Wajah Native (Wajib Sebelum Absensi)

1. Masuk menu **Absensi**.
2. Klik **Buka Absensi Native**.
3. Saat diminta, izinkan:
   - Kamera
   - Lokasi (GPS)
4. Pastikan wajah terlihat jelas (jarak 30–50 cm, pencahayaan cukup).
5. Klik **Daftar Wajah Native**.
6. Tunggu notifikasi berhasil.

### Screenshot yang diambil:
1. Tombol **Buka Absensi Native**.
2. Dialog izin kamera/lokasi.
3. Layar native dengan status wajah stabil.
4. Konfirmasi daftar wajah berhasil.

## G. Absensi Native (Masuk / Pulang)

1. Dari layar native yang sama, pastikan:
   - Status wajah stabil
   - Lokasi terbaca
2. Klik **Kirim Absen Native**.
3. Sistem otomatis menentukan:
   - Absensi pertama hari itu = Masuk
   - Absensi berikutnya = Pulang

Catatan:
1. Absensi hanya berhasil jika berada dalam radius lokasi kantor yang ditentukan.
2. Jika di luar radius, absensi ditolak.

### Screenshot yang diambil:
1. Status lokasi valid.
2. Proses kirim absen.
3. Notifikasi absensi berhasil.
4. Contoh error di luar radius (opsional).

## H. Lihat Rekap Absensi

Masuk menu **Rekap** lalu pilih salah satu mode:

1. **Cut-off 20-21**
   - Pilih bulan
   - Pilih tahun
   - Klik **OK**

2. **Rentang Tanggal**
   - Pilih tanggal mulai
   - Pilih tanggal akhir
   - Klik **OK**

Rekap menampilkan:
1. Periode aktif
2. Hari tercatat
3. Total jam kerja
4. Total lembur
5. Daftar detail harian

### Screenshot yang diambil:
1. Menu rekap dengan pilihan mode.
2. Mode cut-off terisi.
3. Mode rentang tanggal terisi.
4. Hasil rekap + detail harian.

## I. Lihat Slip Gaji

1. Masuk menu **Slip**.
2. Pilih periode gaji.
3. Klik **Lihat**.
4. Ringkasan slip tampil (total penerimaan, potongan, gaji bersih).
5. Klik **Lihat Detail** untuk rincian komponen.

### Screenshot yang diambil:
1. Pemilihan periode slip.
2. Ringkasan slip.
3. Detail slip setelah expand.

## J. Troubleshooting

### 1) Tidak bisa login
Kemungkinan:
1. Email/password salah.
2. Akun belum diaktivasi admin.

Solusi:
1. Cek ulang email/password.
2. Hubungi admin untuk aktivasi.

### 2) Tombol native tidak berfungsi
Kemungkinan:
1. Buka dari browser, bukan APK.
2. APK versi lama.

Solusi:
1. Gunakan aplikasi `mobile_hr-bcp.apk`.
2. Install ulang APK terbaru dari link download.

### 3) Absensi gagal karena lokasi
Kemungkinan:
1. GPS belum akurat.
2. Di luar radius kantor.

Solusi:
1. Aktifkan mode lokasi akurasi tinggi.
2. Tunggu GPS stabil.
3. Pastikan berada di titik kantor yang benar.

## K. Checklist UAT (User Acceptance Test)

1. Register berhasil.
2. Muncul pesan menunggu aktivasi admin.
3. Login ditolak saat belum aktif.
4. Login berhasil setelah diaktifkan.
5. Daftar wajah native berhasil.
6. Absen masuk berhasil.
7. Absen pulang berhasil.
8. Rekap tampil benar di mode cut-off.
9. Rekap tampil benar di mode rentang tanggal.
10. Slip gaji ringkasan dan detail tampil benar.

---

## Template Penamaan Screenshot (Disarankan)

1. `01-login-mobile.png`
2. `02-link-download-apk.png`
3. `03-register-form.png`
4. `04-register-success-pending-admin.png`
5. `05-native-permission-camera-location.png`
6. `06-native-face-enroll-success.png`
7. `07-native-attendance-success.png`
8. `08-rekap-cutoff.png`
9. `09-rekap-date-range.png`
10. `10-slip-summary.png`
11. `11-slip-detail.png`

