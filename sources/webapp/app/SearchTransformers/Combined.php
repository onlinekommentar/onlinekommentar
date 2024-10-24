<?php

namespace App\SearchTransformers;

use Illuminate\Support\Str;
use Statamic\Statamic;

class Combined
{
    public function handle($value, $handle, $entry)
    {
        $value = implode(' ', [
            $entry->assigned_authors?->pluck('name')->implode(' '),
            $entry->asigned_editors?->pluck('name')->implode(' '),
            Statamic::modify($entry->get('legal_text'))->bardText()->fetch(),
            Statamic::modify($entry->get('content'))->bardText()->fetch(),
        ]);

        $value = Str::replaceMatches('/https?:\/\/\S+/u', '', $value);

        return $value;
    }
}
