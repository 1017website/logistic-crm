<?php

if (!function_exists('idr')) {
    /**
     * Format angka ke IDR lengkap
     * idr(100000000) → "Rp 100.000.000"
     * idr(100000000, true) → "Rp 100.000.000,00"
     */
    function idr($amount, bool $withDecimal = false): string
    {
        if (is_null($amount) || $amount === '') return 'Rp 0';

        $amount = (float) $amount;

        if ($withDecimal) {
            return 'Rp ' . number_format($amount, 2, ',', '.');
        }

        return 'Rp ' . number_format($amount, 0, ',', '.');
    }
}

if (!function_exists('idrm')) {
    /**
     * Format angka ke IDR singkat (M/K)
     * idrm(1500000000) → "Rp 1,50M"
     * idrm(150000000)  → "Rp 150M"
     * idrm(5000000)    → "Rp 5M"
     * idrm(500000)     → "Rp 500rb"
     */
    function idrm($amount): string
    {
        if (is_null($amount) || $amount === '') return 'Rp 0';

        $amount = (float) $amount;

        if ($amount >= 1_000_000_000) {
            $val = $amount / 1_000_000_000;
            return 'Rp ' . rtrim(rtrim(number_format($val, 2, ',', '.'), '0'), ',') . 'M';
        }

        if ($amount >= 1_000_000) {
            $val = $amount / 1_000_000;
            // Kalau bulat (misal 100M, 500M), tidak perlu desimal
            return 'Rp ' . rtrim(rtrim(number_format($val, 1, ',', '.'), '0'), ',') . ' Jt';
        }

        if ($amount >= 1_000) {
            $val = $amount / 1_000;
            return 'Rp ' . number_format($val, 0, ',', '.') . ' Rb';
        }

        return 'Rp ' . number_format($amount, 0, ',', '.');
    }
}

if (!function_exists('idr_input')) {
    /**
     * Format untuk value di input field (tanpa prefix Rp)
     * idr_input(100000000) → "100.000.000"
     */
    function idr_input($amount): string
    {
        if (is_null($amount) || $amount === '') return '';
        return number_format((float) $amount, 0, ',', '.');
    }
}
