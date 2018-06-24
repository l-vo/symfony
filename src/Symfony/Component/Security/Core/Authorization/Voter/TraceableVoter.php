<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authorization\Voter;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Decorates voter classes to log vote results.
 *
 * @author Laurent VOULLEMIER <laurent.voullemier@gmail.com>
 *
 * @internal
 */
class TraceableVoter implements VoterInterface
{
    /**
     * @var VoterInterface decorated voter
     */
    private $voter;

    /**
     * @var int the result of the last voter vote
     */
    private $voteLog;

    /**
     * TraceableVoter constructor.
     *
     * @param VoterInterface $voter decorated voter
     */
    public function __construct(VoterInterface $voter)
    {
        $this->voter = $voter;
    }

    /**
     * {@inheritdoc}
     */
    public function vote(TokenInterface $token, $subject, array $attributes)
    {
        $vote = $this->voter->vote($token, $subject, $attributes);

        $this->voteLog = $vote;

        return $vote;
    }

    /**
     * Returns the result of the last vote made by the decorated voter.
     *
     * @return int
     */
    public function getVoteLog()
    {
        return $this->voteLog;
    }

    /**
     * Returns the class of the decorated voter
     *
     * @return string
     */
    public function getVoterClass()
    {
        return get_class($this->voter);
    }
}