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

class Version
{
    public const STABLE = '1.0.2';

    public const VERSION_ID = 00012;

    public const MAJOR_VERSION = 1;

    public const MINOR_VERSION = 0;

    public const RELEASE_VERSION = 2;

    public const EXTRA_VERSION = '';

    public const DEV_MASTER = '0.1.2-dev-master';

    /**
     * Compares a BiuradPHP Framework's version with the current one.
     *
     * @param string $version biuradPHP Framework's version to compare
     *
     * @return int returns -1 if older, 0 if it is the same, 1 if version
     *             passed as argument is newer
     */
    public static function compare($version)
    {
        $currentVersion = \str_replace(' ', '', \strtolower(self::STABLE));
        $version        = \str_replace(' ', '', $version);

        return \version_compare($version, $currentVersion);
    }
}
