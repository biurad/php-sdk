<?php
/** @noinspection PhpUndefinedMethodInspection */

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

namespace BiuradPHP\MVC;

use Nette;
use ReflectionClass;
use ReflectionException;
use Tracy;
use Composer;
use BiuradPHP;
use InvalidArgumentException;
use BiuradPHP\DependencyInjection\Config\Adapter;
use BiuradPHP\MVC\Compilers\ExtensionCompilerPass;
use BiuradPHP\DependencyInjection\Config as DIConfig;

use function BiuradPHP\Support\detect_debug_mode;
use function BiuradPHP\Support\detect_environment;
use function BiuradPHP\Support\env;

/**
 * Initial system DependencyInjection container generator.
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @license   BSD-3-Clause
 */
class CoreKernel
{
    use Nette\SmartObject;

    public const COOKIE_SECRET = 'BF_SESSID';

    /** @var callable[] function (Configurator $sender, DI\Compiler $compiler); Occurs after the compiler is created */
    public $onCompile;

    /** @var array */
    protected $parameters;

    /** @var array */
    protected $dynamicParameters = [];

    /** @var array */
	protected $services = [];

    /** @var array of string|array */
    protected $configs = [];

    public function __construct(string $rootPath)
    {
        $this->parameters = $this->getDefaultParameters($rootPath);
    }

    /**
     * Set parameter %env.DEBUG%.
     *
     * @param bool|string|array $value
     *
     * @return static
     */
    public function setDebugMode($value)
    {
        if (is_string($value) || is_array($value)) {
            $value = detect_debug_mode($value, self::COOKIE_SECRET);
        } elseif (!is_bool($value)) {
            throw new InvalidArgumentException(
                sprintf('Value must be either a string, array, or boolean, %s given.', gettype($value))
            );
        }

        $this->parameters['env']['DEBUG'] = $value;
        $this->parameters['env']['DEPLOY'] = !$this->parameters['env']['DEBUG']; // compatibility

        if (true === $this->parameters['env']['DEPLOY']) {
            $this->parameters['env']['ENVIRONMENT'] = 'production';
        } elseif (true === $this->parameters['env']['DEBUG']) {
            $this->parameters['env']['ENVIRONMENT'] = 'development';
        }

        return $this;
    }

    /**
     * Check the debugmode is active.
     */
    public function isDebugMode(): bool
    {
        return $this->parameters['env']['DEBUG'];
    }

    /**
     * Sets path to temporary directory.
     *
     * @param string $path
     *
     * @return static
     */
    public function setTempDirectory(string $path)
    {
        $this->parameters['path']['TEMP'] = $path;

        return $this;
    }

    /**
     * Sets the default timezone.
     *
     * @param string $timezone
     *
     * @return static
     */
    public function setTimeZone(string $timezone)
    {
        date_default_timezone_set($timezone);
        @ini_set('date.timezone', $timezone); // @ - function may be disabled

        return $this;
    }

    /**
     * Adds new parameters. The %params% will be expanded.
     *
     * @param array $params
     *
     * @return static
     */
    public function addParameters(array $params)
    {
        $this->parameters = Nette\DI\Config\Helpers::merge($params, $this->parameters);

        return $this;
    }

    /**
     * Adds new dynamic parameters.
     *
     * @param array $params
     *
     * @return static
     */
    public function addDynamicParameters(array $params)
    {
        $this->dynamicParameters = array_merge($params, $this->dynamicParameters);

        return $this;
    }

    /**
     * The Default parameters.
     *
     * @param string $rootPath
     *
     * @return array
     */
    public function getDefaultParameters(string $rootPath): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $last = end($trace);
        $debugMode = detect_debug_mode(null, self::COOKIE_SECRET);
        $cli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');

        try {
            $loaderRc = new ReflectionClass(Composer\Autoload\ClassLoader::class);
        } catch (ReflectionException $e) {
            $loaderRc = null;
        }

        return [
            'path' => [
                'ROOT'      => realpath($rootPath),
                'APP'       => isset($trace[1]['file']) ? dirname($trace[1]['file']) : $rootPath . '/app',
                'PUBLIC'    => isset($last['file']) ? dirname($last['file']) : $rootPath . '/public',
                'VENDOR'    => $loaderRc ? dirname($loaderRc->getFileName(), 2) : $rootPath . '/vendor',
            ],
            'env' => [
                'ENVIRONMENT'   => detect_environment($debugMode),
                'DEPLOY'        => $cli ? false : !$debugMode,
                'DEBUG'         => $cli ? true : $debugMode,
                'URL'           => env('APP_URL'),
                'NAME'          => env('APP_NAME'),
                'CONSOLE'       => $cli
            ]
        ];
    }

    public function enableTracy(string $logDirectory = null, string $email = null): void
	{
		Tracy\Debugger::$strictMode = true;
        Tracy\Debugger::enable($this->parameters['env']['DEPLOY'], $logDirectory, $email);
		Tracy\Bridges\Nette\Bridge::initialize();
	}

    /**
     * @throws Nette\NotSupportedException if RobotLoader is not available
     */
    public function createRobotLoader(): Nette\Loaders\RobotLoader
    {
        if (!class_exists(Nette\Loaders\RobotLoader::class)) {
            throw new Nette\NotSupportedException('RobotLoader not found, do you have [nette/robot-loader] package installed?');
        }

        $loader = new Nette\Loaders\RobotLoader();
        $loader->setTempDirectory($this->getCacheDirectory().'/biurad.robotLoader');
        $loader->setAutoRefresh($this->parameters['env']['DEBUG']);

        return $loader;
    }

    /**
     * Adds configuration file.
     *
     * @param string|array $config
     *
     * @return static
     */
    public function addConfig($config)
    {
        $this->configs[] = $config;

        return $this;
    }

    /**
     * Add instances of services.
     *
     * @param array $services
     *
     * @return static
     */
	public function addServices(array $services)
	{
		$this->services = array_merge($services, $this->services);
		return $this;
	}

    /**
     * Returns system DI container.
     */
    public function createContainer(): BiuradPHP\DependencyInjection\Container
    {
        $class = $this->loadContainer();
        $container = new $class($this->dynamicParameters);

        foreach ($this->services as $name => $service) {
			$container->addService($name, $service);
		}

        $container->initialize();

        return $container;
    }

    /**
     * Loads system DI container class and returns its name.
     */
    public function loadContainer(): string
    {
        $loader = new BiuradPHP\DependencyInjection\Concerns\ContainerLoader(
            $this->getCacheDirectory().'/biurad.container', $this->parameters['env']['DEBUG']
        );
        $class = $loader->load(
            [$this, 'generateContainer'],
            [$this->parameters, array_keys($this->dynamicParameters), $this->configs, PHP_VERSION_ID - PHP_RELEASE_VERSION]
        );

        return $class;
    }

    /**
     * @param BiuradPHP\DependencyInjection\Concerns\Compiler $compiler
     *
     * @internal
     */
    public function generateContainer(BiuradPHP\DependencyInjection\Concerns\Compiler $compiler): void
    {
        $loader = $this->createLoader();
        $loader->setParameters($this->parameters);

        foreach ($this->configs as $config) {
            if (is_string($config)) {
                $compiler->loadConfig($config, $loader);
            } else {
                $compiler->addConfig($config);
            }
        }

        $compiler->addConfig(['parameters' => $this->parameters]);
        $compiler->setDynamicParameterNames(array_keys($this->dynamicParameters));

        $defaultExtensions = new ExtensionCompilerPass;
        $defaultExtensions = $defaultExtensions->setCompiler($compiler);

        /** @noinspection PhpParamsInspection */
        $defaultExtensions->process($compiler->getContainerBuilder());

        $this->onCompile($this, $compiler);
    }

    /**
     * The configuration Loader.
     */
    protected function createLoader(): Nette\DI\Config\Loader
    {
        $loader = new DIConfig\Loader();
        $loader->addAdapter('ini', Adapter\IniAdapter::class);
        $loader->addAdapter('xml', Adapter\XmlAdapter::class);

        return $loader;
    }

    /**
     * Get the Caches directory.
     *
     * @return string
     */
    protected function getCacheDirectory(): string
    {
        if (empty($this->parameters['path']['TEMP'])) {
            throw new Nette\InvalidStateException('Set path to temporary directory using setTempDirectory().');
        }
        $dir = $this->parameters['path']['TEMP'].'/caches';
        Nette\Utils\FileSystem::createDir($dir);

        return $dir;
    }
}
