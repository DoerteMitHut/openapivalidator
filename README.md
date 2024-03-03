#Laravel openAPIValidator

An artisan command line tool to check the compliance of laravel project's API with an openAPI3.0 specification.

## installation

To your `composer.json` add under `repositories`:


```json
    ...
    "repositories":[
        ...
        {
            "type":"vcs",
            "url":"https://github.com/doertemithut/openapivalidator"
        }
    ]

```
Then inside your project directory call
```bash
composer require doertemithut/openapivalidator

```
Finally, get an openAPI3.0-compliant JSON file for your project to be checked against, name it `api_spec.json` and place it under 

```
config/api_spec.json
```

## use

**Note: this project is meant for me to familiarize myself with the package creation process and is in no way ready for as is production use. Continue at your own peril.**

To get some information about differences between your API and the provided specification, run

```bash
php artisan api:validate
```

## Limitations:
    
currently, the validation checks the following:
* check for existing and missing endpoints in your `api.php` file.
* check for existing and missing combinations of endpoints and http methods.
* check whether the controller class specified in the openAPI document via the `x-controllerClass`-property exists in your project
* check whether the controller class specified in the openAPI document via the `x-controllerClass`-property is linked to the appropriate endpoint as a handler
* check whether the controller class specified in the openAPI document via the `x-controllerClass`-property of the path opbject features a method as specified in the openAPI specification
* check whether the controller method specified in the openAPI document via the `x-controllerMethod`-property of the httpMethod object is linked to the appropriate combination of route and http method.

The following limitations exist:

* only routes defined explicitly via the following pattern are registered as route definitions: 

```regex
Route::<httpMethod>\('<route>', \[<x-controllerClass>::class, '<x-controllerMethod>'\]\);
```

* The `<route>` in the above pattern must be identical between the openapi specs and laravel's `api.php`.

* Only Controller classes from the `App\Http\Controllers` namespace are taken into account.

* Only the existence of the `x-controllerMethod`on the `x-controllerClass` gets checked. The method might do nothing, be untested or do something unintended by the specification and will nonetheless be counted.

## Planned Features
### compliance scan
* reach coverage of all registered routes regardless of their definition syntax
* respect an `.apivalidateignore` to exclude endpoints from being checked for or being counted.
* check for properties on model classes to comply with schemas defined in the api specs.
* check the database for compliance with the schema
### codegen
* generate missing models, controllers and migration stubs
* create missing route definitions on the `api.php`
* create missing methods on controller classes
* create tests for testing framework