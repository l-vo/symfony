<?php
/**
 * Created by IntelliJ IDEA.
 * User: laurent
 * Date: 08/10/2018
 * Time: 13:25
 */

namespace Symfony\Component\Security\Core;


final class VoteEvents
{
    /**
     * The VOTE event occurs after a vote is processed by a voter.
     *
     * @Event("Symfony\Component\Security\Core\Event\VoteEvents")
     */
    const VOTE = 'security.authorization.vote';
}
