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
            $granted = false;
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
