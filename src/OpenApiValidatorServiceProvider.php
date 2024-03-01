<?php
namespace doertemithut\openapivalidator;

use Illuminate\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;

class OpenApiValidatorServiceProvider extends ServiceProvider {

    public function boot(\Illuminate\Routing\Router $router) {
        $this->commands([
            \doertemithut\openapivalidator\ValidateAPI ::class,
        ]);
    }
}