<?php

declare(strict_types=1);

namespace SarissaOps\SyliusEnviatodoPlugin\Command;

use SarissaOps\SyliusEnviatodoPlugin\Shipping\EnviatodoStatusSynchronizer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'sarissaops:enviatodo:sync-statuses',
    description: 'Pulls Enviatodo order history and advances local shipment handover milestones.',
)]
final class SyncEnviatodoStatusesCommand extends Command
{
    public function __construct(private readonly EnviatodoStatusSynchronizer $synchronizer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('since', null, InputOption::VALUE_REQUIRED, 'Start of the date window (any strtotime value).', '-7 days')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum history rows to process.', 100)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Resolve rows without writing.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sinceOption = $input->getOption('since');
        $since = \is_string($sinceOption) ? $sinceOption : '-7 days';
        $start = date('Y-m-d', strtotime($since) ?: strtotime('-7 days'));
        $criteria = [
            'date_range' => ['start' => $start, 'end' => date('Y-m-d')],
            'provider' => 'all',
            'status' => 'all',
            'user_id' => 'all',
        ];
        $limitOption = $input->getOption('limit');
        $limit = \is_numeric($limitOption) ? (int) $limitOption : 100;

        if ($input->getOption('dry-run')) {
            foreach ($this->synchronizer->preview($criteria, $limit) as $row) {
                $output->writeln(sprintf(
                    '%s | %s | code %s | %s',
                    $row['tracking_id'],
                    $row['status_name'],
                    null === $row['code'] ? '?' : (string) $row['code'],
                    $row['handover'],
                ));
            }

            return Command::SUCCESS;
        }

        $summary = $this->synchronizer->sync($criteria, $limit);
        $output->writeln(sprintf(
            'Statuses synced: %d updated, %d skipped, %d unknown, %d failed.',
            $summary['updated'],
            $summary['skipped'],
            $summary['unknown'],
            $summary['failed'],
        ));

        return Command::SUCCESS;
    }
}
