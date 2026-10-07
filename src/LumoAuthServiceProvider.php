<?php

declare(strict_types=1);

namespace LumoAuth\Laravel;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use LumoAuth\Laravel\Http\Middleware\RequirePermission;
use LumoAuth\LumoAuth;

final class LumoAuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/lumoauth.php', 'lumoauth');

        $this->app->singleton(LumoAuth::class, static function ($app): LumoAuth {
            $config = $app['config']->get('lumoauth', []);

            return new LumoAuth(
                apiKey: $config['api_key'] ?? null,
                baseUrl: $config['url'] ?? null,
                orgId: $config['org_id'] ?? null,
            );
        });
        $this->app->alias(LumoAuth::class, 'lumoauth');
    }

    public function boot(): void
    {
        $this->publishes([__DIR__ . '/../config/lumoauth.php' => $this->app->configPath('lumoauth.php')], 'lumoauth-config');

        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('lumoauth.permission', RequirePermission::class);
    }
}
