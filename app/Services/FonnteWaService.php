<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FonnteWaService
{
    public function sendActivationPassword(string $phone, string $plainPassword): array
    {
        $message = "*Belanja Numart*\n\n".
            "Password sementara akun Anda:\n*{$plainPassword}*\n\n".
            "Masuk di situs belanja online, lalu segera ganti password.\n".
            "Jangan bagikan pesan ini ke siapapun.";

        return $this->deliverMessage($phone, $message);
    }

    public function sendPasswordReset(string $phone, string $plainPassword): array
    {
        $message = "*Belanja Numart — Reset Password*\n\n".
            "Password sementara baru:\n*{$plainPassword}*\n\n".
            "Masuk dengan password ini, lalu segera ganti di menu Ganti Password.\n".
            "Jangan bagikan pesan ini ke siapapun.";

        return $this->deliverMessage($phone, $message);
    }

    protected function deliverMessage(string $phone, string $message): array
    {
        $api = $this->sendViaNumartApi($phone, $message);
        if ($api['ok']) {
            return $api;
        }

        if ($api['attempted'] ?? false) {
            return $api;
        }

        return $this->sendViaDirectFonnte($phone, $message);
    }

    protected function sendViaNumartApi(string $phone, string $message): array
    {
        $url = rtrim((string) config('marketplace.wa_api_url'), '/');
        $secret = (string) config('marketplace.wa_api_secret');

        if ($url === '' || $secret === '' || str_contains($secret, 'ganti-dengan')) {
            return ['ok' => false, 'attempted' => false, 'message' => ''];
        }

        try {
            $response = Http::timeout(60)
                ->withHeaders(['X-Marketplace-Secret' => $secret])
                ->post($url, [
                    'phone' => $phone,
                    'message' => $message,
                ]);

            $body = $response->json();
            if (! is_array($body)) {
                $body = ['success' => false, 'message' => $response->body()];
            }

            if ($response->successful() && ($body['success'] ?? false)) {
                return ['ok' => true, 'attempted' => true, 'message' => (string) ($body['message'] ?? 'Pesan terkirim.')];
            }

            return [
                'ok' => false,
                'attempted' => true,
                'message' => (string) ($body['message'] ?? 'Gagal mengirim WhatsApp via API Numart.'),
            ];
        } catch (\Throwable $e) {
            Log::error('WA aktivasi API gagal', ['error' => $e->getMessage()]);

            return ['ok' => false, 'attempted' => true, 'message' => 'Tidak dapat menghubungi server WhatsApp Numart.'];
        }
    }

    protected function sendViaDirectFonnte(string $phone, string $message): array
    {
        $token = $this->loadFonnteToken();
        if ($token === '') {
            return [
                'ok' => false,
                'message' => 'WhatsApp belum dikonfigurasi. Isi NUMART_WA_API_* di .env atau pastikan file api/no.js Fonnte di POS tersedia.',
            ];
        }

        $target = $this->normalizeWaTarget($phone);
        if ($target === '') {
            return ['ok' => false, 'message' => 'Nomor WhatsApp tidak valid.'];
        }

        try {
            $response = Http::timeout(60)
                ->withHeaders(['Authorization' => $token])
                ->asForm()
                ->post('https://api.fonnte.com/send', [
                    'target' => $target,
                    'message' => $message,
                ]);

            $body = $response->json();
            if (is_array($body) && (($body['status'] ?? false) === true || ($body['Status'] ?? false) === true)) {
                return ['ok' => true, 'message' => 'Password dikirim via WhatsApp.'];
            }

            $reason = is_array($body)
                ? (string) ($body['reason'] ?? $body['message'] ?? json_encode($body))
                : $response->body();

            Log::warning('Fonnte direct gagal', ['response' => $reason]);

            return ['ok' => false, 'message' => 'Gagal mengirim WhatsApp: '.$reason];
        } catch (\Throwable $e) {
            Log::error('Fonnte direct exception', ['error' => $e->getMessage()]);

            return ['ok' => false, 'message' => 'Gagal menghubungi layanan Fonnte.'];
        }
    }

    protected function loadFonnteToken(): string
    {
        $path = (string) config('marketplace.fonnte_no_js_path');
        if ($path === '' || ! is_readable($path)) {
            return '';
        }

        $raw = file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            return '';
        }

        $raw = preg_replace('/^\xEF\xBB\xBF/', '', trim($raw));
        if ($raw !== '' && ($raw[0] === '{' || $raw[0] === '[')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                foreach (['token', 'fonnte_token', 'authorization'] as $key) {
                    if (! empty($decoded[$key])) {
                        return trim((string) $decoded[$key]);
                    }
                }
            }
        }

        if (preg_match('/token\s*[:=]\s*(.+)$/mi', $raw, $m)) {
            return trim($m[1]);
        }

        return '';
    }

    protected function normalizeWaTarget(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return '';
        }
        if (str_starts_with($digits, '62')) {
            return $digits;
        }
        if ($digits[0] === '0') {
            return '62'.substr($digits, 1);
        }

        return '62'.$digits;
    }
}
