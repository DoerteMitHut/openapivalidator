<?php

namespace doertemithut\openapivalidator;

use Illuminate\Console\Command;
use App\Http\Controllers;

class ValidateAPI extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:validate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * produce the appropriate indent for output 
     */
    private function indent($indentLevel, $value)
    {
        return str_repeat('  ', $indentLevel) . $value;
    }

    private function lineOut($value,$indent=0){
        $this->line($this->indent($indent, $value));
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        define("CONTROLLER_USE_PATTERN","{use App\\\Http\\\Controllers\\\(.*Controller)}");
        define("ROUTE_DEFINITION_PATTERN", "{Route::(post|get|update|delete)\('([^']*)', \[([a-zA-Z]*)::class, '([^']*)'\]\);}");
        define("CONTROLLER_NAMESPACE_PREFIX", "App\\Http\\Controllers\\");
        define("API_FILE_PATH","./routes/api.php");


        $nErrors = 0;
        $nWarnings = 0;
        
        $indentLevel = 0;
        $apifile = fopen(API_FILE_PATH, "r");
        $route_blocks = [];
        $usedControllers = [];
        $definedRoutes = [];
        $definedActions = [];
        $definedHandlers = [];
        if ($apifile) {
            $lineIndex = 0;
            $inBlock = false;
            while (($line = fgets($apifile)) !== false) {

                if (preg_match($ROUTE_DEFINITION_PATTERN, $line, $matches)) {
                    if (!$inBlock) {
                        $inBlock = true;
                        $this->lineOut($indentLevel, '<bg=yellow> starting Route Block</>'));
                    }
                    $httpMethod = $matches[1];
                    $route = $matches[2];
                    $controller = $matches[3];
                    $controllerMethod = $matches[4];
                    array_push($definedRoutes, $route);
                    if (!array_key_exists($route, $definedActions)) {
                        $definedActions[$route] = [$httpMethod];
                    } else {
                        array_push($definedActions[$route], $httpMethod);
                    }
                    if (!array_key_exists($httpMethod . "|" . $route, $definedHandlers)) {
                        $definedHandlers[$httpMethod . "|" . $route] = [["controllerClass" => $controller, "controllerMethod" => $controllerMethod]];
                    } else {
                        array_push($definedHandlers[$httpMethod . "|" . $route], ["controllerClass" => $controller, "controllerMethod" => $controllerMethod]);
                    }
                    $this->lineOut($indentLevel, sprintf('<bg=blue> ROUTE </> <bg=green> %s %s</>', $httpMethod, $route)));
                    $this->lineOut($indentLevel, '<bg=blue>    CONTROLLER/METHOD </> ' . $controller . " / " . $controllerMethod));
                    $a = "App\\Http\\Controllers\\" . $controller;
                    $b = method_exists($a, $controllerMethod);
                    var_dump($b);

                    // array_push($usedControllers, $matches[1]);
                    continue;
                } else {
                    if ($inBlock && trim($line) != "") {
                        $inBlock = false;
                        $this->lineOut($indentLevel, '<bg=yellow> ending Route Block</>'));
                    }
                }

                if (preg_match($CONTROLLER_USE_PATTERN, $line, $matches)) {
                    $this->lineOut($indentLevel, '<bg=blue> USE </> ' . $matches[1]));
                    array_push($usedControllers, $matches[1]);
                }
                $lineIndex++;
            }

            fclose($apifile);
        }

        // find controller files
        $controllerFiles = scandir("./app/Http/Controllers");
        foreach ($controllerFiles as $controllerFile) {
            if (str_ends_with($controllerFile, 'Controller.php')) {
                $this->lineOut($indentLevel, '<bg=blue> CONTROLLER </> ' . $controllerFile));
            }
        }




        // Read the openAPI JSON file  
        $openAPIFile = file_get_contents('./config/api_spec.json');
        // var_dump($openAPIFile);
        // Decode the JSON file 
        $specs = json_decode($openAPIFile, true);
        // var_dump($specs);
        foreach ($specs['paths'] as $path => $pathspec) {
            $indentLevel = 0;
            $this->lineOut($indentLevel, sprintf('<bg=gray>SPEC</> %s', $path)));
            $indentLevel++;
            if (in_array($path, $definedRoutes)) {
                $this->lineOut($indentLevel, sprintf('<bg=green>ROUTE DEFINED</> %s', $path)));
                $indentLevel++;
                foreach ($pathspec as $httpMethod => $actionspec) {
                    if (in_array($httpMethod, $definedActions[$path])) {
                        $this->lineOut($indentLevel, sprintf('<bg=green>METHOD DEFINED</> %s %s', $httpMethod, $path)));
                        if (array_key_exists('x-controllerClass', $actionspec)) {
                            //method level
                            $indentLevel++;
                            $specControllerClass = $actionspec['x-controllerClass'];
                            if (class_exists($CONTROLLER_NAMESPACE_PREFIX.$specControllerClass)) {
                                $this->lineOut($indentLevel, sprintf('<bg=green>CONTROLLER CLASS EXISTS</> %s', $specControllerClass)));
                            } else {
                                $this->lineOut($indentLevel, sprintf('<bg=red>CONTROLLER CLASS MISSING</> %s', $specControllerClass)));
                            }
                            if (array_key_exists('x-controllerMethod', $actionspec)) {
                                $indentLevel++;
                                $specControllerMethod = $actionspec['x-controllerMethod'];
                                if (method_exists($CONTROLLER_NAMESPACE_PREFIX.$specControllerClass,$specControllerMethod)) {
                                    $this->lineOut($indentLevel, sprintf('<bg=green>CONTROLLER CLASS HAS METHOD</> %s', $specControllerMethod)));
                                } else {
                                    $this->lineOut($indentLevel, sprintf('<bg=red>CONTROLLER MISSES METHOD</> %s', $specControllerMethod)));
                                }
                                $indentLevel--;
                            }else{
                                $nWarnings++;
                            }
                            $indentLevel--;
                        }
                        else{
                            $nWarnings++;
                        }
                    } 
                    else {
                        $this->lineOut($indentLevel, sprintf('<bg=red>METHOD UNDEFINED</> %s %s', $httpMethod, $path)));
                        $nErrors++;
                    }
                }
                $indentLevel--;
            } 
            else {
                $this->lineOut($indentLevel, sprintf('<bg=red>ROUTE UNDEFINED</> %s', $path)));
                $nErrors++;
            }
            $indentLevel--;
        }
        $indentLevel=0;
        $this->lineOut($indentLevel,sprintf('Finished: <fg=red>%d</> errors | <fg=yellow>%d</> warnings',$nErrors,$nWarnings)));
    }
}
