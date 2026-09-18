<?php

declare(strict_types=1);

namespace Calmfox\AdminInvitation\Model\Config\Source;

use Calmfox\AdminInvitation\Core\Password\StrengthEstimator;
use Magento\Framework\Data\OptionSourceInterface;

class Strength implements OptionSourceInterface
{
    /** @return array<int, array{value: int, label: \Magento\Framework\Phrase}> */
    public function toOptionArray(): array
    {
        return [
            ['value' => StrengthEstimator::WEAK, 'label' => __('Weak')],
            ['value' => StrengthEstimator::MEDIUM, 'label' => __('Medium')],
            ['value' => StrengthEstimator::STRONG, 'label' => __('Strong (recommended)')],
            ['value' => StrengthEstimator::VERY_STRONG, 'label' => __('Very strong')],
        ];
    }
}
