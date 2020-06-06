<?php /** @noinspection PhpUndefinedMethodInspection */

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

namespace BiuradPHP\MVC\EventListeners;

use BiuradPHP\MVC\KernelEvents;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use BiuradPHP\Events\Interfaces\EventSubscriberInterface;
use BiuradPHP\Http\Interfaces\RequestExceptionInterface;
use BiuradPHP\MVC\Events\ExceptionEvent;

/**
 * @author James Halsall <james.t.halsall@googlemail.com>
 * @author Robin Chalas <robin.chalas@gmail.com>
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
class ErrorListener implements EventSubscriberInterface
{
    private $logger;

    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * This method checks if there has been an error in a command then,
     * it checks if or not to display a better error message.
     *
     * @param ConsoleErrorEvent $event
     */
    public function onConsoleError(ConsoleErrorEvent $event): void
    {
        if (null === $this->logger) {
            return;
        }

        $error = $event->getError();

        if (!$inputString = $this->getInputString($event)) {
            $this->logger->error(sprintf('An error occurred while using the console. Message: "%s"', $error->getMessage()), ['exception' => $error]);

            return;
        }

        $this->logger->error(sprintf('Error thrown while running command "%s". Message: "%s"', $inputString, $error->getMessage()), ['exception' => $error]);
    }

    /**
     * This method checks if there has been an exit code in a command then,
     * terminate the command on event.
     *
     * @param ConsoleTerminateEvent $event
     */
    public function onConsoleTerminate(ConsoleTerminateEvent $event): void
    {
        if (null === $this->logger) {
            return;
        }

        $exitCode = $event->getExitCode();

        if (0 === $exitCode) {
            return;
        }

        if (!$inputString = $this->getInputString($event)) {
            $this->logger->debug(sprintf('The console exited with code "%s"', $exitCode));

            return;
        }

        $this->logger->debug(sprintf('Command "%s" exited with code "%s"', $inputString, $exitCode));
    }

    /**
     * This method checks if the triggered exception is related to the framework
     *
     * @param ExceptionEvent $event
     */
    public function handleKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if ($exception instanceof RequestExceptionInterface && null !== $exception->getResponse()) {
            $event->setResponse($exception->getResponse());
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::CONSOLE_ERROR => ['onConsoleError', -128],
            KernelEvents::CONSOLE_TERMINATE => ['onConsoleTerminate', -128],
            KernelEvents::EXCEPTION => ['handleKernelException', -128],
        ];
    }

    /**
     * @param ConsoleEvent $event
     * 
     * @return string|null
     */
    private function getInputString(ConsoleEvent $event): ?string
    {
        $commandName = $event->getCommand() ? $event->getCommand()->getName() : null;
        $input = $event->getInput();

        if (method_exists($input, '__toString')) {
            if ($commandName) {
                return str_replace(["'$commandName'", "\"$commandName\""], $commandName, (string) $input);
            }

            return (string) $input;
        }

        return $commandName;
    }
}
