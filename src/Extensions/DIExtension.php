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

namespace Biurad\Framework\Extensions;

use Biurad\DependencyInjection\ContainerPanel;
use Biurad\Framework\Commands\Debug\ContainerCommand;
use Nette;

/**
 * DI extension.
 */
final class DIExtension extends Nette\DI\CompilerExtension
{
    /** @var array */
    public $exportedTags = [];

    /** @var array */
    public $exportedTypes = [];

    /** @var bool */
    private $debugMode;

    /** @var float */
    private $time;

    public function __construct(bool $debugMode = false)
    {
        $this->debugMode = $debugMode;
        $this->time      = \microtime(true);

        $this->config = new class() {
            /** @var bool */
            public $debugger;

            /** @var string[] */
            public $excluded = [];

            /** @var ?string */
            public $parentClass;

            /** @var object */
            public $export;
        };
        $this->config->export = new class() {
            /** @var bool */
            public $parameters = true;

            /** @var null|bool|string[] */
            public $tags = true;

            /** @var null|bool|string[] */
            public $types = true;
        };
        $this->config->debugger = \interface_exists(\Tracy\IBarPanel::class);
    }

    public function loadConfiguration(): void
    {
        $builder = $this->getContainerBuilder();
        $builder->addExcludedClasses($this->config->excluded);

        if ($builder->parameters['consoleMode']) {
            $builder->addDefinition($this->prefix('command_debug'))
                ->setFactory(ContainerCommand::class)
                ->addTag('console.command', 'debug:container');
        }
    }

    public function beforeCompile(): void
    {
        if (!$this->config->export->parameters) {
            $this->getContainerBuilder()->parameters = [];
        }
    }

    public function afterCompile(Nette\PhpGenerator\ClassType $class): void
    {
        if ($this->config->parentClass) {
            $class->setExtends($this->config->parentClass);
        }

        $this->restrictTags($class);
        $this->restrictTypes($class);

        if ($this->debugMode && $this->config->debugger) {
            $this->enableTracyIntegration();
        }

        $this->initializeTaggedServices();
    }

    private function restrictTags(Nette\PhpGenerator\ClassType $class): void
    {
        $option = $this->config->export->tags;

        if ($option === true) {
        } elseif ($option === false) {
            $class->removeProperty('tags');
        } elseif ($prop = $class->getProperties()['tags'] ?? null) {
            $prop->value = \array_intersect_key($prop->value, $this->exportedTags + \array_flip((array) $option));
        }
    }

    private function restrictTypes(Nette\PhpGenerator\ClassType $class): void
    {
        $option = $this->config->export->types;

        if ($option === true) {
            return;
        }
        $prop        = $class->getProperty('wiring');
        $prop->value = \array_intersect_key(
            $prop->value,
            $this->exportedTypes + (\is_array($option) ? \array_flip($option) : [])
        );
    }

    private function initializeTaggedServices(): void
    {
        foreach (\array_filter($this->getContainerBuilder()->findByTag('run')) as $name => $on) {
            \trigger_error("Tag 'run' used in service '$name' definition is deprecated.", \E_USER_DEPRECATED);
            $this->initialization->addBody('$this->getService(?);', [$name]);
        }
    }

    private function enableTracyIntegration(): void
    {
        ContainerPanel::$compilationTime = $this->time;
        $this->initialization->addBody($this->getContainerBuilder()->formatPhp('?;', [
            new Nette\DI\Definitions\Statement(
                '@Tracy\Bar::addPanel',
                [new Nette\DI\Definitions\Statement(ContainerPanel::class)]
            ),
        ]));
    }
}
