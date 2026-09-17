<?php

namespace App\Providers;

use App\Console\Commands\ApiRoutesCommand;
use Dingo\Api\Transformer\Adapter\Fractal;
use Illuminate\Console\Application as Artisan;
use Illuminate\Foundation\Console\RouteListCommand;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Meilisearch\Client;
use Meva\Entities\Search\SearchIndex;
use Meva\Web\ImageSize;
use Meva\Serializers\CustomSerializer;
use PHPOpenSourceSaver\Fractal\Manager;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One Meilisearch client, built from config, so nothing anywhere else
        // has to know the host or carry the key.
        $this->app->singleton(Client::class, fn (): Client => new Client(
            config('search.host'),
            config('search.key'),
        ));

        $this->app->singleton(SearchIndex::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app['Dingo\Api\Transformer\Factory']->setAdapter(function () {
            $fractal = new Manager;
            $fractal->setSerializer(new CustomSerializer);

            return new Fractal($fractal);
        });

        $this->registerConsoleCommands();
        $this->measureShareImages();
    }

    /**
     * Every share page declares the true shape of its picture.
     *
     * Done here rather than at each call site so a new share page cannot forget
     * it: a card that claims the wrong dimensions is drawn as the small
     * one-line preview instead of the large one.
     */
    protected function measureShareImages(): void
    {
        View::composer('share.page', function ($view): void {
            $image = $view->getData()['image'] ?? null;
            $size = ImageSize::of($image);

            $view->with('imageSize', $size);
            $view->with('imageType', str_ends_with(strtolower((string) $image), '.png') ? 'image/png' : 'image/jpeg');
        });
    }

    /**
     * Dingo eagerly registers its route listing command, which inherits Laravel's
     * "route:list" signature and therefore takes over that command name. These
     * registrations run after Dingo's, restoring "route:list" and exposing the
     * Dingo listing under its intended "api:routes" name.
     */
    protected function registerConsoleCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        Artisan::starting(function (Artisan $artisan) {
            $artisan->addCommand($this->app->make(RouteListCommand::class));
            $artisan->addCommand($this->app->make(ApiRoutesCommand::class));
        });
    }
}
