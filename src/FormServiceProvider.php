<?php

namespace Uspdev\Forms;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use \Spatie\Activitylog\Models\Activity;
use Uspdev\Forms\Providers\EventServiceProvider;
use Uspdev\Forms\FormsManager;

class FormServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->register(EventServiceProvider::class);

        // Publish config file
        $this->publishes([
            __DIR__ . '/../config/uspdev-forms.php' => config_path('uspdev-forms.php'),
        ], 'forms-config');

        // Migrations are owned by the package and loaded directly by the provider.
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations/v2');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'uspdev-forms');

        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Uspdev\Forms\Console\Commands\FormDemo::class,
            ]);
        }

        $this->commands([
                \Uspdev\Forms\Console\Commands\FormSync::class,
            ]);

        // Registra a diretiva
        // para chamar use @submissionsTable($form) 
        Blade::directive('submissionsTable', function ($form) {
            return "<?php
                \$__uspdevFormsForm = $form;
                \$__uspdevFormsDefinition = \Uspdev\Forms\Facades\Forms::definition(\$__uspdevFormsForm->name, \$__uspdevFormsForm->version);
                \$__uspdevFormsSubmissions = \$__uspdevFormsDefinition
                    ? \Uspdev\Forms\Models\FormSubmission::query()
                        ->where('form_definition_id', \$__uspdevFormsDefinition->id)
                        ->when(
                            \$__uspdevFormsForm->key != config('uspdev-forms.defaultKey'),
                            fn (\$query) => \$query->where('key', \$__uspdevFormsForm->key)
                        )
                        ->get()
                    : collect();
                echo view('uspdev-forms::partials.submissions-table', [
                    'form' => \$__uspdevFormsForm,
                    'definition' => \$__uspdevFormsDefinition,
                    'submissions' => \$__uspdevFormsSubmissions,
                ])->render();
            ?>";
        });

        // https://github.com/spatie/laravel-activitylog/issues/39
        Activity::saving(function (Activity $activity) {
            $activity->properties = $activity->properties->put('agent', [
                'ip' => Request()->ip()
            ]);
        });
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/uspdev-forms.php',
            'uspdev-forms'
        );

        $this->app->singleton(FormsManager::class);

        $this->app->alias(FormsManager::class, 'forms');
    }
}
