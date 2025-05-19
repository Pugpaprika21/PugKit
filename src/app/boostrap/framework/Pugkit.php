<?php

/**
 *@PugKit The Mini PHP Framework
 *@create by Pug
 *@create version v0.1
 */

namespace PugKit\Builder {

    use PugKit\DI\Container;
    use PugKit\DI\ContainerIneterface;
    use PugKit\Router\RouterInterface;
    use PugKit\Router\RouterCore;

    interface ApplicationInterface
    {
        public static function concreate(): self;
        public function useRouterCore(): RouterInterface;
    }

    /**
     * Concreate App Builder classes
     */
    class Application implements ApplicationInterface
    {
        private static RouterInterface $router;
        private static ContainerIneterface $container;

        private function __construct() {}

        public static function concreate(): self
        {
            self::$router = new RouterCore();
            self::$container = new Container();
            return new self;
        }

        public function useRouterCore(): RouterInterface
        {
            return self::$router;
        }

        public function useContianer(): ContainerIneterface
        {
            return self::$container;
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

        public const Multipart = "FILES";
        public const Json = "JSON";

        public const CodeNotFound = 404;
        public const MethodNotAllowed = 405;
    }

    class ErrCode
    {
        public const APP  = 500;
    }
}

namespace PugKit\Router {

    use Closure;
    use Exception;
    use PugKit\BackendEnums\ErrCode;
    use PugKit\BackendEnums\Http;
    use PugKit\Request\Request;
    use PugKit\Response\ResponseHandler;

    interface RouterInterface
    {
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
        private array $routes = [];
        private string $prefix = "";

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
                                ResponseHandler::IfRespHandlerEchoValue($this->handlerController($route["handler"], $params));
                            }

                            if (is_object($route["handler"])) {
                                ResponseHandler::IfRespHandlerEchoValue($this->handlerFunc($route, $params));
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

                throw new Exception("404 Not Found", Http::CodeNotFound);
            } catch (Exception $e) {
                header("Content-type: application/json");

                error_log($e->getMessage());
                echo json_encode(["data" => null, "message" => $e->getMessage(), "error_line" => $e->getLine(), "code" => $e->getCode()], JSON_PRETTY_PRINT);
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
                throw new Exception("Invalid HTTP method. Expected {$method}", Http::MethodNotAllowed);
            }
        }

        private function handlerFunc(array $actionHandler, array $params)
        {
            return call_user_func_array($actionHandler["handler"], $params);
        }

        private function handlerController(array $actionHandler, array $params): mixed
        {
            list($controllerClass, $methodName) = $actionHandler;

            if (!class_exists($controllerClass)) {
                throw new Exception("Controller {$controllerClass} not found.", ErrCode::APP);
            }

            global $container;
            $request = Request::createFromGlobals();

            $constructorArgs = [$container];
            $controller = new $controllerClass(...$constructorArgs);

            if (!method_exists($controller, $methodName)) {
                throw new Exception("Method {$methodName} not found in controller {$controllerClass}.", ErrCode::APP);
            }

            $params = count($params) ? $params : [];
            $methodArgs = array_merge([$request], $params);

            return call_user_func_array([$controller, $methodName], $methodArgs);
        }
    }
}

namespace PugKit\DI {

    use Exception;
    use PugKit\BackendEnums\ErrCode;

    use function PugKit\Helper\str_or_object;

    interface ContainerIneterface
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

    class Container implements ContainerIneterface
    {
        /**
         * @var array
         */
        public $building = [];

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
            throw new Exception("Container not found: {$container}", ErrCode::APP);
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
                throw new Exception(ucfirst($type) . " not found: {$interface}", ErrCode::APP);
            }
            throw new Exception("Invalid interface or missing '{$type}' keyword: {$interface}", ErrCode::APP);
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

namespace PugKit\Request {

    use PugKit\BackendEnums\Http;

    interface RequestInterface
    {
        public function only(array $defaults): array;
        public function get(): array;
        public function post(): array;
        public function multipart(): array;
        public function json(): array;
        public function getData(string $type): array;
        public function hasData(string $type): bool;
    }

    class Request implements RequestInterface
    {
        private static array $data = [];

        private function __construct() {}

        public static function createFromGlobals(): RequestInterface
        {
            static::$data = [
                "POST"   => static::sanitize($_POST),
                "GET"    => static::sanitize($_GET),
                "FILES"  => $_FILES,
                "JSON"   => static::fromJSON(),
                "SERVER" => $_SERVER,
            ];

            return new static();
        }

        public function only(array $defaults): array
        {
            $methods = [];
            foreach ($defaults as $default) {
                $default = strtoupper($default);
                $methods[$default] = !empty(self::$methods[$default]) ? self::$data[$default] : [];
            }

            return $methods;
        }

        public function get(): array
        {
            return $this->only([Http::Get])[Http::Get];
        }

        public function post(): array
        {
            return $this->only([Http::Post])[Http::Post];
        }

        public function multipart(): array
        {
            return $this->only([Http::Multipart])[Http::Multipart];
        }

        public function json(): array
        {
            return $this->only([Http::Json])[Http::Json];
        }

        public function getData(string $type): array
        {
            $key = strtoupper($type);
            return static::$data[$key] ?? [];
        }

        public function hasData(string $type): bool
        {
            $key = strtoupper($type);
            return array_key_exists($key, static::$data) && !empty(static::$data[$key]);
        }

        private static function fromJSON(): array
        {
            $raw = file_get_contents("php://input");
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? static::sanitize($decoded) : [];
        }

        private static function sanitize(array $input): array
        {
            return array_map(function ($value) {
                if (is_array($value)) {
                    return static::sanitize($value);
                }
                return is_string($value) ? htmlspecialchars(trim($value), ENT_QUOTES, "UTF-8") : $value;
            }, $input);
        }
    }
}

namespace PugKit\Response {

    use JsonSerializable;

    class ResponseEnums
    {
        // Informational 1xx
        public const CONTINUE = 100;
        public const SWITCHING_PROTOCOLS = 101;

        // Successful 2xx
        public const OK = 200;
        public const CREATED = 201;
        public const ACCEPTED = 202;
        public const NO_CONTENT = 204;

        // Redirection 3xx
        public const MOVED_PERMANENTLY = 301;
        public const FOUND = 302;
        public const NOT_MODIFIED = 304;

        // Client Error 4xx
        public const BAD_REQUEST = 400;
        public const UNAUTHORIZED = 401;
        public const FORBIDDEN = 403;
        public const NOT_FOUND = 404;
        public const METHOD_NOT_ALLOWED = 405;
        public const UNPROCESSABLE_ENTITY = 422;

        // Server Error 5xx
        public const INTERNAL_SERVER_ERROR = 500;
        public const NOT_IMPLEMENTED = 501;
        public const BAD_GATEWAY = 502;
        public const SERVICE_UNAVAILABLE = 503;
    }

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

    class ResponseHandler
    {
        public static function IfRespHandlerEchoValue($type = null)
        {
            if ($type instanceof JsonResponse) {
                header("Content-type: application/json");
                echo json_encode($type, JSON_PRETTY_PRINT);
                return;
            }

            if (is_array($type)) {
                header("Content-type: application/json");
                echo json_encode($type, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                return;
            }

            if (is_object($type)) {
                header("Content-type: application/json");
                echo json_encode($type, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                return;
            }

            header("Content-type: text/plain; charset=utf-8");
            echo $type;
        }
    }
}

namespace PugKit\Helper {

    use InvalidArgumentException;
    use PugKit\BackendEnums\ErrCode;
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
                    throw new InvalidArgumentException("Class $instance does not exist.", ErrCode::APP);
                }
            }

            if (is_object($instance)) {
                return $instance;
            }

            throw new InvalidArgumentException("Argument must be a string or an object.", ErrCode::APP);
        }
    }

    if (!function_exists("dd")) {

        function dd(mixed $data)
        {
            echo "<pre>";
            print_r($data);
            exit;
        }
    }
}
