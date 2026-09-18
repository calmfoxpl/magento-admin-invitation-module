# Magento Admin Invitation Module

[![Build](https://github.com/calmfoxpl/magento-admin-invitation-module/actions/workflows/build.yml/badge.svg)](https://github.com/calmfoxpl/magento-admin-invitation-module/actions/workflows/build.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Invite administrators to the Magento 2 panel by e-mail instead of handing out passwords. Type an address, and the invitee receives a link. On the linked page they give their name and choose their own strong password, and then land in the panel already signed in.

## Features

- **Invitations:** an *Invite Administrator* button on *System → Permissions → All Users* opens a form with an e-mail address, a role and a language. The account is created inactive, with a password nobody knows, until the invitation is accepted.
- **Two steps, one account:** the acceptance page asks who you are, then for a password. Both steps are posted together, so an abandoned setup never leaves a half-made account behind. Without JavaScript both steps are simply shown at once.
- **Strong passwords:** a minimum length plus an entropy estimate (the same formula as Symfony's `PasswordStrength`), optionally checked against known data breaches. A *Generate a secure password* button creates one in the browser with the Web Crypto API.
- **The policy applies everywhere, or only here:** the rules hook into Magento's own password validation, so by default they also cover *Forgot your password?*, *Account Setting* and the user form. One setting turns that off.
- **Account access:** an *Account Access* tab on the administrator's page:
  - an account that has not accepted its invitation yet gets it again,
  - an active account gets a link to set a new password, opened on Magento's own reset page.
- **Native look:** the acceptance page reuses the admin sign-in screen, logo and all, and the e-mails use the shop's own transactional layout. Everything can be overridden (see [Appearance](#appearance)).
- **Console:** `bin/magento calmfox:admin:invite <email>` invites the first administrator of a new installation or resends an invitation.
- **Extension point:** the `calmfox_admin_invitation_accepted` event lets other modules take over after acceptance, for example to send the new administrator to two-factor setup.
- **Translations:** English and Polish.

## Requirements

| | Version |
|---|---|
| Magento | 2.4.6, 2.4.7 (Open Source and Adobe Commerce) |
| PHP | 8.2, 8.3, 8.4 |
| Mail | any working transport; the module sends two ordinary transactional e-mails |

## Installation

```bash
composer require calmfox/magento-admin-invitation-module

bin/magento module:enable Calmfox_AdminInvitation
bin/magento setup:upgrade
bin/magento setup:di:compile     # production mode only
bin/magento cache:flush
```

The upgrade adds one table, `calmfox_admin_invitation`: one row per invited administrator, holding the hash of the link token and when it expires. Deleting a user deletes the row.

Not published on Packagist? Install from the package the same way as any other module directory:

```bash
mkdir -p app/code/Calmfox
unzip magento-admin-invitation-module.zip -d app/code/Calmfox
mv app/code/Calmfox/magento-admin-invitation-module app/code/Calmfox/AdminInvitation

bin/magento module:enable Calmfox_AdminInvitation
bin/magento setup:upgrade
bin/magento cache:flush
```

The target directory name is not free: Magento reads `app/code/*/*/registration.php`, and this module reports itself as `Calmfox_AdminInvitation`.

## Configuration

*Stores → Configuration → Security → Administrator Invitations.* Everything has a default, so the module works without opening this page.

| Setting | Default | What it does |
|---|---|---|
| Invitation Link Validity (days) | 3 | After this, the link stops working and the invitation has to be sent again. |
| Minimum Length | 12 | At least 8. Magento's own rule (7 characters, letters and digits) still applies on top. |
| Minimum Strength | Strong | Entropy estimate: weak, medium, strong, very strong. |
| Reject Passwords Found in Data Breaches | No | Asks haveibeenpwned.com; see [Privacy](#privacy). |
| Generated Password Length | 20 | Length the *Generate* button suggests, 12 to 64. |
| Apply to All Administrator Password Changes | Yes | No: the policy covers only passwords set through an invitation. |
| Sender, Invitation Template, Password Reset Link Template | General Contact, the module's own | Pick your own templates from *Marketing → Email Templates*. |

Password reset links use Magento's own *Recovery Link Expiration Period* (*Stores → Configuration → Advanced → Admin → Security*), because they open Magento's own reset page.

## How it works

**Inviting** creates an ordinary `admin_user` row that nobody can sign in to: inactive, with a long random password that is never shown to anyone, and with the e-mail address as the user name until the invitee chooses one. Magento requires a first and last name on every save, so until acceptance the account carries the local part of the address as a placeholder. A row in `calmfox_admin_invitation` holds the SHA-256 of the link token, so a copy of the table does not let anyone accept somebody else's invitation.

**Accepting** looks the invitation up by id and token, checks that it is still pending and not expired, then stores the name, the user name and the password, enables the account, spends the token and signs the invitee in through Magento's own authentication — so login observers (lockouts, password history, notifications) all run as usual. Any stale *Forgot your password?* token on the account is cleared at the same time.

**The acceptance pages are public on purpose.** They do not extend Magento's backend action, because the plugin that guards it sends every signed-out admin request to the sign-in screen and its list of open pages cannot be extended. Implementing the action interfaces directly keeps the page public without touching that plugin, at the price of doing by hand the two things the backend action would have done: loading the admin design and choosing the interface language. The POST checks the form key itself through `CsrfAwareActionInterface`.

**The password policy** is added to `Magento\User\Model\UserValidationRules::addPasswordRules()` with a plugin, which is the one place Magento consults before saving any administrator password. That is why one setting can cover every page that changes one, and why no page had to be replaced.

## Privacy

With the breach check turned on, the module asks haveibeenpwned.com whether a password is known from a data breach. It sends the first five characters of the password's SHA-1 hash and compares the answer locally: the password itself never leaves the server, and the service cannot tell which of the roughly 800 hashes in the answer was being asked about. If the API cannot be reached, the password is accepted — a broken network must not lock administrators out.

## Appearance

The module looks native. To give it your own look, change your application and leave the module untouched:

- **E-mails:** copy the templates in *Marketing → Email Templates* and select them in the module's configuration. Variables: `user`, `invitation_url`, `valid_until`, `store` for the invitation; `user`, `reset_url`, `valid_until`, `store` for the reset link.
- **Pages:** override the templates under `Calmfox_AdminInvitation::` in your admin theme, the same as any Magento template:
  - `accept/form.phtml`: the acceptance page,
  - `user/invite/form.phtml`: the invite form,
  - `user/edit/tab/account_access.phtml`: the tab on the administrator's page.
- **CSS:** the two steps carry `data-step="1"` and `data-step="2"`; the step markers carry `_current` and `_done`.
- **Texts:** override the strings in `i18n/`.

## Extending

After an invitation is accepted, the module dispatches `calmfox_admin_invitation_accepted` with:

- `user`: the new administrator,
- `redirect`: a `DataObject` whose `url` a listener may change.

```php
public function execute(Observer $observer): void
{
    $observer->getEvent()->getData('redirect')->setData('url', $myUrl);
}
```

That is how [calmfox/magento-admin-two-factor-module](https://github.com/calmfoxpl/magento-admin-two-factor-module) sends a freshly invited administrator to two-factor setup instead of the dashboard.

## Development

The unit tests cover the framework-free core — the password policy, the strength estimate, the breach protocol and the invitation tokens — plus the wiring of the XML the module is assembled from. They need neither Magento nor a database:

```bash
vendor/bin/phpunit -c phpunit.xml.dist
```

Any PHPUnit 10 or newer works; the package needs no `composer install` of its own.

## Security

See [SECURITY.md](SECURITY.md) for how to report a vulnerability.

## License

[MIT](LICENSE)
