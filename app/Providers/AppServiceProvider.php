<?php

namespace App\Providers;

use App\Services\XpengClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Identifiants statiques (config/services.php) plutot que par
        // utilisateur : contrairement a FreeMobileSms, un seul compte Xpeng
        // est relie a l'appli, pas un par vehicule.
        $this->app->singleton(XpengClient::class, fn () => new XpengClient(
            baseUrl: (string) config('services.xpeng.base_url'),
            appId: (string) config('services.xpeng.app_id'),
            appSecret: (string) config('services.xpeng.app_secret'),
            openId: (string) config('services.xpeng.open_id'),
            accessToken: (string) config('services.xpeng.access_token'),
            enterpriseName: (string) config('services.xpeng.enterprise_name'),
            scopeCode: (string) config('services.xpeng.scope_code'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
