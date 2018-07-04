<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Authorization\Voter;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\TraceableVoter;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Test TraceableVoter class.
 *
 * @author Laurent VOULLEMIER <laurent.voullemier@gmail.com>
 */
class TraceableVoterTest extends TestCase
{
    /**
     * Data provider for testVoteLog.
     *
     * @return array
     */
    public function providerVoteLog(): array
    {
        return array(
            array(VoterInterface::ACCESS_ABSTAIN, VoterInterface::ACCESS_ABSTAIN),
            array(VoterInterface::ACCESS_GRANTED, VoterInterface::ACCESS_GRANTED),
            array(VoterInterface::ACCESS_DENIED, VoterInterface::ACCESS_DENIED),
        );
    }

    /**
     * Test that the decorator returns the same value as the original vote returned.
     *
     * @param int $voteReturn   value returned by the decorated voter
     * @param int $voteExpected expected value returned by the decorator
     *
     * @dataProvider providerVoteLog
     */
    public function testVoteLog($voteReturn, $voteExpected): void
    {
        $voterMock = $this
            ->getMockBuilder(VoterInterface::class)
            ->setMethods(array('vote'))
            ->getMock();

        $voterMock
            ->expects($this->any())
            ->method('vote')
            ->willReturn($voteReturn);

        $tokenMock = $this
            ->getMockBuilder(TokenInterface::class)
            ->getMock();

        $traceableVoter = new TraceableVoter($voterMock);
        $traceableVoter->vote($tokenMock, null, array());

        $this->assertEquals($voteExpected, $traceableVoter->getVoteLog(), 'Wrong voteLog return');
        $this->assertEquals(\get_class($voterMock), $traceableVoter->getVoterClass(), 'Wrong decorated voter class');
    }

    /**
     * Test that voteLog returns null if vote method isn't called.
     */
    public function testReturnValueWithoutVote(): void
    {
        $voterMock = $this
            ->getMockBuilder(VoterInterface::class)
            ->getMock();

        $traceableVoter = new TraceableVoter($voterMock);
        $this->assertNull($traceableVoter->getVoteLog(), 'Wrong voteLog return');
    }
}
