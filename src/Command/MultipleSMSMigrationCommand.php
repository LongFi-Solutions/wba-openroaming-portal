<?php

namespace App\Command;

use App\Entity\SMSProvider;
use App\Entity\SMSProviderParam;
use App\Enum\ParamType;
use App\Enum\SettingName;
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
    ) {
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

        $settingsToMigrate = [SettingName::SMS_USERNAME->value, SettingName::SMS_USER_ID->value, SettingName::SMS_HANDLE->value];
        $settingsToUpdate = [];

        foreach ($settingsToMigrate as $settingName) {
            $setting = $this->settingRepository->findOneBy(['name' => $settingName]);

            if ($setting !== null) {
                $settingsToUpdate[$settingName] = (string) $setting->getValue();
            }
        }

        if (!empty($settingsToUpdate)) {
            $smsProvider = new SMSProvider();

            /** @var string $budgetSmsUrl */
            $budgetSmsUrl = $this->parameterBag->get('app.budget_api_url');

            $smsProvider->setName('BudgetSMS');
            $smsProvider->setAddress($budgetSmsUrl);

            foreach ($settingsToUpdate as $settingName => $settingValue) {
                $smsParam = new SMSProviderParam();
                $smsParam->setParamType($settingName);
                $smsParam->setValue($settingValue);
                $smsParam->setType(ParamType::STRING);
                $smsParam->setSmsProvider($smsProvider);

                $smsProvider->addSmsProviderParam($smsParam);
                $this->entityManager->persist($smsParam);
            }

            $this->entityManager->persist($smsProvider);

            $this->entityManager->flush();

            $output->writeln('<info>SMS settings successfully migrated to SMSProvider.</info>');
        } else {
            $output->writeln('<comment>No old SMS settings found to migrate.</comment>');
        }

        return Command::SUCCESS;
    }
}
