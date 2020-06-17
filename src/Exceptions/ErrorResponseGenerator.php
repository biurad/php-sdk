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

namespace BiuradPHP\MVC\Exceptions;

use BiuradPHP\MVC\Events\ExceptionEvent;
use BiuradPHP\MVC\Interfaces\RebootableInterface;
use BiuradPHP\Template\Interfaces\ViewsInterface;
use Laminas\Stratigility\Utils;
use Psr\Http\Message\ResponseInterface;
use Throwable;

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
     * @var null|ViewsInterface
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
        0   => [
            'Sorry! Page Error Encounted',
            'Your browser sent a request that this server could not understand or process.',
        ],
        403 => [
            'Access Denied For This Page',
            'You do not have permission to view this page. Please try contact the web site'
            . ' administrator if you believe you should be able to view this page.',
        ],
        404 => [
            'Page Could Not Be Found',
            'The page you requested could not be found. It is possible that the address is incorrect,'
            . ' or that the page no longer exists. Please use a search engine to find what you are looking for.', ],
        405 => [
            'Method Not Allowed',
            'The requested method is not allowed for the URL.',
        ],
        410 => [
            'Page Not Found',
            'The page you requested has been taken off the site. We apologize for the inconvenience.',
        ],
        429 => [
            'Sorry! Page Error Encounted',
            'Too many request detected in application life cycle and was unable to complete your request.'
            . ' Please try again later.',
        ],
        500 => [
            'Server Error, Something has gone horribly wrong',
            'We\'re sorry! The server encountered an internal error and was unable to complete your request.'
            . ' Please try again later.',
        ],
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
        $this->responseFactory = function () use ($responseFactory): ResponseInterface {
            return $responseFactory();
        };

        $this->debug     = $isDevelopmentMode;
        $this->renderer  = $renderer;
        $this->template  = $template;
    }

    /**
     * @param ExceptionEvent $event
     * @param Throwable      $e
     *
     * @throws Throwable
     *
     * @return ResponseInterface
     */
    public function __invoke(ExceptionEvent $event, Throwable $e): ResponseInterface
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

        if ($this->renderer && (!$event->hasResponse() || \connection_aborted())) {
            return $this->prepareTemplatedResponse(
                $event->getThrowable(),
                $this->renderer,
                [
                    'message' => $this->messages[$code][1] ?? $this->messages[0][1],
                    'reason'  => $this->messages[$code][0] ?? $this->messages[0][0],
                    'code'    => \base64_encode($e->getCode() . ' - ' . $e->getMessage()),
                ],
                $this->debug,
                $response
            );
        }

        return $this->prepareDefaultResponse($event->getThrowable(), $this->debug, $response);
    }

    /**
     * Add your custom errors to famework
     *
     * @param int   $code     response status code
     * @param array $messages an array of [title => message]
     */
    public function setMessages(int $code, array $messages): void
    {
        $this->messages[$code] = $messages;
    }

    /**
     * @param Throwable         $exception
     * @param ViewsInterface    $renderer
     * @param array             $templateData
     * @param bool              $debug
     * @param ResponseInterface $response
     *
     * @throws Throwable
     *
     * @return ResponseInterface
     */
    private function prepareTemplatedResponse(
        Throwable $exception,
        ViewsInterface $renderer,
        array $templateData,
        bool $debug,
        ResponseInterface $response
    ): ResponseInterface {
        if ($debug) {
            throw $exception;
        }

        $response->getBody()
            ->write($renderer->render($this->template, $templateData));

        return $response;
    }

    /**
     * @param Throwable         $e
     * @param bool              $debug
     * @param ResponseInterface $response
     *
     * @throws Throwable
     *
     * @return ResponseInterface
     */
    private function prepareDefaultResponse(
        Throwable $e,
        bool $debug,
        ResponseInterface $response
    ): ResponseInterface {
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
     *
     * @return string
     */
    private function prepareStackTrace(Throwable $e): string
    {
        $message = '';

        do {
            $message .= \sprintf(
                $this->stackTraceTemplate,
                \get_class($e),
                $e->getFile(),
                $e->getLine(),
                $e->getMessage(),
                $e->getTraceAsString()
            );
        } while ($e = $e->getPrevious());

        return $message;
    }
}
