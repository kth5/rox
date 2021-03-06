<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Member;
use EnvironmentExplorer;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class LegacyController extends Controller
{
    public function showAction(Request $request)
    {
        $container = $this->get('service_container');
        $environmentExplorer = new EnvironmentExplorer();
        $environmentExplorer->initializeGlobalState(
            $container->getParameter('database_host'),
            $container->getParameter('database_name'),
            $container->getParameter('database_user'),
            $container->getParameter('database_password')
        );

        // Kick-start the Symfony session. This replaces session_start() in the
        // old code, which is now turned off.
        $session = $this->get('session');
        $session->start();

        $pathInfo = $request->getPathInfo();
        $public = (strpos($pathInfo, '/safety') === false) ||
            (strpos($pathInfo, '/about') === false) ||
            (strpos($pathInfo, '/signup') === false);
        if (!$session->has('IdMember')) {
            $rememberMeToken = unserialize($session->get('_security_default'));
            if ($rememberMeToken === null && !$public) {
                throw new AccessDeniedException();
            }
            if ($rememberMeToken !== false) {
                /** @var Member $user */
                $user = $rememberMeToken->getUser();
                if ($user !== null) {
                    $session->set('IdMember', $user->getId());
                    $session->set('MemberStatus', $user->getStatus());
                    $session->set('APP_User_id', $user->getId());
                }
            }
        }

        try {
            $kernel = $this->get('rox.legacy_kernel');

            return $kernel->handle($request, $request->getRealMethod());
        } catch (ResourceNotFoundException $e) {
            // If the legacy kernel also failed to route the request, let the
            // original error bubble back up to the new Symfony error handler.
            return;
        }
    }
}
