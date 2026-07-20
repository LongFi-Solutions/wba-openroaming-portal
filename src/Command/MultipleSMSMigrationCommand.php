<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand(
    name: 'prepare:multiSMSMigration',
    description: 'Migrate current credentials about BudgetSMS Api from the Settings table for the new dedicated SMSProvider management',
)]
class MultipleSMSMigrationCommand extends Command
{
    public function __construct(

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


        // get SMS_USERNAME, SMS_USER_ID, SMS_HANDLE and add to the new format


        return Command::SUCCESS;
    }
}
