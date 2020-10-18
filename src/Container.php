<?php

declare(strict_types=1);

/*
 * This file is part of Biurad opensource projects.
 *
 * PHP version 7.2 and above required
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 Biurad Group (https://biurad.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Biurad\Framework;

use Biurad\Framework\Exceptions\ContainerResolutionException;
use Biurad\Framework\Exceptions\NotFoundServiceException;
use Biurad\Framework\Interfaces\FactoryInterface;
use Closure;
use Nette\DI\Container as NetteContainer;
use Nette\DI\Helpers;
use Nette\DI\MissingServiceException;
use Nette\DI\Resolver;
use Nette\UnexpectedValueException;
use Nette\Utils\Callback;
use Nette\Utils\Reflection;
use Nette\Utils\Validators;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionType;
use Throwable;

/**
 * The dependency injection container default implementation.
 *
 * Auto-wiring container: declarative singletons, contextual injections, outer delegation and
 * ability to lazy wire.
 *
 * Container does not support setter injections, private properties and etc. Normally it will work
 * with classes only to be as much invisible as possible.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class Container extends NetteContainer implements FactoryInterface
{
    /** @var object[] service name => instance */
    private $instances = [];

    /** @var array circular reference detector */
    private $creating;

    /** @var array */
    private $methods;

    /**
     * Provide psr container interface in order to proxy get and has requests.
     *
     * @param array $params
     */
    public function __construct(array $params = [])
    {
        $this->parameters = $params;
        $this->methods    = $this->getServiceMethods(\get_class_methods($this));

        parent::__construct($params);
    }

    /**
     * {@inheritdoc}
     */
    public function has($id)
    {
        return $this->hasService($id);
    }

    /**
     * {@inheritdoc}
     *
     * @return object
     */
    public function get($id)
    {
        try {
            return $this->make($id);
        } catch (Throwable $e) {
            throw new NotFoundServiceException(\sprintf('Service [%s] is not found in container', $id), 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getParameter(string $name)
    {
        return DependencyInjection\Builder::arrayGet($this->parameters, $name);
    }

    /**
     * {@inheritdoc}
     */
    public function addService(string $name, $service)
    {
        $service = null === $service ? $name : $service;
        $name    = $this->aliases[$name] ?? $name;

        // Create an instancer
        if (\is_string($service) && \class_exists($service)) {
            $service = $this->createInstance($service);
        }

        // Report exception if name already exists.
        if (isset($this->instances[$name])) {
            throw new ContainerResolutionException("Service [$name] already exists.");
        }

        if (!\is_object($service)) {
            throw new ContainerResolutionException(
                \sprintf("Service '%s' must be a object, %s given.", $name, \gettype($name))
            );
        }

        // Resolving the closure of the service to return it's type hint or class.
        $type = $this->parseServiceType($service);

        // Resolving wiring so we could call the service parent classes and interfaces.
        if (!$service instanceof Closure) {
            $this->resolveWiring($name, $type);
        }

        // Resolving the method calls.
        $this->resolveMethod($name, self::getMethodName($name), $type);

        if ($service instanceof Closure) {
            // Get the method binding for the given method.
            $this->methods[self::getMethodName($name)] = $service;
            $this->types[$name]                        = $type;
        } else {
            $this->instances[$name] = $service;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function removeService(string $name): void
    {
        $name = $this->aliases[$name] ?? $name;
        unset($this->instances[$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function getService(string $name)
    {
        if (!isset($this->instances[$name])) {
            if (isset($this->aliases[$name])) {
                return $this->getService($this->aliases[$name]);
            }
            $this->instances[$name] = $this->createService($name);
        }

        return $this->instances[$name];
    }

    /**
     * {@inheritdoc}
     */
    public function getServiceType(string $name): string
    {
        $method = self::getMethodName($name);

        if (isset($this->aliases[$name])) {
            return $this->getServiceType($this->aliases[$name]);
        }

        if (isset($this->types[$name])) {
            return $this->types[$name];
        }

        if ($this->hasMethodBinding($method)) {
            /** @var ReflectionMethod $type */
            $type = $this->parseBindMethod([$this, $method]);

            return $type ? $type->getName() : '';
        }

        throw new MissingServiceException("Service '$name' not found.");
    }

    /**
     * {@inheritdoc}
     */
    public function hasService(string $name): bool
    {
        $name = $this->aliases[$name] ?? $name;

        return $this->hasMethodBinding(self::getMethodName($name)) || isset($this->instances[$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function isCreated(string $name): bool
    {
        if (!$this->hasService($name)) {
            throw new MissingServiceException("Service '$name' not found.");
        }
        $name = $this->aliases[$name] ?? $name;

        return isset($this->instances[$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function createService(string $name, array $args = [])
    {
        $name   = $this->aliases[$name] ?? $name;
        $method = self::getMethodName($name);
        $cb     = $this->methods[$method] ?? null;

        if (isset($this->creating[$name])) {
            throw new ContainerResolutionException(
                \sprintf(
                    'Circular reference detected for services: %s.',
                    \implode(', ', \array_keys($this->creating))
                )
            );
        }

        if ($cb === null) {
            throw new MissingServiceException("Service '$name' not found.");
        }

        try {
            $this->creating[$name] = true;
            $service               = $cb instanceof Closure ? $cb(...$args) : $this->$method(...$args);
        } finally {
            unset($this->creating[$name]);
        }

        if (!\is_object($service)) {
            throw new UnexpectedValueException(
                "Unable to create service '$name', value returned by " .
                ($cb instanceof Closure ? 'closure' : "method $method()") . ' is not object.'
            );
        }

        return $service;
    }

    /**
     * {@inheritdoc}
     */
    public function getByType(string $type, bool $throw = true)
    {
        $type = Helpers::normalizeClass($type);

        if (!empty($this->wiring[$type][0])) {
            if (\count($names = $this->wiring[$type][0]) === 1) {
                return $this->getService($names[0]);
            }
            \natsort($names);

            throw new MissingServiceException(
                "Multiple services of type $type found: " . \implode(', ', $names) . '.'
            );
        }

        if ($throw) {
            if (!\class_exists($type) && !\interface_exists($type)) {
                throw new MissingServiceException(
                    "Service of type '$type' not found. Check class name because it cannot be found."
                );
            }

            foreach ($this->methods as $method => $foo) {
                $methodType = $this->parseBindMethod([\get_class($this), $method])->getName();

                if (\is_a($methodType, $type, true)) {
                    throw new MissingServiceException(
                        "Service of type $type is not autowired or is missing in di › export › types."
                    );
                }
            }

            throw new MissingServiceException(
                "Service of type $type not found. Did you add it to configuration file?"
            );
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function createInstance(string $class, array $args = [])
    {
        try {
            $reflector = new ReflectionClass($class);
        } catch (ReflectionException $e) {
            throw new ContainerResolutionException("Targeted class [$class] does not exist.", 0, $e);
        }

        // If the type is not instantiable, the developer is attempting to resolve
        // an abstract type such as an Interface or Abstract Class and there is
        // no binding registered for the abstractions so we need to bail out.
        if (!$reflector->isInstantiable()) {
            throw new ContainerResolutionException("Targeted [$class] is not instantiable");
        }

        $constructor = $reflector->getConstructor();

        // If there are no constructors, that means there are no dependencies then
        // we can just resolve the instances of the objects right away, without
        // resolving any other types or dependencies out of these containers.
        if (null === $constructor) {
            return $reflector->newInstance();
        }

        // Once we have all the constructor's parameters we can create each of the
        // dependency instances and then use the reflection instances to make a
        // new instance of this class, injecting the created dependencies in.
        // this will be handled in a recursive way...
        try {
            $instances = $this->autowireArguments($constructor, $args);
        } catch (MissingServiceException $e) {
            // Resolve default pararamters on class, if paramter was not given and
            // default paramter exists, why not let's use it.
            foreach ($constructor->getParameters() as $position => $parameter) {
                try {
                    if (!(isset($args[$position]) || isset($args[$parameter->name]))) {
                        $args[$position] = Reflection::getParameterDefaultValue($parameter);
                    }
                } catch (ReflectionException $e) {
                    continue;
                }
            }

            return $this->createInstance($class, $args);
        }

        return $reflector->newInstanceArgs($instances);
    }

    /**
     * {@inheritdoc}
     */
    public function runScope(array $bindings, callable $scope)
    {
        $cleanup = $previous = [];

        foreach ($bindings as $alias => $resolver) {
            if ($this->has($alias)) {
                $previous[$alias] = $this->get($alias);

                continue;
            }

            $cleanup[] = $alias;
            $this->addService($alias, $resolver);
        }

        try {
            return $scope(...[&$this]);
        } finally {
            foreach (\array_reverse($previous) as $alias => $resolver) {
                $this->instances[$alias] = $resolver;
            }

            foreach ($cleanup as $alias) {
                $this->removeService($alias);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function make(string $alias, ...$parameters)
    {
        try {
            return $this->getService($alias);
        } catch (MissingServiceException $e) {
            // Allow passing arrays or individual lists of dependencies
            if (isset($parameters[0]) && \is_array($parameters[0]) && \count($parameters) === 1) {
                $parameters = \array_shift($parameters);
            }

            //Automatically create instance
            if (Validators::isType($alias)) {
                try {
                    $instance = $this->getByType($alias);
                } catch (MissingServiceException $e) {
                    $instance = $this->createInstance($alias, $parameters);
                }

                $this->callInjects($instance); // Call injectors on the new class instance.

                return $instance;
            }
        }

        throw new NotFoundServiceException(\sprintf('Service [%s] is not found in container', $alias));
    }

    /**
     * {@inheritdoc}
     */
    public function hasMethodBinding($method): bool
    {
        return isset($this->methods[$method]);
    }

    /**
     * Resolve callable methods.
     *
     * @param string $abstract
     * @param string $concrete
     * @param string $type
     */
    private function resolveMethod(string $abstract, string $concrete, string $type): void
    {
        if (!$this->hasMethodBinding($concrete)) {
            $this->types[$abstract] = $type;
        }

        if (($expectedType = $this->getServiceType($abstract)) && !\is_a($type, $expectedType, true)) {
            throw new ContainerResolutionException(
                "Service '$abstract' must be instance of $expectedType, " .
                ($type ? "$type given." : 'add typehint to closure.')
            );
        }
    }

    /**
     * Resolve wiring classes + interfaces.
     *
     * @param string $name
     * @param mixed  $class
     */
    private function resolveWiring(string $name, $class): void
    {
        $all = [];

        foreach (\class_parents($class) + \class_implements($class) + [$class] as $class) {
            $all[$class][] = $name;
        }

        foreach ($all as $class => $names) {
            $this->wiring[$class] = \array_filter([
                \array_diff($names, $this->findByType($class) ?? [], $this->findByTag($class) ?? []),
            ]);
        }
    }

    /**
     * Get the method to be bounded.
     *
     * @param array|string $method
     *
     * @return null|ReflectionType
     */
    private function parseBindMethod($method): ?ReflectionType
    {
        return Callback::toReflection($method)->getReturnType();
    }

    private function autowireArguments(ReflectionFunctionAbstract $function, array $args = []): array
    {
        return Resolver::autowireArguments($function, $args, function (string $type, bool $single) {
            return $single
                ? $this->getByType($type)
                : \array_map([$this, 'get'], $this->findAutowired($type));
        });
    }

    /**
     * Get the Closure or class to be used when building a type.
     *
     * @param mixed $abstract
     *
     * @return string
     */
    private function parseServiceType($abstract): string
    {
        if ($abstract instanceof Closure) {
            /** @var ReflectionFunction $tmp */
            if ($tmp = $this->parseBindMethod($abstract)) {
                return $tmp->getName();
            }

            return '';
        }

        return \get_class($abstract);
    }

    private function getServiceMethods(?array $methods): array
    {
        return \array_flip(\array_filter(
            $methods,
            function ($s) {
                return \preg_match('#^createService.#', $s);
            }
        ));
    }
}
