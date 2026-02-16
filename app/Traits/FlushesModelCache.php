<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

trait FlushesModelCache
{
    /**
     * Enable/disable cache flushing per model.
     */
    protected static bool $flushModelCache = true;

    /**
     * Boot the trait.
     */
    protected static function bootFlushesModelCache(): void
    {
        static::saved(function (Model $model) {
            if (!static::$flushModelCache) return;
            static::flushModelCache($model);
        });

        static::deleted(function (Model $model) {
            if (!static::$flushModelCache) return;
            static::flushModelCache($model);
        });

        // If you use soft deletes and want flush on restore too:
        static::restored(function (Model $model) {
            if (!static::$flushModelCache) return;
            static::flushModelCache($model);
        });
    }

    /**
     * Flush all cache keys/tags for this model.
     */
    protected static function flushModelCache(Model $model): void
    {
        $tags = method_exists($model, 'cacheTags')
            ? (array) $model->cacheTags()
            : [static::defaultCacheTag()];

        // If tags aren't supported by your cache store, fall back to forgetting keys only.
        if (!static::cacheSupportsTags()) {
            foreach (static::cacheKeysFor($model) as $key) {
                Cache::forget($key);
            }
            return;
        }

        // Forget known keys
        foreach (static::cacheKeysFor($model) as $key) {
            Cache::tags($tags)->forget($key);
        }

        // Optional: you can also flush the whole tag (heavier)
        // Cache::tags($tags)->flush();
    }

    /**
     * Default tag: e.g. "persons", "trips" from model table name.
     */
    protected static function defaultCacheTag(): string
    {
        // uses the table name as a sensible default tag
        return (new static)->getTable();
    }

    /**
     * Override in model to define extra tags.
     */
    public function cacheTags(): array
    {
        return [static::defaultCacheTag()];
    }

    /**
     * Override in model to define keys to forget.
     * Default: "{table}:id:{id}".
     */
    public function cacheKeys(): array
    {
        return [
            sprintf('%s:id:%s', $this->getTable(), $this->getKey()),
        ];
    }

    /**
     * Collect keys for a model instance.
     */
    protected static function cacheKeysFor(Model $model): array
    {
        return method_exists($model, 'cacheKeys')
            ? (array) $model->cacheKeys()
            : [sprintf('%s:id:%s', $model->getTable(), $model->getKey())];
    }

    /**
     * Very small guard: tag support depends on store.
     * Redis & memcached support tags; file/database don't.
     */
    protected static function cacheSupportsTags(): bool
    {
        $store = config('cache.default');
        return in_array($store, ['redis', 'memcached'], true);
    }
}
