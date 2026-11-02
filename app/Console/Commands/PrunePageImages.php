<?php

namespace App\Console\Commands;

use App\Services\PageImageService;
use Illuminate\Console\Command;

/**
 * Sprząta zdjęcia wgrane do edytora stron, ale nigdy niezapisane w treści
 * (sprzedawca zamknął edytor bez „Zapisz"). Zdjęcia wycięte przy zapisie
 * strony znikają od razu — tu tylko te osierocone.
 */
class PrunePageImages extends Command
{
    protected $signature = 'pages:prune-images';

    protected $description = 'Usuwa zdjęcia stron niewstawione w żadną treść (starsze niż doba)';

    public function handle(PageImageService $images): int
    {
        $this->info('Usunięto zdjęć: '.$images->prune().'.');

        return self::SUCCESS;
    }
}
