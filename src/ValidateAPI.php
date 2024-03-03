<?php

namespace DoerteMitHut\OpenAPIValidator;

use Illuminate\Console\Command;
use App\Http\Controllers;
use App\Models;

class ValidateAPI extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:validate';
    protected static $inBlock = false;
    protected static $nErrors = 0;
    protected static $nWarnings = 0;
    protected static $indentLevel = 0;
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * produce the appropriate indent for output 
     */
    private function indent($value)
    {
        return str_repeat('  ',self::$indentLevel) . $value;
    }

    private function lineOut($value, $indent = 0)
    {
        $this->line($this->indent($indent, $value));
    }

    private function pushToArray(&$array, $key, $value)
    {
        if (!array_key_exists($key, $array)) {
            $array[$key] = [$value];
        } else {
            array_push($array[$key], $value);
        }
    }

    private function processRouteMatches($matches)
    {
        return [
            "httpMethod" => $matches[1],
            "route" => $matches[2],
            "controller" => $matches[3],
            "controllerMethod" => $matches[4]
        ];
    }

    private function enterRouteBlock($index)
    {
        self::$inBlock = true;
        $this->lineOut('<bg=yellow> starting Route Block</>');
    }

    private function exitRouteBlock($index)
    {
        self::$inBlock = false;
        $this->lineOut('<bg=yellow> ending Route Block</>');
    }

    static private function generateOutput(){

    }

    private function readRoutes(&$usedControllers,&$definedRoutes,&$definedActions,&$definedHandlers){
        $usedControllers = [];
        $definedRoutes = [];
        $definedActions = [];
        $definedHandlers = [];

        $apifile = fopen(API_FILE_PATH, "r");
        if ($apifile) {
            $lineIndex = 0;
            self::$inBlock = false;
            // parse api.php line by line
            while (($line = fgets($apifile)) !== false) {
                if (preg_match(ROUTE_DEFINITION_PATTERN, $line, $matches)) {
                    // line matches route definition
                    if (!self::$inBlock) {
                        $this->enterRouteBlock($lineIndex);
                    }
                    $route_definition_line = $this->processRouteMatches($matches);
                    array_push(
                        $definedRoutes,
                        $route_definition_line['route']
                    );
                    array_push(
                        $usedControllers,
                        $route_definition_line["controller"]
                    );
                    $this->pushToArray(
                        $definedActions,
                        $route_definition_line['route'],
                        $route_definition_line["httpMethod"]
                    );
                    $this->pushToArray(
                        $definedHandlers,
                        sprintf('%s|%s', $route_definition_line["httpMethod"], $route_definition_line['route']),
                        [
                            "controllerClass" => $route_definition_line["controller"],
                            "controllerMethod" => $route_definition_line["controllerMethod"]
                        ]
                    );

                    $this->lineOut(sprintf('<bg=blue> ROUTE </> <bg=green> %s %s</>', $route_definition_line["httpMethod"], $route_definition_line['route']));
                    $this->lineOut('<bg=blue> CONTROLLER/METHOD </> ' . $route_definition_line["controller"] . " / " . $route_definition_line["controllerMethod"]);

                    continue;
                } else {
                    if (self::$inBlock && trim($line) != "") {
                        $this->exitRouteBlock($lineIndex);
                    }
                }

                if (preg_match(CONTROLLER_USE_PATTERN, $line, $matches)) {
                    $this->lineOut('<bg=blue> USE </> ' . $matches[1]);
                    array_push($usedControllers, $matches[1]);
                }
                $lineIndex++;
            }
            fclose($apifile);
        }
    }

    private function checkAPISpec($api_spec_path,&$usedControllers,&$definedRoutes,&$definedActions,&$definedHandlers){
        $openAPIFile = file_get_contents($api_spec_path);
        // var_dump($openAPIFile);
        // Decode the JSON file
        if (!$openAPIFile) {
            $this->lineOut("<bg=red> no openAPI configuration file</>");
            return;
        }
        $specs = json_decode($openAPIFile, true);
        if (!$specs) {
            $this->lineOut("<bg=red> openAPI configuration file could not be decoded</>");
            return;
        }
        if (!array_key_exists("paths", $specs)) {
            $this->lineOut("<bg=red> no 'paths' in openAPI configuration file</>");
            return;
        }
        // var_dump($specs);
        foreach ($specs['paths'] as $path => $pathspec) {
            self::$indentLevel = 0;
            $this->lineOut(sprintf('<bg=gray>SPEC</> %s', $path));
            self::$indentLevel++;
            if (in_array($path, $definedRoutes)) {
                $this->lineOut(sprintf('<bg=green>ROUTE DEFINED</> %s', $path));
                self::$indentLevel++;
                foreach ($pathspec as $httpMethod => $actionspec) {
                    if (in_array($httpMethod, $definedActions[$path])) {
                        $this->lineOut(sprintf('<bg=green>METHOD DEFINED</> %s %s', $httpMethod, $path));
                        if (array_key_exists('x-controllerClass', $actionspec)) {
                            //method level
                            self::$indentLevel++;
                            $specControllerClass = $actionspec['x-controllerClass'];
                            if (class_exists(CONTROLLER_NAMESPACE_PREFIX . $specControllerClass)) {
                                $this->lineOut(sprintf('<bg=green>CONTROLLER CLASS EXISTS</> %s', $specControllerClass));
                            } else {
                                $this->lineOut(sprintf('<bg=red>CONTROLLER CLASS MISSING</> %s', $specControllerClass));
                                self::$nErrors++;
                            }

                            if (($definedHandlers[sprintf('%s|%s', $httpMethod, $path)]["controllerClass"] ?? "") == $specControllerClass) {
                                $this->lineOut(sprintf('<bg=green>CONTROLLER CLASS SET AS HANDLER</> %s', $specControllerClass));
                            } else {
                                $this->lineOut(sprintf('<bg=red>CONTROLLER CLASS NOT SET AS HANDLER</> %s', $specControllerClass));
                                self::$nErrors++;
                            }


                            if (array_key_exists('x-controllerMethod', $actionspec)) {
                                self::$indentLevel++;
                                $specControllerMethod = $actionspec['x-controllerMethod'];
                                if (method_exists(CONTROLLER_NAMESPACE_PREFIX . $specControllerClass, $specControllerMethod)) {
                                    $this->lineOut(sprintf('<bg=green>CONTROLLER CLASS HAS METHOD</> %s', $specControllerMethod));
                                } else {
                                    $this->lineOut(sprintf('<bg=red>CONTROLLER MISSES METHOD</> %s', $specControllerMethod));
                                    self::$nErrors++;
                                }
                                if (($definedHandlers[sprintf('%s|%s', $httpMethod, $path)]["controllerMethod"] ?? "") == $specControllerMethod) {
                                    $this->lineOut(sprintf('<bg=green>CONTROLLER METHOD SET AS HANDLER</> %s', $specControllerMethod));
                                } else {
                                    $this->lineOut(sprintf('<bg=red>CONTROLLER METHOD NOT SET AS HANDLER</> %s', $specControllerMethod));
                                    self::$nErrors++;
                                }
                                self::$indentLevel--;
                            } else {
                                self::$nWarnings++;
                            }
                            self::$indentLevel--;
                        } else {
                            self::$nWarnings++;
                        }
                    } else {
                        $this->lineOut(sprintf('<bg=red>METHOD UNDEFINED</> %s %s', $httpMethod, $path));
                        self::$nErrors++;
                    }
                }
                self::$indentLevel--;
            } else {
                $this->lineOut(sprintf('<bg=red>ROUTE UNDEFINED</> %s', $path));
                self::$nErrors++;
            }
            self::$indentLevel--;
        }

        foreach ($specs['components']['schemas'] as $schemaIdentifier => $schema) {
            $this->lineOut(sprintf("<bg=gray>%s</>", $schemaIdentifier));
            if (array_key_exists("x-eloquentModel", $schema)) {
                if (class_exists(MODEL_NAMESPACE_PREFIX . $schema['x-eloquentModel'])) {
                    $this->lineOut(sprintf("<bg=green> MODEL CLASS EXISTS </> %s", $schema['x-eloquentModel']));
                } else {
                    $this->lineOut(sprintf("<bg=red> MODEL CLASS MISSING </> %s", $schema['x-eloquentModel']));
                    self::$nErrors++;
                }
            }
            foreach ($schema['properties'] as $property => $propertyObject) {
                $this->lineOut(sprintf("<bg=gray>%s</>", $property), 2);
            }
        }
    }
    
    /**
     * Execute the console command.
     */
    public function handle()
    {
        define("CONTROLLER_USE_PATTERN", "{use App\\\Http\\\Controllers\\\(.*Controller)}");
        define("ROUTE_DEFINITION_PATTERN", "{Route::(post|get|update|delete)\('([^']*)', \[([a-zA-Z]*)::class, '([^']*)'\]\);}");
        define("CONTROLLER_NAMESPACE_PREFIX", "App\\Http\\Controllers\\");
        define("MODEL_NAMESPACE_PREFIX", "App\\Models\\");
        define("API_FILE_PATH", "./routes/api.php");
        define("API_SPEC_PATH", "./config/api_spec.json");
        
        $this->readRoutes($usedControllers,$definedRoutes,$definedActions,$definedHandlers,self::$indentLevel);

        $this->checkAPISpec(API_SPEC_PATH, $usedControllers,$definedRoutes,$definedActions,$definedHandlers,self::$indentLevel);
        
        self::$indentLevel = 0;
        $this->lineOut(sprintf('Finished: <fg=red>%d</> errors | <fg=yellow>%d</> warnings', self::$nErrors, self::$nWarnings));
    }
}
