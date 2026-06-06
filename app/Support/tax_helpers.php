<?php

// Tax configuration and helpers for PPh21 (TER & annual progressive)

function ptkp_amount($status) {
    $map = [
        'TK/0' => 54000000,
        'TK/1' => 58500000,
        'TK/2' => 63000000,
        'TK/3' => 67500000,
        'K/0'  => 58500000,
        'K/1'  => 63000000,
        'K/2'  => 67500000,
        'K/3'  => 72000000,
    ];
    $status = strtoupper(trim((string)$status));
    return $map[$status] ?? $map['TK/0'];
}

function normalize_ptkp_status($status) {
    $status = strtoupper(trim((string)$status));
    $status = str_replace(' ', '', $status);
    if (preg_match('#^(TK|K)([0-3])$#', $status, $m)) {
        return $m[1] . '/' . $m[2];
    }
    return $status;
}

// Map PTKP status to TER category (A/B/C)
function ptkp_ter_category($status) {
    $status = normalize_ptkp_status($status);
    if (in_array($status, ['TK/0','TK/1'], true)) {
        return 'A';
    }
    if (in_array($status, ['TK/2','TK/3','K/0'], true)) {
        return 'B';
    }
    if (in_array($status, ['K/1','K/2','K/3'], true)) {
        return 'C';
    }
    return 'A';
}

// TER tables (monthly) based on PP 58/2023
function ter_tables() {
    $max = 1000000000000000;
    return [
        'A' => [
            [5400000, 0.00],
            [5650000, 0.0025],
            [5950000, 0.0050],
            [6300000, 0.0075],
            [6750000, 0.0100],
            [7500000, 0.0125],
            [8550000, 0.0150],
            [9650000, 0.0175],
            [10050000, 0.0200],
            [10350000, 0.0225],
            [10700000, 0.0250],
            [11050000, 0.0300],
            [11600000, 0.0350],
            [12500000, 0.0400],
            [13750000, 0.0500],
            [15100000, 0.0600],
            [16950000, 0.0700],
            [19750000, 0.0800],
            [24150000, 0.0900],
            [26450000, 0.1000],
            [28000000, 0.1100],
            [30050000, 0.1200],
            [32400000, 0.1300],
            [35400000, 0.1400],
            [39100000, 0.1500],
            [43850000, 0.1600],
            [47800000, 0.1700],
            [51400000, 0.1800],
            [56300000, 0.1900],
            [62200000, 0.2000],
            [68600000, 0.2100],
            [77500000, 0.2200],
            [89000000, 0.2300],
            [103000000, 0.2400],
            [125000000, 0.2500],
            [157000000, 0.2600],
            [206000000, 0.2700],
            [337000000, 0.2800],
            [454000000, 0.2900],
            [550000000, 0.3000],
            [695000000, 0.3100],
            [910000000, 0.3200],
            [1400000000, 0.3300],
            [$max, 0.3400],
        ],
        'B' => [
            [6200000, 0.00],
            [6500000, 0.0025],
            [6850000, 0.0050],
            [7300000, 0.0075],
            [9200000, 0.0100],
            [10750000, 0.0150],
            [11250000, 0.0200],
            [11600000, 0.0250],
            [12600000, 0.0300],
            [13600000, 0.0400],
            [14950000, 0.0500],
            [16400000, 0.0600],
            [18450000, 0.0700],
            [21850000, 0.0800],
            [26000000, 0.0900],
            [27700000, 0.1000],
            [29350000, 0.1100],
            [31450000, 0.1200],
            [33950000, 0.1300],
            [37100000, 0.1400],
            [41100000, 0.1500],
            [45800000, 0.1600],
            [49500000, 0.1700],
            [53800000, 0.1800],
            [58500000, 0.1900],
            [64000000, 0.2000],
            [71000000, 0.2100],
            [80000000, 0.2200],
            [93000000, 0.2300],
            [109000000, 0.2400],
            [129000000, 0.2500],
            [163000000, 0.2600],
            [211000000, 0.2700],
            [374000000, 0.2800],
            [459000000, 0.2900],
            [555000000, 0.3000],
            [704000000, 0.3100],
            [957000000, 0.3200],
            [1405000000, 0.3300],
            [$max, 0.3400],
        ],
        'C' => [
            [6600000, 0.00],
            [6950000, 0.0025],
            [7350000, 0.0050],
            [7800000, 0.0075],
            [8850000, 0.0100],
            [9800000, 0.0125],
            [10950000, 0.0150],
            [11200000, 0.0175],
            [12050000, 0.0200],
            [12950000, 0.0300],
            [14150000, 0.0400],
            [15550000, 0.0500],
            [17050000, 0.0600],
            [19500000, 0.0700],
            [22700000, 0.0800],
            [26600000, 0.0900],
            [28100000, 0.1000],
            [30100000, 0.1100],
            [32600000, 0.1200],
            [35400000, 0.1300],
            [38900000, 0.1400],
            [43000000, 0.1500],
            [47400000, 0.1600],
            [51200000, 0.1700],
            [55800000, 0.1800],
            [60400000, 0.1900],
            [66700000, 0.2000],
            [74500000, 0.2100],
            [83200000, 0.2200],
            [95600000, 0.2300],
            [110000000, 0.2400],
            [134000000, 0.2500],
            [169000000, 0.2600],
            [221000000, 0.2700],
            [390000000, 0.2800],
            [463000000, 0.2900],
            [561000000, 0.3000],
            [709000000, 0.3100],
            [965000000, 0.3200],
            [1419000000, 0.3300],
            [$max, 0.3400],
        ],
    ];
}

function ter_rate($category, $bruto) {
    $tables = ter_tables();
    $cat = strtoupper($category);
    if (!isset($tables[$cat])) {
        $cat = 'A';
    }
    $value = (float)$bruto;
    foreach ($tables[$cat] as $row) {
        [$max, $rate] = $row;
        if ($value <= $max) {
            return (float)$rate;
        }
    }
    return 0.0;
}

function getKategoriTER($statusPtkp) {
    return ptkp_ter_category($statusPtkp);
}

function getPTKP($statusPtkp) {
    return ptkp_amount($statusPtkp);
}

function getTarifTER($conn, $kategori, $bruto) {
    // $conn reserved for future DB-based TER master table.
    return ter_rate($kategori, $bruto);
}

function hitungBrutoPajak($basicSalary, array $allowances = [], array $taxableExtras = []) {
    // Tambahkan komponen taxable baru ke $taxableExtras tanpa mengubah fungsi ini.
    $sum = (float)$basicSalary + array_sum($allowances) + array_sum($taxableExtras);
    return $sum;
}

function hitungPPh21BulananTER($brutoPajak, $statusPtkp, $conn = null) {
    $kategori = getKategoriTER($statusPtkp);
    $tarif = getTarifTER($conn, $kategori, $brutoPajak);
    return (float)$brutoPajak * (float)$tarif;
}

function hitungPPh21Tahunan($annualBruto, $statusPtkp, $annualJht, $annualJp) {
    return calc_pph21_annual_progressive($annualBruto, $statusPtkp, $annualJht, $annualJp);
}

function hitungPPh21Desember($annualBruto, $statusPtkp, $annualJht, $annualJp, $pphJanNov) {
    $annualTax = hitungPPh21Tahunan($annualBruto, $statusPtkp, $annualJht, $annualJp);
    return (float)$annualTax - (float)$pphJanNov;
}

function calc_pph21_monthly_ter($bruto, $ptkpStatus) {
    return hitungPPh21BulananTER($bruto, $ptkpStatus);
}

function calc_pph21_annual_progressive($annualBruto, $ptkpStatus, $annualJht, $annualJp) {
    $jobExpense = min($annualBruto * 0.05, 6000000);
    $neto = $annualBruto - $jobExpense - $annualJht - $annualJp;
    $pkp = $neto - ptkp_amount($ptkpStatus);
    if ($pkp <= 0) {
        return 0.0;
    }
    $pkp = floor($pkp / 1000) * 1000;

    $remaining = $pkp;
    $tax = 0.0;
    $bands = [
        [60000000, 0.05],
        [250000000, 0.15],
        [500000000, 0.25],
        [5000000000, 0.30],
        [INF, 0.35],
    ];

    $prevLimit = 0;
    foreach ($bands as [$limit, $rate]) {
        $cap = $limit - $prevLimit;
        $amount = min($remaining, $cap);
        if ($amount > 0) {
            $tax += $amount * $rate;
            $remaining -= $amount;
        }
        $prevLimit = $limit;
        if ($remaining <= 0) {
            break;
        }
    }

    return $tax;
}
