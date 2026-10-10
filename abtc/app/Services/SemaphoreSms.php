<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SemaphoreSms
{
    protected string $apiKey;
    protected string $sender;
    protected string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.semaphore.api_key', env('SEMAPHORE_SMS_API_KEY', ''));
        $this->sender = config('services.semaphore.sender', env('SEMAPHORE_SMS_SENDER', ''));
        $this->baseUrl = config('services.semaphore.base_url', env('SEMAPHORE_SMS_BASE_URL', 'https://api.semaphore.co/api/v4'));
    }

    /**
     * Send a single SMS message.
     *
     * @return array{success: bool, status: string, response: mixed}
     */
    public function send(string $recipient, string $message): array
    {
        $recipient = $this->normalizeNumber($recipient);

        if (blank($this->apiKey)) {
            // No API key configured (demo mode): record as pending/failed with a notice.
            return [
                'success' => false,
                'status' => 'failed',
                'response' => ['error' => 'Semaphore API key not configured. Set SEMAPHORE_SMS_API_KEY in .env.'],
            ];
        }

        try {
            $payload = [
                'apikey' => $this->apiKey,
                'number' => $recipient,
                'message' => $message,
            ];

            if ($this->sender) {
                $payload['sendername'] = $this->sender;
            }

            $response = Http::post($this->baseUrl.'/messages', $payload);
            $body = $response->json();

            $success = $response->successful() && !empty($body) && !isset($body['error']);

            return [
                'success' => $success,
                'status' => $success ? 'delivered' : 'failed',
                'response' => $body,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'status' => 'failed',
                'response' => ['exception' => $e->getMessage()],
            ];
        }
    }

    protected function normalizeNumber(string $number): string
    {
        $number = preg_replace('/[^0-9]/', '', $number);
        if (str_starts_with($number, '0')) {
            $number = '63'.substr($number, 1);
        }
        return $number;
    }
}
