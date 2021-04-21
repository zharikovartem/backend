<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use  App\Services\Parsers\OnlinerParser;

class CatalogItemParsingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $catalogItem;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        $this->catalogItem = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        OnlinerParser::getCatalogItem($this->catalogItem);
        info('info');
    }
}
