<?php

declare(strict_types=1);

namespace Calmfox\AdminInvitation\Console\Command;

use Calmfox\AdminInvitation\Model\Invitation\Inviter;
use Calmfox\AdminInvitation\Model\Invitation\Repository;
use Magento\Authorization\Model\ResourceModel\Role\CollectionFactory as RoleCollectionFactory;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Validator\EmailAddress;
use Magento\Framework\Validator\ValidatorChain;
use Magento\User\Model\ResourceModel\User as UserResource;
use Magento\User\Model\UserFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * The same as the "Invite Administrator" button, for scripted installs and for resending an
 * invitation that got lost: the first administrator of a fresh shop never has to be given a
 * password over chat.
 */
class InviteCommand extends Command
{
    private const ARGUMENT_EMAIL = 'email';

    private const OPTION_ROLE = 'role';

    private const OPTION_LOCALE = 'locale';

    public function __construct(
        private readonly Inviter $inviter,
        private readonly Repository $invitations,
        private readonly UserFactory $userFactory,
        private readonly UserResource $userResource,
        private readonly RoleCollectionFactory $roleCollectionFactory,
        private readonly State $appState,
        ?string $name = null,
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('calmfox:admin:invite');
        $this->setDescription('Invites an administrator by e-mail; they set their own name and password.');
        $this->addArgument(self::ARGUMENT_EMAIL, InputArgument::REQUIRED, 'E-mail address of the administrator');
        $this->addOption(self::OPTION_ROLE, null, InputOption::VALUE_REQUIRED, 'User role name or id (default: the only role, when there is one)');
        $this->addOption(self::OPTION_LOCALE, null, InputOption::VALUE_REQUIRED, 'Interface locale of the new account', 'en_US');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = trim((string) $input->getArgument(self::ARGUMENT_EMAIL));

        if (!ValidatorChain::is($email, EmailAddress::class)) {
            $io->error(sprintf('"%s" is not a valid e-mail address.', $email));

            return Command::FAILURE;
        }

        try {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        } catch (\Magento\Framework\Exception\LocalizedException) {
            // already set, e.g. when another command called this one
        }

        try {
            $existing = $this->findByEmail($email);

            if (null !== $existing) {
                if (!$this->invitations->isPending((int) $existing->getId())) {
                    $io->error(sprintf('%s already has an active account. They can use "Forgot your password?" on the sign-in page.', $email));

                    return Command::FAILURE;
                }

                $this->inviter->resend($existing, null);
                $io->success(sprintf('The invitation has been sent to %s again.', $email));

                return Command::SUCCESS;
            }

            $roleId = $this->resolveRoleId($input, $io);
            if (null === $roleId) {
                return Command::FAILURE;
            }

            $this->inviter->invite($email, $roleId, (string) $input->getOption(self::OPTION_LOCALE), null);
            $io->success(sprintf('The invitation has been sent to %s.', $email));

            return Command::SUCCESS;
        } catch (\Magento\Framework\Exception\MailException $exception) {
            $io->error(sprintf(
                "The account was created, but the invitation could not be sent: %s\nFix the mail settings and run this command again to resend it.",
                $exception->getMessage(),
            ));

            return Command::FAILURE;
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }
    }

    private function findByEmail(string $email): ?\Magento\User\Model\User
    {
        $user = $this->userFactory->create();
        $this->userResource->load($user, $email, 'email');

        return $user->getId() ? $user : null;
    }

    /** The role from --role, or the only role there is; anything else has to be named. */
    private function resolveRoleId(InputInterface $input, SymfonyStyle $io): ?int
    {
        $collection = $this->roleCollectionFactory->create();
        $collection->setRolesFilter()->setOrder('role_name', 'ASC');

        $roles = [];
        foreach ($collection as $role) {
            $roles[(int) $role->getId()] = (string) $role->getRoleName();
        }

        if ([] === $roles) {
            $io->error('There is no user role to assign. Create one in System > Permissions > User Roles first.');

            return null;
        }

        $requested = $input->getOption(self::OPTION_ROLE);
        if (null === $requested || '' === $requested) {
            if (1 === \count($roles)) {
                return (int) array_key_first($roles);
            }

            $io->error(sprintf('Choose a role with --role. Available: %s.', implode(', ', $roles)));

            return null;
        }

        if (ctype_digit((string) $requested) && isset($roles[(int) $requested])) {
            return (int) $requested;
        }

        foreach ($roles as $id => $name) {
            if (0 === strcasecmp($name, (string) $requested)) {
                return $id;
            }
        }

        $io->error(sprintf('There is no role "%s". Available: %s.', $requested, implode(', ', $roles)));

        return null;
    }
}
