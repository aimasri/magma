<?php

declare(strict_types=1);

namespace Magma\routing;

use Magma\container\Container;
use Magma\http\RequestInterface;
use Magma\validation\ValidatableRequestInterface;
use Magma\validation\Validator;

/**
 * Title: Route Parameter Resolver
 * Purpose:
 * - Resolves dependencies for route handlers.
 * Why/Why this design:
 * - Extracted from Router/RouteDispatcher to adhere to SRP.
 * Teaching notes:
 * - Auto-wires container dependencies, request instances, and path parameters.
 */
class RouteParameterResolver
{
    private ?Container $container;
    private static array $reflectionCache = [];

    public function __construct(?Container $container = null)
    {
        $this->container = $container;
    }

    public function resolveDependencies(\ReflectionFunctionAbstract $ref, array $params, RequestInterface $request): array
    {
        $isMethod = $ref instanceof \ReflectionMethod;
        $cacheKey = $isMethod ? $ref->getDeclaringClass()->getName() . '@' . $ref->getName() : null;

        if ($cacheKey !== null && isset(self::$reflectionCache[$cacheKey])) {
            $metaData = self::$reflectionCache[$cacheKey];
        } else {
            $metaData = $this->buildReflectionMeta($ref);
            if ($cacheKey !== null) {
                self::$reflectionCache[$cacheKey] = $metaData;
            }
        }

        $args = [];
        foreach ($metaData as $meta) {
            $name = $meta['name'];
            $className = $meta['class'];

            if ($className !== null && is_subclass_of($className, ValidatableRequestInterface::class)) {
                $validatableRequest = $this->container->get($className);
                try {
                    $validatableRequest->validate();
                } catch (\Magma\validation\ValidationException $e) {
                    $expectsJson = $request->expectsJson() || $request->isJsonExpected();
                    if (!$expectsJson && $this->container->has(\Magma\http\SessionInterface::class)) {
                        $session = $this->container->get(\Magma\http\SessionInterface::class);
                        $session->set('errors', $e->getErrors());
                        $session->set('old', $request->request());
                        
                        $referer = $request->getServer('HTTP_REFERER') ?? '/';
                        $redirect = new \Magma\http\RedirectResponse($referer);
                        throw new \Magma\http\HttpResponseException($redirect);
                    }
                    throw $e;
                }
                
                $args[] = $validatableRequest;
                continue;
            }

            if (array_key_exists($name, $params)) {
                $val = $params[$name];
                if ($meta['isBuiltin']) {
                    $typeName = $className;
                    $val = match ($typeName) {
                        'int' => (int)$val,
                        'float' => (float)$val,
                        'bool' => filter_var($val, FILTER_VALIDATE_BOOLEAN),
                        'string' => (string)$val,
                        default => $val,
                    };
                }
                $args[] = $val;
            } elseif ($className && ($className === RequestInterface::class || is_a($request, $className))) {
                $args[] = $request;
            } elseif ($className && $this->container !== null && $this->container->has($className)) {
                $args[] = $this->container->get($className);
            } elseif ($meta['hasDefault']) {
                $args[] = $meta['default'];
            } elseif ($meta['allowsNull']) {
                $args[] = null;
            } else {
                throw new \RuntimeException("Unable to resolve dependency '\${$name}'.");
            }
        }
        return $args;
    }

    private function buildReflectionMeta(\ReflectionFunctionAbstract $ref): array
    {
        $meta = [];
        foreach ($ref->getParameters() as $param) {
            $type = $param->getType();
            $meta[] = [
                'name' => $param->getName(),
                'class' => $type instanceof \ReflectionNamedType ? $type->getName() : null,
                'isBuiltin' => $type instanceof \ReflectionNamedType ? $type->isBuiltin() : false,
                'hasDefault' => $param->isDefaultValueAvailable(),
                'default' => $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null,
                'allowsNull' => $param->allowsNull(),
            ];
        }
        return $meta;
    }
}
