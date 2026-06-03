<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Event;
use App\Entity\User;
use App\Enum\AnalyticalEventType;
use App\Enum\PlatformMode;
use App\Enum\SettingName;
use App\Enum\UserTwoFactorAuthenticationStatus;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Random\RandomException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Console\Helper\Table;

#[AsCommand(
    name: 'backup:createBreakingGlassAdmin',
    description: 'Creates a temporary emergency administrator account.'
)]
class CreateBreakingGlassAdminAccountCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UserRepository $userRepository,
        private readonly SettingRepository $settingRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('backup:createBreakingGlassAdmin')
            ->setDescription('Creates a temporary emergency administrator account.')
            ->addOption(
                'yes',
                'y',
                InputOption::VALUE_NONE,
                'Automatically confirm the creation of a new administrator account.'
            );
    }

    /**
     * @throws RandomException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$input->getOption('yes')) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                'This action will create or reactivate the break-glass administrator account' .
                'Do you want to continue? [y/N]',
                false
            );
            /** @var QuestionHelper $helper */
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('Command aborted.');
                return Command::SUCCESS;
            }
        }

        $defaultValue = $this->settingRepository->findOneBy(['name' => SettingName::BREAKING_GLASS_ADMIN_EMAIL->value]);

        $usedAccount = null;
        if ($defaultValue) {
            $usedAccount = $this->userRepository->findOneBy(['email' => $defaultValue->getValue()]);
        }
        $plainPassword = bin2hex(random_bytes(16));
        $validEmail = !($defaultValue->getValue() === null) && str_starts_with(
            $defaultValue->getValue(),
            'breakglass_'
        );
        if ($usedAccount instanceof User && $validEmail) {
            $hashedPassword = $this->passwordHasher->hashPassword(
                $usedAccount,
                $plainPassword
            );
            $usedAccount->setPassword($hashedPassword);
            $usedAccount->setDeletedAt(null);
            $usedAccount->setIsVerified(true);
            $usedAccount->setTwoFAtype(UserTwoFactorAuthenticationStatus::DISABLED->value);
            $usedAccount->setRoles([
                'ROLE_SUPER_ADMIN',
            ]);
            $this->entityManager->persist($usedAccount);
            $email = $usedAccount->getEmail();
        } else {
            $username = sprintf(
                'breakglass_%s',
                substr(Uuid::v4()->toRfc4122(), 0, 8)
            );

            $email = $username . '@openroaming.com';

            $defaultValue->setValue($email);

            $user = new User();
            $user->setEmail($email);
            $user->setUsername($username);
            $user->setCreatedAt(new DateTime());
            $user->setIsVerified(true);
            $user->setTwoFAtype(UserTwoFactorAuthenticationStatus::DISABLED->value);

            $user->setRoles([
                'ROLE_SUPER_ADMIN',
            ]);


            $hashedPassword = $this->passwordHasher->hashPassword(
                $user,
                $plainPassword
            );

            $user->setPassword($hashedPassword);
            $this->entityManager->persist($user);
            $this->entityManager->persist($defaultValue);
        }

        $event = new Event();
        $event->setEventDatetime(new DateTime());
        $event->setEventName(AnalyticalEventType::BREAKING_GLASS_ACCOUNT_GENERATION->value);
        $hostname = gethostname();
        if (!$hostname) {
            $ip = '';
        } else {
            $ip = gethostbyname($hostname);
        }
        $eventMetadata = [
            'platform' => PlatformMode::CLI->value,
            'ip' => $ip,
        ];
        $event->setEventMetadata($eventMetadata);
        $this->entityManager->persist($event);

        $this->entityManager->flush();

        $output->writeln('');
        $output->writeln('<info>Break-glass admin created successfully.</info>');
        $output->writeln('');

        $table = new Table($output);

        $table
            ->setHeaders(['Field', 'Value'])
            ->setRows([
                ['Email', $email],
                ['Password', $plainPassword],
            ]);

        $table->render();

        $output->writeln('');
        $output->writeln('<comment>Store these credentials securely.</comment>');

        return Command::SUCCESS;
    }
}
