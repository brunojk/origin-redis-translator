<?php

namespace brunojk\OriginRedisTranslator;

use Illuminate\Support\ServiceProvider;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;

class RedisTranslatorServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register() {
        $this->registerLoader();

        $this->app->singleton('translator', function ($app) {
            $loader = $app['translation.loader'];

            // When registering the translator component, we'll need to set the default
            // locale as well as the fallback locale. So, we'll grab the application
            // configuration so we can easily get both of these values from there.
            $locale = $app['config']['app.locale'];
            $fblocale = $app['config']['app.fallback_locale'];
            $cached = $app['config']['app.cached_translations'];

            $rediscon = isset($app['config']['database.redis']['translations']);

            $filetrans = new Translator($loader, $locale);

            $trans = new RedisTranslator($filetrans, $locale, $rediscon ? 'translations' : null);
            $trans->setFallback($fblocale);
            $trans->useCachedTranslations($cached);

            return $trans;
        });
    }

    /**
     * Register the translation line loader.
     *
     * @return void
     */
    protected function registerLoader()
    {
        $this->app->singleton('translation.loader', function ($app) {
            return new FileLoader($app['files'], $app['path.lang']);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides() {
        return ['translator', 'translation.loader'];
    }
}