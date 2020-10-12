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

namespace Biurad\Framework\DependencyInjection\Definitions;

use Nette;
use Nette\DI\Definitions;
use Nette\DI\ServiceCreationException;

/**
 * Definition of standard service.
 */
final class InterfaceDefinition extends Definitions\Definition
{
    /** @var Definitions\Definition|Definitions\ServiceDefinition */
    private $resultDefinition;

    public function __construct()
    {
        $this->resultDefinition = new Definitions\ServiceDefinition();
    }

    public function __clone()
    {
        parent::__clone();
        $this->resultDefinition = \unserialize(\serialize($this->resultDefinition));
    }

    /** @return static */
    public function setImplement(string $type)
    {
        if (!\interface_exists($type)) {
            throw new Nette\InvalidArgumentException("Service '{$this->getName()}': Interface '$type' not found.");
        }

        $this->resultDefinition->setName($this->getName());

        return parent::setType($type);
    }

    public function getImplement(): ?string
    {
        return $this->getType();
    }

    final public function getResultType(): ?string
    {
        return $this->resultDefinition->getType();
    }

    /** @return static */
    public function setResultDefinition(Definitions\Definition $definition)
    {
        $this->resultDefinition = $definition;

        return $this;
    }

    /** @return Definitions\ServiceDefinition */
    public function getResultDefinition(): Definitions\Definition
    {
        return $this->resultDefinition;
    }

    public function resolveType(Nette\DI\Resolver $resolver): void
    {
        $resultDef = $this->resultDefinition;

        try {
            $resolver->resolveDefinition($resultDef);

            return;
        } catch (ServiceCreationException $e) {
        }

        if (!$resultDef->getType()) {
            $interface = $this->getType();

            if (!$interface || !\interface_exists($interface)) {
                throw new ServiceCreationException('Type is missing in definition of service.');
            }

            $resultDef->setType($interface);
        }

        $resolver->resolveDefinition($resultDef);
    }

    public function complete(Nette\DI\Resolver $resolver): void
    {
        $resultDef = $this->resultDefinition;

        if ($resultDef instanceof Definitions\ServiceDefinition) {
            if ($resultDef->getEntity() instanceof Definitions\Reference && !$resultDef->getFactory()->arguments) {
                $resultDef->setFactory([ // render as $container->createMethod()
                    new Definitions\Reference(Nette\DI\ContainerBuilder::THIS_CONTAINER),
                    Nette\DI\Container::getMethodName($resultDef->getEntity()->getValue()),
                ]);
            }
        }

        $resolver->completeDefinition($resultDef);
    }

    public function generateMethod(Nette\PhpGenerator\Method $method, Nette\DI\PhpGenerator $generator): void
    {
        $class = (new Nette\PhpGenerator\ClassType())
            ->addImplement($this->getType());

        if (!\is_string($entity = $this->resultDefinition->getEntity())) {
            throw new ServiceCreationException(
                \sprintf('Type entity must be a string of interface, \'%s\' given', \gettype($entity))
            );
        }

        $code = new Definitions\Statement('class', $this->resultDefinition->getFactory()->arguments);
        $code = $generator->formatStatement($code);

        $method->setBody('$service = ' . "{$code} " . $class->addExtend($entity) . ';');

        if (!empty($this->resultDefinition->getSetup())) {
            $setups = '';

            foreach ($this->resultDefinition->getSetup() as $setup) {
                $setups .= $generator->formatStatement($setup) . ";\n";
            }

            $method->addBody("\n" . $setups);
        }

        $method->addBody('return $service;');
    }
}
