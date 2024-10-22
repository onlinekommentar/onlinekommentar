<?php

namespace App\Providers;

use Algolia\AlgoliaSearch\SearchClient;
use App\Fieldtypes\Bard\Nodes\Paragraph;
use App\Search\AlgoliaSplit\Index;
use Illuminate\Support\ServiceProvider;
use Statamic\Facades\Search;
use Statamic\Facades\User;
use Statamic\Fieldtypes\Bard\Augmentor;
use Statamic\Statamic;
use Statamic\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register() {}

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Statamic::script('app', 'cp.js');
        date_default_timezone_set('Europe/Zurich');

        Augmentor::replaceExtension('paragraph', new Paragraph);

        User::computed('family_name', function ($user) {
            return Str::afterLast($user->name, ' ');
        });

        Search::extend('algolia_split', function ($app, array $config, $name, $locale = null) {
            $credentials = $config['credentials'];

            $client = SearchClient::create($credentials['id'], $credentials['secret']);

            return new Index($client, $name, $config, $locale);
        });
    }
}
