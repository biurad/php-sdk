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

namespace Biurad\Framework\DependencyInjection;

use ArrayAccess;
use Biurad\Framework\Container;
use Biurad\Framework\DependencyInjection\Definitions\InterfaceDefinition;
use Biurad\Framework\Exceptions\ParameterNotFoundException;
use Nette\DI\ContainerBuilder as NetteContainerBuilder;
use Nette\DI\Definitions;
use Nette\DI\Definitions\Definition;
use Nette\InvalidArgumentException;

/**
 * Container builder.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class Builder extends NetteContainerBuilder
{
    public function __construct()
    {
        parent::__construct();

        $this->removeDefinition(self::THIS_CONTAINER);
        $this->addImportedDefinition(self::THIS_CONTAINER)->setType(Container::class);
    }

    /**
     * Adds the service definitions.
     *
     * @param Definition[] $definitions An array of service definitions
     */
    public function addDefinitions(array $definitions): void
    {
        foreach ($definitions as $id => $definition) {
            $this->addDefinition($id, $definition);
        }
    }

    /**
     * Registers a service definition.
     *
     * This methods allows for simple registration of service definition
     * with a fluid interface.
     *
     * @param array|Definition|Definitions\Reference|Definitions\Statement|string $class
     *
     * @return Definitions\ServiceDefinition A Definition instance
     */
    public function register(string $id, $class = null, ?Definition $definition = null): Definition
    {
        return $this->addDefinition($id, $definition)->setFactory($class);
    }

    /**
     * Registers an autowired service definition.
     *
     * This method implements a shortcut for using addDefinition() with
     * an autowired definition.
     *
     * @return Definitions\ServiceDefinition The created definition
     */
    public function autowire(string $id, string $class = null)
    {
        return $this->register($id, $class)->setAutowired(true);
    }

    public function addInterfaceDefinition(string $name): InterfaceDefinition
    {
        return $this->addDefinition($name, new InterfaceDefinition());
    }

    /**
     * Computes a reasonably unique hash of a value.
     *
     * @param mixed $value A serializable value
     *
     * @return string
     */
    public static function hash($value)
    {
        $hash = \substr(\base64_encode(\hash('sha256', \serialize($value), true)), 0, 7);

        return \str_replace(['/', '+'], ['.', '_'], $hash);
    }

    /**
     * Adds the service aliases.
     */
    public function addAliases(array $aliases): void
    {
        foreach ($aliases as $alias => $id) {
            $this->addAlias($alias, $id);
        }
    }

    /**
     * Gets a parameter.
     *
     * @param string $name The parameter name
     *
     * @throws ParameterNotFoundException if the parameter is not defined
     *
     * @return mixed The parameter value
     */
    public function getParameter(string $name)
    {
        if (!\array_key_exists($name, $this->parameters)) {
            $alternatives = [];

            foreach ($this->parameters as $key => $parameterValue) {
                $lev = \levenshtein($name, $key);

                if ($lev <= \strlen($name) / 3 || false !== \strpos($key, $name)) {
                    $alternatives[] = $key;
                }
            }

            $nonNestedAlternative = null;

            if (!\count($alternatives) && false !== \strpos($name, '.')) {
                $namePartsLength = \explode('.', $name);
                $key             = \array_shift($namePartsLength);

                while (\count($namePartsLength)) {
                    if ($this->hasParameter($key)) {
                        if (!\is_array($this->getParameter($key))) {
                            $nonNestedAlternative = $key;

                            throw new ParameterNotFoundException(
                                $name,
                                null,
                                $alternatives,
                                $nonNestedAlternative
                            );
                        }

                        return self::arrayGet($this->parameters, $name);
                    }
                }
            }
        }

        return $this->parameters[$name] ?? null;
    }

    /**
     * Checks if a parameter exists.
     *
     * @internal should not be used to check parameters existence
     *
     * @param string $name The parameter name
     *
     * @return bool The presence of parameter in container
     */
    public function hasParameter(string $name)
    {
        return \array_key_exists($name, $this->parameters);
    }

    /**
     * Adds parameters to the service container parameters.
     *
     * @param string $name  The parameter name
     * @param mixed  $value The parameter value
     */
    public function setParameter(string $name, $value): void
    {
        if (\strpos($name, '.') !== false) {
            $parameters = &$this->parameters;
            $keys       = \explode('.', $name);

            while (\count($keys) > 1) {
                $key = \array_shift($keys);

                if (!isset($parameters[$key]) || !\is_array($parameters[$key])) {
                    $parameters[$key] = [];
                }

                $parameters = &$parameters[$key];
            }

            $parameters[\array_shift($keys)] = $value;
        } else {
            $this->parameters[$name] = $value;
        }
    }

    /**
     * Removes a parameter.
     *
     * @param string $name The parameter name
     */
    public function removeParameter(string $name): void
    {
        if ($this->hasParameter($name)) {
            unset($this->parameters[$name]);
        } elseif (\strpos($name, '.') !== false) {
            $parts = \explode('.', $name);
            $array = &$this->parameters;

            while (\count($parts) > 1) {
                $part = \array_shift($parts);

                if (isset($array[$part]) && \is_array($array[$part])) {
                    $array = &$array[$part];
                }
            }

            unset($array[\array_shift($parts)]);
        }
    }

    /**
     * @internal
     *
     * Gets a dot-notated key from an array/object, with a default value if it does
     * not exist
     *
     * @param array|object $array   The search array
     * @param mixed        $key     The dot-notated key or array of keys
     * @param string       $default The default value
     *
     * @throws InvalidArgumentException
     *
     * @return mixed
     */
    public static function arrayGet($array, $key, $default = null)
    {
        if (!\is_array($array) && !$array instanceof ArrayAccess) {
            throw new InvalidArgumentException('First parameter must be an array or ArrayAccess object.');
        }

        if (null === $key) {
            return $array;
        }

        if (\is_array($key)) {
            $return = [];

            foreach ($key as $k) {
                $return[$k] = self::arrayGet($array, $k, $default);
            }

            return $return;
        }

        if (\is_object($key)) {
            $key = (string) $key;
        }

<<<<<<< HEAD
        if (\array_key_exists($key, $array)) {
=======
        if (isset($array[$key])) {
>>>>>>> master
            return $array[$key];
        }

        foreach (\explode('.', $key) as $field) {
            if (\is_object($array) && isset($array->{$field})) {
                $array = $array->{$field};
            } elseif (\is_array($array) && isset($array[$field])) {
                $array = $array[$field];
            } else {
                return $default;
            }
        }

        return $array;
    }
}
