<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Catalog;
use App\Services\Parsers\OnlinerParser;


class CatalogParsingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $catalogItem;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        // $this->catalogItem = $test;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        OnlinerParser::getCatalog();
        info('info');

        echo 'Выполняем парсинг123<br/>';
    }
}
