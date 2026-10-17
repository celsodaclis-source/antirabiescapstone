<?php
error_reporting(E_ALL);

$loginUrl = 'http://127.0.0.1:8123/login';

$ctx = stream_context_create(['http' => [
    'method' => 'GET',
    'header' => "Connection: close\r\nUser-Agent: smoke",
    'ignore_errors' => true,
]]);
$page = @file_get_contents($loginUrl, false, $ctx);
file_put_contents(__DIR__ . '/login_response.html', $page);
file_put_contents(__DIR__ . '/login_headers.txt', implode("\n", $http_response_headers ?? ['NO HEADERS']));

echo 'Login page bytes: ' . strlen($page) . PHP_EOL;
$hdrs = $http_response_headers ?? [];
foreach ($hdrs as $h) {
    if (stripos($h, 'Set-Cookie:') !== false || stripos($h, 'Location:') !== false || stripos($h, 'HTTP/') !== false) {
        echo 'HDR: ' . $h . PHP_EOL;
    }
}
// Show a snippet around the csrf token
preg_match('/name="_token"[^>]*value="([^"]+)"/', $page, $m);
echo 'CSRF token: ' . ($m[1] ?? 'MISSING') . PHP_EOL;
echo 'Has N-VISION: ' . (stripos($page, 'N-VISION') !== false ? 'YES' : 'no') . PHP_EOL;
echo 'Has role dropdown: ' . (strpos($page, 'role') !== false ? 'YES' : 'no') . PHP_EOL;
