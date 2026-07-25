<?php
declare(strict_types=1);

namespace Crustum\Mcp\Server;

use Cake\Core\ContainerInterface;
use Crustum\Mcp\Request;
use Crustum\Mcp\Support\McpContainerBindings;
use InvalidArgumentException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Invoke MCP handlers with Cake container-aware dependency resolution.
 */
class ContainerInvoker
{
    /**
     * @param \Cake\Core\ContainerInterface $container Container instance
     */
    public function __construct(
        protected ContainerInterface $container,
    ) {
    }

    /**
     * Invoke a callable with container-aware dependency resolution.
     *
     * @param callable|array{0: object, 1: string} $callback Callable or class method pair
     * @param array<string, mixed> $parameters Named parameters
     * @return mixed
     */
    public function call(callable|array $callback, array $parameters = []): mixed
    {
        if (is_array($callback)) {
            [$object, $method] = $callback;
            $reflection = new ReflectionMethod($object, $method);
            $arguments = $this->resolveArguments($reflection->getParameters(), $parameters);

            return $reflection->invoke($object, ...$arguments);
        }

        $reflection = new ReflectionFunction($callback);
        $arguments = $this->resolveArguments($reflection->getParameters(), $parameters);

        return $reflection->invoke(...$arguments);
    }

    /**
     * Resolve callable arguments from bindings and type hints.
     *
     * @param array<int, \ReflectionParameter> $parameters Reflection parameters
     * @param array<string, mixed> $parametersByName Named parameter values
     * @return array<int, mixed>
     */
    protected function resolveArguments(array $parameters, array $parametersByName): array
    {
        $arguments = [];

        foreach ($parameters as $parameter) {
            $name = $parameter->getName();

            if (array_key_exists($name, $parametersByName)) {
                $arguments[] = $parametersByName[$name];

                continue;
            }

            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $className = $type->getName();
                if ($className === Request::class) {
                    $resolvedRequest = $this->resolveRequestArgument($className);

                    if ($resolvedRequest instanceof Request) {
                        $arguments[] = $resolvedRequest;

                        continue;
                    }
                }

                if (is_subclass_of($className, Request::class)) {
                    $resolvedRequest = $this->resolveRequestArgument($className);

                    if ($resolvedRequest instanceof Request) {
                        $arguments[] = $resolvedRequest;

                        continue;
                    }
                }

                $resolved = $this->resolveFromContainer($className);
                if ($resolved !== null) {
                    $arguments[] = $resolved;

                    continue;
                }

                $arguments[] = new $className();
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();

                continue;
            }

            throw new InvalidArgumentException("Unable to resolve parameter [{$name}].");
        }

        return $arguments;
    }

    /**
     * Resolve a value from the container when available.
     *
     * @param string $id Binding identifier or class name
     * @return mixed
     */
    protected function resolveFromContainer(string $id): mixed
    {
        if (!$this->container->has($id)) {
            return null;
        }

        try {
            return $this->container->get($id);
        } catch (NotFoundExceptionInterface | ContainerExceptionInterface) {
            return null;
        }
    }

    /**
     * Resolve a request argument for handler injection.
     *
     * When mcp.request is bound, hydrate a new Request (or subclass) from it.
     *
     * @param class-string<\Crustum\Mcp\Request> $className Request class name
     * @return \Crustum\Mcp\Request|null
     */
    protected function resolveRequestArgument(string $className): ?Request
    {
        $current = $this->resolveFromContainer(McpContainerBindings::REQUEST)
            ?? $this->resolveFromContainer(Request::class);

        if (!$current instanceof Request) {
            if ($className === Request::class) {
                return new Request();
            }

            return null;
        }

        if ($className !== Request::class && !is_subclass_of($className, Request::class)) {
            return null;
        }

        /** @var class-string<\Crustum\Mcp\Request> $className */
        $request = new $className();
        $this->hydrateRequest($request, $current);

        return $request;
    }

    /**
     * Copy MCP request state onto a newly resolved request instance.
     *
     * @param \Crustum\Mcp\Request $request Target request
     * @param \Crustum\Mcp\Request $current Bound mcp.request
     * @return void
     */
    protected function hydrateRequest(Request $request, Request $current): void
    {
        $request->setArguments($current->all());
        $request->setSessionId($current->sessionId());
        $request->setMeta($current->meta());
        $request->setUri($current->uri());
        $request->setIdentity($current->getIdentity());
    }
}
