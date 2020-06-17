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

use LogicException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class HomeController extends AbstractController implements RequestHandlerInterface
{
    private $defaults;

    public function __construct(bool $default = false)
    {
        $this->defaults = $default;
    }

    /**
     * This is a router annotated Middleware Controller Method.
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    final public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $userName = null;
        $security = true;

        try {
            // Incase It's null, let's use user's fullname
            if ((null !== $user = $this->getUser()) && null === $userName = $user->getUsername()) {
                $userName = $this->getUser()->getFullName();
            }
        } catch (LogicException $e) {
            $security = false;
        }

        try {
            // To avoid the exception thrown since no middleware is runned on this controller.
            $granted = null === $userName ? false : $this->isGranted('IS_AUTHENTICATED_REMEMBERED');
        } catch (LogicException $e) {
            $granted  = false;
            $security = false;
        }

        $templateData = [
            'title'         => $this->getParameter('env.NAME'),
            'version'       => Version::MAJOR_VERSION,
            'default'       => $this->defaults,
            'security'      => $security,
            'username'      => $userName,
            'loggedIn'      => $granted,
            'registered'    => $this->getFlash('registered', true),
        ];

        return $this->renderResponse('@Base::welcome', $templateData);

        //return $this->redirectToRoute('api.home', ['number' => 244444]);
    }
}
