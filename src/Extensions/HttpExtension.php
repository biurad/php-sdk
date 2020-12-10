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

use Biurad\DependencyInjection\Extension;
use Biurad\Framework\Interfaces\DispatcherInterface;
use Biurad\Framework\Interfaces\KernelInterface;
use Biurad\Http\Factories\GuzzleHttpPsr7Factory;
use Biurad\Http\Factory\CookieFactory;
use Biurad\Http\Interfaces\CspInterface;
use Biurad\Http\Middlewares\AccessControlMiddleware;
use Biurad\Http\Middlewares\CacheControlMiddleware;
use Biurad\Http\Middlewares\HttpMiddleware;
use Biurad\Http\Session;
use Biurad\Http\Sessions\HandlerFactory;
use Biurad\Http\Sessions\Storage\NativeSessionStorage;
use Biurad\Http\Strategies\ContentSecurityPolicy;
use Closure;
use Nette;
use Nette\DI\Definitions\Statement;
use Nette\Schema\Expect;
use Psr\Cache\CacheItemPoolInterface;

class HttpExtension extends Extension
{
    /** @var string */
    private $tempDir;

    public function __construct(string $tempDir)
    {
        $this->tempDir = $tempDir;
    }

    /**
     * {@inheritDoc}
     */
    public function getConfigSchema(): Nette\Schema\Schema
    {
        return Nette\Schema\Expect::structure([
            'caching' => Nette\Schema\Expect::structure([
                'cache_lifetime'                    => Nette\Schema\Expect::int()->default(86400 * 30),
                'default_ttl'                       => Nette\Schema\Expect::int(),
                'hash_algo'                         => Nette\Schema\Expect::anyOf(...\hash_algos())->default('sha1'),
                'methods'                           => Nette\Schema\Expect::list()->default(['GET', 'HEAD']),
                'respect_response_cache_directives' => Nette\Schema\Expect::list()->default(['no-cache', 'private', 'max-age', 'no-store']),
                'cache_key_generator'               => Nette\Schema\Expect::anyOf(Expect::object(), Expect::string(), Expect::null()),
                'cache_listeners'                   => Nette\Schema\Expect::listOf('object|string'),
                'blacklisted_paths'                 => Nette\Schema\Expect::list(),
            ])->castTo('array'),
            'policies' => Nette\Schema\Expect::structure([
                'content_security_policy' => Expect::array(), // Content-Security-Policy
                'csp_report_only'         => Expect::array(), // Content-Security-Policy-Report-Only
                'feature_policy'          => Expect::array(), // Feature-Policy
                'referrer_policy'         => Expect::array(), // Referrer-Policy
                'frame_policy'            => Expect::anyOf(Expect::string(), false)->default('SAMEORIGIN')
                    ->before(function ($value) {
                        return null === $value ? '' : $value;
                    }),
            ])->castTo('array'), // X-Frame-Options
            'headers' => Nette\Schema\Expect::structure([
                'cors' => Nette\Schema\Expect::structure(\array_merge(
                    $this->corsConfig(),
                    [
                        'allow_paths' => Nette\Schema\Expect::anyOf('*', Expect::bool(),
                            Expect::arrayOf(Expect::structure($this->corsConfig())->castTo('array'))
                        )->before(Closure::fromCallable([$this, 'normalizeAllowPath'])),
                    ]
                ))->before(function ($values) {
                    if (isset($values['allow_paths'])) {
                        $allowedPaths = [];

                        foreach ($values['allow_paths'] as $index => $path) {
                            if (is_int($index)) {
                                $allowedPaths += $this->normalizeAllowPath($path);
                            }
                        }

                        if (!empty($allowedPaths)) {
                            $values['allow_paths'] = $allowedPaths;
                        }
                    }

                    return $values;
                })->castTo('array'),
                'request'               => Nette\Schema\Expect::arrayOf(Expect::string()),
                'response'              => Nette\Schema\Expect::arrayOf(Expect::string()),
            ])->castTo('array'),
            'sessions' => Expect::structure([
                'handler'    => Expect::string()->nullable()->default('file://' . $this->tempDir),
                'storage'    => Nette\Schema\Expect::string()->nullable(),
                'options'    => Nette\Schema\Expect::structure([
                    'name'            => Nette\Schema\Expect::string()->default('nette-debug'),
                    'cookie_lifetime' => Nette\Schema\Expect::int()->default((int) \ini_get('session.gc_maxlifetime')),
                    'cookie_path'     => Nette\Schema\Expect::string()->default('/'),
                    'cookie_domain'   => Nette\Schema\Expect::string()->default(''),
                    'cookie_samesite' => Nette\Schema\Expect::int()->default('lax'),
                ])->otherItems('array')->castTo('array'),
            ])->castTo('array'),
        ])->castTo('array');
    }

    /**
     * {@inheritDoc}
     */
    public function loadConfiguration(): void
    {
        $container = $this->getContainerBuilder();
        $container->register($this->prefix('factory'), GuzzleHttpPsr7Factory::class);

        $csPolicy = $container->register($this->prefix('csp'), ContentSecurityPolicy::class)
            ->setType(CspInterface::class);

        if (false === ($this->getExtensionConfig(FrameworkExtension::class, 'content_security_policy') ?? false)) {
            $csPolicy->setArgument('disable', true);
        }

        $container->register($this->prefix('middleware'), HttpMiddleware::class)
            ->setArgument('config', \array_intersect_key(
                $this->config,
                \array_flip(['policies', 'headers'])
            ));

        $container->register($this->prefix('cors_control'), AccessControlMiddleware::class)
            ->setArguments([$this->getFromConfig('headers.cors')]);

        if ($container->getByType(CacheItemPoolInterface::class)) {
            $container->register($this->prefix('cache_control'), CacheControlMiddleware::class)
                ->setArgument('config', $this->getFromConfig('caching'));
        }

        $sessionOptions = $this->getFromConfig('sessions.options');

        $container->register($this->prefix('cookie'), CookieFactory::class)
            ->addSetup('setDefaultPathAndDomain', [
                $sessionOptions['cookie_path'],
                $sessionOptions['cookie_domain'],
                $sessionOptions['cookie_secure'] ?? false,
            ]);

        $container->register($this->prefix('session'), Session::class)
            ->setArgument(
                'storage',
                $this->getFromConfig('sessions.storage') ?? new Statement(NativeSessionStorage::class, [$sessionOptions])
            )
            ->addSetup('setHandler', [
                new Statement(
                    [
                        new Statement(HandlerFactory::class, ['minutes' => $sessionOptions['cookie_lifetime']]),
                        'createHandler',
                    ],
                    [$this->getFromConfig('sessions.handler')]
                ),
            ]);

        $container->addAlias('session', $this->prefix('session'));
    }

    /**
     * {@inheritDoc}
     */
    public function beforeCompile(): void
    {
        $container = $this->getContainerBuilder();
        $listeners = $container->findByType(DispatcherInterface::class);

        // Register as services
        $container->getDefinitionByType(KernelInterface::class)
            ->addSetup(
                '?->addDispatcher(...?)',
                ['@self', $this->getHelper()->getServiceDefinitionsFromDefinitions($listeners)]
            );
    }

    private function corsConfig(): array
    {
        return [
            'allow_origin'       => Nette\Schema\Expect::anyOf(Expect::list(), Expect::string(), Expect::bool())
                    ->before(function ($value) {
                    return \is_string($value) ? [$value] : $value;
                }),
            'allow_headers'      => Nette\Schema\Expect::anyOf(Expect::list(), Expect::string(), Expect::bool())
                    ->before(function ($value) {
                    return \is_string($value) ? [$value] : $value;
                }),
            'allow_methods'      => Nette\Schema\Expect::list()
                ->before(function ($value) {
                    return \is_string($value) ? [$value] : $value;
                }),
            'exposed_headers'    => Nette\Schema\Expect::list()
                ->before(function ($value) {
                    return \is_string($value) ? [$value] : $value;
                }),
            'hosts'              => Nette\Schema\Expect::list()
                ->before(function ($value) {
                    return \is_string($value) ? [$value] : $value;
                }),
            'allow_credentials'  => Nette\Schema\Expect::bool(false),
            'origin_regex'       => Nette\Schema\Expect::bool(false),
            'max_age'            => Nette\Schema\Expect::int(0),
        ];
    }

    private function normalizeAllowPath(array $values): array
    {
        if (isset($values['value'])) {
            if (is_bool($values['value']) || '*' === $values['value']) {
                return $values['value'];
            }
    
            $keys   = array_flip(array_keys($this->corsConfig()));
            $values = [$values['value'] => \array_intersect_key($values, $keys)];
        }

        return $values;
    }
}
