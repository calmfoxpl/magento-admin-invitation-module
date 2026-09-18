<?php

declare(strict_types=1);

namespace Calmfox\AdminInvitation\Model\Password;

use Calmfox\AdminInvitation\Core\Password\Policy;
use Magento\Framework\Validator\AbstractValidator;

/** The policy as one of Magento's validation rules for the "password" field of an admin user. */
class PolicyValidator extends AbstractValidator
{
    public function __construct(private readonly PolicyFactory $policyFactory)
    {
    }

    /** @param mixed $value */
    public function isValid($value): bool
    {
        $this->_clearMessages();
        if (!\is_string($value) || '' === $value) {
            // emptiness is Magento's own rule
            return true;
        }

        $policy = $this->policyFactory->get();
        $messages = [];
        foreach ($policy->violations($value) as $violation) {
            $messages[$violation] = (string) match ($violation) {
                Policy::TOO_SHORT => __('Your password must be at least %1 characters.', $policy->minLength()),
                Policy::TOO_WEAK => __('This password is too easy to guess. Make it longer and mix letters, digits and symbols, or use the generator.'),
                Policy::COMPROMISED => __('This password has appeared in a data breach. Please choose a different one.'),
            };
        }
        $this->_addMessages($messages);

        return [] === $messages;
    }
}
