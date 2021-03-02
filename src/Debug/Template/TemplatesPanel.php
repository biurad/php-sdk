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

use Biurad\UI\Interfaces\RenderInterface;
use Tracy\Helpers;
use Tracy\IBarPanel;
use Biurad\UI\Profile;
use Biurad\UI\Template;

class TemplatesPanel implements IbarPanel
{
    /** @var RenderInterface[] */
    protected $renders;

    /** @var int */
    protected $templateCount = 0;

    /** @var int */
    protected $renderCount = 0;

    /** @var int */
    protected $memoryCount = 0;

    /** @var int|string */
    protected $duration = 0;

    /** @var array<int,mixed[]> */
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
        return Helpers::capture(function () {
            $memory = self::formatBytes($this->memoryCount);

            require __DIR__.'/templates/TemplatesPanel.panel.phtml';
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getTab(): string
    {
        $duration = 0;
        $profiles = [];

        foreach ($this->renders as $render) {
            $profiler = $render->getLoader()->getProfile();
            $duration += $profiler->getDuration();

            if (empty($profiler->getProfiles())) {
                continue;
            }

            $this->processData($profiler);
            $profiles += $profiler->getProfiles();
        }

        $this->duration = self::formatDuration($duration);
        $this->templateCount = count(\array_filter($profiles));

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
