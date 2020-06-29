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

namespace BiuradPHP\MVC;

use BiuradPHP;
use BiuradPHP\DependencyInjection\Config\Adapter;
use BiuradPHP\DependencyInjection\Config as DIConfig;
use BiuradPHP\MVC\Compilers\ExtensionCompilerPass;
use Composer;
use InvalidArgumentException;
use Nette;
use ReflectionClass;
use ReflectionException;
use Tracy;

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

    /** @var callable[] function (Configurator, DI\Compiler); Occurs after the compiler is created */
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
        $this->parameters = self::escape($this->getDefaultParameters($rootPath));
    }

    /**
     * Set parameter %env.DEBUG%.
     *
     * @param array|bool|string $value
     *
     * @return static
     */
    public function setDebugMode($value)
    {
        if (\is_string($value) || \is_array($value)) {
            $value = detect_debug_mode($value, self::COOKIE_SECRET);
        } elseif (!\is_bool($value)) {
            throw new InvalidArgumentException(
                \sprintf('Value must be either a string, array, or boolean, %s given.', \gettype($value))
            );
        }

        $this->parameters['env']['DEBUG']  = $value;
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
        $this->parameters['path']['TEMP'] = self::escape($path);

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
        \date_default_timezone_set($timezone);
        @\ini_set('date.timezone', $timezone); // @ - function may be disabled

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
        $this->parameters = Nette\Schema\Helpers::merge($params, $this->parameters);

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
        $this->dynamicParameters = \array_merge($params, $this->dynamicParameters);

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
        $trace     = \debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS);
        $last      = \end($trace);
        $debugMode = detect_debug_mode(null, self::COOKIE_SECRET);
        $cli       = (\PHP_SAPI === 'cli' || \PHP_SAPI === 'phpdbg');

        try {
            $loaderRc = new ReflectionClass(Composer\Autoload\ClassLoader::class);
        } catch (ReflectionException $e) {
            $loaderRc = null;
        }

        return [
            'path' => [
                'ROOT'      => \realpath($rootPath),
                'APP'       => isset($trace[1]['file']) ? \dirname($trace[1]['file']) : $rootPath . '/app',
                'PUBLIC'    => isset($last['file']) ? \dirname($last['file']) : $rootPath . '/public',
                'VENDOR'    => $loaderRc ? \dirname($loaderRc->getFileName(), 2) : $rootPath . '/vendor',
            ],
            'env' => [
                'ENVIRONMENT'   => detect_environment($debugMode),
                'DEPLOY'        => $cli ? false : !$debugMode,
                'DEBUG'         => $cli ? true : $debugMode,
                'URL'           => env('APP_URL'),
                'NAME'          => env('APP_NAME'),
                'CONSOLE'       => $cli,
            ],
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
        if (!\class_exists(Nette\Loaders\RobotLoader::class)) {
            throw new Nette\NotSupportedException(
                'RobotLoader not found, do you have [nette/robot-loader] package installed?'
            );
        }

        $loader = new Nette\Loaders\RobotLoader();
        $loader->setTempDirectory($this->getCacheDirectory() . '/biurad.robotLoader');
        $loader->setAutoRefresh($this->parameters['env']['DEBUG']);

        return $loader;
    }

    /**
     * Adds configuration file.
     *
     * @param array|string $config
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
        $this->services = \array_merge($services, $this->services);

        return $this;
    }

    /**
     * Returns system DI container.
     */
    public function createContainer(): BiuradPHP\DependencyInjection\Container
    {
        $class     = $this->loadContainer();
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
        $loader = new BiuradPHP\DependencyInjection\Compilers\ContainerLoader(
            $this->getCacheDirectory() . '/biurad.container',
            $this->parameters['env']['DEBUG']
        );

        try {
            $loaderRc = (new ReflectionClass(Composer\Autoload\ClassLoader::class))->getFileName();
        } catch (ReflectionException $e) {
            $loaderRc = null;
        }

        $class = $loader->load(
            [$this, 'generateContainer'],
            [
                $this->parameters,
                \array_keys($this->dynamicParameters),
                $this->configs,
                \PHP_VERSION_ID - \PHP_RELEASE_VERSION, // minor PHP version
                \file_exists($loaderRc) ? \filemtime($loaderRc) : null, // composer update
            ]
        );

        return $class;
    }

    /**
     * @param BiuradPHP\DependencyInjection\Compilers\Compiler $compiler
     *
     * @internal
     */
    public function generateContainer(BiuradPHP\DependencyInjection\Compilers\Compiler $compiler): void
    {
        $loader = $this->createLoader();
        $loader->setParameters($this->parameters);

        foreach ($this->configs as $config) {
            if (\is_string($config)) {
                $compiler->loadConfig($config, $loader);
            } else {
                $compiler->addConfig($config);
            }
        }

        $compiler->addConfig(['parameters' => $this->parameters]);
        $compiler->setDynamicParameterNames(\array_keys($this->dynamicParameters));

        $defaultExtensions = new ExtensionCompilerPass();
        $defaultExtensions = $defaultExtensions->setCompiler($compiler);

        /* @noinspection PhpParamsInspection */
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

        $dir = Nette\DI\Helpers::expand('%path.TEMP%/caches', $this->parameters, true);
        Nette\Utils\FileSystem::createDir($dir);

        return $dir;
    }

    /**
     * Expand counterpart.
     */
    private static function escape($value)
    {
        if (\is_array($value)) {
            return \array_map([self::class, 'escape'], $value);
        }

        if (\is_string($value)) {
            return \str_replace('%', '%%', $value);
        }

        return $value;
    }
}
