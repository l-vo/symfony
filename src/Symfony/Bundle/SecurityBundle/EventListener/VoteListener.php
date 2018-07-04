<?php
/**
 * Created by IntelliJ IDEA.
 * User: laurent
 * Date: 08/10/2018
 * Time: 20:34
 */

namespace Symfony\Bundle\SecurityBundle\EventListener;


use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authorization\TraceableAccessDecisionManager;
use Symfony\Component\Security\Core\Event\VoteEvent;
use Symfony\Component\Security\Core\VoteEvents;

/**
 * Listen to vote events from access decision manager.
 *
 * @author Laurent VOULLEMIER <laurent.voullemier@gmail.com>
 */
class VoteListener implements EventSubscriberInterface
{
    private $traceableAccessDecisionManager;

    public function __construct(TraceableAccessDecisionManager $traceableAccessDecisionManager)
    {
        $this->traceableAccessDecisionManager = $traceableAccessDecisionManager;
    }

    /**
     * Event dispatched by a voter during access manager decision.
     *
     * @param VoteEvent $event event with voter data
     */
    public function onVoterVote(VoteEvent $event)
    {
        $this->traceableAccessDecisionManager->addVoterVote($event->getVoter(), $event->getVote());
    }

    public static function getSubscribedEvents()
    {
        return array(VoteEvents::VOTE => 'onVoterVote');
    }

}
