<?php

namespace ItmoSoft\Approvals;

use Illuminate\Support\ServiceProvider;

class ApprovalsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/approvals.php', 'itmosoft-approvals');

        $this->app->singleton(ApprovalManager::class, fn ($app) => new ApprovalManager());
        $this->app->alias(ApprovalManager::class, 'itmosoft.approvals');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/approvals.php' => config_path('itmosoft-approvals.php'),
        ], 'itmosoft-approvals-config');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
