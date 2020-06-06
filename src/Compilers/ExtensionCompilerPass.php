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

namespace BiuradPHP\MVC\Compilers;

use ArrayAccess;
use Countable;
use BiuradPHP, Nette, Tracy;
use IteratorAggregate;
use ReflectionClass;
use ReflectionException;
use stdClass;
use Traversable;
use BiuradPHP\DependencyInjection\Concerns\ContainerBuilder;
use BiuradPHP\DependencyInjection\Compiler\AbstractCompilerPass;
use Iterator;
use JsonSerializable;
use Serializable;
use SplDoublyLinkedList;
use SplStack;

class ExtensionCompilerPass extends AbstractCompilerPass
{
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
        'http'          => [BiuradPHP\Http\Bridges\HttpExtension::class, ['%env.DEBUG%', '%path.TEMP%/caches/biurad.http']],
        'inject'        => Nette\DI\Extensions\InjectExtension::class,
        'annotation'    => [BiuradPHP\Annotation\Bridges\AnnotationsExtension::class, ['%env.DEBUG%']],
        'templating'    => [BiuradPHP\Template\Bridges\TemplateExtension::class, ['%env.DEBUG%']],
        'filemanager'   => BiuradPHP\FileManager\Bridges\FileManagerExtension::class,
        'routing'       => [BiuradPHP\Routing\Bridges\RoutingExtension::class, ['%env.DEBUG%', '%env.DEPLOY%']],
        'search'        => [Nette\DI\Extensions\SearchExtension::class, ['%path.TEMP%/caches/biurad.searches']],
        'session'       => [BiuradPHP\Session\Bridges\SessionExtension::class,['%path.TEMP%/caches/biurad.sessions']],
        'security'      => BiuradPHP\Security\Bridges\SecurityExtension::class,
        'tracy'         => [Tracy\Bridges\Nette\TracyExtension::class, ['%env.DEBUG%', '%env.CONSOLE%']],
        'monolog'       => BiuradPHP\Monolog\Bridges\MonologExtension::class,
        'terminal'      => [BiuradPHP\Terminal\Bridges\TerminalExtension::class, ['%env.CONSOLE%']],
        'scaffolder'    => [BiuradPHP\Scaffold\Bridges\ScaffoldExtension::class, ['%env.CONSOLE%']]
    ];

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

    /**
     * {@inheritdoc}
     * @throws ReflectionException
     */
    public function process(ContainerBuilder $container)
    {
        $container->addExcludedClasses(self::EXCLUDED_CLASSES);

        foreach ($this->extensions as $name => $extension) {
            [$class, $args] = is_string($extension) ? [$extension, []] : $extension;

            if (class_exists($class)) {
                $args = Nette\DI\Helpers::expand($args, $this->getCompiler()->getParameters(), true);

                /** @noinspection PhpParamsInspection */
                $this->getCompiler()->addExtension($name, (new ReflectionClass($class))->newInstanceArgs($args));
            }
        }
    }
}
