<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Authorization;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\DebugAccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\TraceableAccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\Voter\TraceableVoter;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class TraceableAccessDecisionManagerTest extends TestCase
{
    /**
     * @dataProvider provideObjectsAndLogs
     */
    public function testDecideLog($expectedLog, $object, MockObject $voter1Mock, MockObject $voter2Mock)
    {
        $adm = new TraceableAccessDecisionManager(new AccessDecisionManager(array($voter1Mock, $voter2Mock)));
        $adm->decide($this->getMockBuilder(TokenInterface::class)->getMock(), array('ATTRIBUTE_1'), $object);

        $this->assertSame($expectedLog, $adm->getDecisionLog());
    }

    public function provideObjectsAndLogs()
    {
        $object = new \stdClass();

        $mockVoterInterface1 = $this->createVoterMock(false, 'Dummy\Voter\Voter1', VoterInterface::ACCESS_GRANTED);
        $mockVoterInterface2 = $this->createVoterMock(false, 'Dummy\Voter\Voter2', VoterInterface::ACCESS_ABSTAIN);

        yield array(
            array(array(
                'attributes' => array('ATTRIBUTE_1'),
                'object' => null,
                'result' => false,
                'voterDetails' => array(
                    'Dummy\Voter\Voter1' => VoterInterface::ACCESS_GRANTED,
                    'Dummy\Voter\Voter2' => VoterInterface::ACCESS_GRANTED,
                ),
            )),
            null,
            $this->createVoterMock(true, 'Dummy\Voter\Voter1', VoterInterface::ACCESS_GRANTED),
            $this->createVoterMock(true, 'Dummy\Voter\Voter2', VoterInterface::ACCESS_GRANTED),
        );
        yield array(
            array(array(
                'attributes' => array('ATTRIBUTE_1'),
                'object' => true,
                'result' => false,
                'voterDetails' => array(
                    'Dummy\Voter\Voter1' => VoterInterface::ACCESS_ABSTAIN,
                    'Dummy\Voter\Voter2' => VoterInterface::ACCESS_GRANTED,
                ),
            )),
            true,
            $this->createVoterMock(true, 'Dummy\Voter\Voter1', VoterInterface::ACCESS_ABSTAIN),
            $this->createVoterMock(true, 'Dummy\Voter\Voter2', VoterInterface::ACCESS_GRANTED),
        );
        yield array(
            array(array(
                'attributes' => array('ATTRIBUTE_1'),
                'object' => 'jolie string',
                'result' => false,
                'voterDetails' => array(
                    'Dummy\Voter\Voter1' => VoterInterface::ACCESS_ABSTAIN,
                    'Dummy\Voter\Voter2' => VoterInterface::ACCESS_DENIED,
                ),
            )),
            'jolie string',
            $this->createVoterMock(true, 'Dummy\Voter\Voter1', VoterInterface::ACCESS_ABSTAIN),
            $this->createVoterMock(true, 'Dummy\Voter\Voter2', VoterInterface::ACCESS_DENIED),
        );
        yield array(
            array(array(
                'attributes' => array('ATTRIBUTE_1'),
                'object' => 12345,
                'result' => false,
                'voterDetails' => array(
                    'Dummy\Voter\Voter1' => null,
                    'Dummy\Voter\Voter2' => VoterInterface::ACCESS_ABSTAIN,
                ),
            )),
            12345,
            $this->createVoterMock(true, 'Dummy\Voter\Voter1', null),
            $this->createVoterMock(true, 'Dummy\Voter\Voter2', VoterInterface::ACCESS_ABSTAIN),
        );
        yield array(
            array(array(
                'attributes' => array('ATTRIBUTE_1'),
                'object' => $x = fopen(__FILE__, 'rb'),
                'result' => false,
                'voterDetails' => array(
                    'Dummy\Voter\Voter1' => null,
                    'Dummy\Voter\Voter2' => null,
                ),
            )),
            $x,
            $this->createVoterMock(true, 'Dummy\Voter\Voter1', null),
            $this->createVoterMock(true, 'Dummy\Voter\Voter2', null),
        );
        yield array(
            array(array(
                'attributes' => array('ATTRIBUTE_1'),
                'object' => $x = array(),
                'result' => false,
                'voterDetails' => array(
                    'Dummy\Voter\Voter1' => VoterInterface::ACCESS_DENIED,
                    'Dummy\Voter\Voter2' => null,
                ),
            )),
            $x,
            $this->createVoterMock(true, 'Dummy\Voter\Voter1', VoterInterface::ACCESS_DENIED),
            $this->createVoterMock(true, 'Dummy\Voter\Voter2', null),
        );
        yield array(
            array(array(
                'attributes' => array('ATTRIBUTE_1'),
                'object' => $object,
                'result' => false,
                'voterDetails' => array(
                    'Dummy\Voter\Voter1' => VoterInterface::ACCESS_ABSTAIN,
                    \get_class($mockVoterInterface2) => null,
                ),
            )),
            $object,
            $this->createVoterMock(true, 'Dummy\Voter\Voter1', VoterInterface::ACCESS_ABSTAIN),
            $this->createVoterMock(false, 'Dummy\Voter\Voter2', VoterInterface::ACCESS_ABSTAIN),
        );
        yield array(
            array(array(
                'attributes' => array('ATTRIBUTE_1'),
                'object' => 12.74,
                'result' => false,
                'voterDetails' => array(
                    \get_class($mockVoterInterface1) => null,
                    \get_class($mockVoterInterface2) => null,
                ),
            )),
            12.74,
            $mockVoterInterface1,
            $mockVoterInterface2,
        );
    }

    /**
     * Create a mock of TraceableVoter.
     *
     * @param bool   $traceableVoter wether the created mock is a TraceableVoter instance
     * @param string $voterClass     class of the mocked voter
     * @param int    $voterVote      logged result of the voter
     *
     * @return MockObject
     */
    private function createVoterMock(bool $traceableVoter, string $voterClass, ?int $voterVote): MockObject
    {
        $methodsToMock = array('vote');
        if ($traceableVoter) {
            $classToMock = TraceableVoter::class;
            $methodsToMock = array_merge($methodsToMock, array('getVoteLog', 'getVoterClass'));
        } else {
            $classToMock = VoterInterface::class;
        }

        $mock = $this
            ->getMockBuilder($classToMock)
            ->disableOriginalConstructor()
            ->setMethods($methodsToMock)
            ->getMock();

        if ($traceableVoter) {
            $mock
                ->expects($this->any())
                ->method('getVoteLog')
                ->willReturn($voterVote);

            $mock
                ->expects($this->any())
                ->method('getVoterClass')
                ->willReturn($voterClass);
        }

        return $mock;
    }

    public function testDebugAccessDecisionManagerAliasExistsForBC()
    {
        $adm = new TraceableAccessDecisionManager(new AccessDecisionManager());

        $this->assertInstanceOf(DebugAccessDecisionManager::class, $adm, 'For BC, TraceableAccessDecisionManager must be an instance of DebugAccessDecisionManager');
    }
}
