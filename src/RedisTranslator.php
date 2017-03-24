<?php

namespace brunojk\OriginRedisTranslator;

use Illuminate\Container\Container;
use Illuminate\Support\Collection;
use Illuminate\Support\NamespacedItemResolver;
use Illuminate\Support\Str;
use Illuminate\Translation\Translator;
use Illuminate\Translation\MessageSelector;
use Illuminate\Contracts\Translation\Translator as TranslatorContract;

class RedisTranslator extends NamespacedItemResolver implements TranslatorContract
{
    /**
     * The redis connection name.
     *
     * @var string
     */
    protected $rediscon;

    /**
     * The redis connection database.
     *
     * @var string
     */
    protected $redis = null;

    /**
     * The default locale being used by the translator.
     *
     * @var string
     */
    protected $locale;

    /**
     * The fallback locale used by the translator.
     *
     * @var string
     */
    protected $fallback;

    /**
     * The message selector.
     *
     * @var \Symfony\Component\Translation\MessageSelector
     */
    protected $selector;

    /**
     * The message selector.
     *
     * @var Translator
     */
    protected $filetranslator;

    /**
     * Cached keys translated.
     *
     * @var Collection
     */
    protected $cached;

    protected $use_cache = false;

    /**
     * Create a new translator instance.
     *
     * @param Translator $filetranslator
     * @param  string $locale
     * @param string $rediscon
     */
    public function __construct(Translator $filetranslator, $locale, $rediscon = 'default')
    {
        $this->filetranslator = $filetranslator;
        $this->locale = $locale;
        $this->rediscon = $rediscon;
        $this->cached = new Collection();
    }

    /**
     * @return \Illuminate\Redis\Connections\Connection
     */
    protected function redis() {
        if( function_exists('app') )
            $this->redis = $this->redis ?: app()->make('redis')->connection($this->rediscon);
        else
            $this->redis = $this->redis ?: Container::getInstance()->make('redis')->connection($this->rediscon);

        return $this->redis;
    }

    /**
     * @param null $lang
     * @return string
     */
    protected function resolveLang($lang = null) {
        return $lang ?: $this->locale;
    }

    public function resolveKeys(&$id, &$context = null, &$lang = null) {
        if( strpos($id, '.') === false )
            $id = $context . '.' . $id;

        list($namespace, $context, $item) = $this->parseKey($id);

        $id = $item; //last element, the key
        $lang = $this->resolveLang($lang);

        $key_app = "app.$lang.$context.$id";
        $key_plt = "plt.$lang.$context.$id";

        return [$key_app, $key_plt];
    }

    private function getCached($key, $default = null) {
        if( $this->use_cache )
            return $this->cached->get($key, $default);

        return null;
    }

    private function putInCache($key, $value) {
        if( $this->use_cache )
            return $this->cached->put($key, $value);
    }

    public function get($id, array $parameters = [], $context = 'default', $lang = null, $fallback = true) {
        $old_key = $id;
        $keys = $this->resolveKeys($id, $context, $lang);
        $res = $this->getCached($keys[0], $this->cached->get($keys[1]));

        if( empty($res) ) {
            foreach( $keys as $key ) {
                $res = $this->redis()->get($key);
                if( !empty($res) ) break;
            }

            if( empty($res) && $fallback && $lang != $this->fallback ) {
                if( str_contains($lang, '-') )
                    return $this->get($old_key, $parameters, $context, explode('-', $lang)[0]);

                return $this->get($old_key, $parameters, $context, $this->fallback);
            }
        }

        $this->putInCache($keys[0], $res);
        $this->putInCache($keys[1], $res);

        $res = !empty($res) ?
            $this->makeReplacements($res, $parameters) :
            $old_key;

        return $res;
    }

    /**
     * Get a translation according to an integer value.
     *
     * @param $id
     * @param int $number
     * @param array $parameters
     * @param null $context
     * @param  string $locale
     * @return string
     */
    public function choice($id, $number, array $parameters = [], $context = null, $locale = null) {
        $line = $this->get($id, $parameters, $context, $locale);

        return $this->makeReplacements($this->getSelector()->choose($line, $number, $locale), $parameters);
    }

    /**
     * Make the place-holder replacements on a line.
     *
     * @param  string  $line
     * @param  array   $replace
     * @return string
     */
    protected function makeReplacements($line, array $replace)
    {
        foreach ($replace as $key => $value) {
            $line = str_replace(
                [':'.$key, ':'.Str::upper($key), ':'.Str::ucfirst($key)],
                [$value, Str::upper($value), Str::ucfirst($value)],
                $line
            );
        }

        return $line;
    }

    /**
     * Get the translation for a given key.
     *
     * @param  string  $id
     * @param  array   $parameters
     * @param  string  $domain
     * @param  string  $locale
     * @return string|array|null
     */
    public function trans($id, array $parameters = [], $domain = 'messages', $locale = null) {
        if(starts_with($id, 'validation'))
            return $this->filetranslator->trans($id, $parameters, $locale);

        return $this->get($id, $parameters, !$domain || $domain == 'messages' ? 'default' : $domain, $locale);
    }

    /**
     * Get a translation according to an integer value.
     *
     * @param  string  $id
     * @param  int|array|\Countable  $number
     * @param  array   $parameters
     * @param  string  $domain
     * @param  string  $locale
     * @return string
     */
    public function transChoice($id, $number, array $parameters = [], $domain = 'messages', $locale = null)
    {
        if(starts_with('validation', $id))
            return $this->filetranslator->transChoice($id, $parameters, $locale);

        return $this->choice($id, $number, $parameters, !$domain || $domain == 'messages' ? 'default' : $domain, $locale);
    }

    /**
     * Parse a key into namespace, group, and item.
     *
     * @param  string  $key
     * @return array
     */
    public function parseKey($key)
    {
        $segments = parent::parseKey($key);

        if (is_null($segments[0])) {
            $segments[0] = '*';
        }

        return $segments;
    }


    /**
     * Get the message selector instance.
     *
     * @return \Illuminate\Translation\MessageSelector
     */
    public function getSelector()
    {
        if (! isset($this->selector)) {
            $this->selector = new MessageSelector;
        }

        return $this->selector;
    }

    /**
     * Get the default locale being used.
     *
     * @return string
     */
    public function locale()
    {
        return $this->getLocale();
    }

    /**
     * Get the default locale being used.
     *
     * @return string
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * Set the default locale.
     *
     * @param  string  $locale
     * @return void
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;
        $this->filetranslator->setLocale($locale);
    }

    /**
     * Get the fallback locale being used.
     *
     * @return string
     */
    public function getFallback()
    {
        return $this->fallback;
    }

    /**
     * Set the fallback locale being used.
     *
     * @param  string  $fallback
     * @return void
     */
    public function setFallback($fallback)
    {
        $this->fallback = $fallback;
        $this->filetranslator->setFallback($fallback);
    }

    public function useCachedTranslations($cached)
    {
        $this->use_cache = $cached;
    }
}
