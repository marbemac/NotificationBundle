<?php

namespace Marbemac\NotificationBundle\Document;

use Doctrine\ODM\MongoDB\DocumentManager;
use FOS\UserBundle\Model\UserInterface;

class NotificationManager
{
    protected $dm;
    protected $m;
    protected $router;
    protected $class;
    protected $maxContributorShow;
    protected $userRoute;
    protected $userRouteParameter;

    public function __construct(DocumentManager $dm, $router, $class, $maxContributorShow, $userRoute, $userRouteParameter)
    {
        $this->dm = $dm;
        $this->m = $dm->getConnection()->selectDatabase($dm->getConfiguration()->getDefaultDB());
        $this->router = $router;
        $this->class = $class;
        $this->maxContributorShow = $maxContributorShow;
        $this->userRoute = $userRoute;
        $this->userRouteParameter = $userRouteParameter;
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

    public function findNotificationBy(array $criteria)
    {
        $qb = $this->dm->createQueryBuilder($this->class);

        foreach ($criteria as $field => $val)
        {
            $qb->field($field)->equals($val);
        }

        $query = $qb->getQuery();

        return $query->getSingleResult();
    }

    public function findNotificationsBy(array $criteria, array $inCriteria = array(), array $sorts = array(), $dateRange = null, $limit = null, $offset = null)
    {
        $qb = $this->dm->createQueryBuilder($this->class);

        foreach ($criteria as $field => $val)
        {
            $qb->field($field)->equals($val);
        }

        foreach ($inCriteria as $field => $vals)
        {
            $vals = is_array($vals) ? $vals : array();
            $qb->field($field)->in($vals);
        }

        foreach ($sorts as $field => $order)
        {
            $qb->sort($field, $order);
        }

        if ($dateRange)
        {
            if (isset($dateRange['start']))
            {
                $qb->field($dateRange['target'])->gte(new \MongoDate(strtotime($dateRange['start'])));
            }

            if (isset($dateRange['end']))
            {
                $qb->field($dateRange['target'])->lte(new \MongoDate(strtotime($dateRange['end'])));
            }
        }

        if ($limit !== null && $offset !== null)
        {
            $qb->limit($limit)
               ->skip($offset);
        }

        $query = $qb->getQuery();

        return $query->execute();
    }

    public function addNotification($userId, $objectId, $objectType, $notificationType, UserInterface $contributor, $message)
    {
        $today = new \DateTime();
        $qb = $this->dm->createQueryBuilder('Marbemac\NotificationBundle\Document\Notification')
            ->field('userId')->equals(new \MongoId($userId))
            ->field('day')->equals($today->format('Y-m-d'));

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
        $paramMethod = 'get'.ucfirst($this->userRouteParameter);
        $newContributor[$this->userRouteParameter] = $contributor->$paramMethod();
        $newContributor['ca'] = new \MongoDate();
        $newContributor['active'] = true;

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
            $notification['day'] = $today->format('Y-m-d');
            $notification['unread'] = true;
            $notification['active'] = true;

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
                            'active' => true,
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

        // Get the notification
        $notification = $this->findNotificationBy($conditions);

        if ($notification)
        {
            $set = array();
            $set['contributors.'.$contributor->getId()->__toString().'.active'] = false;

            $activeContributorCount = $notification->getActiveContributorCount();
            if ($activeContributorCount <= 1)
            {
                $set['active'] = false;
            }

            $this->m->Notification->update(
                array('_id' => $notification->getId()),
                array(
                    '$set' => $set
                )
            );
        }
    }

    /*
     * TODO: description...
     */
    public function buildNotifications(array $criteria, $limit, $offset)
    {
        $notifications = $this->m->Notification->find($criteria);

        if ($limit)
        {
            $notifications->limit($limit);
        }

        if ($offset)
        {
            $notifications->skip($offset);
        }

        while($notifications->hasNext()) {
            $formattedNotification = array();

            $notification = $notifications->getNext();
            $contributors = $this->getActiveContributors($notification);
            $contributorCount = count($contributors);

            $formattedNotification['text'] = $this->buildContributorsString($contributors, $contributorCount);

            if ($contributorCount == 1)
            {
                $formattedNotification['text'] .= ' '.$this->notificationVerbs[$notification['type']]['singular'];
            }
            else
            {
                $formattedNotification['text'] .= ' '.$this->notificationVerbs[$notification['type']]['plural'];
            }

            $formattedNotifications[] = $formattedNotification;
        }

        return $formattedNotifications;
    }

    protected function buildContributorsString($contributors, $contributorCount)
    {
        $text = '';

        if ($contributorCount == 0)
        {
            return 'Woops, error!';
        }
        else if ($contributorCount == 1)
        {
            $text .= $this->generateUserLink($contributors[0]);
        }
        else if ($contributorCount == 2)
        {
            $text .= $this->generateUserLink($contributors[0]).' and '.$this->generateUserLink($contributors[1]);
        }
        else if ($contributorCount <= $this->maxContributorShow)
        {
            for ($i=0; $i<$contributorCount-1; $i++)
            {
                $text .= $this->generateUserLink($contributors[$i]).', ';
            }
            $text .= 'and '.$this->generateUserLink($contributors[$contributorCount-1]);
        }
        else
        {
            for ($i=0; $i<$contributorCount-1; $i++)
            {
                $text .= $this->generateUserLink($i).', ';
            }
            $text .= 'and '.($this->maxContributorShow - $contributorCount).' others';
        }

        return $text;
    }

    protected function getActiveContributors($notification)
    {
        $contributors = array();
        foreach ($notification['contributors'] as $contributor)
        {
            if ($contributor['active'])
            {
                $contributors[] = $contributor;
            }
        }

        return $contributors;
    }

    protected function generateUserLink($contributor)
    {
        return "<a href='".$this->router->generate($this->userRoute, array($this->userRouteParameter => $contributor[$this->userRouteParameter]))."' class='user'>".$contributor['name']."</a>";
    }
}