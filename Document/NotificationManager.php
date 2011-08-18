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

    public function addNotification($userId, $objectId, $objectType, $objectTitle, $objectUrl, $notificationType, UserInterface $contributor, $message, $checkBy)
    {
        $today = new \DateTime();

        $conditions = array();
        $conditions['uid'] = new \MongoId($userId);
        $conditions['type'] = $notificationType;

        if ($objectId && $objectType)
        {
            $conditions['oid'] = $objectId;
            $conditions['otype'] = $objectType;
        }

        if ($checkBy == 'today')
        {
            $conditions['day'] = $today->format('Y-m-d');
        }
        else if ($checkBy == 'contributor')
        {
            $conditions['contributors.'.$contributor->getId()->__toString()] = array('$exists' => true);
        }

        $notification = $this->m->Notification->findOne($conditions);

        // If we didn't find a notification and we were checking by contributor, let's try by today.
        if (!$notification && $checkBy == 'contributor')
        {
            $checkBy = 'date';
            unset($conditions['contributors.'.$contributor->getId()->__toString()]);
            $conditions['day'] = $today->format('Y-m-d');
            $notification = $this->m->Notification->findOne($conditions);
        }

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
            if ($objectTitle)
            {
                $notification['otitle'] = $objectTitle;
            }
            if ($objectUrl)
            {
                $notification['ourl'] = $objectUrl;
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
            $set = array(
                            'emailed' => false,
                            'pushed' => false,
                            'active' => true,
                            'ua' => new \MongoDate(),
                        );

            if ($checkBy == 'contributor')
            {
                $set['contributors.'.$contributor->getId()->__toString().'.active'] = true;
            }
            else
            {
                $set['contributors.'.$contributor->getId()->__toString()] = $newContributor;
            }

            $this->m->Notification->update(
                array('_id' => $notification['_id']),
                array(
                    '$set' => $set
                )
            );
        }
    }

    public function removeNotification($userId, UserInterface $contributor, $type, $objectId=null, $objectType=null, $date=null)
    {
        $conditions = array();
        $conditions['uid'] = new \MongoId($userId);
        $conditions['type'] = $type;
        $conditions['contributors.'.$contributor->getId()->__toString().'.active'] = true;

        if ($objectId && $objectType)
        {
            $conditions['oid'] = $objectId;
            $conditions['otype'] = $objectType;
        }

        if ($date)
        {
            $conditions['day'] = $date->format('Y-m-d');
        }

        // Get the notification
        $notification = $this->m->Notification->findOne($conditions);

        if ($notification)
        {
            $set = array();
            $set['contributors.'.$contributor->getId()->__toString().'.active'] = false;

            $activeContributorCount = $this->getActiveContributors($notification);
            if ($activeContributorCount <= 1)
            {
                $set['active'] = false;
            }

            $this->m->Notification->update(
                array('_id' => $notification['_id']),
                array(
                    '$set' => $set
                )
            );
        }
    }

    // Mark all notifications for user as read
    public function markRead($userId)
    {
        $criteria = array('unread' => true, 'uid' => new \MongoId($userId), 'active' => true);

        $this->m->Notification->update(
            $criteria,
            array(
                '$set' =>
                    array(
                        'unread' => false
                    )
            ),
            array(
                'multiple' => true
            )
        );
    }

    /*
     * TODO: description...
     */
    public function buildNotifications(array $criteria, $limit, $offset, $absoluteUrl = false)
    {
        $notifications = $this->m->Notification->find($criteria)->sort(array('ca' => -1));

        if ($limit)
        {
            $notifications->limit($limit);
        }

        if ($offset)
        {
            $notifications->skip($offset);
        }

        $formattedNotifications = array();

        while($notifications->hasNext()) {
            $formattedNotification = array();

            $notification = $notifications->getNext();
            $contributors = $this->getActiveContributors($notification);
            $contributorCount = count($contributors);

            $formattedNotification['id'] = $notification['_id'];
            $formattedNotification['userId'] = $notification['uid'];
            $formattedNotification['unread'] = $notification['unread'];
            // Add the linked contributors
            $formattedNotification['text'] = $this->buildContributorsString($this->notificationThemes[$notification['type']], $contributors, $contributorCount, $absoluteUrl);
            $formattedNotification['timestamp'] = $notification['ca']->sec;

            // Add the verb
            if ($contributorCount == 1)
            {
                $formattedNotification['text'] .= ' '.$this->notificationVerbs[$notification['type']]['singular'];
            }
            else
            {
                $formattedNotification['text'] .= ' '.$this->notificationVerbs[$notification['type']]['plural'];
            }

            // Add the linked (if url is available) object with title (if available)
            if (isset($notification['oid']) && isset($notification['otype']))
            {
                if (isset($notification['ourl']) && isset($notification['otitle']))
                {
                    $formattedNotification['text'] .= ' '.strtolower($notification['otype']).': <a href="'.$notification['ourl'].'">'.$notification['otitle'].'</a>';
                }
                else if (isset($notification['ourl']))
                {
                    $formattedNotification['text'] .= ' <a href="'.$notification['ourl'].'">'.strtolower($notification['otype']).'</a>';
                }
                else
                {
                    $formattedNotification['text'] .= ' '.strtolower($notification['otype']);
                }
            }

            $formattedNotifications[] = $formattedNotification;
        }

        return $formattedNotifications;
    }

    protected function buildContributorsString($theme, $contributors, $contributorCount, $absoluteUrl)
    {
        $text = '';

        if ($theme == 'count')
        {
            $text .= $contributorCount.($contributorCount > 1 ? ' people' : ' person');
        }
        else if ($theme == 'full')
        {
            if ($contributorCount == 0)
            {
                return 'Woops, error!';
            }
            else if ($contributorCount == 1)
            {
                $text .= $this->generateUserLink($contributors[0], $absoluteUrl);
            }
            else if ($contributorCount == 2)
            {
                $text .= $this->generateUserLink($contributors[0], $absoluteUrl).' and '.$this->generateUserLink($contributors[1], $absoluteUrl);
            }
            else if ($contributorCount <= $this->maxContributorShow)
            {
                for ($i=0; $i<$contributorCount-1; $i++)
                {
                    $text .= $this->generateUserLink($contributors[$i], $absoluteUrl).', ';
                }
                $text .= 'and '.$this->generateUserLink($contributors[$contributorCount-1], $absoluteUrl);
            }
            else
            {
                for ($i=0; $i<$contributorCount-1; $i++)
                {
                    $text .= $this->generateUserLink($i, $absoluteUrl).', ';
                }
                $text .= 'and '.($this->maxContributorShow - $contributorCount).' others';
            }
        }

        return $text;
    }

    public function getMongoConnection()
    {
        return $this->m;
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

    protected function generateUserLink($contributor, $absoluteUrl)
    {
        return "<a href='".$this->router->generate($this->userRoute, array($this->userRouteParameter => $contributor[$this->userRouteParameter]), $absoluteUrl)."' class='user'>".$contributor['name']."</a>";
    }
}