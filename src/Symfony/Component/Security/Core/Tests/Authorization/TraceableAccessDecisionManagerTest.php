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
    public function testDecideLog(array $expectedLog, array $attributes, $object, array $voterVotes, bool $result)
    {
        $token = $this->createMock(TokenInterface::class);

        $admMock = $this
            ->getMockBuilder(AccessDecisionManager::class)
            ->setMethods(['decide'])
            ->getMock();

        $adm = new TraceableAccessDecisionManager($admMock);

        $admMock
            ->expects($this->once())
            ->method('decide')
            ->with($token, $attributes, $object)
            ->willReturnCallback(function() use ($voterVotes, $adm, $result) {
                foreach ($voterVotes as $voterVote) {
                    list($voter, $vote) = $voterVote;
                    $adm->addVoterVote($voter, $vote);
                }

                return $result;
            })
        ;

        $adm->decide($token, $attributes, $object);

        $this->assertEquals($expectedLog, $adm->getDecisionLog());
    }

    public function provideObjectsAndLogs(): \Generator
    {
        $voter1 = $this->getMockForAbstractClass(VoterInterface::class);
        $voter2 = $this->getMockForAbstractClass(VoterInterface::class);

        yield array(
            array(array(
                'attributes' => array('ATTRIBUTE_1'),
                'object' => null,
                'result' => true,
                'voterDetails' => array(
                    array('voter' => $voter1, 'vote' => VoterInterface::ACCESS_GRANTED),
                    array('voter' => $voter2, 'vote' => VoterInterface::ACCESS_GRANTED),
                ),
            )),
            array('ATTRIBUTE_1'),
            null,
            array(
                array($voter1, VoterInterface::ACCESS_GRANTED),
                array($voter2, VoterInterface::ACCESS_GRANTED)
            ),
            true
        );
        yield array(
            array(array(
                'attributes' => array('ATTRIBUTE_1'),
                'object' => true,
                'result' => false,
                'voterDetails' => array(
                    array('voter' => $voter1, 'vote' => VoterInterface::ACCESS_ABSTAIN),
                    array('voter' => $voter2, 'vote' => VoterInterface::ACCESS_GRANTED),
                ),
            )),
            array('ATTRIBUTE_1'),
            true,
            array(
                array($voter1, VoterInterface::ACCESS_ABSTAIN),
                array($voter2, VoterInterface::ACCESS_GRANTED)
            ),
            false
        );
        yield array(
            array(array(
                'attributes' => array(null),
                'object' => 'jolie string',
                'result' => false,
                'voterDetails' => array(
                    array('voter' => $voter1, 'vote' => VoterInterface::ACCESS_ABSTAIN),
                    array('voter' => $voter2, 'vote' => VoterInterface::ACCESS_DENIED),
                ),
            )),
            array(null),
            'jolie string',
            array(
                array($voter1, VoterInterface::ACCESS_ABSTAIN),
                array($voter2, VoterInterface::ACCESS_DENIED)
            ),
            false
        );
        yield array(
            array(array(
                'attributes' => array(12),
                'object' => 12345,
                'result' => true,
                'voterDetails' => array(),
            )),
            'attributes' => array(12),
            12345,
            array(),
            true
        );
        yield array(
            array(array(
                'attributes' => array(new \stdClass()),
                'object' => $x = fopen(__FILE__, 'rb'),
                'result' => true,
                'voterDetails' => array(),
            )),
            array(new \stdClass()),
            $x,
            array(),
            true
        );
        yield array(
            array(array(
                'attributes' => array('ATTRIBUTE_2'),
                'object' => $x = array(),
                'result' => false,
                'voterDetails' => array(
                    array('voter' => $voter1, 'vote' => VoterInterface::ACCESS_ABSTAIN),
                    array('voter' => $voter2, 'vote' => VoterInterface::ACCESS_ABSTAIN),
                ),
            )),
            array('ATTRIBUTE_2'),
            $x,
            array(
                array($voter1, VoterInterface::ACCESS_ABSTAIN),
                array($voter2, VoterInterface::ACCESS_ABSTAIN)
            ),
            false
        );
        yield array(
            array(array(
                'attributes' => array(12.13),
                'object' => new \stdClass(),
                'result' => false,
                'voterDetails' => array(
                    array('voter' => $voter1, 'vote' => VoterInterface::ACCESS_DENIED),
                    array('voter' => $voter2, 'vote' => VoterInterface::ACCESS_DENIED),
                ),
            )),
            array(12.13),
            new \stdClass(),
            array(
                array($voter1, VoterInterface::ACCESS_DENIED),
                array($voter2, VoterInterface::ACCESS_DENIED)
            ),
            false
        );
    }

    public function testDebugAccessDecisionManagerAliasExistsForBC()
    {
        $adm = new TraceableAccessDecisionManager(new AccessDecisionManager());

        $this->assertInstanceOf(DebugAccessDecisionManager::class, $adm, 'For BC, TraceableAccessDecisionManager must be an instance of DebugAccessDecisionManager');
    }
}
