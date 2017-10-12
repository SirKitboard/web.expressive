<?php declare(strict_types=1);

namespace Dms\Web\Expressive\Http\Middleware;

use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Expressive\Session\SessionInterface;
use Zend\Expressive\Session\SessionPersistenceInterface;

class Session implements MiddlewareInterface
{
    /**
     * @var SessionPersistenceInterface
     */
    private $persistence;

    /**
     * @var SessionInterface
     */
    private $session;

    public function __construct(SessionPersistenceInterface $persistence, SessionInterface $session)
    {
        $this->persistence = $persistence;
        $this->session = $session;
    }

    public function process(ServerRequestInterface $request, DelegateInterface $delegate) : ResponseInterface
    {
        $response = $delegate->process($request);
        return $this->persistence->persistSession($this->session, $response);
    }
}
