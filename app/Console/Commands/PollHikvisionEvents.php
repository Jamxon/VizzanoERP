<?php

namespace App\Console\Commands;

use App\Http\Controllers\HikvisionEventController;
use Illuminate\Console\Command;

class PollHikvisionEvents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hikvision:poll-events';

    protected $description = 'Poll events from Hikvision ISAPI';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        // HikvisionEventController ni chaqirib hodisalarni olish
        $controller = new HikvisionEventController();
        $controller->getEvents();

        $this->info('Polling complete');
    }
}
