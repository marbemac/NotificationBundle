<?php

namespace Marbemac\NotificationBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class SendEmailsCommand extends ContainerAwareCommand
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setName('marbemac:notification:sendemails')
            ->setDescription('Send outstanding notification emails.')
            ->setHelp(<<<EOT
The <info>marbemac:notification:sendemails</info> command sends notification emails to users:

  <info>php app/console marbemac:notification:sendemails</info>
EOT
            );
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $notificationManager = $this->getContainer()->get('marbemac.manager.notification');
        $m = $notificationManager->getMongoConnection();
        $notifications = $notificationManager->buildNotifications(array('active' => true, 'unread' => true, 'emailed' => false), null, null, true);

        // Group by user
        $userNotifications = array();
        foreach ($notifications as $notification)
        {
            $userNotifications[$notification['userId']->__toString()][] = $notification;
        }

        $userManager = $this->getContainer()->get('whoot.manager.user');
        $templating = $this->getContainer()->get('templating');
        $mailer = $this->getContainer()->get('mailer');
        $userCount = 0;
        $notificationCount = 0;

        foreach ($userNotifications as $userId => $userNotification)
        {
            // Mark the notification as emailed
            foreach ($userNotification as $notification)
            {
                $notificationCount++;
                $criteria = array('_id' => $notification['id']);
                $m->Notification->update(
                    $criteria,
                    array(
                        '$set' =>
                            array(
                                'emailed' => true
                            )
                    )
                );
            }

            $targetUser = $userManager->findUserBy(array('id' => new \MongoId($userId)));
            $message = \Swift_Message::newInstance()
                ->setSubject($targetUser->getFullName().', you\'ve got new notifications on the whoot')
                ->setFrom('notifications@thewhoot.com')
                ->setTo($targetUser->getEmail())
                ->setBody($templating->render('MarbemacNotificationBundle:Notification:email.html.twig', array('notifications' => $userNotification, 'targetUser' => $targetUser)), 'text/html')
                ->addPart($templating->render('MarbemacNotificationBundle:Notification:email.txt.twig', array('notifications' => $userNotification, 'targetUser' => $targetUser)), 'text/plain')
            ;
            $mailer->send($message);
            
            $userCount++;
        }

        $output->writeln(sprintf('"%d" notifications sent to "%d" users.', $notificationCount, $userCount));
    }

    /**
     * @see Command
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
    }
}
