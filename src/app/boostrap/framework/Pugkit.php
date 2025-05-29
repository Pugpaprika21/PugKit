<?php

/**
 *@PugKit The Mini PHP Framework
 *@create by Pug
 *@create version v0.2
 */

namespace PugKit\Singleton {

    use PugKit\DI\Container;
    use PugKit\DI\ContainerInterface;
    use PugKit\RouterCore\RouterInterface;
    use PugKit\RouterCore\ServerRouter;
    use PugKit\Web\Display\View;
    use PugKit\Web\Display\ViewDisplayInterface;
    use PugKit\Web\Url\Redirect;

    interface ApplicationInterface
    {
        public static function concreate(): ApplicationInterface;
        public function getServerRouter(): ?RouterInterface;
        public function getView(): ?ViewDisplayInterface;
    }

    class Application implements ApplicationInterface, ContainerInterface
    {
        use Container;
        use Redirect;

        private static ?ApplicationInterface $instance = null;

        private static ?RouterInterface $router = null;
        
        private static ?ViewDisplayInterface $view = null;

        private function __construct(?RouterInterface $router, ?ViewDisplayInterface $view)
        {
            self::$router = $router;
            self::$view = $view;
        }

        public static function concreate(): ApplicationInterface
        {
            if (is_null(self::$instance)) {
                $view = new View(); 
                $router = new ServerRouter($view); 
                self::$instance = new self($router, $view);
            }

            return self::$instance;
        }

        public function getServerRouter(): ?RouterInterface
        {
            return self::$router;
        }

        public function getView(): ?ViewDisplayInterface
        {
            return self::$view;
        }
    }
}

namespace PugKit\RouterCore {

    use Closure;
    use Exception;
    use JsonSerializable;
    use PugKit\Http\Request\Request;
    use PugKit\Http\Response\BackendEnums\Method;
    use PugKit\Web\Display\ViewDisplayInterface;

    interface RouterInterface
    {
        public function group(string $prefix, callable $callback): void;
        public function get(string $pattern, callable|array $handler, array $middlewares = []): void;
        public function post(string $pattern, callable|array $handler, array $middlewares = []): void;
        public function put(string $pattern, callable|array $handler, array $middlewares = []): void;
        public function delete(string $pattern, callable|array $handler, array $middlewares = []): void;
        public function view(string $pattern, string $viewPath, array $middlewares = []): void;
        public function dispatch(string $uri): void;
    }

    interface RouterGroupInterface extends RouterInterface {}

    abstract class AbstractRouter implements RouterInterface
    {
        private const CodeNotFound = 404;
        private const MethodNotAllowed = 405;

        private array $routes = [];
        private string $prefix = "";

        public ?ViewDisplayInterface $view;

        public function __construct(?ViewDisplayInterface $view)
        {
            $this->view = $view;
        }

        private function addRoute(string $pattern, callable|array $handler, array $middlewares = []): void
        {
            $this->routes[] = [
                "regex" => $this->toRegex($this->prefix . $pattern, $paramNames),
                "paramNames" => $paramNames,
                "handler" => $handler,
                "middlewares" => $middlewares,
            ];
        }

        private function addMethod(Method $method): void
        {
            if ($_SERVER["REQUEST_METHOD"] !== $method->value) throw new Exception("Invalid HTTP method. Expected {$method->value}", self::MethodNotAllowed);
        }

        private function toRegex(string $pattern, ?array &$paramNames = []): string
        {
            $paramNames = [];
            $regex = preg_replace_callback("#\{(\w+)\}#", function ($matches) use (&$paramNames) {
                $paramNames[] = $matches[1];
                return "([^/]+)";
            }, $pattern);

            return sprintf("#^%s$#", $regex);
        }

        public function group(string $prefix, callable $callback): void
        {
            $previousPrefix = $this->prefix;
            $this->prefix = rtrim($prefix, "/");

            $group = new class($this, $this->prefix) implements RouterGroupInterface {
                private object $router;
                private string $prefix;

                public function __construct(object $router, string $prefix)
                {
                    $this->router = $router;
                    $this->prefix = $prefix;
                }

                public function get(string $pattern, callable|array $handler, array $middlewares = []): void
                {
                    $this->router->get($pattern, $handler, $middlewares);
                }

                public function post(string $pattern, callable|array $handler, array $middlewares = []): void
                {
                    $this->router->post($pattern, $handler, $middlewares);
                }

                public function put(string $pattern, callable|array $handler, array $middlewares = []): void
                {
                    $this->router->put($pattern, $handler, $middlewares);
                }

                public function delete(string $pattern, callable|array $handler, array $middlewares = []): void
                {
                    $this->router->delete($pattern, $handler, $middlewares);
                }

                public function view(string $pattern, string $viewPath, array $middlewares = []): void
                {
                    $this->router->view($pattern, $viewPath, $middlewares);
                }

                public function group(string $prefix, callable $callback): void
                {
                    $this->router->group($this->prefix . $prefix, $callback);
                }

                public function dispatch(string $uri): void
                {
                    $this->router->dispatch($uri);
                }
            };

            $callback($group);
            $this->prefix = $previousPrefix;
        }

        public function get(string $pattern, callable|array $handler, array $middlewares = []): void
        {
            $this->addMethod(Method::Get);
            $this->addRoute($pattern, $handler, $middlewares);
        }

        public function post(string $pattern, callable|array $handler, array $middlewares = []): void
        {
            $this->addMethod(Method::Post);
            $this->addRoute($pattern, $handler, $middlewares);
        }

        public function put(string $pattern, callable|array $handler, array $middlewares = []): void
        {
            $this->addMethod(Method::Put);
            $this->addRoute($pattern, $handler, $middlewares);
        }

        public function delete(string $pattern, callable|array $handler, array $middlewares = []): void
        {
            $this->addMethod(Method::Delete);
            $this->addRoute($pattern, $handler, $middlewares);
        }

        public function view(string $pattern, string $viewPath, array $middlewares = []): void
        {
            $this->addMethod(Method::Get);
            $this->addRoute($pattern, fn() => $this->view->view($viewPath, []), $middlewares);
        }

        public function dispatch(string $uri): void
        {
            try {
                $request = Request::fromGlobals();

                foreach ($this->routes as $route) {
                    if (preg_match($route["regex"], $uri, $matches)) {
                        array_shift($matches);
                        $params = array_combine($route["paramNames"], $matches);
                        $request->setParams($params);

                        $next = function () use ($route, $request, $params) {
                            ob_start();

                            $args = array_values($params);
                            array_unshift($args, $request);

                            $handler = $route["handler"];
                            $result = null;

                            if ($handler instanceof Closure) {
                                $bound = Closure::bind($handler, $this, get_class($this));
                                $result = call_user_func_array($bound, $args);
                            } elseif (is_array($handler) && is_string($handler[0]) && is_string($handler[1])) {
                                $instance = new $handler[0]($this);
                                $methodName = $handler[1];
                                $result = $instance->$methodName(...$args);
                            }

                            $output = ob_get_clean();

                            if ($result instanceof JsonSerializable) {
                                header("Content-Type: application/json");
                                http_response_code($result->codeStatus);
                                echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                            } elseif ($result instanceof ViewDisplayInterface) {
                                echo $result->render();
                            } elseif (is_scalar($result)) {
                                echo $output . $result;
                            } elseif ($output !== "") {
                                echo $output;
                            } elseif ($result !== null) {
                                echo is_string($result) ? $result : json_encode($result);
                            }
                        };

                        foreach (array_reverse($route["middlewares"] ?? []) as $middleware) {
                            $current = $next;
                            $next = function () use ($middleware, $params, $current): Closure {
                                return $middleware($params, $current);
                            };
                        }

                        $next();
                        return;
                    }
                }

                throw new Exception("404 Not Found", self::CodeNotFound);
            } catch (Exception $e) {
                echo $this->view::errors("404.php", $e)->render();
                exit;
            }
        }
    }

    class ServerRouter extends AbstractRouter
    {
        public function __construct(?ViewDisplayInterface $view)
        {
            parent::__construct($view);
        }
    }
}

namespace PugKit\Pattern\AppFactory {

    readonly class Factory
    {
        public static function concreate(string|object $interface) {}
    }
}

namespace PugKit\Web\Url {

    trait Redirect
    {
        public function url(string $path)
        {
            print_r($this->routes);
            exit;
        }
    }
}

namespace PugKit\Web\Display {

    use Exception;

    interface ViewDisplayInterface
    {
        public static function view(string $viewPath, array $viewData = []): ViewDisplayInterface;
        public static function errors(string $errView, Exception $excep): ViewDisplayInterface;
        public function render(): string;
    }

    abstract class AbstractView implements ViewDisplayInterface
    {
        private static string $viewPath;
        private static array $viewData;

        private static string $hasErr;
        private static string $errView;
        private static Exception $excep;

        public static function view(string $viewPath, array $viewData = []): ViewDisplayInterface
        {
            self::$hasErr = "";
            self::$viewPath = $viewPath;
            self::$viewData = $viewData;
            return new static();
        }

        public static function errors(string $errView, Exception $excep): ViewDisplayInterface
        {
            error_log($excep->getMessage());

            self::$hasErr = "ERROR";
            self::$errView = $errView;
            self::$excep = $excep;
            return new static();
        }

        public function render(): string
        {
            ob_start();
            if (!empty(self::$hasErr)) {
                $e = self::$excep;
                if ($e->getCode() == 404) {
                    include_once sprintf("%s../../../views/_errors/%s", __DIR__, self::$errView);
                } else {
                    include_once sprintf("%s../../../views/_errors/%s", __DIR__, "500.php");
                }

                http_response_code($e->getCode());
            } else {
                $data = $this->escapeData(self::$viewData);
                extract($data);
                include_once sprintf("%s/../../views/_pages/%s", __DIR__, self::$viewPath);
            }

            header("Content-Type: text/html; charset=UTF-8");
            header("X-Content-Type-Options: nosniff");
            header("X-Frame-Options: DENY");

            return ob_get_clean();
        }

        private function escapeData(array $data): array
        {
            return array_map(fn($value) => is_string($value) ? htmlspecialchars($value, ENT_QUOTES, "UTF-8") : $value, $data);
        }
    }

    class View extends AbstractView {}
}

namespace PugKit\DI {

    use Exception;
    use PugKit\Http\Response\BackendEnums\Error;

    interface ContainerInterface
    {
        public function bind(mixed $container, callable $fn): void;
        public function has(mixed $container): bool;
        public function using(mixed $container): mixed;
    }

    trait Container
    {
        private array $building = [];

        public function bind(mixed $container, callable $fn): void
        {
            $this->building[$container] = $fn->bindTo($this, $this);
        }

        public function has(mixed $container): bool
        {
            return !empty($this->building[$container]) ? true : false;
        }

        public function using(mixed $container): mixed
        {
            if (isset($this->building[$container])) {
                return ($this->building[$container])();
            }
            throw new Exception("Container not found: {$container}", Error::App->value);
        }
    }
}

namespace PugKit\Http\Response\BackendEnums {

    enum Method: string
    {
        case Get = "GET";
        case Post = "POST";
        case Put = "PUT";
        case Delete = "DELETE";
    }

    enum HttpStatus: int
    {
        case CONTINUE = 100;
        case SWITCHING_PROTOCOLS = 101;

        case OK = 200;
        case CREATED = 201;
        case ACCEPTED = 202;
        case NO_CONTENT = 204;

        case MOVED_PERMANENTLY = 301;
        case FOUND = 302;
        case NOT_MODIFIED = 304;

        case BAD_REQUEST = 400;
        case UNAUTHORIZED = 401;
        case FORBIDDEN = 403;
        case NOT_FOUND = 404;
        case METHOD_NOT_ALLOWED = 405;
        case UNPROCESSABLE_ENTITY = 422;

        case INTERNAL_SERVER_ERROR = 500;
        case NOT_IMPLEMENTED = 501;
        case BAD_GATEWAY = 502;
        case SERVICE_UNAVAILABLE = 503;
    }

    enum Error: int
    {
        case App = 500;
    }
}

namespace PugKit\Http\Response {

    use JsonSerializable;

    class JsonResponse implements JsonSerializable
    {
        private array|object $data;
        private string $message;
        private int $status;

        public int $codeStatus;

        public function __construct(array|object $data, string $message, int $status)
        {
            $this->data = $data;
            $this->message = $message;
            $this->status = $status;
            $this->codeStatus = $status;
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

namespace PugKit\Http\Request {

    use function PugKit\Helpers\conv_toobj;

    interface RequestInterface
    {
        public function only(array $keys): array;
        public function setParams(array $params): void;
        public function params(): object;
    }

    class Request implements RequestInterface
    {
        private static array $store = [];
        private array $params = [];

        public static function fromGlobals(): RequestInterface
        {
            self::$store = [
                "POST"   => $_POST ?? [],
                "GET"    => $_GET ?? [],
                "FILES"  => $_FILES ?? [],
                "JSON"   => json_decode(file_get_contents("php://input"), true) ?? [],
                "SERVER" => $_SERVER ?? [],
            ];

            return new self();
        }

        public function setParams(array $params): void
        {
            $this->params = $params;
        }

        public function params(): object
        {
            return conv_toobj($this->params);
        }

        public function only(array $keys): array
        {
            $method = strtoupper($_SERVER["REQUEST_METHOD"] ?? "GET");

            $data = self::$store[$method] ?? [];
            return array_intersect_key($data, array_flip($keys));
        }
    }
}

namespace PugKit\DotENV {

    interface EnvironmentInterface
    {
        public static function load(string $path): void;
    }

    class Environment implements EnvironmentInterface
    {
        public static function load(string $path): void
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
            }
        }
    }
}

namespace PugKit\Helpers {

    use InvalidArgumentException;
    use PugKit\Http\Response\BackendEnums\Error;
    use PugKit\Singleton\Application;
    use PugKit\Singleton\ApplicationInterface;
    use stdClass;

    if (!function_exists("app")) {

        function app(): ApplicationInterface
        {
            return Application::concreate();
        }
    }

    if (!function_exists("factory")) {

        function factory()
        {
            return false;
        }
    }

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

        function context_xss(mixed $input): string
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

        function str_or_object(string $instance): ?object
        {
            if (is_string($instance)) {
                if (class_exists($instance)) {
                    return new $instance();
                } else {
                    throw new InvalidArgumentException("Class $instance does not exist.", Error::App->value);
                }
            }

            if (is_object($instance)) {
                return $instance;
            }

            throw new InvalidArgumentException("Argument must be a string or an object.", Error::App->value);
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
