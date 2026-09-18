<?php

declare(strict_types=1);

namespace Calmfox\AdminInvitation\Plugin;

use Calmfox\AdminInvitation\Model\Config;
use Calmfox\AdminInvitation\Model\Password\PolicyScope;
use Calmfox\AdminInvitation\Model\Password\PolicyValidatorFactory;
use Magento\Framework\Validator\DataObject;
use Magento\User\Model\UserValidationRules;

/**
 * Adds the policy to Magento's password rules. Magento applies these rules whenever an admin
 * password is about to be saved, so one plugin covers the invitation page, "Forgot your
 * password?", Account Setting and the user form.
 */
class PasswordPolicyRules
{
    public function __construct(
        private readonly PolicyValidatorFactory $validatorFactory,
        private readonly PolicyScope $scope,
        private readonly Config $config,
    ) {
    }

    public function afterAddPasswordRules(UserValidationRules $subject, DataObject $validator): DataObject
    {
        if ($this->config->policyAppliesEverywhere() || $this->scope->isEnforced()) {
            $validator->addRule($this->validatorFactory->create(), 'password');
        }

        return $validator;
    }
}
