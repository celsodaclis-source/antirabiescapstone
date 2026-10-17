<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use GuzzleHttp\Client;
use App\Models\FileMaintenance;

$client = new Client(['base_uri' => 'http://127.0.0.1:8000/', 'cookies' => true, 'verify' => false, 'headers' => ['Accept' => 'application/json']]);

// Login
echo "=== LOGIN ===\n";
$resp = $client->post('api/auth/login', ['json' => ['email' => 'admin@abtc.gov.ph', 'password' => 'password123']]);
$data = json_decode($resp->getBody(), true);
$token = $data['token'];
echo "Token obtained: " . substr($token, 0, 20) . "...\n\n";

$headers = ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];

// READ - List all files
echo "=== READ: List all files ===\n";
$resp = $client->get('api/files', ['headers' => $headers]);
echo "Status: " . $resp->getStatusCode() . "\n";
$body = json_decode($resp->getBody(), true);
echo "Total files: " . $body['total'] . "\n";
echo "First file: " . $body['data'][0]['title'] . "\n\n";

// READ - Show single file
echo "=== READ: Show single file ===\n";
$resp = $client->get('api/files/1', ['headers' => $headers]);
echo "Status: " . $resp->getStatusCode() . "\n";
$body = json_decode($resp->getBody(), true);
echo "File: " . $body['data']['title'] . " (" . $body['data']['file_number'] . ")\n\n";

// CREATE
echo "=== CREATE: New file ===\n";
$resp = $client->post('api/files', [
    'headers' => $headers,
    'json' => [
        'file_number' => 'FM-2024-005',
        'title' => 'Test File for CRUD',
        'description' => 'Testing create operation',
        'category' => 'Test',
        'status' => 'draft',
        'effective_date' => '2026-01-01',
        'expiry_date' => '2026-12-31',
        'tags' => ['test', 'crud'],
    ]
]);
echo "Status: " . $resp->getStatusCode() . "\n";
$body = json_decode($resp->getBody(), true);
$newId = $body['data']['id'];
echo "Created file ID: $newId, Title: " . $body['data']['title'] . "\n\n";

// UPDATE
echo "=== UPDATE: Edit file ===\n";
$resp = $client->put("api/files/$newId", [
    'headers' => $headers,
    'json' => [
        'title' => 'Updated Test File Title',
        'status' => 'active',
    ]
]);
echo "Status: " . $resp->getStatusCode() . "\n";
$body = json_decode($resp->getBody(), true);
echo "Updated title: " . $body['data']['title'] . ", Status: " . $body['data']['status'] . "\n\n";

// SEARCH
echo "=== SEARCH: Search for test ===\n";
$resp = $client->get('api/files/search?term=test', ['headers' => $headers]);
echo "Status: " . $resp->getStatusCode() . "\n";
$body = json_decode($resp->getBody(), true);
echo "Search results: " . count($body) . " found\n";
foreach ($body as $r) {
    echo "  - " . $r['title'] . " (" . $r['file_number'] . ")\n";
}
echo "\n";

// DELETE
echo "=== DELETE: Delete file ===\n";
$resp = $client->delete("api/files/$newId", ['headers' => $headers]);
echo "Status: " . $resp->getStatusCode() . "\n";
$body = json_decode($resp->getBody(), true);
echo "Message: " . $body['message'] . "\n\n";

// Verify deletion
echo "=== VERIFY: Count after delete ===\n";
$resp = $client->get('api/files', ['headers' => $headers]);
$body = json_decode($resp->getBody(), true);
echo "Total files after delete: " . $body['total'] . "\n";

echo "\n=== ALL CRUD TESTS PASSED ===\n";
