<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$users = App\Models\User::select('id','name','email','role')->get();
foreach ($users as $u) {
    echo $u->id . ' | ' . $u->role . ' | ' . $u->email . ' | ' . $u->name . PHP_EOL;
}
echo 'Total users: ' . $users->count() . PHP_EOL;

echo PHP_EOL . 'Roles available: ' . implode(', ', App\Models\User::roles()) . PHP_EOL;

echo PHP_EOL . 'Low stock batches: ' . App\Models\VaccineInventory::whereRaw('quantity <= low_stock_threshold')->count() . PHP_EOL;
echo 'Near-expiry (<=30d): ' . App\Models\VaccineInventory::where('expiration_date', '<=', Illuminate\Support\Carbon::today()->addDays(30))->count() . PHP_EOL;
