<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Statamic\Facades\Entry;
use Statamic\Facades\User;
use Statamic\View\View;

class SearchController extends Controller
{
    public function index($locale)
    {
        app()->setLocale($locale);

        $filters = Cache::remember('search-filter-'.$locale, 3600 * 24, function () {
            $legal_domains = Entry::query()
                ->where('collection', 'legal_domains')
                ->get()
                ->map(function ($entry) {
                    return [
                        'id' => $entry->id,
                        'label' => __($entry->title),
                    ];
                })
                ->unshift(['id' => null, 'label' => __('legal_domain_filter_label').': '.__('filter_all')])
                ->all();
            $commenatries = Entry::query()
                ->where('collection', 'commentaries')
                ->whereStatus('published')
                ->where('locale', app()->getLocale())
                ->where(function ($query) {
                    $query->where('assigned_authors', '!=', null)
                        ->orWhere('assigned_editors', '!=', null);
                })
                ->get();
            $authors = User::query()
                ->whereIn('id', $commenatries
                    ->where('assigned_authors', '!=', null)
                    ->flatMap(fn ($commenatry) => $commenatry->value('assigned_authors'))
                    ->all())
                ->orderBy('name')
                ->get()
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'label' => $user->name,
                    ];
                })
                ->unshift(['id' => null, 'label' => __('author_filter_label').': '.__('filter_all')])
                ->all();
            $editors = User::query()
                ->whereIn('id', $commenatries
                    ->where('assigned_editors', '!=', null)
                    ->flatMap(fn ($commenatry) => $commenatry->value('assigned_editors'))
                    ->all())
                ->orderBy('name')
                ->get()
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'label' => $user->name,
                    ];
                })
                ->unshift(['id' => null, 'label' => __('editor_filter_label').': '.__('filter_all')])
                ->all();
            $sorts = [
                ['id' => null, 'label' => __('sort_label').': '.__('sort_relevance')],
                ['id' => 'date', 'label' => __('sort_label').': '.__('sort_date')],
            ];

            return compact('legal_domains', 'authors', 'editors', 'sorts');
        });

        return (new View)
            ->template('search')
            ->layout('layout')
            ->with(array_merge([
                'title' => __('Search Results'),
                'locale' => $locale,
                ...$filters,
            ]))
            ->render();
    }
}
