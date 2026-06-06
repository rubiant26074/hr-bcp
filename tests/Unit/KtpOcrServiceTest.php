<?php

namespace Tests\Unit;

use App\Services\KtpOcrService;
use PHPUnit\Framework\TestCase;

class KtpOcrServiceTest extends TestCase
{
    public function test_extracts_address_from_multiline_ktp_text(): void
    {
        $service = new KtpOcrService();

        $text = <<<TEXT
PROVINSI JAWA BARAT
KABUPATEN BEKASI
NIK 3216220403740002
Nama Budi Rubiantoro
Alamat
Jl. Melati No. 10 Blok C
RT/RW 001/002
TEXT;

        $this->assertSame('Jl. Melati No. 10 Blok C', $service->extractAddressFromText($text));
    }

    public function test_extracts_inline_address_from_ktp_text(): void
    {
        $service = new KtpOcrService();

        $text = <<<TEXT
NIK : 3216220403740002
Alamat : Perum Griya Asri Blok A No 5
Kel/Desa : Wanasari
TEXT;

        $this->assertSame('Perum Griya Asri Blok A No 5', $service->extractAddressFromText($text));
    }

    public function test_extracts_address_without_explicit_alamat_label(): void
    {
        $service = new KtpOcrService();

        $text = 'PROVINSI JAWA BARAT KABUPATEN BEKASI HIK RUBIANTORO Lahir MAGELANG 04-03-1974 PERUM 0059 007 CIBARUSAH KARYAWAN SWASTA';

        $this->assertSame('PERUM 0059 007 CIBARUSAH', $service->extractAddressFromText($text));
    }
}
