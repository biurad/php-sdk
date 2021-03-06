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

use Biurad\Framework\Debug\Template\TemplatesPanel;
use Biurad\DependencyInjection\Extension;
use Biurad\UI\Helper\SlotsHelper;
use Biurad\UI\Interfaces\RenderInterface;
use Biurad\UI\Interfaces\TemplateInterface;
use Biurad\UI\Renders\LatteRender;
use Biurad\UI\Renders\PhpNativeRender;
use Biurad\UI\Renders\TwigRender;
use Biurad\UI\Storage\CacheStorage;
use Biurad\UI\Storage\FilesystemStorage;
use Biurad\UI\Template;
use Latte\Bridges\Tracy\LattePanel;
use Nette;
use Nette\DI\Definitions\Reference;
use Nette\DI\Definitions\Statement;
use Nette\Schema\Expect;

class TemplatingExtension extends Extension
{
    /** @var string */
    private $tempPath;

    /**
     * @param null|string $tempPath
     */
    public function __construct(?string $tempPath = null)
    {
        $this->tempPath = $tempPath;
    }

    /**
     * {@inheritDoc}
     */
    public function getConfigSchema(): Nette\Schema\Schema
    {
        return Nette\Schema\Expect::structure([
            'paths'          => Nette\Schema\Expect::listOf(Expect::string()->assert('is_dir'))
                ->before(function ($value) {
                    return \is_string($value) ? [$value] : $value;
                }),
            'cache_path'    => Nette\Schema\Expect::string()->default($this->tempPath),
            'namespaces'    => Nette\Schema\Expect::arrayOf(Expect::string()->assert('is_dir'))
                ->before(function ($value) {
                    return \is_string($value) ? [$value] : $value;
                }),
            'globals'       => Nette\Schema\Expect::array(),
        ])->castTo('array');
    }

    /**
     * {@inheritDoc}
     */
    public function loadConfiguration(): void
    {
        $container = $this->getContainerBuilder();

        if (!class_exists(Template::class)) {
            return;
        }

        if (null !== $this->tempPath && !is_dir($this->tempPath)) {
            @mkdir($this->tempPath. 0777, true);
        }

        $filesystemLoader = new Statement(FilesystemStorage::class, [$this->getFromConfig('paths')]);
        $cacheLoader      = new Statement(CacheStorage::class, [$filesystemLoader, $this->getFromConfig('cache_path')]);

        if (\class_exists('Latte\Engine')) {
            $container->register($this->prefix('latte_engine'), 'Latte\Engine')
                ->addSetup('setTempDirectory', [$this->getFromConfig('cache_path')])
                ->addSetup('setAutoRefresh', [$container->getParameter('debugMode')]);

            $container->register($this->prefix('render_latte'), LatteRender::class);
        }

        if (\class_exists('Twig\Environment')) {
            $container->register('twig_environment', 'Twig\Environment')
                ->setArguments([new Statement('Twig\Loader\ArrayLoader'), [
                    'cache' => $this->getFromConfig('cache_path') ?? false,
                    'debug' => $container->getParameter('debugMode'),
                ]]);

            $container->register($this->prefix('render_twig'), TwigRender::class);
        }

        $factory = $container->register($this->prefix('factory'), Template::class)
            ->setArguments(
                [
                    !$container->getParameter('debugMode') ? $cacheLoader : $filesystemLoader,
                    $container->getParameter('debugMode'),
                    [],
                ]
            );

        foreach ($this->getFromConfig('globals') as $key => $value) {
            $factory->addSetup('addGobal', [$key, $value]);
        }

        foreach ($this->getFromConfig('namespaces') as $name => $hints) {
            $factory->addSetup('addNamespace', [$name, $hints]);
        }

        $container->register($this->prefix('render_php'), PhpNativeRender::class)
            ->addSetup('set', [new Statement(SlotsHelper::class)]);

        $container->addAlias('templating', $this->prefix('factory'));
    }

    /**
     * {@inheritdoc}
     */
    public function beforeCompile(): void
    {
        if (!interface_exists(RenderInterface::class)) {
            return;
        }

        $container  = $this->getContainerBuilder();
        $type       = $container->findByType(RenderInterface::class);
        $template   = $container->getDefinitionByType(TemplateInterface::class);

        // Register as services
        foreach ($this->getHelper()->getServiceDefinitionsFromDefinitions($type) as $definition) {
            $template->addSetup('addRender', [$definition]);
        }

        if ($container->getParameter('debugMode')) {
            if (class_exists(LattePanel::class)) {
                $template->addSetup(
                    [new Reference('Tracy\Bar'), 'addPanel'],
                    [new Statement(LattePanel::class, [new Reference('Latte\Engine')])]
                );
            }

            $template->addSetup([new Reference('Tracy\Bar'), 'addPanel'], [new Statement(TemplatesPanel::class, [$template])]);
        }
    }
}
