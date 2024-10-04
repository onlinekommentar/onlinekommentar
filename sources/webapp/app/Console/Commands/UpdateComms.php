<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Statamic;

class UpdateComms extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-comms';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        exit;

        $sites = Site::all();

        foreach ($sites as $site) {
            $items = Statamic::tag('nav:collection:commentaries')->params([
                'max_depth' => 0,
                'site' => $site->handle(),
            ])->fetch();
            foreach ($items as $item) {
                $id = $item['entry_id']->value();
                $entry = Entry::find($id);
                $entry->set('blueprint', 'legal_act');
                $entry->saveQuietly();
            }
        }

    }
}
