<?php
namespace Ssnus\DevTools;

use Bitrix\Main\Data\Cache;
use Bitrix\Main\Data\HtmlCache;
use COption;
use CMenu;
use Redis;
use Memcached;
use Exception;
use RuntimeException;
use Throwable;

class CacheManager
{
    public static function clearCache($options = [])
    {
        $defaults = [
            'components' => true,
            'managed'    => true,
            'html'       => false,
            'menu'       => true,
            'browser'    => false,
            'redis'      => false,
            'memcached'  => false,
        ];

        $options = array_merge($defaults, $options);

        if ($options['components']) {
            $cache = Cache::createInstance();
            if ($cache) {
                $cache->cleanDir();
            }
            $cachePath = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/cache';
            if (is_dir($cachePath)) {
                self::cleanDirectory($cachePath);
            }
        }

        if ($options['managed']) {
            $managedPath = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/managed_cache';
            if (is_dir($managedPath)) {
                self::cleanDirectory($managedPath);
            }
        }

        if ($options['html']) {
            if (class_exists('\Bitrix\Main\Data\HtmlCache')) {
                if (method_exists('\Bitrix\Main\Data\HtmlCache', 'clean')) {
                    HtmlCache::clean();
                }
            }
            $htmlCachePath = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/htmlcache';
            if (is_dir($htmlCachePath)) {
                self::cleanDirectory($htmlCachePath);
            }
        }

        if ($options['menu']) {
            if (method_exists('CMenu', 'ClearCache')) {
                CMenu::ClearCache();
            }
        }

        if ($options['browser']) {
            $staticCssPath = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/cache/css';
            if (is_dir($staticCssPath)) {
                self::cleanDirectory($staticCssPath);
            }
            $staticJsPath = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/cache/js';
            if (is_dir($staticJsPath)) {
                self::cleanDirectory($staticJsPath);
            }
        }

        if ($options['redis'] && class_exists('\Redis')) {
            try {
                $redis = new Redis();
                $redisConfig = COption::GetOptionString('main', 'component_cache_redis', '');
                if ($redisConfig) {
                    $config = @unserialize($redisConfig);
                    if ($config && is_array($config)) {
                        $connected = $redis->connect(
                            $config['host'] ?? '127.0.0.1',
                            $config['port'] ?? 6379,
                            $config['timeout'] ?? 0
                        );
                        if ($connected) {
                            if (!empty($config['password'])) {
                                $authResult = $redis->auth($config['password']);
                                if ($authResult === false) {
                                    throw new RuntimeException('Redis authentication failed');
                                }
                            }
                            $redis->flushDB();
                            $redis->close();
                        }
                    }
                }
            } catch (Exception $e) {
                AddMessage2Log('DevTools Redis clear error: ' . $e->getMessage(), 'dev.tools');
            }
        }

        if ($options['memcached'] && class_exists('\Memcached')) {
            try {
                $memcached = new Memcached();
                $memcachedConfig = COption::GetOptionString('main', 'component_cache_memcache', '');
                if ($memcachedConfig) {
                    $config = @unserialize($memcachedConfig);
                    if ($config && is_array($config)) {
                        $memcached->addServer(
                            $config['host'] ?? '127.0.0.1',
                            $config['port'] ?? 11211
                        );
                        $stats = $memcached->getStats();
                        if (!empty($stats)) {
                            $memcached->flush();
                        }
                        $memcached->quit();
                    }
                }
            } catch (Exception $e) {
                AddMessage2Log('DevTools Memcached clear error: ' . $e->getMessage(), 'dev.tools');
            }
        }

        return true;
    }

    public static function setCacheDisabled($disabled = true)
    {
        COption::SetOptionString('dev.tools', 'cache_disabled', $disabled ? 'Y' : 'N');

        if ($disabled) {
            COption::SetOptionString('main', 'component_cache_on', 'N');
            COption::SetOptionString('main', 'managed_cache', 'N');
        } else {
            COption::SetOptionString('main', 'component_cache_on', 'Y');
            COption::SetOptionString('main', 'managed_cache', 'Y');
        }

        return $disabled;
    }

    public static function isCacheDisabled()
    {
        return COption::GetOptionString('dev.tools', 'cache_disabled', 'N') === 'Y';
    }

    private static function cleanDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        try {
            $files = array_diff(scandir($dir), ['.', '..']);
            foreach ($files as $file) {
                $path = $dir . '/' . $file;
                if (is_dir($path)) {
                    self::cleanDirectory($path);
                    @rmdir($path);
                } else {
                    @unlink($path);
                }
            }
        } catch (Throwable $e) {
            AddMessage2Log('DevTools cleanDirectory error: ' . $e->getMessage(), 'dev.tools');
        }
    }
}
