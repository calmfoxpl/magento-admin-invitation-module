<?php

declare(strict_types=1);

namespace Calmfox\AdminInvitation\Model;

use Magento\Backend\Model\UrlInterface as BackendUrl;
use Magento\Framework\App\State;

/**
 * Absolute links to the module's public pages and to Magento's own password reset page.
 * They carry no secret key (the reader has no session) and respect a custom admin URL.
 */
class Link
{
    public function __construct(
        private readonly BackendUrl $backendUrl,
        private readonly State $appState,
    ) {
    }

    public function acceptInvitation(int $userId, #[\SensitiveParameter] string $token): string
    {
        return $this->build('calmfox_invitation/accept/index', ['id' => $userId, 'token' => $token]);
    }

    public function resetPassword(int $userId, #[\SensitiveParameter] string $token): string
    {
        return $this->build('adminhtml/auth/resetpassword', ['id' => $userId, 'token' => $token]);
    }

    public function login(): string
    {
        return $this->build('adminhtml', []);
    }

    /** @param array<string, int|string> $query */
    private function build(string $route, array $query): string
    {
        $params = ['_nosecret' => true, '_nosid' => true];
        if ([] !== $query) {
            $params['_query'] = $query;
        }

        // the console has no area; the URL model needs the admin one to know its front name
        try {
            $this->appState->getAreaCode();

            return $this->backendUrl->getUrl($route, $params);
        } catch (\Magento\Framework\Exception\LocalizedException) {
            return $this->appState->emulateAreaCode(
                \Magento\Framework\App\Area::AREA_ADMINHTML,
                fn (): string => $this->backendUrl->getUrl($route, $params),
            );
        }
    }
}
