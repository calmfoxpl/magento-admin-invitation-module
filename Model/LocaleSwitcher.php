<?php

declare(strict_types=1);

namespace Calmfox\AdminInvitation\Model;

use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\TranslateInterface;
use Magento\Framework\Validator\Locale as LocaleValidator;

/**
 * Renders the current page in the given interface locale. The public pages have no logged-in
 * user, so the panel would use its default language; the invitee's own locale (copied from
 * the inviter) is friendlier.
 */
class LocaleSwitcher
{
    public function __construct(
        private readonly ResolverInterface $localeResolver,
        private readonly TranslateInterface $translate,
        private readonly LocaleValidator $localeValidator,
    ) {
    }

    /**
     * @param string|null $area the area whose translations to load, or null for the current one.
     *                          An e-mail is rendered in the frontend area even though it is sent
     *                          from the panel, and asking for the wrong one loads the wrong words.
     */
    public function switchTo(?string $locale, ?string $area = null): void
    {
        if (null === $locale || '' === $locale || !$this->localeValidator->isValid($locale)) {
            return;
        }

        try {
            $this->localeResolver->setLocale($locale);
            $this->translate->setLocale($locale)->loadData($area, true);
        } catch (\Throwable) {
            // a locale that is not deployed: the default language is fine
        }
    }
}
