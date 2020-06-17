<?php

declare(strict_types=1);

/*
 * This file is part of BiuradPHP opensource projects.
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

namespace BiuradPHP\MVC\Compilers;

use ArrayAccess;
use BiuradPHP;
use BiuradPHP\DependencyInjection\Compiler\AbstractCompilerPass;
use BiuradPHP\DependencyInjection\Concerns\ContainerBuilder;
use Countable;
use Iterator;
use IteratorAggregate;
use JsonSerializable;
use Nette;
use ReflectionClass;
use ReflectionException;
use Serializable;
use SplDoublyLinkedList;
use SplStack;
use stdClass;
use Tracy;
use Traversable;

class ExtensionCompilerPass extends AbstractCompilerPass
{
    /** @var string[] of classes which shouldn't be autowired */
    private const EXCLUDED_CLASSES = [
        ArrayAccess::class,
        Countable::class,
        IteratorAggregate::class,
        SplDoublyLinkedList::class,
        stdClass::class,
        SplStack::class,
        Iterator::class,
        Traversable::class,
        Serializable::class,
        JsonSerializable::class,
    ];

    /** @var array [id => CompilerExtension] */
    private $extensions = [
        'php'           => Nette\DI\Extensions\PhpExtension::class,
        'loader'        => BiuradPHP\Loader\Bridges\LoaderExtension::class,
        'framework'     => BiuradPHP\MVC\Bridges\FrameworkExtension::class,
        'events'        => [BiuradPHP\Events\Bridges\EventsExtension::class, ['%env.DEBUG%']],
        'caching'       => BiuradPHP\Cache\Bridges\CacheExtension::class,
        'constants'     => Nette\DI\Extensions\ConstantsExtension::class,
        'database'      => [BiuradPHP\Database\Bridges\DatabaseExtension::class, ['%env.CONSOLE%']],
        'cycle'         => BiuradPHP\CycleORM\Bridges\CycleExtension::class,
        'decorator'     => Nette\DI\Extensions\DecoratorExtension::class,
        'di'            => [Nette\DI\Extensions\DIExtension::class, ['%env.DEBUG%']],
        'extensions'    => Nette\DI\Extensions\ExtensionsExtension::class,
        'http'          => [BiuradPHP\Http\Bridges\HttpExtension::class, ['%path.TEMP%/caches/biurad.http']],
        'inject'        => Nette\DI\Extensions\InjectExtension::class,
        'annotation'    => [BiuradPHP\Annotation\Bridges\AnnotationsExtension::class, ['%env.DEBUG%']],
        'templating'    => [BiuradPHP\Template\Bridges\TemplateExtension::class, ['%env.DEBUG%']],
        'filemanager'   => BiuradPHP\FileManager\Bridges\FileManagerExtension::class,
        'routing'       => [BiuradPHP\Routing\Bridges\RoutingExtension::class, ['%env.DEBUG%', '%env.DEPLOY%']],
        'search'        => [Nette\DI\Extensions\SearchExtension::class, ['%path.TEMP%/caches/biurad.searches']],
        'session'       => [BiuradPHP\Session\Bridges\SessionExtension::class, ['%path.TEMP%/caches/biurad.sessions']],
        'security'      => BiuradPHP\Security\Bridges\SecurityExtension::class,
        'tracy'         => [Tracy\Bridges\Nette\TracyExtension::class, ['%env.DEBUG%', '%env.CONSOLE%']],
        'monolog'       => BiuradPHP\Monolog\Bridges\MonologExtension::class,
        'terminal'      => [BiuradPHP\Terminal\Bridges\TerminalExtension::class, ['%env.CONSOLE%']],
        'scaffolder'    => [BiuradPHP\Scaffold\Bridges\ScaffoldExtension::class, ['%env.CONSOLE%']],
    ];

    /**
     * {@inheritdoc}
     *
     * @throws ReflectionException
     */
    public function process(ContainerBuilder $container): void
    {
        $container->addExcludedClasses(self::EXCLUDED_CLASSES);

        foreach ($this->extensions as $name => $extension) {
            [$class, $args] = \is_string($extension) ? [$extension, []] : $extension;

            if (\class_exists($class)) {
                $args = Nette\DI\Helpers::expand($args, $this->getCompiler()->getParameters(), true);

                /* @noinspection PhpParamsInspection */
                $this->getCompiler()->addExtension($name, (new ReflectionClass($class))->newInstanceArgs($args));
            }
        }
    }
}
