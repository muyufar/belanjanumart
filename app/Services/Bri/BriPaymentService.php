<?php

namespace App\Services\Bri;

use App\Models\Order;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class BriPaymentService
{
    public function createVirtualAccount(Order $order, string $customerName): Payment
    {
        $custCode = $this->buildCustCode($order);
        $amount = (int) $order->grand_total;
        $expire = now()->addHours((int) config('bri.va_expire_hours'));

        if (config('bri.mock') || ! config('bri.client_id')) {
            return $this->mockPayment($order, $custCode, $amount, $expire);
        }

        $token = $this->accessToken();
        $path = '/v1/briva';
        $body = [
            'institutionCode' => config('bri.institution_code'),
            'brivaNo' => config('bri.briva_no'),
            'custCode' => $custCode,
            'nama' => Str::limit($customerName, 40, ''),
            'amount' => (string) $amount,
            'keterangan' => 'Belanja '.$order->order_number,
            'expiredDate' => $expire->format('Y-m-d H:i:s'),
        ];

        $jsonBody = json_encode($body, JSON_UNESCAPED_SLASHES);
        $timestamp = Carbon::now()->format('Y-m-d\TH:i:s.000+07:00');
        $signature = BriSignature::make($path, 'POST', 'Bearer '.$token, $timestamp, $jsonBody, config('bri.client_secret'));

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$token,
            'BRI-Timestamp' => $timestamp,
            'BRI-Signature' => $signature,
            'Content-Type' => 'application/json',
        ])->withBody($jsonBody, 'application/json')
            ->post(rtrim(config('bri.base_url'), '/').$path);

        $data = $response->json() ?? [];

        if (! $response->successful()) {
            throw new \RuntimeException('BRI BRIVA gagal: '.($data['responseMessage'] ?? $response->body()));
        }

        $vaNumber = (string) ($data['data']['virtualAccount'] ?? $data['virtualAccount'] ?? (config('bri.briva_no').$custCode));

        return Payment::create([
            'order_id' => $order->id,
            'provider' => 'bri',
            'cust_code' => $custCode,
            'virtual_account' => $vaNumber,
            'amount' => $amount,
            'status' => 'pending',
            'bri_response' => $data,
        ]);
    }

    public function checkStatus(Payment $payment): array
    {
        if (config('bri.mock') || ! config('bri.client_id')) {
            return ['status' => $payment->status];
        }

        $token = $this->accessToken();
        $inst = config('bri.institution_code');
        $briva = config('bri.briva_no');
        $cust = $payment->cust_code;
        $path = "/v1/briva/status/{$inst}/{$briva}/{$cust}";
        $timestamp = Carbon::now()->format('Y-m-d\TH:i:s.000+07:00');
        $signature = BriSignature::make($path, 'GET', 'Bearer '.$token, $timestamp, '', config('bri.client_secret'));

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$token,
            'BRI-Timestamp' => $timestamp,
            'BRI-Signature' => $signature,
        ])->get(rtrim(config('bri.base_url'), '/').$path);

        return $response->json() ?? [];
    }

    protected function accessToken(): string
    {
        return Cache::remember('bri_access_token', 800, function () {
            $response = Http::asForm()->post(
                rtrim(config('bri.base_url'), '/').'/oauth/client_credential/accesstoken?grant_type=client_credentials',
                [
                    'client_id' => config('bri.client_id'),
                    'client_secret' => config('bri.client_secret'),
                ]
            );

            $json = $response->json();
            if (! $response->successful() || empty($json['access_token'])) {
                throw new \RuntimeException('BRI OAuth gagal: '.$response->body());
            }

            return (string) $json['access_token'];
        });
    }

    protected function buildCustCode(Order $order): string
    {
        return str_pad((string) $order->id, 10, '0', STR_PAD_LEFT);
    }

    protected function mockPayment(Order $order, string $custCode, int $amount, Carbon $expire): Payment
    {
        $brivaNo = config('bri.briva_no', '77777');
        $va = $brivaNo.$custCode;

        return Payment::create([
            'order_id' => $order->id,
            'provider' => 'bri',
            'cust_code' => $custCode,
            'virtual_account' => $va,
            'amount' => $amount,
            'status' => 'pending',
            'bri_response' => [
                'mock' => true,
                'expiredDate' => $expire->toIso8601String(),
            ],
        ]);
    }
}
