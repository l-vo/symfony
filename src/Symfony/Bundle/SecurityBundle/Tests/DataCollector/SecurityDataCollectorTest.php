<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\DataCollector;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\DataCollector\SecurityDataCollector;
use Symfony\Bundle\SecurityBundle\Debug\TraceableFirewallListener;
use Symfony\Bundle\SecurityBundle\Security\FirewallConfig;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\TraceableAccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\Voter\TraceableVoter;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Role\Role;
use Symfony\Component\Security\Core\Role\RoleHierarchy;
use Symfony\Component\Security\Core\Role\SwitchUserRole;
use Symfony\Component\Security\Http\Firewall\ListenerInterface;
use Symfony\Component\Security\Http\FirewallMapInterface;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator;

class SecurityDataCollectorTest extends TestCase
{
    public function testCollectWhenSecurityIsDisabled()
    {
        $collector = new SecurityDataCollector();
        $collector->collect($this->getRequest(), $this->getResponse());

        $this->assertSame('security', $collector->getName());
        $this->assertFalse($collector->isEnabled());
        $this->assertFalse($collector->isAuthenticated());
        $this->assertFalse($collector->isImpersonated());
        $this->assertNull($collector->getImpersonatorUser());
        $this->assertNull($collector->getImpersonationExitPath());
        $this->assertNull($collector->getTokenClass());
        $this->assertFalse($collector->supportsRoleHierarchy());
        $this->assertCount(0, $collector->getRoles());
        $this->assertCount(0, $collector->getInheritedRoles());
        $this->assertEmpty($collector->getUser());
        $this->assertNull($collector->getFirewall());
    }

    public function testCollectWhenAuthenticationTokenIsNull()
    {
        $tokenStorage = new TokenStorage();
        $collector = new SecurityDataCollector($tokenStorage, $this->getRoleHierarchy());
        $collector->collect($this->getRequest(), $this->getResponse());

        $this->assertTrue($collector->isEnabled());
        $this->assertFalse($collector->isAuthenticated());
        $this->assertFalse($collector->isImpersonated());
        $this->assertNull($collector->getImpersonatorUser());
        $this->assertNull($collector->getImpersonationExitPath());
        $this->assertNull($collector->getTokenClass());
        $this->assertTrue($collector->supportsRoleHierarchy());
        $this->assertCount(0, $collector->getRoles());
        $this->assertCount(0, $collector->getInheritedRoles());
        $this->assertEmpty($collector->getUser());
        $this->assertNull($collector->getFirewall());
    }

    /** @dataProvider provideRoles */
    public function testCollectAuthenticationTokenAndRoles(array $roles, array $normalizedRoles, array $inheritedRoles)
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken('hhamon', 'P4$$w0rD', 'provider', $roles));

        $collector = new SecurityDataCollector($tokenStorage, $this->getRoleHierarchy());
        $collector->collect($this->getRequest(), $this->getResponse());
        $collector->lateCollect();

        $this->assertTrue($collector->isEnabled());
        $this->assertTrue($collector->isAuthenticated());
        $this->assertFalse($collector->isImpersonated());
        $this->assertNull($collector->getImpersonatorUser());
        $this->assertNull($collector->getImpersonationExitPath());
        $this->assertSame('Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken', $collector->getTokenClass()->getValue());
        $this->assertTrue($collector->supportsRoleHierarchy());
        $this->assertSame($normalizedRoles, $collector->getRoles()->getValue(true));
        $this->assertSame($inheritedRoles, $collector->getInheritedRoles()->getValue(true));
        $this->assertSame('hhamon', $collector->getUser());
    }

    public function testCollectImpersonatedToken()
    {
        $adminToken = new UsernamePasswordToken('yceruto', 'P4$$w0rD', 'provider', array('ROLE_ADMIN'));

        $userRoles = array(
            'ROLE_USER',
            new SwitchUserRole('ROLE_PREVIOUS_ADMIN', $adminToken),
        );

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken('hhamon', 'P4$$w0rD', 'provider', $userRoles));

        $collector = new SecurityDataCollector($tokenStorage, $this->getRoleHierarchy());
        $collector->collect($this->getRequest(), $this->getResponse());
        $collector->lateCollect();

        $this->assertTrue($collector->isEnabled());
        $this->assertTrue($collector->isAuthenticated());
        $this->assertTrue($collector->isImpersonated());
        $this->assertSame('yceruto', $collector->getImpersonatorUser());
        $this->assertSame('Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken', $collector->getTokenClass()->getValue());
        $this->assertTrue($collector->supportsRoleHierarchy());
        $this->assertSame(array('ROLE_USER', 'ROLE_PREVIOUS_ADMIN'), $collector->getRoles()->getValue(true));
        $this->assertSame(array(), $collector->getInheritedRoles()->getValue(true));
        $this->assertSame('hhamon', $collector->getUser());
    }

    public function testGetFirewall()
    {
        $firewallConfig = new FirewallConfig('dummy', 'security.request_matcher.dummy', 'security.user_checker.dummy');
        $request = $this->getRequest();

        $firewallMap = $this
            ->getMockBuilder(FirewallMap::class)
            ->disableOriginalConstructor()
            ->getMock();
        $firewallMap
            ->expects($this->once())
            ->method('getFirewallConfig')
            ->with($request)
            ->willReturn($firewallConfig);

        $collector = new SecurityDataCollector(null, null, null, null, $firewallMap, new TraceableFirewallListener($firewallMap, new EventDispatcher(), new LogoutUrlGenerator()));
        $collector->collect($request, $this->getResponse());
        $collector->lateCollect();
        $collected = $collector->getFirewall();

        $this->assertSame($firewallConfig->getName(), $collected['name']);
        $this->assertSame($firewallConfig->allowsAnonymous(), $collected['allows_anonymous']);
        $this->assertSame($firewallConfig->getRequestMatcher(), $collected['request_matcher']);
        $this->assertSame($firewallConfig->isSecurityEnabled(), $collected['security_enabled']);
        $this->assertSame($firewallConfig->isStateless(), $collected['stateless']);
        $this->assertSame($firewallConfig->getProvider(), $collected['provider']);
        $this->assertSame($firewallConfig->getContext(), $collected['context']);
        $this->assertSame($firewallConfig->getEntryPoint(), $collected['entry_point']);
        $this->assertSame($firewallConfig->getAccessDeniedHandler(), $collected['access_denied_handler']);
        $this->assertSame($firewallConfig->getAccessDeniedUrl(), $collected['access_denied_url']);
        $this->assertSame($firewallConfig->getUserChecker(), $collected['user_checker']);
        $this->assertSame($firewallConfig->getListeners(), $collected['listeners']->getValue());
    }

    public function testGetFirewallReturnsNull()
    {
        $request = $this->getRequest();
        $response = $this->getResponse();

        // Don't inject any firewall map
        $collector = new SecurityDataCollector();
        $collector->collect($request, $response);
        $this->assertNull($collector->getFirewall());

        // Inject an instance that is not context aware
        $firewallMap = $this
            ->getMockBuilder(FirewallMapInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $collector = new SecurityDataCollector(null, null, null, null, $firewallMap, new TraceableFirewallListener($firewallMap, new EventDispatcher(), new LogoutUrlGenerator()));
        $collector->collect($request, $response);
        $this->assertNull($collector->getFirewall());

        // Null config
        $firewallMap = $this
            ->getMockBuilder(FirewallMap::class)
            ->disableOriginalConstructor()
            ->getMock();

        $collector = new SecurityDataCollector(null, null, null, null, $firewallMap, new TraceableFirewallListener($firewallMap, new EventDispatcher(), new LogoutUrlGenerator()));
        $collector->collect($request, $response);
        $this->assertNull($collector->getFirewall());
    }

    /**
     * @group time-sensitive
     */
    public function testGetListeners()
    {
        $request = $this->getRequest();
        $event = new GetResponseEvent($this->getMockBuilder(HttpKernelInterface::class)->getMock(), $request, HttpKernelInterface::MASTER_REQUEST);
        $event->setResponse($response = $this->getResponse());
        $listener = $this->getMockBuilder(ListenerInterface::class)->getMock();
        $listener
            ->expects($this->once())
            ->method('handle')
            ->with($event);
        $firewallMap = $this
            ->getMockBuilder(FirewallMap::class)
            ->disableOriginalConstructor()
            ->getMock();
        $firewallMap
            ->expects($this->any())
            ->method('getFirewallConfig')
            ->with($request)
            ->willReturn(null);
        $firewallMap
            ->expects($this->once())
            ->method('getListeners')
            ->with($request)
            ->willReturn(array(array($listener), null, null));

        $firewall = new TraceableFirewallListener($firewallMap, new EventDispatcher(), new LogoutUrlGenerator());
        $firewall->onKernelRequest($event);

        $collector = new SecurityDataCollector(null, null, null, null, $firewallMap, $firewall);
        $collector->collect($request, $response);

        $this->assertNotEmpty($collected = $collector->getListeners()[0]);
        $collector->lateCollect();
        $this->addToAssertionCount(1);
    }

    public function providerCollectDecisionLog(): \Generator
    {
        $voter1 = $this->getMockBuilder('Voter1')->getMock();
        $voter2 = $this->getMockBuilder('Voter2')->getMock();

        yield array(
            AccessDecisionManager::STRATEGY_AFFIRMATIVE,
            array(array(
                'attributes' => array('view'),
                'object' => new \stdClass(),
                'result' => true,
                'voterDetails' => array(
                    array('voter' => $voter1, 'vote' => VoterInterface::ACCESS_ABSTAIN),
                    array('voter' => $voter2, 'vote' => VoterInterface::ACCESS_ABSTAIN)
                ),
            )),
            array($voter1, $voter2),
            array(\get_class($voter1), \get_class($voter2)),
            array(array(
                'attributes' => array('view'),
                'object' => new \stdClass(),
                'result' => true,
                'voter_details' => array(
                    array('class' => \get_class($voter1), 'vote' => VoterInterface::ACCESS_ABSTAIN),
                    array('class' => \get_class($voter2), 'vote' => VoterInterface::ACCESS_ABSTAIN)
                ),
            )),
        );

        yield array(
            AccessDecisionManager::STRATEGY_UNANIMOUS,
            array(
                array(
                    'attributes' => array('view', 'edit'),
                    'object' => new \stdClass(),
                    'result' => false,
                    'voterDetails' => array(
                        array('voter' => $voter1, 'vote' => VoterInterface::ACCESS_DENIED),
                        array('voter' => $voter2, 'vote' => VoterInterface::ACCESS_GRANTED)
                    ),
                ),
                array(
                    'attributes' => array('update'),
                    'object' => new \stdClass(),
                    'result' => true,
                    'voterDetails' => array(
                        array('voter' => $voter1, 'vote' => VoterInterface::ACCESS_GRANTED),
                        array('voter' => $voter2, 'vote' => VoterInterface::ACCESS_GRANTED)
                    ),
                ),
            ),
            array($voter1, $voter2),
            array(\get_class($voter1), \get_class($voter2)),
            array(
                array(
                    'attributes' => array('view', 'edit'),
                    'object' => new \stdClass(),
                    'result' => false,
                    'voter_details' => array(
                        array('class' => \get_class($voter1), 'vote' => VoterInterface::ACCESS_DENIED),
                        array('class' => \get_class($voter2), 'vote' => VoterInterface::ACCESS_GRANTED)
                    ),
                ),
                array(
                    'attributes' => array('update'),
                    'object' => new \stdClass(),
                    'result' => true,
                    'voter_details' => array(
                        array('class' => \get_class($voter1), 'vote' => VoterInterface::ACCESS_GRANTED),
                        array('class' => \get_class($voter2), 'vote' => VoterInterface::ACCESS_GRANTED)
                    ),
                ),
            )
        );
    }

    /**
     * Test the returned data when AccessDecisionManager is a TraceableAccessDecisionManager.
     *
     * @param string $strategy             strategy returned by the AccessDecisionManager
     * @param array  $voters               voters returned by AccessDecisionManager
     * @param array  $decisionLog          log of the votes and final decisions from AccessDecisionManager
     * @param array  $expectedVoterClasses expected voter classes returned by the collector
     * @param array  $expectedDecisionLog  expected decision log returned by the collector
     *
     * @dataProvider providerCollectDecisionLog
     */
    public function testCollectDecisionLog(string $strategy, array $decisionLog, array $voters, array $expectedVoterClasses, array $expectedDecisionLog): void
    {
        $accessDecisionManager = $this
            ->getMockBuilder(TraceableAccessDecisionManager::class)
            ->disableOriginalConstructor()
            ->setMethods(array('getStrategy', 'getVoters', 'getDecisionLog'))
            ->getMock();

        $accessDecisionManager
            ->expects($this->any())
            ->method('getStrategy')
            ->willReturn($strategy);

        $accessDecisionManager
            ->expects($this->any())
            ->method('getVoters')
            ->willReturn($voters);

        $accessDecisionManager
            ->expects($this->any())
            ->method('getDecisionLog')
            ->willReturn($decisionLog);

        $dataCollector = new SecurityDataCollector(null, null, null, $accessDecisionManager);
        $dataCollector->collect($this->getRequest(), $this->getResponse());

        $this->assertEquals($dataCollector->getAccessDecisionLog(), $expectedDecisionLog, 'Wrong value returned by getAccessDecisionLog');

        $this->assertSame(
            array_map(function ($classStub) { return (string) $classStub; }, $dataCollector->getVoters()),
            $expectedVoterClasses,
            'Wrong value returned by getVoters'
        );
        $this->assertSame($dataCollector->getVoterStrategy(), $strategy, 'Wrong value returned by getVoterStrategy');
    }

    public function provideRoles()
    {
        return array(
            // Basic roles
            array(
                array('ROLE_USER'),
                array('ROLE_USER'),
                array(),
            ),
            array(
                array(new Role('ROLE_USER')),
                array('ROLE_USER'),
                array(),
            ),
            // Inherited roles
            array(
                array('ROLE_ADMIN'),
                array('ROLE_ADMIN'),
                array('ROLE_USER', 'ROLE_ALLOWED_TO_SWITCH'),
            ),
            array(
                array(new Role('ROLE_ADMIN')),
                array('ROLE_ADMIN'),
                array('ROLE_USER', 'ROLE_ALLOWED_TO_SWITCH'),
            ),
            array(
                array('ROLE_ADMIN', 'ROLE_OPERATOR'),
                array('ROLE_ADMIN', 'ROLE_OPERATOR'),
                array('ROLE_USER', 'ROLE_ALLOWED_TO_SWITCH'),
            ),
        );
    }

    private function getRoleHierarchy()
    {
        return new RoleHierarchy(array(
            'ROLE_ADMIN' => array('ROLE_USER', 'ROLE_ALLOWED_TO_SWITCH'),
            'ROLE_OPERATOR' => array('ROLE_USER'),
        ));
    }

    private function getRequest()
    {
        return $this
            ->getMockBuilder('Symfony\Component\HttpFoundation\Request')
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function getResponse()
    {
        return $this
            ->getMockBuilder('Symfony\Component\HttpFoundation\Response')
            ->disableOriginalConstructor()
            ->getMock();
    }
}
