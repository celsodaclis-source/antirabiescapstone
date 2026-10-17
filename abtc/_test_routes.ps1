#!/usr/bin/env pwsh
$base = "http://127.0.0.1:8799"
$cookiejar = "C:\Users\dacli\Downloads\antirabiescapstone\abtc\cookies_test.txt"

# 1. Get login page + CSRF token
$loginPage = curl.exe -s -c $cookiejar "$base/login"
if ($loginPage -cmatch 'name="csrf_token" content="([^"]*)"') {
    $token = $Matches[1]
    Write-Host "Got CSRF token: $($token.Substring(0,8))..."
} else {
    Write-Host "Could not find CSRF token in login page"
    Write-Host ($loginPage | Select-Object -First -Last 5)
    exit 1
}

# 2. Login as admin
$loginResp = curl.exe -s -b $cookiejar -c $cookiejar -X POST "$base/login" `
    -H "X-CSRF-TOKEN: $token" `
    -H "Content-Type: application/x-www-form-urlencoded" `
    -d "email=admin@abtc.gov.ph&password=password123&_token=$token&role=Admin" `
    -w "\nHTTP_%{http_code}"

# Check we got redirected (login success = 302) or stayed (200 = failed)
$loginCode = $loginResp.Split("`n")[-1]
Write-Host "Login response code: $loginCode"
if ($loginCode -ne "302") {
    Write-Host "Login seems to have failed. Response:"
    Write-Host ($loginResp | Select-Object -First -Last 20)
    exit 1
}

Write-Host "=== Login successful, testing protected routes ==="

# 3. Test protected endpoints
$routes = @(
    "GET /dashboard",
    "GET /patients",
    "GET /patients/create",
    "GET /queue",
    "GET /queue/data",
    "GET /schedules",
    "GET /schedules/overdue/list",
    "GET /inventory",
    "GET /inventory/create",
    "GET /files",
    "GET /sms",
    "GET /sms/template",
    "GET /reports",
    "GET /settings",
    "GET /settings/users",
    "GET /settings/audit",
    "GET /profile"
)

foreach ($route in $routes) {
    $method = $route.Split(' ')[0]
    $path = $route.Split(' ')[1]
    $resp = curl.exe -s -b $cookiejar -o /dev/null -w "%{http_code}" "$base$path" 2>&1
    if ($resp -ne "200") {
        Write-Host "FAIL: $route -> HTTP $resp"
    } else {
        Write-Host "OK:   $route -> HTTP 200"
    }
}
