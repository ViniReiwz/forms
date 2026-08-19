<?php

namespace Uspdev\Forms\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Uspdev\Forms\FormServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            FormServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('uspdev-forms.defaultKey', 'default');
        $app['config']->set('uspdev-forms.defaultMethod', 'POST');
        $app['config']->set('uspdev-forms.defaultGroup', 'default');
        $app['config']->set('uspdev-forms.defaultBtnLabel', 'Enviar');
        $app['config']->set('uspdev-forms.bootstrapVersion', 5);
        $app['config']->set('uspdev-forms.formSize', 'normal');
        $app['config']->set('activitylog', [
            'enabled' => true,
            'delete_records_older_than_days' => 365,
            'default_log_name' => 'default',
            'default_auth_driver' => null,
            'subject_returns_soft_deleted_models' => false,
            'activity_model' => \Spatie\Activitylog\Models\Activity::class,
            'table_name' => 'activity_log',
            'database_connection' => null,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate')->run();
    }
}
