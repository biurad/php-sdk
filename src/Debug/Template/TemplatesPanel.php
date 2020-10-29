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

namespace Biurad\Framework\Debug\Template;

use Tracy\Helpers;
use Tracy\IBarPanel;
use Biurad\UI\Profile;
use Biurad\UI\Template;

class TemplatesPanel implements IbarPanel
{
    protected $icon = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="#4E910C" d="M8.932 22.492c.016-6.448-.971-11.295-5.995-11.619 4.69-.352 7.113 2.633 9.298 6.907C12.205 6.354 9.882 1.553 4.8 1.297c7.433.07 10.028 5.9 11.508 14.293 1.171-2.282 3.56-5.553 5.347-1.361-1.594-2.04-3.607-1.617-3.978 8.262H8.933z"></path></svg>';
    protected $renders;
    protected $templateCount = 0;
    protected $renderCount = 0;
    protected $memoryCount = 0;
    protected $templates = [];

    /**
     * Initialize the panel.
     */
    public function __construct(Template $template)
    {
        $this->renders = $template->getRenders();
    }

    /**
     * {@inheritdoc}
     */
    public function getPanel(): string
    {
        $duration = $memory = 0;
        $profiles = [];

        foreach ($this->renders as $render) {
            $profiler = $render->getLoader()->getProfile();
            $duration += $profiler->getDuration();

            if (empty($profiler->getProfiles())) {
                continue;
            }

            $profiles += $profiler->getProfiles();
            $this->processData($profiler);
        }

        $this->templateCount = count(array_filter($profiles));
        $duration = self::formatDuration($duration);
        $memory   = self::formatBytes($this->memoryCount);

        return Helpers::capture(function () use ($duration, $memory) {
            require __DIR__.'/templates/TemplatesPanel.panel.phtml';
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getTab(): string
    {
        return Helpers::capture(function () {
            require __DIR__.'/templates/TemplatesPanel.tab.phtml';
        });
    }

    public static function formatBytes(int $size, int $precision = 2): string
    {
        if (0 === $size || null === $size)
        {
            return '0B';
        }

        $sign = $size < 0 ? '-' : '';
        $size = \abs($size);

        $base     = \log($size) / \log(1024);
        $suffixes = ['B', 'KB', 'MB', 'GB', 'TB'];

        return $sign.\round(\pow(1024, $base - \floor($base)), $precision).$suffixes[\floor($base)];
    }

    public static function formatDuration(float $seconds): string
    {
        $duration = \round($seconds, 2).'s';

        if ($seconds < 0.001)
        {
            $duration = \round($seconds * 1000000).'μs';
        }
        elseif ($seconds < 0.1)
        {
            $duration = \round($seconds * 1000, 2).'ms';
        }
        elseif ($seconds < 1)
        {
            $duration = \round($seconds * 1000).'ms';
        }

        return $duration;
    }

    private function processData(Profile $profile): void
    {
        $this->renderCount += (!empty($profile->getProfiles()) ? 1 : 0);
        $this->memoryCount += $profile->getMemoryUsage();

        if ($profile->isTemplate()) {
            $this->templates[$profile->getName()] = [
                'name'     => $profile->getName(),
                'duration' => self::formatDuration($profile->getDuration()),
                'memory'   => self::formatBytes($profile->getMemoryUsage()),
            ];

            return;
        }

        foreach ($profile as $p) {
            $this->processData($p);
        }
    }
}
