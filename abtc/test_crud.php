<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Login as admin to get token
$client = new GuzzleHttp\Client(['base_uri' => 'http://127.0.0.1:8000/', 'cookies' => true, 'verify' => false]);

// Try to get a token via the login API
try {
    $response = $client->post('api/auth/login', [
        'json' => ['email' => 'admin@abtc.gov.ph', 'password' => 'password123']
    ]);
    $body = json_decode($response->getBody(), true);
    echo "Login response: " . json_encode($body, JSON_PRETTY_PRINT) . "\n";
} catch (Exception $e) {
    echo "Login error: " . $e->getMessage() . "\n";
    if ($e->hasResponse()) {
        echo "Response: " . $e->getResponse()->getBody() . "\n";
    }
}
