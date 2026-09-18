<?php

declare(strict_types=1);

namespace Calmfox\AdminInvitation\Model\Mail;

use Calmfox\AdminInvitation\Model\Config;
use Calmfox\AdminInvitation\Model\LocaleSwitcher;
use Magento\Framework\App\Area;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;
use Magento\User\Model\User;

/**
 * The two e-mails of the module, in the shop's transactional layout and in the language of the
 * administrator they are addressed to.
 *
 * That last part takes some doing. Magento renders a transactional e-mail in the language of
 * the store view it is sent from, not of the person receiving it: the template starts its own
 * store emulation, which overwrites whatever language was in effect. Setting the language
 * around the send therefore has no effect at all.
 *
 * The seam is that the emulation refuses to nest — it returns immediately when one is already
 * running. So the emulation is started here first, the language is set inside it, and the
 * template's own attempt becomes a no-op. Stopping it restores the panel's language, so the
 * administrator who sent the invitation carries on in their own.
 *
 * Sending errors are left to the caller, who decides whether the account is kept.
 */
class Mailer
{
    public function __construct(
        private readonly TransportBuilder $transportBuilder,
        private readonly StoreManagerInterface $storeManager,
        private readonly TimezoneInterface $timezone,
        private readonly LocaleSwitcher $localeSwitcher,
        private readonly Emulation $emulation,
        private readonly Config $config,
    ) {
    }

    /** @throws \Magento\Framework\Exception\MailException */
    public function sendInvitation(User $user, string $invitationUrl, \DateTimeImmutable $validUntil): void
    {
        $this->send(
            $this->config->invitationTemplate(),
            $user,
            $validUntil,
            ['invitation_url' => $invitationUrl],
            static fn (string $storeName): string => (string) __('You have been invited to the %1 administration panel', $storeName),
        );
    }

    /** @throws \Magento\Framework\Exception\MailException */
    public function sendPasswordResetLink(User $user, string $resetUrl, \DateTimeImmutable $validUntil): void
    {
        $this->send(
            $this->config->passwordResetTemplate(),
            $user,
            $validUntil,
            ['reset_url' => $resetUrl],
            static fn (string $storeName): string => (string) __('Set a new password for the %1 administration panel', $storeName),
        );
    }

    /**
     * @param array<string, mixed>    $vars
     * @param callable(string): string $subject
     */
    private function send(string $template, User $user, \DateTimeImmutable $validUntil, array $vars, callable $subject): void
    {
        $store = $this->storeManager->getDefaultStoreView() ?? $this->storeManager->getStore();
        $storeId = (int) $store->getId();
        $locale = (string) $user->getInterfaceLocale();
        $name = trim($user->getFirstName() . ' ' . $user->getLastName());

        // force: without it the emulation declines when the store and area already match, and
        // then the template would start one of its own and choose the language for us
        $this->emulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);

        try {
            $this->localeSwitcher->switchTo($locale, Area::AREA_FRONTEND);

            $transport = $this->transportBuilder
                ->setTemplateIdentifier($template)
                ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => $storeId])
                ->setTemplateVars($vars + [
                    'user' => $user,
                    'store' => $store,
                    // the date is formatted in the same language as the words around it
                    'valid_until' => $this->formatDate($validUntil, $locale),
                    // and so is the subject, which is why it is a variable rather than a
                    // {{trans}} in the template: Magento filters the subject after it has
                    // already stopped the emulation and put the panel's language back, so a
                    // translated subject in the template would always come out in the wrong one
                    'email_subject' => $subject((string) $store->getFrontendName()),
                ])
                ->setFromByScope($this->config->emailIdentity(), $storeId)
                ->addTo((string) $user->getEmail(), '' !== $name ? $name : (string) $user->getEmail())
                ->getTransport();

            $transport->sendMessage();
        } finally {
            $this->emulation->stopEnvironmentEmulation();
        }
    }

    private function formatDate(\DateTimeImmutable $moment, string $locale): string
    {
        return $this->timezone->formatDateTime(
            $moment,
            \IntlDateFormatter::MEDIUM,
            \IntlDateFormatter::SHORT,
            '' !== $locale ? $locale : null,
        );
    }
}
