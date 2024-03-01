<?php

namespace App\Console\Commands;

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


    private function indent($indentLevel, $value)
    {
        return str_repeat('  ', $indentLevel) . $value;
    }
    /**
     * Execute the console command.
     */
    public function handle()
    {

        $indentLevel = 0;
        $nErrors = 0;
        $nWarnings = 0;
        $ControllerNamespacePrefix = "App\\Http\\Controllers\\";
        $apifile = fopen("./routes/api.php", "r");
        $route_blocks = [];
        $usedControllers = [];
        $definedRoutes = [];
        $definedActions = [];
        $definedHandlers = [];
        $usePattern = "{use App\\\Http\\\Controllers\\\(.*Controller)}";
        $routePattern = "{Route::(post|get|update|delete)\('([^']*)', \[([a-zA-Z]*)::class, '([^']*)'\]\);}";
        if ($apifile) {
            $lineIndex = 0;
            $inBlock = false;
            while (($line = fgets($apifile)) !== false) {

                if (preg_match($routePattern, $line, $matches)) {
                    if (!$inBlock) {
                        $inBlock = true;
                        $this->line($this->indent($indentLevel, '<bg=yellow> starting Route Block</>'));
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
                    $this->line($this->indent($indentLevel, sprintf('<bg=blue> ROUTE </> <bg=green> %s %s</>', $httpMethod, $route)));
                    $this->line($this->indent($indentLevel, '<bg=blue>    CONTROLLER/METHOD </> ' . $controller . " / " . $controllerMethod));
                    $a = "App\\Http\\Controllers\\" . $controller;
                    $b = method_exists($a, $controllerMethod);
                    var_dump($b);

                    // array_push($usedControllers, $matches[1]);
                    continue;
                } else {
                    if ($inBlock && trim($line) != "") {
                        $inBlock = false;
                        $this->line($this->indent($indentLevel, '<bg=yellow> ending Route Block</>'));
                    }
                }

                if (preg_match($usePattern, $line, $matches)) {
                    $this->line($this->indent($indentLevel, '<bg=blue> USE </> ' . $matches[1]));
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
                $this->line($this->indent($indentLevel, '<bg=blue> CONTROLLER </> ' . $controllerFile));
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
            $this->line($this->indent($indentLevel, sprintf('<bg=gray>SPEC</> %s', $path)));
            $indentLevel++;
            if (in_array($path, $definedRoutes)) {
                $this->line($this->indent($indentLevel, sprintf('<bg=green>ROUTE DEFINED</> %s', $path)));
                $indentLevel++;
                foreach ($pathspec as $httpMethod => $actionspec) {
                    if (in_array($httpMethod, $definedActions[$path])) {
                        $this->line($this->indent($indentLevel, sprintf('<bg=green>METHOD DEFINED</> %s %s', $httpMethod, $path)));
                        if (array_key_exists('x-controllerClass', $actionspec)) {
                            //method level
                            $indentLevel++;
                            $specControllerClass = $actionspec['x-controllerClass'];
                            if (class_exists($ControllerNamespacePrefix.$specControllerClass)) {
                                $this->line($this->indent($indentLevel, sprintf('<bg=green>CONTROLLER CLASS EXISTS</> %s', $specControllerClass)));
                            } else {
                                $this->line($this->indent($indentLevel, sprintf('<bg=red>CONTROLLER CLASS MISSING</> %s', $specControllerClass)));
                            }
                            if (array_key_exists('x-controllerMethod', $actionspec)) {
                                $indentLevel++;
                                $specControllerMethod = $actionspec['x-controllerMethod'];
                                if (method_exists($ControllerNamespacePrefix.$specControllerClass,$specControllerMethod)) {
                                    $this->line($this->indent($indentLevel, sprintf('<bg=green>CONTROLLER CLASS HAS METHOD</> %s', $specControllerMethod)));
                                } else {
                                    $this->line($this->indent($indentLevel, sprintf('<bg=red>CONTROLLER MISSES METHOD</> %s', $specControllerMethod)));
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
                        $this->line($this->indent($indentLevel, sprintf('<bg=red>METHOD UNDEFINED</> %s %s', $httpMethod, $path)));
                        $nErrors++;
                    }
                }
                $indentLevel--;
            } 
            else {
                $this->line($this->indent($indentLevel, sprintf('<bg=red>ROUTE UNDEFINED</> %s', $path)));
                $nErrors++;
            }
            $indentLevel--;
        }
        $indentLevel=0;
        $this->line($this->indent($indentLevel,sprintf('<fg=red>%d</> errors | <fg=yellow>%d</> warnings',$nErrors,$nWarnings)));
    }
}
