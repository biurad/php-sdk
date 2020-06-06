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

namespace BiuradPHP\MVC\Exceptions;

use BiuradPHP\MVC\Events\ExceptionEvent;
use BiuradPHP\MVC\Interfaces\RebootableInterface;
use BiuradPHP\Template\Interfaces\ViewsInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use Laminas\Stratigility\Utils;

use function get_class;
use function base64_encode;
use function sprintf;

/**
 * Generates a response for use when the application fails.
 */
class ErrorResponseGenerator
{
    public const TEMPLATE_DEFAULT = 'Base::layouts._errors';

    /**
     * Factory capable of generating a ResponseInterface instance.
     *
     * @var callable
     */
    private $responseFactory;

    /**
     * Whether or not we are in debug/development mode.
     *
     * @var bool
     */
    private $debug;

    /**
     * @var ViewsInterface|null
     */
    private $renderer;

    /**
     * @var string
     */
    private $stackTraceTemplate = <<< 'EOT'
%s raised in file %s line %d:
Message: %s
Stack Trace:
%s

EOT;

    /**
     * Custom messages for error page.
     *
     * @var array
     */
    private $messages = [
        0 => ['Sorry! Page Error Encounted', 'Your browser sent a request that this server could not understand or process.'],
        403 => ['Access Denied For This Page', 'You do not have permission to view this page. Please try contact the web site administrator if you believe you should be able to view this page.'],
        404 => ['Page Could Not Be Found', 'The page you requested could not be found. It is possible that the address is incorrect, or that the page no longer exists. Please use a search engine to find what you are looking for.'],
        405 => ['Method Not Allowed', 'The requested method is not allowed for the URL.'],
        410 => ['Page Not Found', 'The page you requested has been taken off the site. We apologize for the inconvenience.'],
        429 => ['Sorry! Page Error Encounted', 'Too many request detected in application life cycle and was unable to complete your request. Please try again later.'],
        500 => ['Server Error, Something has gone horribly wrong', 'We\'re sorry! The server encountered an internal error and was unable to complete your request. Please try again later.'],
    ];

    /**
     * Name of the template to render.
     *
     * @var string
     */
    private $template;

    public function __construct(
        callable $responseFactory,
        bool $isDevelopmentMode = false,
        ViewsInterface $renderer = null,
        string $template = self::TEMPLATE_DEFAULT
    ) {
        $this->responseFactory = function () use ($responseFactory) : ResponseInterface {
            return $responseFactory();
        };

        $this->debug     = $isDevelopmentMode;
        $this->renderer  = $renderer;
        $this->template  = $template;
    }

    /**
     * Add your custom errors to famework
     *
     * @param int $code response status code.
     * @param array $messages an array of [title => message]
     */
    public function setMessages(int $code, array $messages): void
    {
        $this->messages[$code] = $messages;
    }

    /**
     * @param ExceptionEvent $event
     * @param Throwable $e
     *
     * @return ResponseInterface
     * @throws Throwable
     */
    public function __invoke(ExceptionEvent $event, Throwable $e) : ResponseInterface
    {
        $response  = ($this->responseFactory)();
        $response  = $response->withStatus($code = Utils::getStatusCode($event->getThrowable(), $response));

        // Reboot the application on rebootable empty response.
        if ($event->hasResponse() && $event->getResponse() instanceof RebootableInterface) {
            $new = clone $event->getKernel();

            return $new->processRequest($event->getRequest());
        }

        // Catch Response from events.
        if ($event->hasResponse() || $event->getThrowable() instanceof NotConfiguredException) {
            return $event->getResponse();
        }

        if ($this->renderer && (!$event->hasResponse() || connection_aborted())) {
            return $this->prepareTemplatedResponse(
                $event->getThrowable(),
                $this->renderer,
                [
                    'message' => $this->messages[$code][1] ?? $this->messages[0][1],
                    'reason'  => $this->messages[$code][0] ?? $this->messages[0][0],
                    'code'    => base64_encode($e->getCode().' - '.$e->getMessage()),
                ],
                $this->debug,
                $response
            );
        }

        return $this->prepareDefaultResponse($event->getThrowable(), $this->debug, $response);
    }

    /**
     * @param Throwable $exception
     * @param ViewsInterface $renderer
     * @param array $templateData
     * @param bool $debug
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     * @throws Throwable
     */
    private function prepareTemplatedResponse(
        Throwable $exception,
        ViewsInterface $renderer,
        array $templateData,
        bool $debug,
        ResponseInterface $response
    ) : ResponseInterface {
        if ($debug) {
            throw $exception;
        }

        $response->getBody()
            ->write($renderer->render($this->template, $templateData));

        return $response;
    }

    /**
     * @param Throwable $e
     * @param bool $debug
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     * @throws Throwable
     */
    private function prepareDefaultResponse(
        Throwable $e,
        bool $debug,
        ResponseInterface $response
    ) : ResponseInterface {
        $message = $this->messages[$response->getStatusCode()][1] ?? $this->messages[0][1];

        if ($debug) {
            //$message .= "; stack trace:\n\n" . $this->prepareStackTrace($e);
            throw $e;
        }

        $response->getBody()->write($message);

        return $response;
    }

    /**
     * Prepares a stack trace to display.
     *
     * @param Throwable $e
     * @return string
     */
    private function prepareStackTrace(Throwable $e) : string
    {
        $message = '';
        do {
            $message .= sprintf(
                $this->stackTraceTemplate,
                get_class($e),
                $e->getFile(),
                $e->getLine(),
                $e->getMessage(),
                $e->getTraceAsString()
            );
        } while ($e = $e->getPrevious());

        return $message;
    }
}
