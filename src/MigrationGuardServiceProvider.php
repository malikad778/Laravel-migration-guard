<?php

namespace Malikad778\MigrationGuard;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;

class MigrationGuardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/migration-guard.php', 'migration-guard'
        );

        
        $this->app->singleton(MigrationAnalyser::class, function ($app) {
            $analyser = new MigrationAnalyser();

            $checks = [
                Checks\DropColumnCheck::class,
                Checks\DropTableCheck::class,
                Checks\RenameColumnCheck::class,
                Checks\RenameTableCheck::class,
                Checks\AddColumnNotNullCheck::class,
                Checks\ChangeColumnTypeCheck::class,
                Checks\AddIndexCheck::class,
                Checks\ModifyPrimaryKeyCheck::class,
                Checks\TruncateCheck::class,
            ];

            foreach ($checks as $checkClass) {
                $check = $app->make($checkClass);
                $app->tag([$checkClass], 'migration-guard.check');
                $analyser->addCheck($check);
            }

            return $analyser;
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/migration-guard.php' => config_path('migration-guard.php'),
            ], 'migration-guard-config');

            
            $this->commands([
                Commands\AnalyseCommand::class,   
                Commands\IgnoreCommand::class,    
                Commands\DigestCommand::class,    
                Commands\FixCommand::class,       
            ]);
        }

        
        
        $this->registerMigrationListener();
    }

    private function registerMigrationListener(): void
    {
        
        if (env('MIGRATION_GUARD_DISABLE', false)) {
            return;
        }

        $environments = Config::get('migration-guard.environments', []);

        
        if (!empty($environments) && !$this->app->environment($environments)) {
            return;
        }

        $this->app['events']->listen(
            \Illuminate\Database\Events\MigrationStarting::class,
            Listeners\MigrationStartingListener::class
        );
    }
}
