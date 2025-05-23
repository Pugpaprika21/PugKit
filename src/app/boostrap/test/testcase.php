<?php

namespace PugKit\RouterCore {

    use Closure;
    use Exception;

    interface RouterInterface
    {
        public function group(string $prefix, callable $callback): void;
        public function get(string $pattern, callable|array $handler, array $middlewares = []): void;
        public function post(string $pattern, callable|array $handler, array $middlewares = []): void;
        public function put(string $pattern, callable|array $handler, array $middlewares = []): void;
        public function delete(string $pattern, callable|array $handler, array $middlewares = []): void;
        public function dispatch(string $uri): void;
    }

    trait Router
    {
        private const Get = "GET";
        private const Post = "POST";
        private const Put = "PUT";
        private const Delete = "DELETE";
        private const CodeNotFound = 404;
        private const MethodNotAllowed = 405;

        private array $routes = [];
        private string $prefix = "";

        private function addRoute(string $pattern, callable|array $handler, array $middlewares = []): void
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
            if ($_SERVER["REQUEST_METHOD"] !== $method) throw new Exception("Invalid HTTP method. Expected {$method}", self::MethodNotAllowed);
        }

        private function convertToRegex(string $pattern, ?array &$paramNames = []): string
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
            $this->prefix .= $prefix;
            $callback($this);
            $this->prefix = $previousPrefix;
        }

        public function get(string $pattern, callable|array $handler, array $middlewares = []): void
        {
            $this->addMethod(self::Get);
            $this->addRoute($pattern, $handler, $middlewares);
        }

        public function post(string $pattern, callable|array $handler, array $middlewares = []): void
        {
            $this->addMethod(self::Post);
            $this->addRoute($pattern, $handler, $middlewares);
        }

        public function put(string $pattern, callable|array $handler, array $middlewares = []): void
        {
            $this->addMethod(self::Put);
            $this->addRoute($pattern, $handler, $middlewares);
        }

        public function delete(string $pattern, callable|array $handler, array $middlewares = []): void
        {
            $this->addMethod(self::Delete);
            $this->addRoute($pattern, $handler, $middlewares);
        }

        public function dispatch(string $uri): void
        {
            try {
                foreach ($this->routes as $route) {
                    if (preg_match($route["regex"], $uri, $matches)) {
                        array_shift($matches);
                        $params = array_combine($route["paramNames"], $matches);

                        $next = function () use ($route, $params) {
                            if (is_object($route["handler"])) {
                                return call_user_func_array($route["handler"], $params);
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

                throw new Exception("404 Not Found", self::CodeNotFound);
            } catch (Exception $e) {
                header("Content-type: application/json");
                error_log($e->getMessage());
                echo json_encode(["data" => null, "message" => $e->getMessage(), "error_line" => $e->getLine(), "code" => $e->getCode()], JSON_PRETTY_PRINT);
                exit;
            }
        }
    }
}

namespace PugKit\DI {

    interface ContainerInterface
    {
        public function blind(mixed $container, callable $fn): mixed;
        public function has(mixed $container): bool;
        public function using(mixed $container): mixed;
    }

    trait Container
    {
        public function blind(mixed $container, callable $fn): mixed
        {
            return null;
        }

        public function has(mixed $container): bool
        {
            return false;
        }

        public function using(mixed $container): mixed
        {
            return null;
        }
    }
}

namespace PugKit\Singleton {

    use PugKit\DI\Container;
    use PugKit\DI\ContainerInterface;
    use PugKit\RouterCore\Router;
    use PugKit\RouterCore\RouterInterface;

    interface ApplicationInterface
    {
        public static function concreate(): ApplicationInterface;
    }

    final class Application implements ApplicationInterface, RouterInterface, ContainerInterface
    {
        use Router;
        use Container;

        private static ?ApplicationInterface $instance = null;

        public static function concreate(): ApplicationInterface
        {
            if (!self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }
    }

    $app = Application::concreate();

    /** @var Application&RouterInterface $app */
    $app->get("/", function () {});
}
