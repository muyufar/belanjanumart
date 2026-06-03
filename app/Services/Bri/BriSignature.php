<?php

namespace App\Services\Bri;

class BriSignature
{
    /**
     * @see https://developers.bri.co.id/en/docs/authentication
     */
    public static function make(string $path, string $verb, string $bearerToken, string $timestamp, string $body, string $clientSecret): string
    {
        $token = str_starts_with($bearerToken, 'Bearer ') ? $bearerToken : 'Bearer '.$bearerToken;
        $payload = $path.'&'.$verb.'&'.$token.'&'.$timestamp.'&'.$body;

        return base64_encode(hash_hmac('sha256', $payload, $clientSecret, true));
    }
}
