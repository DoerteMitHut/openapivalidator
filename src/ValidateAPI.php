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
    protected static $inBlock = false;
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

    private function pushToArray(&$array, $key, $value){
        if (!array_key_exists($key, $array)) {
            $array[$key] = [$value];
        } else {
            array_push($array[$key], $value);
        }
    } 

    private function processRouteMatches($matches){
        return[
            "httpMethod" => $matches[1],
            "route" => $matches[2],
            "controller" => $matches[3],
            "controllerMethod" => $matches[4]
        ];
    }

    private function enterRouteBlock($index){
        self::$inBlock = true;
        $this->lineOut('<bg=yellow> starting Route Block</>');
    }

    private function exitRouteBlock($index){
        self::$inBlock = false;
        $this->lineOut('<bg=yellow> ending Route Block</>');
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
                        $route_definition_line['route']);
                    array_push(
                        $usedControllers,
                        $route_definition_line["controller"]);
                    $this->pushToArray(
                        $definedActions,
                        $route_definition_line['route'],
                        $route_definition_line["httpMethod"]);
                    $this->pushToArray(
                        $definedHandlers,
                        sprintf('%s|%s',$route_definition_line["httpMethod"],$route_definition_line['route']),
                        [
                            "controllerClass" => $route_definition_line["controller"],
                            "controllerMethod" => $route_definition_line["controllerMethod"]
                        ]);

                    $this->lineOut(sprintf('<bg=blue> ROUTE </> <bg=green> %s %s</>', $route_definition_line["httpMethod"], $route_definition_line['route'], $indentLevel));
                    $this->lineOut('<bg=blue> CONTROLLER/METHOD </> ' . $route_definition_line["controller"] . " / " . $route_definition_line["controllerMethod"],$indentLevel);

                    continue;
                } 
                else {
                    if (self::$inBlock && trim($line) != "") {
                        $this->exitRouteBlock($lineIndex);
                    }
                }

                if (preg_match(CONTROLLER_USE_PATTERN, $line, $matches)) {
                    $this->lineOut('<bg=blue> USE </> ' . $matches[1], $indentLevel);
                    array_push($usedControllers, $matches[1]);
                }
                $lineIndex++;
            }
            fclose($apifile);
        }

        // Read the openAPI JSON file  
        $openAPIFile = file_get_contents('./config/api_spec.json');
        // var_dump($openAPIFile);
        // Decode the JSON file 
        $specs = json_decode($openAPIFile, true);
        // var_dump($specs);
        foreach ($specs['paths'] as $path => $pathspec) {
            $indentLevel = 0;
            $this->lineOut( sprintf('<bg=gray>SPEC</> %s', $path, $indentLevel));
            $indentLevel++;
            if (in_array($path, $definedRoutes)) {
                $this->lineOut( sprintf('<bg=green>ROUTE DEFINED</> %s', $path, $indentLevel));
                $indentLevel++;
                foreach ($pathspec as $httpMethod => $actionspec) {
                    if (in_array($httpMethod, $definedActions[$path])) {
                        $this->lineOut( sprintf('<bg=green>METHOD DEFINED</> %s %s', $httpMethod, $path, $indentLevel));
                        if (array_key_exists('x-controllerClass', $actionspec)) {
                            //method level
                            $indentLevel++;
                            $specControllerClass = $actionspec['x-controllerClass'];
                            if (class_exists(CONTROLLER_NAMESPACE_PREFIX.$specControllerClass)) {
                                $this->lineOut( sprintf('<bg=green>CONTROLLER CLASS EXISTS</> %s', $specControllerClass, $indentLevel));
                            } else {
                                $this->lineOut( sprintf('<bg=red>CONTROLLER CLASS MISSING</> %s', $specControllerClass, $indentLevel));
                                $nErrors++;
                            }
                            
                            if (($definedHandlers[sprintf('%s|%s',$httpMethod,$path)]["controllerClass"] ?? "") == $specControllerClass) {
                                $this->lineOut( sprintf('<bg=green>CONTROLLER CLASS SET AS HANDLER</> %s', $specControllerClass, $indentLevel));
                            } else {
                                $this->lineOut( sprintf('<bg=red>CONTROLLER CLASS NOT SET AS HANDLER</> %s', $specControllerClass, $indentLevel));
                                $nErrors++;
                            }


                            if (array_key_exists('x-controllerMethod', $actionspec)) {
                                $indentLevel++;
                                $specControllerMethod = $actionspec['x-controllerMethod'];
                                if (method_exists(CONTROLLER_NAMESPACE_PREFIX.$specControllerClass,$specControllerMethod)) {
                                    $this->lineOut( sprintf('<bg=green>CONTROLLER CLASS HAS METHOD</> %s', $specControllerMethod, $indentLevel));
                                } else {
                                    $this->lineOut( sprintf('<bg=red>CONTROLLER MISSES METHOD</> %s', $specControllerMethod, $indentLevel));
                                    $nErrors++;
                                }
                                if (($definedHandlers[sprintf('%s|%s',$httpMethod,$path)]["controllerMethod"] ?? "") == $specControllerMethod) {
                                    $this->lineOut( sprintf('<bg=green>CONTROLLER METHOD SET AS HANDLER</> %s', $specControllerMethod, $indentLevel));
                                } else {
                                    $this->lineOut( sprintf('<bg=red>CONTROLLER METHOD NOT SET AS HANDLER</> %s', $specControllerMethod, $indentLevel));
                                    $nErrors++;
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
                        $this->lineOut( sprintf('<bg=red>METHOD UNDEFINED</> %s %s', $httpMethod, $path, $indentLevel));
                        $nErrors++;
                    }
                }
                $indentLevel--;
            } 
            else {
                $this->lineOut( sprintf('<bg=red>ROUTE UNDEFINED</> %s', $path, $indentLevel));
                $nErrors++;
            }
            $indentLevel--;
        }
        $indentLevel=0;
        $this->lineOut(sprintf('Finished: <fg=red>%d</> errors | <fg=yellow>%d</> warnings',$nErrors,$nWarnings, $indentLevel));
    }
}
