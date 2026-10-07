<?php

namespace App\Console\Commands;

use App\Services\CertificationService;
use Illuminate\Console\Command;

class RemindCertifications extends Command
{
    protected $signature = 'certifications:remind';

    protected $description = 'Recuerda a los revisores con accesos pendientes de certificar (campañas por vencer o vencidas)';

    public function handle(CertificationService $service): int
    {
        $this->info('Revisores recordados: '.$service->remindDue());

        return self::SUCCESS;
    }
}
