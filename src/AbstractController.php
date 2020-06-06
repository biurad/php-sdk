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

namespace BiuradPHP\MVC;

use Exception;
use LogicException;
use Rakit\Validation\Validator;
use Psr\Http\Message\ResponseInterface;
use BiuradPHP\DependencyInjection\Interfaces;
use BiuradPHP\Http\Response\EmptyResponse;
use BiuradPHP\Http\Response\JsonResponse;
use BiuradPHP\Http\Response\RedirectResponse;
use SplFileInfo;
use Throwable;
use Symfony\Component\Security\Csrf\CsrfToken;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use GuzzleHttp\Psr7\UploadedFile;
use BiuradPHP\Http\Exceptions\ClientExceptions\NotFoundException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\UserInterface;

abstract class AbstractController
{
    /** @var Interfaces\FactoryInterface @inject */
    public $container;

    /**
     * Returns true if the service id is defined.
     *
     * @final
     * @param string $id
     *
     * @return bool
     */
    protected function has(string $id): bool
    {
        return $this->container->has($id);
    }

    /**
     * Gets a container service by its id.
     *
     * @final
     * @param string $id
     *
     * @return object The service
     */
    protected function get(string $id)
    {
        return $this->container->get($id);
    }

    /**
     * Gets a container configuration parameter by its name.
     *
     * @final
     * @param string $name
     *
     * @return mixed
     */
    protected function getParameter(string $name)
    {
        return $this->container->getParameter($name);
    }

    /**
     * Generates a URL from the given parameters.
     *
     * @final
     * @param string $route
     * @param array $parameters
     *
     * @return string
     * @see UrlGeneratorInterface
     */
    protected function generateUri(string $route, array $parameters = []): string
    {
        return $this->container->get('router')->generateUri($route, $parameters);
    }

    /**
     * Returns a RedirectResponse to the given URL.
     *
     * @final
     * @param string $url
     * @param int $status
     *
     * @return RedirectResponse
     */
    protected function redirect(string $url, int $status = 302): RedirectResponse
    {
        return new RedirectResponse($url, $status);
    }

    /**
     * Returns a RedirectResponse to the given route with the given parameters.
     *
     * @final
     * @param string $route
     * @param array $parameters
     * @param int $status
     *
     * @return RedirectResponse
     */
    protected function redirectToRoute(string $route, array $parameters = [], int $status = 302): RedirectResponse
    {
        return $this->redirect($this->generateUri($route, $parameters), $status);
    }

    /**
     * Returns a UploadedFileResponse object with original or customized file name and disposition header.
     *
     * @param SplFileInfo|string|resource|StreamInterface $file File object or path to file to be sent as response
     * @param int $size
     * @param int $error
     * @param string|null $fileName
     * @param string|null $mediaType
     *
     * @return UploadedFileInterface
     * @final
     */
    protected function file($file, int $size = null, int $error = UPLOAD_ERR_OK, string $fileName = null, string $mediaType = null): UploadedFileInterface
    {
        if ($file instanceof StreamInterface && is_null($size)) {
            $size = $file->getSize();
        }

        return new UploadedFile($file, $size, $error, $fileName, $mediaType);
    }

    /**
     * Adds a flash message to the current session for type.
     *
     * @final
     * @param string $type
     * @param string $message
     *
     * @return mixed
     */
    protected function addFlash(string $type, string $message)
    {
        if (!$this->container->has('session')) {
            throw new LogicException('You can not use the addFlash method if sessions are disabled');
        }

        return $this->container->get('session')->withFlash($type, $message);
    }

    /**
     * Checks a flash message exists in current session for type.
     *
     * @final
     * @param string $type
     *
     * @return bool
     */
    protected function hasFlash(string $type): bool
    {
        if (!$this->container->has('session')) {
            throw new LogicException('You can not use the hasFlash method if sessions are disabled');
        }

        return $this->container->get('session')->hasFlash($type);
    }

    /**
     * Get a flash message from current session for type.
     *
     * @final
     * @param string $type
     * @param bool $once Whether to get and pull-out flash or keep it.
     *
     * @return mixed
     */
    protected function getFlash(string $type, bool $once = false)
    {
        if (!$this->container->has('session')) {
            throw new LogicException('You can not use the getFlash method if sessions are disabled');
        }

        $flashed = $this->container->get('session')->getFlash($type);

        if (false !== $once) {
            $this->container->get('session')->getFlashBag()->delete($type);
        }

        return $flashed;
    }

    /**
     * Checks if the attributes are granted against the current authentication token and optionally supplied subject.
     *
     * @param array|string $attributes
     * @param null $subject
     *
     * @return bool
     * @final
     */
    protected function isGranted($attributes, $subject = null): bool
    {
        if (!$this->container->has('security.authorization_checker')) {
            throw new LogicException('Security is not registered in your application. Try running "composer require biurad/biurad-security".');
        }

        return $this->container->get('security.authorization_checker')->isGranted($attributes, $subject);
    }

    /**
     * Throws an exception unless the attributes are granted against the current authentication token and optionally
     * supplied subject.
     *
     * @param $attributes
     * @param null $subject
     * @param string $message
     *
     * @final
     * @return AccessDeniedException|null
     */
    protected function denyAccessUnlessGranted($attributes, $subject = null, string $message = 'Access Denied.'): ?AccessDeniedException
    {
        if (!$this->isGranted($attributes, $subject)) {
            $exception = $this->createAccessDeniedException($message);
            $exception->setAttributes($attributes);
            $exception->setSubject($subject);

            return $exception;
        }

        return null;
    }

    /**
     * Returns a rendered view.
     *
     * @final
     * @param string $view
     * @param array $parameters
     * @param array $errors
     *
     * @return string|null
     */
    protected function renderView(string $view, array $parameters = [], array $errors = []): ?string
    {
        if (!$this->container->has('templating')) {
            throw new LogicException('You can not use the "renderView" method if the Templating Component is not available. Try running "composer require biurad/template-manager".');
        }

        return $this->container->get('templating')->renderWithErrors($view, $parameters, $errors);
    }

    /**
     * Renders a view or array to the response.
     *
     * NOTE: adding a [content-type => {type}] in $attributes will be added to response.
     *
     * @param string|array $view Template name or array to json
     * @param array $attributes Template data or json or empty response headers.
     * @param array $errors
     *
     * @return ResponseInterface
     * @final
     */
    protected function renderResponse($view = '', array $attributes = [], array $errors = []): ResponseInterface
    {
        if (empty($contents = $view)) {
            return new EmptyResponse(204, $attributes);
        }

        if (is_array($contents) && !empty($contents)) {
            return new JsonResponse($view, 200, $attributes);
        }

        $contents = $this->renderView($view, $attributes, $errors);

        // Set a new response...
        $response = $this->get(ResponseInterface::class);
        assert($response instanceof ResponseInterface);

        if (isset($attributes['content-type'])) {
            $response = $response->withHeader('Content-Type', $attributes['content-type']);
        }

        $response->getBody()->write($contents);

        // Making sure contents of response body exists.
        if ($response->getBody()->getSize() <= 0) {
            $response->getBody()->write($contents);
        }

        return $response;
    }

    /**
     * Returns a HttpException.
     *
     * This will result in a 404 response code. Usage example:
     *
     *     throw $this->createNotFoundException('Page not found!');
     *
     * @final
     * @param string $message
     * @param Throwable|null $previous
     *
     * @return NotFoundException
     */
    protected function createNotFoundException(string $message = 'Not Found', Throwable $previous = null): NotFoundException
    {
        $exception = new NotFoundException();
        $exception->withMessage($message);
        $exception->withPreviousException($previous);

        return $exception;
    }

    /**
     * Returns an AccessDeniedException.
     *
     * This will result in a 403 response code. Usage example:
     *
     *     throw $this->createAccessDeniedException('Unable to access this page!');
     *
     * @param string $message
     * @param Exception|null $previous
     *
     * @return AccessDeniedException
     * @final
     */
    protected function createAccessDeniedException(string $message = 'Access Denied.', Exception $previous = null): AccessDeniedException
    {
        if (!class_exists(AccessDeniedException::class)) {
            throw new LogicException('You can not use the "createAccessDeniedException" method if the Security component is not available. Try running "composer require symfony/security-core".');
        }

        return new AccessDeniedException($message, $previous);
    }

    /**
     * Get a user from the Security Guard.
     *
     * @return UserInterface|null
     *
     * @throws LogicException If is not available
     *
     * @final
     */
    protected function getUser(): ?UserInterface
    {
        if (!$this->container->has('security.token_storage')) {
            throw new LogicException('Security is not registered in your application. Try running "composer require biurad/biurad-security".');
        }

        if (null === $token = $this->container->get('security.token_storage')->getToken()) {
            return null;
        }

        if (!is_object($user = $token->getUser())) {
            // e.g. anonymous authentication
            return null;
        }

        return $user;
    }

    /**
     * Validate the fields input $fields and match it to the $rules.
     *
     * @param array $fields
     * @param array $rules
     *
     * @return array of validData or errors.
     * @final
     */
    protected function ValidateFields(array $fields, array $rules): array
    {
        if (!class_exists(Validator::class)) {
            throw new LogicException('The Validator is not registered in your application. Try running "composer require rakit/validation".');
        }

        // make it
        $valiate = new Validator();

        // then validate
        $validation = $valiate->validate($fields, $rules);

        if (false !== $validation->fails()) {
            return array_merge($validation->errors()->firstOfAll(), ['error' => true]);
        }

        //Get Valid Data
        return $validation->getValidData();
    }

    /**
     * Checks the validity of a CSRF token.
     *
     * @param string $id The id used when generating the token
     * @param string|null $token The actual token sent with the request that should be validated
     *
     * @final
     * @return bool
     */
    protected function isCsrfTokenValid(string $id, ?string $token): bool
    {
        if (!$this->container->has('security.csrf.token_manager')) {
            throw new LogicException('CSRF protection is not enabled in your application.');
        }

        return $this->container->get('security.csrf.token_manager')->isTokenValid(new CsrfToken($id, $token));
    }

    /**
     * Dispatches an event to the controller.
     *
     * @param object|string $message The event's message to be dispatched
     * @param array $payload
     *
     * @return object|mixed
     * @final
     */
    protected function dispatchEvent($message, array $payload = [])
    {
        if (!$this->container->has('events')) {
            throw new LogicException('The EventDispatcher is not enabled in your application. '.$message);
        }

        return $this->container->get('events')->dispatch($message, $payload);
    }
}
