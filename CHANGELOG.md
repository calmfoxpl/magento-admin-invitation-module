# Changelog

All notable changes to this project are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.0.0] - 2026-09-17

### Added

- Invite administrators by e-mail from *System → Permissions → All Users → Invite Administrator* and with `calmfox:admin:invite`; the account stays inactive and unusable until the invitation is accepted.
- Acceptance page in the admin sign-in look, asking in two steps — name and user name, then password — and signing the new administrator in.
- Password policy (minimum length, entropy estimate, optional breach check) plugged into Magento's own password validation, so it also covers *Forgot your password?*, *Account Setting* and the user form.
- Browser-side secure password generator using the Web Crypto API.
- *Account Access* tab: resend a pending invitation, or send a password reset link that opens Magento's own reset page.
- The `calmfox_admin_invitation_accepted` event for taking over the response after acceptance.
- English and Polish translations.

[Unreleased]: https://github.com/calmfoxpl/magento-admin-invitation-module/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/calmfoxpl/magento-admin-invitation-module/releases/tag/v1.0.0
