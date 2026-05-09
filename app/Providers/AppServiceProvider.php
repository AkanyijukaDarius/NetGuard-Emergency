<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Broadcast;
use Native\Mobile\Runtime;
use App\Services\CamaraService;
use App\Services\AiTriageService;
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CamaraService::class);
        $this->app->singleton(AiTriageService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
// Safety: Ensure the mobile runtime is booted before doing anything else
        if (class_exists(Runtime::class) && !Runtime::isBooted()) {
            // We use the app instance from the container
            Runtime::boot(app());
        }


        Broadcast::routes(['middleware' => ['auth:sanctum']]);

        require base_path('routes/channels.php');


    }
}
