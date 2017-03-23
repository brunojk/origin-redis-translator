<?php

class TestAbstract extends PHPUnit_Framework_TestCase
{
    protected $redis;

    /** @var \Illuminate\Container\Container */
    protected $container;

    /** Setup the database schema. */
    public function setUp()
    {
        $this->container = \Illuminate\Container\Container::getInstance();

        $this->container->singleton('config', Illuminate\Config\Repository::class);

        // load custom config
        $dbconfig = require 'config/database.php';
        $appconfig = require 'config/app.php';

        $this->container->make('config')->set('database.redis', $dbconfig['redis']);
        $this->container->make('config')->set('app.locale', $appconfig['locale']);
        $this->container->make('config')->set('app.fallback_locale', $appconfig['fallback_locale']);
        $this->container->make('config')->set('app.cached_translations', $appconfig['cached_translations']);

        $this->container->singleton('files', function () {
            return new \Illuminate\Filesystem\Filesystem();
        });

        $this->container->singleton('redis', function ($app) {
            $config = $app->make('config')->get('database.redis');

            return new \Illuminate\Redis\RedisManager(\Illuminate\Support\Arr::pull($config, 'client', 'predis'), $config);
        });

        $this->container->bind('redis.connection', function ($app) {
            return $app['redis']->connection();
        });

        $this->container->singleton('translation.loader', function ($app) {
            return new \Illuminate\Translation\FileLoader($app['files'], '');
        });

        $this->container->singleton('translator', function ($app) {
            $loader = $app['translation.loader'];

            // When registering the translator component, we'll need to set the default
            // locale as well as the fallback locale. So, we'll grab the application
            // configuration so we can easily get both of these values from there.
            $locale = $app['config']['app.locale'];
            $fblocale = $app['config']['app.fallback_locale'];
            $cached = $app['config']['app.cached_translations'];

            $rediscon = isset($app['config']['database.redis']['translations']);

            $filetrans = new \Illuminate\Translation\Translator($loader, $locale);

            $trans = new \brunojk\OriginRedisTranslator\RedisTranslator($filetrans, $locale, $rediscon ? 'translations' : null);
            $trans->setFallback($fblocale);
            $trans->useCachedTranslations($cached);

            return $trans;
        });

        $this->redis = $this->container->make('redis')->connection(isset($dbconfig['redis']['translations']) ? 'translations' : 'default');
    }

    protected function trans($id, array $parameters = [], $domain = 'default', $locale = null) {
        return $this->container->make('translator')->trans($id, $parameters, $domain, $locale);
    }

    protected function transChoice($id, $number, array $parameters = [], $domain = 'default', $locale = null) {
        return $this->container->make('translator')->transChoice($id, $number, $parameters, $domain, $locale);
    }
}
