<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;

class KtpOcrService
{
    public function extractAddressFromFile(?string $relativePath): ?string
    {
        $result = $this->extractAddressWithReason($relativePath);
        return $result['address'] ?? null;
    }

    public function extractAddressWithReason(?string $relativePath): array
    {
        $relativePath = trim((string) $relativePath);
        if ($relativePath === '') {
            return ['address' => null, 'reason' => 'File KTP belum dipilih.'];
        }

        $fullPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, public_path($relativePath));
        if (!is_file($fullPath)) {
            return ['address' => null, 'reason' => 'File KTP tidak ditemukan.'];
        }

        $ext = strtolower((string) pathinfo($fullPath, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
            return ['address' => null, 'reason' => 'Format KTP harus JPG/PNG.'];
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            return ['address' => null, 'reason' => 'OCR hanya tersedia di Windows.'];
        }

        $text = $this->recognizeText($fullPath);
        if ($text === null) {
            return ['address' => null, 'reason' => 'OCR gagal diproses.'];
        }

        $address = $this->extractAddressFromText($text);
        if ($address === null) {
            return ['address' => null, 'reason' => 'Alamat tidak terbaca dari gambar.'];
        }

        return ['address' => $address, 'reason' => null];
    }

    public function extractAddressFromText(string $text): ?string
    {
        $text = preg_replace("/\r\n?/", "\n", $text);
        $text = preg_replace("/[ \t]+/", ' ', (string) $text);
        $lines = array_values(array_filter(array_map(static function ($line) {
            return trim((string) $line);
        }, explode("\n", (string) $text)), static function ($line) {
            return $line !== '';
        }));

        if ($lines === []) {
            return null;
        }

        foreach ($lines as $index => $line) {
            if (!preg_match('/alamat/i', $line)) {
                continue;
            }

            $start = preg_replace('/^.*alamat\s*[:\-]?\s*/i', '', $line);
            $parts = [];
            if ($start !== '' && !$this->isAddressStopLine($start)) {
                $parts[] = $this->cleanAddressLine($start);
            }

            for ($i = $index + 1; $i < count($lines); $i++) {
                $candidate = $this->cleanAddressLine($lines[$i]);
                if ($candidate === '' || $this->isAddressStopLine($candidate)) {
                    break;
                }
                $parts[] = $candidate;
                if (count($parts) >= 2) {
                    break;
                }
            }

            $parts = array_values(array_filter(array_unique($parts)));
            if ($parts !== []) {
                return implode(', ', $parts);
            }
        }

        $inline = $this->extractInlineAddress((string) $text);
        if ($inline !== null) {
            return $inline;
        }

        return null;
    }

    private function recognizeText(string $fullPath): ?string
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return null;
        }

        $script = <<<'POWERSHELL'
Add-Type -AssemblyName System.Runtime.WindowsRuntime
[void][Windows.Storage.StorageFile, Windows.Storage, ContentType=WindowsRuntime]
[void][Windows.Storage.Streams.IRandomAccessStream, Windows.Storage.Streams, ContentType=WindowsRuntime]
[void][Windows.Graphics.Imaging.BitmapDecoder, Windows.Graphics.Imaging, ContentType=WindowsRuntime]
[void][Windows.Graphics.Imaging.SoftwareBitmap, Windows.Graphics.Imaging, ContentType=WindowsRuntime]
[void][Windows.Media.Ocr.OcrEngine, Windows.Media.Ocr, ContentType=WindowsRuntime]

function AwaitWinRt($Operation, [Type]$ResultType) {
    $method = [System.WindowsRuntimeSystemExtensions].GetMethods([System.Reflection.BindingFlags] 'Public,Static') |
        Where-Object {
            $_.Name -eq 'AsTask' -and
            $_.IsGenericMethodDefinition -and
            $_.GetGenericArguments().Count -eq 1 -and
            $_.GetParameters().Count -eq 1 -and
            $_.GetParameters()[0].ParameterType.Name -eq 'IAsyncOperation`1'
        } |
        Select-Object -First 1

    if ($null -eq $method) {
        throw 'AsTask helper for WinRT not available.'
    }

    $task = $method.MakeGenericMethod($ResultType).Invoke($null, @($Operation))
    $task.Wait(-1) | Out-Null
    return $task.Result
}

$path = $args[0]
$file = AwaitWinRt ([Windows.Storage.StorageFile]::GetFileFromPathAsync($path)) ([Windows.Storage.StorageFile])
$stream = AwaitWinRt ($file.OpenAsync([Windows.Storage.FileAccessMode]::Read)) ([Windows.Storage.Streams.IRandomAccessStream])
$decoder = AwaitWinRt ([Windows.Graphics.Imaging.BitmapDecoder]::CreateAsync($stream)) ([Windows.Graphics.Imaging.BitmapDecoder])
$bitmap = AwaitWinRt ($decoder.GetSoftwareBitmapAsync()) ([Windows.Graphics.Imaging.SoftwareBitmap])
if ($bitmap.BitmapPixelFormat -ne [Windows.Graphics.Imaging.BitmapPixelFormat]::Bgra8 -and $bitmap.BitmapPixelFormat -ne [Windows.Graphics.Imaging.BitmapPixelFormat]::Gray8) {
    $bitmap = [Windows.Graphics.Imaging.SoftwareBitmap]::Convert($bitmap, [Windows.Graphics.Imaging.BitmapPixelFormat]::Bgra8)
}
$engine = [Windows.Media.Ocr.OcrEngine]::TryCreateFromUserProfileLanguages()
$result = AwaitWinRt ($engine.RecognizeAsync($bitmap)) ([Windows.Media.Ocr.OcrResult])
Write-Output $result.Text
POWERSHELL;

        $tempScript = tempnam(storage_path('app'), 'ktp-ocr-');
        if ($tempScript === false) {
            return null;
        }

        $scriptPath = $tempScript . '.ps1';
        @rename($tempScript, $scriptPath);
        file_put_contents($scriptPath, $script);

        $result = Process::timeout(45)->run([
            'powershell',
            '-NoProfile',
            '-ExecutionPolicy',
            'Bypass',
            '-File',
            $scriptPath,
            $fullPath,
        ]);

        @unlink($scriptPath);

        if (!$result->successful()) {
            return null;
        }

        $output = trim((string) $result->output());
        return $output !== '' ? $output : null;
    }

    private function cleanAddressLine(string $line): string
    {
        $line = preg_replace('/^alamat\s*[:\-]?\s*/i', '', trim($line));
        return trim((string) preg_replace('/\s+/', ' ', $line));
    }

    private function extractInlineAddress(string $text): ?string
    {
        $flatOriginal = strtoupper($text);
        $flatOriginal = preg_replace('/\s+/', ' ', (string) $flatOriginal);
        $flatNormalized = str_replace(['0', '1', '5'], ['O', 'I', 'S'], $flatOriginal);

        if (!preg_match('/\b(ALAMAT|JL\.?|JALAN|PERUM|KP\.?|KAMPUNG|KOMP\.?|KOMPLEK|BLOK|GANG|GG\.?)\b/u', $flatNormalized, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $startAt = (int) $matches[0][1];
        $segment = trim((string) substr($flatOriginal, $startAt));
        if ($segment === '') {
            return null;
        }

        $stopPatterns = [
            '/\b(RT\/RW|RW\/RT|KEL\/DESA|DESA|KELURAHAN|KECAMATAN|AGAMA|STATUS|PERKAWINAN|PEKERJAAN|KARYAWAN|SWASTA|KEWARGANEGARAAN|BERLAKU|TEMPAT\/TGL|TEMPAT|TGL|LAHIR|JENIS KELAMIN|GOL\.?\s*DARAH|PROVINSI|KAB\/KOTA|NIK|NAMA)\b/u',
            '/\b(KARY|SWAST|WNI|WNA)\S*/u',
        ];

        $endAt = strlen($segment);
        foreach ($stopPatterns as $pattern) {
            if (preg_match($pattern, $segment, $stop, PREG_OFFSET_CAPTURE)) {
                $endAt = min($endAt, (int) $stop[0][1]);
            }
        }

        $segment = trim((string) substr($segment, 0, $endAt));
        $segment = preg_replace('/^ALAMAT\s*[:\-]?\s*/u', '', $segment);
        $segment = preg_replace('/\s+/', ' ', (string) $segment);
        $segment = trim((string) preg_replace('/[^\p{L}\p{N}\s.,\/\-]/u', ' ', (string) $segment));
        $segment = trim((string) preg_replace('/\s+/', ' ', (string) $segment), " ,.-");

        if ($segment === '' || mb_strlen($segment) < 6) {
            return null;
        }

        return $segment;
    }

    private function isAddressStopLine(string $line): bool
    {
        return preg_match('/^(rt\/rw|rw\/rt|kel\/desa|desa|kelurahan|kecamatan|agama|status\b|perkawinan|pekerjaan|karyawan|swasta|kewarganegaraan|berlaku|tempat\/tgl|tempat|tgl|lahir|jenis kelamin|gol\.?\s*darah|provinsi|kab\/kota|nik\b|nama\b)/i', trim($line)) === 1;
    }
}
