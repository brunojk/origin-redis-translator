<?php

namespace brunojk\OriginRedisTranslator;

class TraitTranslatedAttributes
{
    protected $cached_translations = [];

    protected function getTranslatedAttr($value, $trans_key, array $params = [], $default = null) {
        if( !function_exists('trans')
            || !isset($this->cached_translations)
            || empty($this->cached_translations) )
            return $default ?: $value;

        if( !array_key_exists($trans_key, $this->cached_translations) ) {
            $translated = trans($trans_key, $params);

            $this->cached_translations[$trans_key] =
                $trans_key == $translated
                    ? ( $default ?: ($value ?: $translated) )
                    : $translated;
        }

        return $this->cached_translations[$trans_key];
    }
}