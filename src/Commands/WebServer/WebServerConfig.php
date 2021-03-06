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

namespace Biurad\Framework\Commands\WebServer;

use InvalidArgumentException;
use RuntimeException;

class WebServerConfig
{
    /** @var string */
    private $hostname;

    /** @var string */
    private $port;

    /** @var string */
    private $documentRoot;

    /** @var string */
    private $env;

    /** @var string */
    private $router;

    public function __construct(string $documentRoot, string $env, string $address = null, string $router = null)
    {
        if (!\is_dir($documentRoot)) {
            throw new InvalidArgumentException(\sprintf('The document root directory "%s" does not exist.', $documentRoot));
        }

        if (null === $file = $this->findFrontController($documentRoot, $env)) {
            throw new InvalidArgumentException(\sprintf('Unable to find the front controller under "%s" (none of these files exist: %s).', $documentRoot, \implode(', ', $this->getFrontControllerFileNames($env))));
        }

        $_ENV['APP_FRONT_CONTROLLER'] = $file;

        $this->documentRoot = $documentRoot;
        $this->env          = $env;

        if (null !== $router) {
            $absoluteRouterPath = \realpath($router);

            if (false === $absoluteRouterPath) {
                throw new InvalidArgumentException(\sprintf('Router script "%s" does not exist.', $router));
            }

            $this->router = $absoluteRouterPath;
        } else {
            $this->router = __DIR__ . '/dev-router.php';
        }

        if (null === $address) {
            $this->hostname = '127.0.0.1';
            $this->port     = $this->findBestPort();
        } elseif (false !== $pos = \mb_strrpos($address, ':')) {
            $this->hostname = \mb_substr($address, 0, $pos);

            if ('*' === $this->hostname) {
                $this->hostname = '0.0.0.0';
            }
            $this->port = \mb_substr($address, $pos + 1);
        } elseif (\ctype_digit($address)) {
            $this->hostname = '127.0.0.1';
            $this->port     = $address;
        } else {
            $this->hostname = $address;
            $this->port     = $this->findBestPort();
        }

        if (!\ctype_digit($this->port)) {
            throw new InvalidArgumentException(\sprintf('Port "%s" is not valid.', $this->port));
        }
    }

    public function getDocumentRoot()
    {
        return $this->documentRoot;
    }

    public function getEnv()
    {
        return $this->env;
    }

    public function getRouter()
    {
        return $this->router;
    }

    public function getHostname()
    {
        return $this->hostname;
    }

    public function getPort()
    {
        return $this->port;
    }

    public function getAddress()
    {
        return $this->hostname . ':' . $this->port;
    }

    /**
     * @return string contains resolved hostname if available, empty string otherwise
     */
    public function getDisplayAddress()
    {
        if ('0.0.0.0' !== $this->hostname) {
            return '';
        }

        if (false === $localHostname = \gethostname()) {
            return '';
        }

        return \gethostbyname($localHostname) . ':' . $this->port;
    }

    private function findFrontController($documentRoot, $env)
    {
        $fileNames = $this->getFrontControllerFileNames($env);

        foreach ($fileNames as $fileName) {
            if (\file_exists($documentRoot . '/' . $fileName)) {
                return $fileName;
            }
        }
    }

    private function getFrontControllerFileNames($env)
    {
        return ['index.php', 'app_' . $env . '.php', 'app.php', 'server.php', 'server_' . $env . '.php'];
    }

    private function findBestPort()
    {
        $port = 8000;

        while (false !== $fp = @\fsockopen($this->hostname, $port, $errno, $errstr, 1)) {
            \fclose($fp);

            if ($port++ >= 8100) {
                throw new RuntimeException('Unable to find a port available to run the web server.');
            }
        }

        return $port;
    }
}
