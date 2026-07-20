<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\SMSProvider;
use App\Entity\SMSProviderParam;
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
    name: 'clear:smsProviders',
    description: 'Clear all SMS providers and their parameters from the database',
)]
class ClearSMSProvidersCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('clear:smsProviders')
            ->setDescription('Clear all SMS providers and their parameters from the database')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Automatically confirm the deletion');
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Skip confirmation prompt if --yes option is set
        if (!$input->getOption('yes')) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                'This action will DELETE ALL SMS providers and their parameters. Are you sure? [y/N] ',
                false
            );

            /** @var QuestionHelper $helper */
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('Command aborted.');
                return Command::SUCCESS;
            }
        }

        // Begin database transaction
        $this->entityManager->beginTransaction();

        try {
            // Delete parameters first due to foreign key relationships
            $paramsDeleted = $this->entityManager
                ->createQuery('DELETE FROM ' . SMSProviderParam::class)
                ->execute();

            $providersDeleted = $this->entityManager
                ->createQuery('DELETE FROM ' . SMSProvider::class)
                ->execute();

            $this->entityManager->commit();

            $message = <<<EOL

<info>Success:</info> Successfully removed all SMS providers and parameters.
<comment>Summary:</comment>
  - Deleted <fg=yellow>{$providersDeleted}</> SMS Provider(s)
  - Deleted <fg=yellow>{$paramsDeleted}</> SMS Provider Parameter(s)

EOL;

            $output->write($message);
        } catch (Exception $e) {
            $this->entityManager->rollback();
            $output->writeln('<error>An error occurred while clearing SMS providers:</error> ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
