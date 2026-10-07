<?php

namespace App\Console\Commands;

use App\Services\ApprovalNotifier;
use Illuminate\Console\Command;

class RemindPendingApprovals extends Command
{
    protected $signature = 'approvals:remind';

    protected $description = 'Envía recordatorios a los aprobadores con pasos vencidos según el SLA';

    public function handle(ApprovalNotifier $notifier): int
    {
        $count = $notifier->remindOverdue();

        $this->info("Recordatorios enviados: {$count}");

        return self::SUCCESS;
    }
}
