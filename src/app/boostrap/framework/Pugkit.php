<?php

/**
 *@PugKit The Mini PHP Framework
 *@create by Pug
 *@create version v0.1
 */

namespace PugKit\Builder {

    use PugKit\DI\Contianer;
    use PugKit\DI\ContinerIneterface;
    use PugKit\Router\RouterInterface;
    use PugKit\Router\RouterCore;

    interface ApplicationInterface
    {
        public static function concreate(): self;
        public function useRouterCore(ContinerIneterface $container): RouterInterface;
    }

    /**
     * Concreate App Builder classes
     */
    class Application implements ApplicationInterface
    {
        private static RouterInterface $router;
        private static ContinerIneterface $continer;

        private function __construct() {}

        public static function concreate(): self
        {
            self::$router = new RouterCore();
            self::$continer = new Contianer();
            return new self;
        }

        public function useRouterCore(ContinerIneterface $container): RouterInterface
        {
            self::$router->setContainer($container);
            return self::$router;
        }

        public function useContianer(): ContinerIneterface
        {
            return self::$continer;
        }
    }
}

namespace PugKit\BackendEnums {

    class Http
    {
        public const Get = "GET";
        public const Post = "POST";
        public const Put = "PUT";
        public const Delete = "DELETE";
    }
}

namespace PugKit\Router {

    use Closure;
    use Exception;
    use PugKit\BackendEnums\Http;
    use PugKit\DI\ContinerIneterface;
    use PugKit\Response\JsonResponse;

    interface RouterInterface
    {
        public function setContainer(ContinerIneterface $container): void;
        public function group(string $prefix, callable $callback): void;
        public function get(string $pattern, callable|array $handler, array $middlewares = []): void;
        public function post(string $pattern, callable|array $handler, array $middlewares = []): void;
        public function put(string $pattern, callable|array $handler, array $middlewares = []): void;
        public function delete(string $pattern, callable|array $handler, array $middlewares = []): void;
        public function dispatch(string $uri): void;
    }

    interface RouteGroupInterface extends RouterInterface {}

    class RouterCore implements RouteGroupInterface
    {
        /**
         * @var array
         */
        private $buildingContainer = [];

        private array $routes = [];
        private string $prefix = "";

        public function setContainer(ContinerIneterface $container): void
        {
            echo "<pre>";
            print_r($container);
        }

        public function group(string $prefix, callable $callback): void
        {
            $previousPrefix = $this->prefix;
            $this->prefix .= $prefix;
            $callback($this);
            $this->prefix = $previousPrefix;
        }
        public function get(string $pattern, callable|array $handler, array $middlewares = []): void
        {
            $this->addMethod(Http::Get);
            $this->add($pattern, $handler, $middlewares);
        }

        public function post(string $pattern, callable|array $handler, array $middlewares = []): void
        {
            $this->addMethod(Http::Post);
            $this->add($pattern, $handler, $middlewares);
        }

        public function put(string $pattern, callable|array $handler, array $middlewares = []): void
        {
            $this->addMethod(Http::Put);
            $this->add($pattern, $handler, $middlewares);
        }

        public function delete(string $pattern, callable|array $handler, array $middlewares = []): void
        {
            $this->addMethod(Http::Delete);
            $this->add($pattern, $handler, $middlewares);
        }

        public function dispatch(string $uri): void
        {
            try {
                foreach ($this->routes as $route) {
                    if (preg_match($route["regex"], $uri, $matches)) {
                        array_shift($matches);
                        $params = array_combine($route["paramNames"], $matches);
                        $next = function () use ($route, $params) {
                            if (is_array($route["handler"])) {
                                $this->handlerController($route["handler"], $params);
                            }

                            if (is_object($route["handler"])) {
                                call_user_func_array($route["handler"], $params);
                            }
                        };

                        $middlewareChain = array_reverse(!empty($route["middlewares"]) ? $route["middlewares"] : []);
                        foreach ($middlewareChain as $middleware) {
                            $current = $next;
                            $next = function () use ($middleware, $params, $current): Closure {
                                return $middleware($params, $current);
                            };
                        }

                        $next();
                        return;
                    }
                }

                throw new Exception("404 Not Found", 404);
            } catch (Exception $e) {
                http_response_code($e->getCode());
                error_log($e->getMessage());
                echo json_encode(new JsonResponse([], $e->getMessage(), $e->getCode()), JSON_PRETTY_PRINT);
                exit;
            }
        }

        private function convertToRegex(string $pattern, ?array &$paramNames = []): string
        {
            $paramNames = [];
            $regex = preg_replace_callback("#\{(\w+)\}#", function ($matches) use (&$paramNames) {
                $paramNames[] = $matches[1];
                return "([^/]+)";
            }, $pattern);
            return "#^" . $regex . "$#";
        }

        private function add(string $pattern, callable|array $handler, array $middlewares = []): void
        {
            $this->routes[] = [
                "regex" => $this->convertToRegex($this->prefix . $pattern, $paramNames),
                "paramNames" => $paramNames,
                "handler" => $handler,
                "middlewares" => $middlewares,
            ];
        }

        private function addMethod(string $method): void
        {
            if ($_SERVER["REQUEST_METHOD"] !== $method) {
                throw new Exception("Invalid HTTP method. Expected {$method}", 405);
            }
        }

        private function handlerController(array $actionHandler, array $params): mixed
        {
            list($controllerClass, $methodName) = $actionHandler;

            if (!class_exists($controllerClass)) {
                throw new Exception("Controller {$controllerClass} not found.");
            }

            $controller = new $controllerClass(); // constructor ...params
            if (!method_exists($controller, $methodName)) {
                throw new Exception("Method {$methodName} not found in controller {$controllerClass}.");
            }

            $params = count($params) ? $params : [];

            return call_user_func_array([$controller, $methodName], $params);
        }
    }
}

namespace PugKit\DI {

    use Exception;

    use function PugKit\Helper\str_or_object;

    interface ContinerIneterface
    {
        /**
         * @param string $container
         * @param callable $fn
         * @return void
         */
        public function set($container, $fn);

        /**
         * @param string $container
         * @return boolean
         */
        public function has($container);

        /**
         * @param string $container
         * @return mixed
         * @throws Exception
         */
        public function get($container);

        /**
         * @param string $interface
         * @return object
         * @throws Exception
         */
        public function repository($interface);

        /**
         * @param string $interface
         * @return object
         * @throws Exception
         */
        public function service($interface);
    }

    class Contianer implements ContinerIneterface
    {
        /**
         * @var array
         */
        private $building = [];

        /** 
         * @param string $container
         * @param callable $fn
         * @return void
         */
        public function set($container, $fn)
        {
            $this->building[$container] = $fn->bindTo($this, $this);
        }

        /**
         * @param string $container
         * @return boolean
         */
        public function has($container)
        {
            return !empty($this->building[$container]) ? true : false;
        }

        /**
         * @param string $container
         * @return mixed
         */
        public function get($container)
        {
            if (isset($this->building[$container])) {
                return ($this->building[$container])();
            }
            throw new Exception("Container not found: {$container}");
        }

        /**
         * @param string $interface
         * @param string $type
         * @return object
         * @throws Exception
         */
        private function getInstance($interface, $type)
        {
            if (interface_exists($interface) && strpos($interface, ucfirst($type)) !== false) {
                if (!empty($this->building[$type])) {
                    $instance = $this->building[$type]()[$interface];
                    return str_or_object($instance);
                }
                throw new Exception(ucfirst($type) . " not found: {$interface}");
            }
            throw new Exception("Invalid interface or missing '{$type}' keyword: {$interface}");
        }

        /**
         * @param string $interface
         * @return object
         */
        public function repository($interface)
        {
            return $this->getInstance($interface, "repository");
        }

        /**
         * @param string $interface
         * @return object
         */
        public function service($interface)
        {
            return $this->getInstance($interface, "service");
        }
    }
}

namespace PugKit\DotENV {

    interface DotEnvEnvironmentInterface
    {
        /**
         * @param string $path
         * @return DotEnvEnvironmentInterface
         */
        public function load(string $path): DotEnvEnvironmentInterface;

        /**
         * @return array
         */
        public function all(): array;

        /**
         * @param string $key
         * @return mixed
         */
        public function key(string $key): mixed;
    }

    class DotEnvEnvironment implements DotEnvEnvironmentInterface
    {
        /**
         * @var array
         */
        private $allEnvSetting = [];
        /**
         * @param string $path
         * @return DotEnvEnvironmentInterface
         */
        public function load(string $path): DotEnvEnvironmentInterface
        {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            foreach ($lines as $line) {
                $line = trim($line);

                if (empty($line) || strpos($line, "#") === 0) {
                    continue;
                }

                $parts = explode("=", $line, 2);
                if (count($parts) < 2) {
                    continue;
                }

                list($key, $value) = $parts;
                $key = trim($key);
                $value = trim($value);

                putenv(sprintf("%s=%s", $key, $value));
                $_ENV[$key] = $value;

                $this->allEnvSetting[$key] = $value;
            }

            return $this;
        }

        /**
         * @return array
         */
        public function all(): array
        {
            return $this->allEnvSetting;
        }

        /**
         * @param string $key
         * @return mixed
         */
        public function key(string $key): mixed
        {
            return $this->allEnvSetting[$key];
        }
    }
}

namespace PugKit\Response {

    use JsonSerializable;

    class JsonResponse implements JsonSerializable
    {
        private array|object $data;
        private string $message;
        private int $status;

        public function __construct(array|object $data, string $message, int $status)
        {
            $this->data = $data;
            $this->message = $message;
            $this->status = $status;
        }

        public function jsonSerialize(): array
        {
            return [
                "data" => $this->data,
                "message" => $this->message,
                "status" => $this->status
            ];
        }
    }
}

namespace PugKit\Helper {

    use InvalidArgumentException;
    use stdClass;

    if (!function_exists("conv_toobj")) {
        function conv_toobj(mixed $data): mixed
        {
            if (is_array($data)) {
                $isAssoc = array_keys($data) !== range(0, count($data) - 1);
                if ($isAssoc) {
                    $obj = new stdClass();
                    foreach ($data as $key => $value) {
                        $obj->{$key} = conv_toobj($value);
                    }
                    return $obj;
                } else {
                    return array_map("conv_toobj", $data);
                }
            }

            return $data;
        }
    }

    if (!function_exists("context_xss")) {

        function context_xss($input): string
        {
            $input = trim($input);
            $input = strip_tags($input);
            $input = htmlspecialchars($input, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
            return $input;
        }
    }

    if (!function_exists("arr_upr")) {

        function arr_upr($input, $case = MB_CASE_TITLE): array
        {
            $convToCamel = function ($str) {
                return str_replace(" ", "", ucwords(str_replace("_", " ", $str)));
            };

            if (is_object($input)) {
                $input = json_decode(json_encode($input), true);
            }

            $newArray = array();
            foreach ($input as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    $newArray[$convToCamel($key)] = arr_upr($value, $case);
                } else {
                    $newArray[$convToCamel($key)] = $value;
                }
            }
            return $newArray;
        }
    }

    if (!function_exists("csrf_web")) {

        function csrf_web(): string
        {
            return bin2hex(random_bytes(32));
        }
    }

    if (!function_exists("str_or_object")) {

        function str_or_object($instance)
        {
            if (is_string($instance)) {
                if (class_exists($instance)) {
                    return new $instance();
                } else {
                    throw new InvalidArgumentException("Class $instance does not exist.");
                }
            }

            if (is_object($instance)) {
                return $instance;
            }

            throw new InvalidArgumentException("Argument must be a string or an object.");
        }
    }
}
