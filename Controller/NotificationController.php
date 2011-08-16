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
    public function unreadCountAction($userId)
    {
        $response = new Response();
        $response->setCache(array(
        ));

        if ($response->isNotModified($this->container->get('request'))) {
            // return the 304 Response immediately
            //return $response;
        }

        $today = new \DateTime();
        $unreadNotifications = $this->container->get('marbemac.manager.notification')->findNotificationsBy(array('userId' => $userId, 'unread' => true, 'active' => true));

        return $this->container->get('templating')->renderResponse('MarbemacNotificationBundle:Notification:unreadCount.html.twig', array(
            'unreadCount' => $unreadNotifications->count(true),
        ));
    }

    public function listAction()
    {
        $response = new Response();
        $response->setCache(array(
        ));

        if ($response->isNotModified($this->container->get('request'))) {
            // return the 304 Response immediately
            //return $response;
        }

        $today = new \DateTime();
        $userId = $this->container->get('security.context')->getToken()->getUser()->getId();
        $formattedNotifications = $this->container->get('marbemac.manager.notification')->buildNotifications(array('uid' => $userId, 'active' => true), 10, 0);

        if ($this->container->get('request')->isXmlHttpRequest())
        {
            $result = array();
            $result['status'] = 'success';
            $result['event'] = 'notifications_show';
            $result['notifications'] = $this->container->get('templating')->render('MarbemacNotificationBundle:Notification:ajaxList.html.twig', array(
                                            'formattedNotifications' => $formattedNotifications,
                                        ));
            
            $response->setContent(json_encode($result));
            $response->headers->set('Content-Type', 'application/json');

            return $response;

        }

        return $this->container->get('templating')->renderResponse('MarbemacNotificationBundle:Notification:list.html.twig', array(
            'formattedNotifications' => $formattedNotifications,
        ));
    }
}