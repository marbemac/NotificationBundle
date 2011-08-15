<?php

namespace Marbemac\NotificationBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use FOS\UserBundle\Model\UserInterface;

/**
 * @MongoDB\Document
 */
class Notification
{
    /** @MongoDB\Id */
    protected $id;

    /**
     * @MongoDB\Field(type="object_id", name="uid")
     * @MongoDB\Index(order="asc")
     */
    protected $userId;

    /**
     * @MongoDB\Field(type="object_id", name="oid")
     */
    protected $objectId;

    /**
     * @MongoDB\Field(type="string", name="otype")
     */
    protected $objectType;

    /**
     * @MongoDB\Field(type="string")
     */
    protected $type;

    /**
     * @MongoDB\field(type="boolean")
     */
    protected $emailed;

    /**
     * @MongoDB\field(type="boolean")
     */
    protected $pushed;

    /**
     * @MongoDB\field(type="string")
     */
    protected $message;

    /*
     * @MongoDB\field(type="hash")
     */
    protected $contributors;

    /**
     * @MongoDB\Field(type="date", name="ua")
     * @MongoDB\Index(order="asc")
     */
    protected $updatedAt;

    /**
     * @MongoDB\Field(type="date", name="ca")
     * @MongoDB\Index(order="asc")
     */
    protected $createdAt;

    public function __construct()
    {
        $this->contributors = array();
    }

    public function getId()
    {
        return new \MongoId($this->id);
    }

    public function getUserId()
    {
        return $this->userId;
    }

    public function setUserId($userId)
    {
        $this->userId = $userId;
    }

    public function getObjectId()
    {
        return $this->objectId;
    }

    public function setObjectId($objectId)
    {
        $this->objectId = $objectId;
    }

    public function getObjectType()
    {
        return $this->objectType;
    }

    public function setObjectType($objectType)
    {
        $this->objectType = $objectType;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType($notificationType)
    {
        $this->type = $notificationType;
    }

    public function getEmailed()
    {
        return $this->emailed;
    }

    public function setEmailed($emailed)
    {
        $this->emailed = $emailed;
    }

    public function getPushed()
    {
        return $this->pushed;
    }

    public function setPushed($pushed)
    {
        $this->pushed = $pushed;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function setMessage($message)
    {
        $this->message = $message;
    }

    public function getContributors()
    {
        return $this->contributors;
    }

    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @MongoDB\prePersist
     */
    public function touchCreated()
    {
        $this->createdAt = $this->updatedAt = new \DateTime();
    }

    /**
     * @MongoDB\preUpdate
     */
    public function touchUpdated()
    {
        $this->updatedAt = new \DateTime();
    }
}

/**
 * @MongoDB\EmbeddedDocument
 */
class NotificationContributor
{
    /**
     * @MongoDB\field(type="object_id", name="uid")
     */
    protected $contributorId;

    /**
     * @MongoDB\field(type="string", name="name")
     */
    protected $contributorName;

    /**
     * @MongoDB\field(type="boolean")
     */
    protected $emailed;

    /**
     * @MongoDB\field(type="boolean")
     */
    protected $pushed;

    /**
     * @MongoDB\field(type="date", name="ca")
     */
    protected $createdAt;

    public function __construct()
    {
        $this->emailed = false;
        $this->pushed = false;
    }

    public function getContributorId()
    {
        return $this->contributorId;
    }

    public function setContributorId($contributorId)
    {
        $this->contributorId = $contributorId;
    }

    public function getContributorName()
    {
        return $this->contributorName;
    }

    public function setContributorName($contributorName)
    {
        $this->contributorName = $contributorName;
    }

    public function getEmailed()
    {
        return $this->emailed;
    }

    public function setEmailed($emailed)
    {
        $this->emailed = $emailed;
    }

    public function getPushed()
    {
        return $this->pushed;
    }

    public function setPushed($pushed)
    {
        $this->pushed = $pushed;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $date)
    {
        $this->createdAt = $date;
    }
}