origin-redis-translator
=======================

Redis Translator for new pattern keys: 

```php
// pattern origin.locale.context.key`
$fullkey = 'app.en.default.hello_world';

// usage (normal like laravel/lumen(with helper) default)
$translated = trans($fullkey);
```