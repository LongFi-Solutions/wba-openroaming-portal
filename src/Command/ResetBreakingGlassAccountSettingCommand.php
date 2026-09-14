<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Event;
use App\Entity\Setting;
use App\Enum\AnalyticalEventType;
use App\Enum\EventMetadataKeysType;
use App\Enum\PlatformMode;
use App\Enum\SettingName;
use App\Repository\SettingRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand(
    name: 'reset:breakingGlassAccountSetting',
    description: 'Reset Breaking last saved account'
)]

class ResetBreakingGlassAccountSettingCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SettingRepository $settingRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Automatically confirm the reset');
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Check if the --yes option is provided (comes from a controller), then skip the confirmation prompt
        if (!$input->getOption('yes')) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('This action will RESET' .
                ' the breaking glass setting used to save the default email value for this account. ' .
                'Are you sure you want to proceed? [y/N]', false);
            /** @var QuestionHelper $helper */
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('Command aborted.');
                return Command::SUCCESS;
            }
        }

        $settings = [
            ['name' => SettingName::BREAKING_GLASS_ADMIN_EMAIL->value, 'value' => ''],
        ];

        // Begin a database transaction to ensure data consistency
        $this->entityManager->beginTransaction();

        try {
            foreach ($settings as $settingData) {
                $name = $settingData['name'];
                $value = $settingData['value'];

                // Look for all the settings using the name
                $setting = $this->settingRepository->findOneBy(['name' => $name]);

                if ($setting !== null) {
                    // Update the already existing value
                    $setting->setValue($value);
                } else {
                    // If it doesn't exist, create a new setting from the $setting
                    $setting = new Setting();
                    $setting->setName($name);
                    $setting->setValue($value);
                    $this->entityManager->persist($setting);
                }
            }

            $event = new Event();
            $event->setEventDatetime(new DateTime());
            $event->setEventName(AnalyticalEventType::BREAKING_GLASS_ACCOUNT_RESET->value);
            $hostname = gethostname();
            $ip = $hostname ? gethostbyname($hostname) : '';
            $eventMetadata = [
                EventMetadataKeysType::PLATFORM->value => PlatformMode::CLI->value,
                EventMetadataKeysType::IP->value => $ip,
            ];
            $event->setEventMetadata($eventMetadata);
            $this->entityManager->persist($event);

            $this->entityManager->flush();
            $this->entityManager->commit();

            $message = <<<EOL

<info>Success:</info> Breaking glass setting have been set to the default value.
<comment>Note:</comment> If you want to reset any another setting please check using this command:
      <fg=blue>php bin/console reset</>
EOL;

            // Output the styled message
            $output->write($message);
            $output->writeln(['']);
        } catch (Exception $e) {
            // Handle any exceptions and roll back in case of an error
            $this->entityManager->rollback();
            $output->writeln('An error occurred while resetting settings: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
