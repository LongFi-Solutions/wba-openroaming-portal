<?php

namespace App\Command;

use App\Entity\SMSProvider;
use App\Entity\SMSProviderParam;
use App\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'prepare:multiSMSMigration',
    description: 'Migrate current credentials about BudgetSMS Api from the Settings table for the new dedicated SMSProvider management',
)]
class MultipleSMSMigrationCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SettingRepository $settingRepository,
        private readonly ParameterBagInterface $parameterBag,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('prepare:multiSMSMigration')
            ->setDescription(
                'Migrate current credentials about BudgetSMS Api from the Settings table for the new dedicated SMSProvider management'
            )
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Automatically confirm the migration');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Check if the --yes option is provided (comes from a controller), then skip the confirmation prompt
        if (!$input->getOption('yes')) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                'This action will migrate ALL SMS SETTINGS Budget Api details for the new format. [y/N] ',
                false
            );
            /** @var QuestionHelper $helper */
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('Command aborted.');
                return Command::SUCCESS;
            }
        }

        $smsUsernameSetting = $this->settingRepository->findOneBy(['name' => 'SMS_USERNAME']); // Search with string because we will remove this setting name from enum
        if ($smsUsernameSetting) {
            $settingsToUpdate['SMS_USERNAME'] = $smsUsernameSetting->getValue();
        }

        $this->entityManager->remove($smsUsernameSetting);

        $smsUserIdSetting = $this->settingRepository->findOneBy(['name' => 'SMS_USER_ID']); // Search with string because we will remove this setting name from enum

        $settingsToUpdate = [];
        if ($smsUserIdSetting) {
            $settingsToUpdate['SMS_USER_ID'] = $smsUserIdSetting->getValue();
        }

        $this->entityManager->remove($smsUserIdSetting);

        $smsHandleSetting = $this->settingRepository->findOneBy(['name' => 'SMS_HANDLE']); // Search with string because we will remove this setting name from enum

        if ($smsHandleSetting) {
            $settingsToUpdate['SMS_HANDLE'] = $smsHandleSetting->getValue();
        }

        $this->entityManager->remove($smsHandleSetting);
        $this->entityManager->flush();

        $smsProvider = new SMSProvider();

        $budgetSmsUrl = $this->parameterBag->get('app.budget_api_url');

        $smsProvider->setName('BudgetSMS');
        $smsProvider->setAddress($budgetSmsUrl);

        foreach ($settingsToUpdate as $settingName => $settingValue) {
            $smsParam = new SMSProviderParam();
            $smsParam->setParamType($settingName);
            $smsParam->setValue($settingValue);
            $smsParam->setSmsProvider($smsProvider);
            $smsProvider->addSmsProviderParam($smsParam);
            $this->entityManager->persist($smsProvider);
        }

        $this->entityManager->persist($smsProvider);
        $this->entityManager->flush();


        return Command::SUCCESS;
    }
}
