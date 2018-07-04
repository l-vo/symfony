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
    private $voter;

    /**
     * @var int the result of the last voter vote
     */
    private $voteLog;

    public function __construct(VoterInterface $voter)
    {
        $this->voter = $voter;
    }

    /**
     * {@inheritdoc}
     */
    public function vote(TokenInterface $token, $subject, array $attributes)
    {
        $this->voteLog = $this->voter->vote($token, $subject, $attributes);

        return $this->voteLog;
    }

    public function getVoteLog(): ?int
    {
        return $this->voteLog;
    }

    public function getVoterClass(): string
    {
        return \get_class($this->voter);
    }
}
