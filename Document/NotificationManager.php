<?php

namespace Marbemac\NotificationBundle\Document;

use Doctrine\ODM\MongoDB\DocumentManager;
use FOS\UserBundle\Model\UserInterface;

class NotificationManager
{
    protected $dm;
    protected $m;

    public function __construct(DocumentManager $dm)
    {
        $this->dm = $dm;

        $this->m = $dm->getConnection()->selectDatabase($dm->getConfiguration()->getDefaultDB());
    }

    public function createNotification()
    {
        $notification = new Notification();

        return $notification;
    }

    public function updateNotification(Notification $notification, $andFlush = true)
    {
        $this->dm->persist($notification);

        if ($andFlush)
        {
            $this->dm->flush();
        }
    }

    public function addNotification($userId, $objectId, $objectType, $notificationType, UserInterface $contributor, $message)
    {
        $start = new \DateTime();
        $qb = $this->dm->createQueryBuilder('Marbemac\NotificationBundle\Document\Notification')
            ->field('userId')->equals(new \MongoId($userId))
            ->field('day')->equals($start->format('Y-m-d'));

        if ($objectId && $objectType)
        {
            $qb->field('objectId')->equals(new \MongoId($objectId))
               ->field('objectType')->equals($objectType);
        }

        if ($notificationType)
        {
            $qb->field('type')->equals($notificationType);
        }

        $notification = $qb->getQuery()
                            ->getSingleResult();

        // Create the new contributor array
        $newContributor = array();
        $newContributor['uid'] = $contributor->getId();
        $newContributor['name'] = $contributor->getFullName();
        $newContributor['emailed'] = false;
        $newContributor['pushed'] = false;
        $newContributor['ca'] = new \MongoDate();

        // Create a new notification if we did not find one
        if (!$notification)
        {
            $notification = array();
            $notification['uid'] = new \MongoId($userId);
            $notification['emailed'] = false;
            $notification['pushed'] = false;
            if ($objectId && $objectType)
            {
                $notification['oid'] = new \MongoId($objectId);
                $notification['otype'] = $objectType;
            }
            if ($message)
            {
                $notification['message'] = $message;
            }
            $notification['type'] = $notificationType;
            $notification['contributors'][$contributor->getId()->__toString()] = $newContributor;
            $notification['ca'] = new \MongoDate();
            $notification['ua'] = new \MongoDate();
            $notification['day'] = $start->format('Y-m-d');

            $this->m->Notification->insert($notification);
        }
        // Push the contributor
        else
        {
            $this->m->Notification->update(
                array('_id' => $notification->getId()),
                array(
                    '$set' =>
                        array(
                            'emailed' => false,
                            'pushed' => false,
                            'ua' => new \MongoDate(),
                            'contributors.'.$contributor->getId()->__toString() => $newContributor,
                        )
                )
            );
        }
    }

    public function removeNotification($userId, $contributor, $type, $date)
    {
        $conditions = array();
        $conditions['uid'] = new \MongoId($userId);
        $conditions['type'] = $type;
        if ($date)
        {
            $conditions['day'] = $date->format('Y-m-d');
        }

        $this->m->Notification->update(
            $conditions,
            array(
                '$set' =>
                    array(
                        'contributors.'.$contributor->getId()->__toString().'.active' => false,
                    )
            )
        );
    }
}