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

use Biurad\Framework\Container;
use Exception;
use Nette;
use Nette\DI\ContainerBuilder;
use Nette\DI\Definitions;
use Nette\DI\PhpGenerator as NettePhpGenerator;
use Nette\DI\ServiceCreationException;
use Nette\PhpGenerator as Php;
use Throwable;

/**
 * Container PHP code generator.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class PhpGenerator extends NettePhpGenerator
{
    /** @var ContainerBuilder */
    private $builder;

    /** @var string */
    private $className;

    public function __construct(ContainerBuilder $builder)
    {
        $this->builder = $builder;

        parent::__construct($builder);
    }

    /**
     * Generates PHP classes. First class is the container.
     *
     * @param string $className
     *
     * @return Nette\PhpGenerator\ClassType
     */
    public function generate(string $className): Nette\PhpGenerator\ClassType
    {
        $this->className = $className;
        $class           = new Nette\PhpGenerator\ClassType($this->className);
        $class->setExtends(Container::class);
        $class->addMethod('__construct')
            ->addBody('parent::__construct($params);')
            ->addParameter('params', [])
                ->setType('array');

        foreach ($this->builder->exportMeta() as $key => $value) {
            $class->addProperty($key)
                ->setProtected()
                ->setValue($value);
        }

        $definitions = $this->builder->getDefinitions();
        \ksort($definitions);

        foreach ($definitions as $def) {
            $class->addMember($this->generateMethod($def));
        }

        $class->getMethod(Container::getMethodName(ContainerBuilder::THIS_CONTAINER))
            ->setReturnType($className)
            ->setProtected()
            ->setBody('return $this; //container instance is binded to it self');

        $class->addMethod('initialize');

        return $class;
    }

    /**
     * @param Nette\PhpGenerator\ClassType $class
     *
     * @throws Throwable
     *
     * @return string
     */
    public function toString(Nette\PhpGenerator\ClassType $class): string
    {
        return '/** @noinspection PhpParamsInspection,PhpMethodMayBeStaticInspection */

declare(strict_types=1);

/**
 * Main DependencyInjection Container. This class has been auto-generated
 * by the Nette Dependency Injection Component.
 *
 * Automatically detects if "container" property are presented in class or uses
 * global container as fallback.
 *
 */
' . (string) $class;
    }

    public function generateMethod(Definitions\Definition $def): Nette\PhpGenerator\Method
    {
        $name     = $def->getName();
        $comment  = "This method is an instance of %s.\n\nThe instance can be accessed by it's name in lower case,";
        $comment2 = 'thus `%s`, using container get or make methods.';

        try {
            $method = new Nette\PhpGenerator\Method(Container::getMethodName($name));
            $method->setProtected();
            $method->setComment(\sprintf($comment . "\n" . $comment2, $def->getType(), $name));
            $method->setReturnType($def->getType());
            $def->generateMethod($method, $this);

            return $method;
        } catch (Exception $e) {
            throw new ServiceCreationException("Service '$name': " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Formats PHP statement.
     *
     * @internal
     */
    public function formatPhp(string $statement, array $args): string
    {
        \array_walk_recursive($args, function (&$val): void {
            if ($val instanceof Definitions\Statement) {
                $val = new Php\Literal($this->formatStatement($val));
            } elseif ($val instanceof Definitions\Reference) {
                $name = $val->getValue();

                if ($val->isSelf()) {
                    $val = new Php\Literal('$service');
                } elseif ($name === ContainerBuilder::THIS_CONTAINER) {
                    $val = new Php\Literal('$this');
                } else {
                    $val = ContainerBuilder::literal('$this->getService(?)', [$name]);
                }
            }
        });

        return (new Nette\PhpGenerator\Dumper())->format($statement, ...$args);
    }

    public function getClassName(): ?string
    {
        return $this->className;
    }
}
