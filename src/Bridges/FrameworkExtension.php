<?php

declare(strict_types=1);

/*
 * This code is under BSD 3-Clause "New" or "Revised" License.
 *
 * ---------------------------------------------------------------------------
 * BiuradPHP Framework is a new scheme of php architecture which is simple,  |
 * yet has powerful features. The framework has been built carefully 	     |
 * following the rules of the new PHP 7.2 and 7.3 above, with no support     |
 * for the old versions of PHP. As this framework was inspired by            |
 * several conference talks about the future of PHP and its development,     |
 * this framework has the easiest and best approach to the PHP world,        |
 * of course, using a few intentionally procedural programming module.       |
 * This makes BiuradPHP framework extremely readable and usable for all.     |
 * BiuradPHP is a 35% clone of symfony framework and 30% clone of Nette	     |
 * framework. The performance of BiuradPHP is 300ms on development mode and  |
 * on production mode it's even better with great defense security.          |
 * ---------------------------------------------------------------------------
 *
 * PHP version 7.2 and above required
 *
 * @category  BiuradPHP-Framework
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 Biurad Group (https://biurad.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * @link      https://www.biurad.com/projects/biurad-framework
 */

namespace BiuradPHP\MVC\Bridges;

use BiuradPHP\DependencyInjection\Concerns\Compiler;
use BiuradPHP\DependencyInjection\Concerns\ImportsLocator;
use BiuradPHP\DependencyInjection\Interfaces\PassCompilerAwareInterface;
use BiuradPHP\Loader\Locators\AnnotationLocator;
use BiuradPHP\MVC\Application;
use BiuradPHP\MVC\Compilers\DisptacherPassCompiler;
use BiuradPHP\MVC\Dispatchers\SapiDispatcher;
use BiuradPHP\MVC\EventListeners\ErrorListener;
use BiuradPHP\MVC\EventListeners\KernelListener;
use BiuradPHP\MVC\EventListeners\RouterListener;
use BiuradPHP\MVC\Exceptions\ErrorResponseGenerator;
use Nette\PhpGenerator\ClassType as ClassTypeGenerator;
use Nette, BiuradPHP;
use Nette\DI\Definitions\Reference;
use Nette\DI\Definitions\Statement;
use Nette\Schema\Expect;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class FrameworkExtension extends BiuradPHP\DependencyInjection\CompilerExtension implements PassCompilerAwareInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigSchema(): Nette\Schema\Schema
    {
        return Nette\Schema\Expect::structure([
            'security'          => Nette\Schema\Expect::bool(false),
            'error_template'    => Nette\Schema\Expect::string(),
            'annotations'       => Nette\Schema\Expect::listOf(Expect::string()->assert('class_exists')),
            'module_system'     => Nette\Schema\Expect::structure([
                'enable'    => Nette\Schema\Expect::bool(false),
                'path'      => Nette\Schema\Expect::string()
            ])->castTo('array'),
            'demo_restriction'  => Nette\Schema\Expect::bool(),
            'dispatchers'       => Nette\Schema\Expect::arrayOf(Expect::string()->assert('class_exists'))->nullable(),
            'imports'           => Nette\Schema\Expect::list()->nullable()
        ])->castTo('array');
    }

    /**
     * {@inheritDoc}a
     */
    public function loadConfiguration()
    {
        $builder = $this->getContainerBuilder();

        foreach ($this->compiler->getExtensions() as $name => $extension) {
            foreach ($this->config['imports'] ?? [] as $resource) {
                try {
                    $path = ImportsLocator::getLocation($extension, $resource);
                } catch (Nette\NotSupportedException $e) {
                    continue;
                }

                $this->compiler->loadDefinitionsFromConfig([$name => $this->loadFromFile($path)]);
            }
        }

        // Incase no logger service...
        if (null === $builder->getByType(LoggerInterface::class)) {
            $builder->register($this->prefix('logger'), NullLogger::class);
        }

        $builder->register($this->prefix('router.listener'), RouterListener::class);
        $builder->register($this->prefix('kernel.listener'), KernelListener::class);
        $builder->register($this->prefix('error.listener'), ErrorListener::class);

        $builder->register($this->prefix('dispatcher.web'), SapiDispatcher::class);

        $framework = $builder->register($this->prefix('app'), new Statement([Application::class, 'init']))
            ->addSetup('setLogger');

        foreach ($this->config['dispatchers'] ?? [] as $dispatcher) {
            $framework->addSetup('addDispatcher', [new Statement($dispatcher)]);
        }

        $error = $builder->register($this->prefix('errorhandler'), ErrorResponseGenerator::class)
            ->setArguments([[new Reference(ResponseFactoryInterface::class), 'createResponse'], $builder->getParameter('env.DEBUG')]);

        if (null !== $errorTemplate = $this->config['error_template']) {
            $error->setArgument('template', $errorTemplate);
        }

        $builder->addAlias('application', $this->prefix('app'));
    }

    /**
     * {@inheritdoc}
     */
    public function addCompilerPasses(Compiler &$compiler): void
    {
        $compiler->addPass(new DisptacherPassCompiler);
    }

    /**
     * {@inheritDoc}
     */
    public function afterCompile(ClassTypeGenerator $class): void
    {
        $init = $this->initialization ?? $class->getMethod('initialize');
        if (empty($this->config['annotations'])) {
            return;
        }

        $init->addBody('// Register all annotations for framework.
foreach (? as $annotation) {
    $this->get($annotation)->register($this->createInstance(?)); // For Runtime.
}', [$this->config['annotations'], AnnotationLocator::class]
        );
    }
}
