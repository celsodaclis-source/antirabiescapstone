<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Blade::if('role', function (...$roles) {
            $user = auth()->user();
            if (! $user) {
                return false;
            }
            foreach ($roles as $role) {
                if ($user->role === $role) {
                    return true;
                }
            }
            return false;
        });
    }
}
