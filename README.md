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

The following limitations occur:

* only routes defined explicitly via the pattern 
```regex
Route::<httpMethod>\('<route>', \[<x-controllerClass>::class, '<x-controllerMethod>'\]\);
```
are registered as rute definitions.

## Planned Features