# HR-BCP Mobile iOS (iPhone)

Project ini adalah pasangan iOS dari `mobile_hr-bcp.apk`.
Target minimum diset **iOS 13.0** (support iPhone X / iPhone 10).

## Fitur yang disiapkan
- Login dan modul mobile via WebView ke `https://hr.berkahcipta.co.id/m/login`
- Tombol `Absen Native` muncul saat halaman `/m/attendance`
- Stub native camera + location untuk face attendance

## Prasyarat (Mac)
1. Xcode 15+
2. Homebrew
3. XcodeGen (`brew install xcodegen`)

## Generate project Xcode
```bash
cd ios/HRBCPMobileIOS
xcodegen generate
open HRBCPMobileIOS.xcodeproj
```

## Set signing
- Buka target `HRBCPMobileIOS`
- Tab `Signing & Capabilities`
- Isi `Team` Apple Developer
- Sesuaikan `Bundle Identifier` jika perlu

## Build ke iPhone / Archive IPA
1. Pilih device fisik iPhone
2. Build & Run
3. Untuk file `.ipa` / TestFlight:
   - Product -> Archive
   - Distribute App -> App Store Connect / Ad Hoc

## Catatan penting
- File ini menyiapkan struktur aplikasi iOS terpisah (bukan aplikasi induk).
- Integrasi face recognition native masih stub (`TODO`) dan bisa disambungkan ke endpoint backend yang sudah dipakai Android.
- Jika butuh nama final app: `mobile_hr-bcp.ipa` saat export.
