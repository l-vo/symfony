<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Core\Authorization\Voter\TraceableVoter;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Adds all configured security voters to the access decision manager.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class AddSecurityVotersPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('security.access.decision_manager')) {
            return;
        }

        $voters = $this->findAndSortTaggedServices('security.voter', $container);
        if (!$voters) {
            throw new LogicException('No security voters found. You need to tag at least one with "security.voter"');
        }

        $debug = $container->hasParameter('kernel.debug') && $container->getParameter('kernel.debug');

        $decisionManagerVoters = [];
        foreach ($voters as $voter) {

            $voterServiceId = (string) $voter;
            $definition = $container->getDefinition($voterServiceId);

            $class = $container->getParameterBag()->resolveValue($definition->getClass());

            if (!is_a($class, VoterInterface::class, true)) {
                @trigger_error(sprintf('Using a "security.voter" tag on a class without implementing the "%s" is deprecated as of 3.4 and will throw an exception in 4.0. Implement the interface instead.', VoterInterface::class), E_USER_DEPRECATED);
            }

            if (!method_exists($class, 'vote')) {
                // in case the vote method is completely missing, to prevent exceptions when voting
                throw new LogicException(sprintf('%s should implement the %s interface when used as voter.', $class, VoterInterface::class));
            }

            if ($debug) {
                $debugVoterServiceId = 'debug.'.$voterServiceId;
                $container->register($debugVoterServiceId, TraceableVoter::class)
                    ->setDecoratedService($voterServiceId)
                    ->addArgument(new Reference($debugVoterServiceId.'.inner'))
                    ->setPublic(false);
            }

            $decisionManagerVoters[] = $voter;
        }

        $adm = $container->getDefinition('security.access.decision_manager');
        $adm->replaceArgument(0, new IteratorArgument($decisionManagerVoters));
    }
}
