<?php

declare(strict_types=1);

namespace Calmfox\AdminInvitation\Model\Password;

use Calmfox\AdminInvitation\Core\Password\Policy;
use Calmfox\AdminInvitation\Model\Config;

/** The policy as configured in the panel. */
class PolicyFactory
{
    private ?Policy $policy = null;

    public function __construct(
        private readonly Config $config,
        private readonly BreachLookup $breachLookup,
    ) {
    }

    public function get(): Policy
    {
        return $this->policy ??= new Policy(
            $this->config->passwordMinLength(),
            $this->config->passwordMinStrength(),
            $this->config->generatedPasswordLength(),
            $this->config->passwordNotCompromised() ? $this->breachLookup->isCompromised(...) : null,
        );
    }
}
