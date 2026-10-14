<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class SmsGateway
{
    /**
     * Endpoints exposed by the SMS Gateway for Android app, in priority order.
     * Local mode serves `/message`; the `/3rdparty` group is used by cloud and
     * private-server deployments. Both accept the same payload shape.
     */
    protected const SEND_ENDPOINTS = [
        '/message',
        '/3rdparty/v1/messages',
    ];

    protected const HEALTH_ENDPOINTS = [
        '/health',
        '/health/ready',
        '/healthcheck',
        '/',
    ];

    protected string $baseUrl;

    protected string $username;

    protected string $password;

    protected string $deviceId;

    protected bool $enabled;

    public function __construct()
    {
        $settings = self::getSettings();

        $host = trim($settings['host']);
        if (! preg_match('~^https?://~i', $host)) {
            $host = 'http://'.$host;
        }
        $this->baseUrl = rtrim($host, '/');
        $this->username = $settings['username'];
        $this->password = $settings['password'];
        $this->deviceId = $settings['device_id'];
        $this->enabled = $settings['enabled'];
    }

    /**
     * Send an SMS via the SMS Gateway for Android app.
     *
     * @return array{success: bool, status: string, response: mixed}
     */
    public function send(string $recipient, string $message): array
    {
        if (! $this->enabled) {
            return [
                'success' => false,
                'status' => 'failed',
                'response' => ['error' => 'SMS Gateway is disabled. Enable it in SMS settings.'],
            ];
        }

        $recipient = $this->normalizeNumber($recipient);

        $payload = [
            'textMessage' => ['text' => $message],
            'phoneNumbers' => [$recipient],
        ];

        $lastFailure = null;

        foreach (static::SEND_ENDPOINTS as $endpoint) {
            $url = $this->baseUrl.$endpoint;

            try {
                $response = Http::withBasicAuth($this->username, $this->password)
                    ->acceptJson()
                    ->connectTimeout(3)
                    ->timeout(10)
                    ->post($url.'?skipPhoneValidation=true', $payload);
            } catch (ConnectionException $e) {
                $lastFailure = [
                    'error' => 'Could not reach the SMS Gateway at '.$this->baseUrl.'.',
                    'detail' => $e->getMessage(),
                ];

                break;
            }

            if ($response->successful()) {
                $body = $this->decode($response, $url);
                $gatewayState = is_array($body) ? strtolower((string) ($body['state'] ?? '')) : '';

                return [
                    'success' => true,
                    // A successful API response means the gateway accepted the
                    // request; only a delivery state confirms receipt by the handset.
                    'status' => match ($gatewayState) {
                        'delivered' => 'delivered',
                        'failed' => 'failed',
                        default => 'pending',
                    },
                    'response' => $body,
                ];
            }

            $lastFailure = [
                'error' => 'Gateway rejected the message (HTTP '.$response->status().').',
                'url' => $url,
                'body' => $this->decode($response, $url),
            ];

            // Endpoint does not exist on this gateway - fall through and try the next one.
            if (in_array($response->status(), [404, 405], true)) {
                continue;
            }

            // 401/403 and other errors are terminal; retrying other paths will not help.
            break;
        }

        return [
            'success' => false,
            'status' => 'failed',
            'response' => $lastFailure ?? ['error' => 'Could not connect to the SMS Gateway at '.$this->baseUrl.'.'],
        ];
    }

    /**
     * Check whether the gateway is reachable and healthy.
     */
    public function testConnection(): array
    {
        if (! $this->enabled) {
            return [
                'connected' => false,
                'message' => 'SMS Gateway is disabled. Enable it in SMS settings.',
            ];
        }

        $lastError = null;

        foreach (static::HEALTH_ENDPOINTS as $endpoint) {
            $url = $this->baseUrl.$endpoint;

            try {
                $response = Http::withBasicAuth($this->username, $this->password)
                    ->acceptJson()
                    ->connectTimeout(3)
                    ->timeout(5)
                    ->get($url);
            } catch (ConnectionException $e) {
                return [
                    'connected' => false,
                    'message' => 'Could not reach '.$this->baseUrl.' - '.$e->getMessage(),
                ];
            }

            if ($response->successful()) {
                $body = $this->decode($response, $url);
                $status = is_array($body) ? ($body['status'] ?? null) : null;

                return [
                    'connected' => true,
                    'device' => is_array($body) ? ($body['deviceId'] ?? $this->deviceId) : $this->deviceId,
                    'message' => 'Connected to SMS Gateway ('.$status.') at '.$this->baseUrl.'.',
                ];
            }

            if ($response->status() === 401 || $response->status() === 403) {
                return [
                    'connected' => false,
                    'message' => 'Gateway rejected the credentials (HTTP '.$response->status().'). Check the username and password shown in the app.',
                ];
            }

            $lastError = $endpoint.' -> HTTP '.$response->status();
        }

        // Cloud / private-server mode (e.g. https://api.sms-gate.app:443) does not
        // expose /health. Listing devices proves auth + reachability without sending SMS.
        try {
            $response = Http::withBasicAuth($this->username, $this->password)
                ->acceptJson()
                ->connectTimeout(3)
                ->timeout(5)
                ->get($this->baseUrl.'/3rdparty/v1/devices');
        } catch (ConnectionException $e) {
            return [
                'connected' => false,
                'message' => 'Could not reach '.$this->baseUrl.' - '.$e->getMessage(),
            ];
        }

        if ($response->successful()) {
            $body = $this->decode($response, $this->baseUrl.'/3rdparty/v1/devices');
            $device = $this->deviceId;
            if (is_array($body)) {
                // Single-device responses return the device object directly.
                $device = $body['id'] ?? $body['deviceId'] ?? $device;
            }

            return [
                'connected' => true,
                'device' => $device,
                'message' => 'Connected to SMS Gateway (cloud) at '.$this->baseUrl.'.',
            ];
        }

        if ($response->status() === 401 || $response->status() === 403) {
            return [
                'connected' => false,
                'message' => 'Gateway rejected the credentials (HTTP '.$response->status().'). Check the username and password.',
            ];
        }

        return [
            'connected' => false,
            'message' => 'Reached '.$this->baseUrl.' but no health endpoint responded'.($lastError ? ' ('.$lastError.')' : '').'.',
        ];
    }

    public static function getSettings(): array
    {
        return [
            'enabled' => self::castBool(SystemSetting::get('sms_gateway_enabled', env('SMS_GATEWAY_ENABLED', false))),
            'host' => (string) SystemSetting::get('sms_gateway_host', env('SMS_GATEWAY_HOST', 'https://api.sms-gate.app')),
            'username' => (string) SystemSetting::get('sms_gateway_username', env('SMS_GATEWAY_USERNAME', 'sms')),
            'password' => (string) SystemSetting::get('sms_gateway_password', env('SMS_GATEWAY_PASSWORD', '')),
            'device_id' => (string) SystemSetting::get('sms_gateway_device_id', env('SMS_GATEWAY_DEVICE_ID', '')),
        ];
    }

    public static function saveSettings(array $data): void
    {
        SystemSetting::set('sms_gateway_enabled', self::castBool($data['enabled'] ?? false) ? '1' : '0');
        SystemSetting::set('sms_gateway_host', $data['host'] ?? '');
        SystemSetting::set('sms_gateway_username', $data['username'] ?? 'sms');

        // An empty field means "keep the stored password".
        if (filled($data['password'] ?? null)) {
            SystemSetting::set('sms_gateway_password', $data['password']);
        }

        SystemSetting::set('sms_gateway_device_id', $data['device_id'] ?? '');
    }

    /**
     * Decode a gateway response, falling back to the raw body when it is not JSON.
     */
    protected function decode(Response $response, string $url): mixed
    {
        $body = $response->json();

        if (is_array($body)) {
            return $body;
        }

        return [
            'url' => $url,
            'http_status' => $response->status(),
            'body' => mb_substr((string) $response->body(), 0, 500),
        ];
    }

    /**
     * Settings are stored as strings, so "false" would otherwise cast to true.
     */
    protected static function castBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Convert a local Philippine mobile number to E.164, e.g. 0917... -> +63917...
     */
    protected function normalizeNumber(string $number): string
    {
        $digits = preg_replace('/[^0-9]/', '', $number) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '63')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return '+63'.$digits;
    }
}
