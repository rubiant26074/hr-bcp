<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Verifikasi Email HR-BCP</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
  <p>Halo {{ $name }},</p>
  <p>Terima kasih sudah mendaftar di HR-BCP. Silakan klik link di bawah ini untuk verifikasi email:</p>
  <p>
    <a href="{{ $verifyUrl }}">{{ $verifyUrl }}</a>
  </p>
  <p>Link ini berlaku sampai {{ \Carbon\Carbon::parse($expiresAt)->format('d/m/Y H:i') }}.</p>
  <p>Setelah verifikasi berhasil, Anda bisa login menggunakan email dan password yang sudah dibuat.</p>
  <p>Salam,<br>BCP-HRIS</p>
</body>
</html>
