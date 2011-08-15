<?php

namespace Marbemac\NotificationBundle\Controller;

use Symfony\Component\HttpFoundation\Request,
    Symfony\Component\HttpFoundation\RedirectResponse,
    Symfony\Component\HttpFoundation\Response,
    Symfony\Component\DependencyInjection\ContainerAware,
    Symfony\Component\HttpKernel\Exception\NotFoundHttpException,
    Symfony\Component\Security\Core\Exception\AccessDeniedException,
    Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class NotificationController extends ContainerAware
{
    public function createAction($oid, $collection, $amount)
    {
        $response = new Response();
        $response->setCache(array(
        ));

        if ($response->isNotModified($this->container->get('request'))) {
            // return the 304 Response immediately
            //return $response;
        }

        $user = $this->container->get('security.context')->getToken()->getUser();
        $return = $this->container->get('marbemac.manager.vote')->addVote($user, $oid, $collection, $amount);

        if ($this->container->get('request')->isXmlHttpRequest())
        {
            $result = array();
            if ($return['status'] == 'success')
            {
                $result['event'] = 'vote_toggle';
                $result['objectId'] = $return['object']->getId()->__toString();
                $result['objectNewScore'] = $return['object']->getScore();
                $result['flash'] = array('type' => 'success', 'message' => 'Vote updated successfully!');
            }
            else
            {
                $result['status'] = $return['status'];
                $result['flash'] = array('type' => 'error', 'message' => $return['message']);
            }

            $response = new Response(json_encode($result));
            $response->headers->set('Content-Type', 'application/json');
        }

        return $response;
    }
}