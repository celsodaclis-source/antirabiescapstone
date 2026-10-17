<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Services\SmsGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('sms_gateway_enabled', '1');
        SystemSetting::set('sms_gateway_host', '10.0.0.5:8080');
        SystemSetting::set('sms_gateway_username', 'sms');
        SystemSetting::set('sms_gateway_password', 'secret');
    }

    public function test_it_posts_the_documented_gateway_payload(): void
    {
        Http::fake([
            '*/message*' => Http::response(['state' => 'Pending'], 200),
        ]);

        $result = (new SmsGateway)->send('09171234567', 'Hello');

        $this->assertTrue($result['success']);
        $this->assertSame('pending', $result['status']);

        Http::assertSent(function ($request) {
            $this->assertSame('POST', $request->method());
            $this->assertSame('http://10.0.0.5:8080/message', strtok($request->url(), '?'));
            $this->assertSame('Basic '.base64_encode('sms:secret'), $request->header('Authorization')[0]);

            $body = json_decode($request->body(), true);
            $this->assertSame('Hello', $body['textMessage']['text']);
            $this->assertSame(['+639171234567'], $body['phoneNumbers']);

            return true;
        });
    }

    public function test_it_falls_back_to_the_3rdparty_endpoint_on_404(): void
    {
        Http::fake([
            '*/3rdparty/v1/messages*' => Http::response(['state' => 'Pending'], 200),
            '*/message*' => Http::response(['error' => 'not found'], 404),
        ]);

        $result = (new SmsGateway)->send('09171234567', 'Hello');

        $this->assertTrue($result['success']);
        Http::assertSentCount(2);
    }

    public function test_it_stops_probing_after_an_auth_failure(): void
    {
        Http::fake([
            '*' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $result = (new SmsGateway)->send('09171234567', 'Hello');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('HTTP 401', json_encode($result['response']));
        Http::assertSentCount(1);
    }

    public function test_it_reports_a_connection_failure(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection refused');
        });

        $result = (new SmsGateway)->send('09171234567', 'Hello');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Could not reach', json_encode($result['response']));
    }

    public function test_it_does_not_send_when_disabled(): void
    {
        SystemSetting::set('sms_gateway_enabled', '0');
        Http::fake();

        $result = (new SmsGateway)->send('09171234567', 'Hello');

        $this->assertFalse($result['success']);
        Http::assertNothingSent();
    }

    public function test_test_connection_uses_the_health_endpoint(): void
    {
        Http::fake([
            '*/health*' => Http::response(['status' => 'pass', 'deviceId' => 'abc'], 200),
        ]);

        $result = (new SmsGateway)->testConnection();

        $this->assertTrue($result['connected']);
        $this->assertSame('abc', $result['device']);
    }

    public function test_test_connection_explains_a_bad_password(): void
    {
        Http::fake([
            '*' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $result = (new SmsGateway)->testConnection();

        $this->assertFalse($result['connected']);
        $this->assertStringContainsString('credentials', $result['message']);
    }

    public function test_blank_password_keeps_the_stored_one(): void
    {
        SmsGateway::saveSettings([
            'enabled' => true,
            'host' => '10.0.0.5:8080',
            'username' => 'sms',
            'password' => '',
            'device_id' => 'abc',
        ]);

        $this->assertSame('secret', SystemSetting::get('sms_gateway_password'));
    }

    public function test_saving_settings_stores_enabled_as_a_real_boolean(): void
    {
        SmsGateway::saveSettings([
            'enabled' => 'on',
            'host' => '10.0.0.5:8080',
            'username' => 'sms',
            'password' => 'new',
        ]);

        $this->assertTrue(SmsGateway::getSettings()['enabled']);

        SmsGateway::saveSettings(['enabled' => 'false', 'host' => 'x', 'username' => 'y']);

        $this->assertFalse(SmsGateway::getSettings()['enabled']);
    }
}
