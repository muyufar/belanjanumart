<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class WaTemporaryPasswordService
{
    public function __construct(
        protected NumartCustomerService $numartCustomers,
        protected FonnteWaService $wa,
    ) {}

    /**
     * @return array{ok: bool, user?: User, plain_password?: string, message?: string}
     */
    public function issueForActivation(object $customer): array
    {
        $existing = $this->numartCustomers->userForCustomer((int) $customer->customer_id);
        if ($existing && ! $existing->must_change_password) {
            return [
                'ok' => false,
                'message' => 'Akun marketplace untuk data customer ini sudah aktif. Silakan masuk.',
            ];
        }

        return $this->issue($customer, 'activation', $existing);
    }

    /**
     * @return array{ok: bool, user?: User, plain_password?: string, message?: string}
     */
    public function issueForPasswordReset(object $customer): array
    {
        $existing = $this->numartCustomers->userForCustomer((int) $customer->customer_id);

        if (! $existing) {
            return [
                'ok' => false,
                'message' => 'Akun belanja online untuk data ini belum ada. Gunakan aktivasi akun.',
            ];
        }

        return $this->issue($customer, 'reset', $existing);
    }

    /**
     * @return array{ok: bool, user?: User, message?: string}
     */
    protected function issue(object $customer, string $purpose, ?User $existing = null): array
    {
        $plainPassword = strtoupper(Str::random(8));
        $waResult = $purpose === 'reset'
            ? $this->wa->sendPasswordReset((string) $customer->customer_tlpn, $plainPassword)
            : $this->wa->sendActivationPassword((string) $customer->customer_tlpn, $plainPassword);

        if (! $waResult['ok']) {
            return ['ok' => false, 'message' => $waResult['message'] ?? 'Gagal mengirim WhatsApp.'];
        }

        $phone = $this->numartCustomers->normalizePhone((string) $customer->customer_tlpn);

        if ($existing) {
            $existing->password = Hash::make($plainPassword);
            $existing->must_change_password = true;
            $existing->wa_password_sent_at = now();
            $existing->save();
            $user = $existing;
        } else {
            $user = User::create([
                'name' => $customer->customer_nama,
                'email' => $this->numartCustomers->emailForUser($customer),
                'phone' => $phone,
                'password' => Hash::make($plainPassword),
                'must_change_password' => true,
                'wa_password_sent_at' => now(),
                'numart_customer_id' => (int) $customer->customer_id,
                'address' => (string) ($customer->customer_alamat ?? ''),
                'price_tier' => $this->numartCustomers->priceTierFromCustomer($customer),
                'warung_verification_status' => $this->numartCustomers->warungStatusFromCustomer($customer),
            ]);
        }

        $this->numartCustomers->applyCustomerToUser($user, $customer);

        return ['ok' => true, 'user' => $user];
    }

    public function maskPhone(string $phone): string
    {
        if (strlen($phone) < 6) {
            return $phone;
        }

        return substr($phone, 0, 4).'****'.substr($phone, -4);
    }
}
