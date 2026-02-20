<?
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\EventManager;

Loc::loadMessages(__FILE__);

class DevToolsHelper
{
    private static $customLogFiles = [];

    public static function registerLogFile($path, $label = null)
    {
        if (file_exists($path) && is_readable($path)) {
            self::$customLogFiles[$path] = $label ?: basename($path);
            return true;
        }
        return false;
    }

    public static function getRegisteredLogFiles()
    {
        return self::$customLogFiles;
    }

    public static function applyDebugSettings()
    {
        if (!self::isDebugMode()) {
            return;
        }

        try {
            ini_set('display_errors', '1');
            error_reporting(E_ALL);
        } catch (\Throwable $e) {
            AddMessage2Log('DevTools debug settings error: ' . $e->getMessage(), 'dev.tools');
        }

        if (!defined('DEBUG')) {
            define('DEBUG', true);
        }
    }

    public static function init()
    {
        self::registerLogFile($_SERVER['DOCUMENT_ROOT'] . '/upload/bitrix_error.log', 'Bitrix Error Log');
        self::registerLogFile($_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/log.txt', 'PHP Interface Log');

        $eventManager = EventManager::getInstance();
        $eventManager->addEventHandler('main', 'OnProlog', ['DevToolsHelper', 'applyDebugSettings']);
    }

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
            $cache = \Bitrix\Main\Data\Cache::createInstance();
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
                    \Bitrix\Main\Data\HtmlCache::clean();
                }
            }
            $htmlCachePath = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/htmlcache';
            if (is_dir($htmlCachePath)) {
                self::cleanDirectory($htmlCachePath);
            }
        }

        if ($options['menu']) {
            if (method_exists('CMenu', 'ClearCache')) {
                \CMenu::ClearCache();
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
                $redis = new \Redis();
                $redisConfig = \COption::GetOptionString('main', 'component_cache_redis', '');
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
                                    throw new \RuntimeException('Redis authentication failed');
                                }
                            }
                            $redis->flushDB();
                            $redis->close();
                        }
                    }
                }
            } catch (\Exception $e) {
                AddMessage2Log('DevTools Redis clear error: ' . $e->getMessage(), 'dev.tools');
            }
        }

        if ($options['memcached'] && class_exists('\Memcached')) {
            try {
                $memcached = new \Memcached();
                $memcachedConfig = \COption::GetOptionString('main', 'component_cache_memcache', '');
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
            } catch (\Exception $e) {
                AddMessage2Log('DevTools Memcached clear error: ' . $e->getMessage(), 'dev.tools');
            }
        }

        return true;
    }

    public static function setCacheDisabled($disabled = true)
    {
        \COption::SetOptionString('dev.tools', 'cache_disabled', $disabled ? 'Y' : 'N');

        if ($disabled) {
            \COption::SetOptionString('main', 'component_cache_on', 'N');
            \COption::SetOptionString('main', 'managed_cache', 'N');
        } else {
            \COption::SetOptionString('main', 'component_cache_on', 'Y');
            \COption::SetOptionString('main', 'managed_cache', 'Y');
        }

        return $disabled;
    }

    public static function isCacheDisabled()
    {
        return \COption::GetOptionString('dev.tools', 'cache_disabled', 'N') === 'Y';
    }

    private static function getSettingsFilePath()
    {
        return $_SERVER['DOCUMENT_ROOT'] . '/bitrix/.settings_extra.php';
    }

    public static function setDebugMode($enabled = true)
    {
        $path = self::getSettingsFilePath();
        $settings = [];

        if (file_exists($path)) {
            try {
                $settings = include($path);
                if (!is_array($settings)) {
                    $settings = [];
                }
            } catch (\Throwable $e) {
                AddMessage2Log('DevTools settings load error: ' . $e->getMessage(), 'dev.tools');
                $settings = [];
            }
        }

        if (!isset($settings['error_handling'])) {
            $settings['error_handling'] = ['value' => [], 'readonly' => false];
        }
        if (!isset($settings['error_handling']['value'])) {
            $settings['error_handling']['value'] = [];
        }

        $settings['error_handling']['value']['debug'] = ($enabled === true);

        $content = "<?php\nreturn " . var_export($settings, true) . ";\n";

        try {
            $result = file_put_contents($path, $content, LOCK_EX);
            if ($result === false) {
                return false;
            }
        } catch (\Throwable $e) {
            AddMessage2Log('DevTools settings save error: ' . $e->getMessage(), 'dev.tools');
            return false;
        }

        self::applyDebugSettings();
        return true;
    }

    public static function isDebugMode()
    {
        $path = self::getSettingsFilePath();

        if (!file_exists($path)) {
            return false;
        }

        try {
            $settings = include($path);
            if (is_array($settings) && isset($settings['error_handling']['value']['debug'])) {
                return $settings['error_handling']['value']['debug'] === true;
            }
        } catch (\Throwable $e) {
            AddMessage2Log('DevTools isDebugMode error: ' . $e->getMessage(), 'dev.tools');
        }

        return false;
    }

    public static function runAgents($agentIds = null)
    {
        if (!method_exists('CAgent', 'CheckAgents')) {
            return false;
        }

        if (!empty($agentIds) && is_array($agentIds)) {
            global $DB;
            $results = [];

            foreach ($agentIds as $agentId) {
                $agentId = (int)$agentId;
                $res = $DB->Query("SELECT * FROM b_agent WHERE ID = {$agentId} AND ACTIVE = 'Y'");
                if ($agent = $res->Fetch()) {
                    try {
                        $result = eval("return {$agent['NAME']};");
                        $results[$agentId] = ['success' => true, 'result' => $result];

                        $DB->Query("UPDATE b_agent SET LAST_EXEC = NOW(), NEXT_EXEC = DATE_ADD(NEXT_EXEC, INTERVAL {$agent['AGENT_INTERVAL']} SECOND) WHERE ID = {$agentId}");
                    } catch (\Throwable $e) {
                        $results[$agentId] = ['success' => false, 'error' => $e->getMessage()];
                        AddMessage2Log("Agent #{$agentId} execution error: " . $e->getMessage(), 'dev.tools');
                    }
                }
            }
            return $results;
        }

        return \CAgent::CheckAgents();
    }

    public static function getLastErrorLog($lines = 50, $customPath = null)
    {
        $logPath = $customPath;

        if ($logPath) {
            if (strpos($logPath, '/') !== 0 && strpos($logPath, ':') !== 1) {
                $logPath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($logPath, '/');
            }
            $logPath = str_replace('\\', '/', realpath($logPath) ?: $logPath);
        }

        if (!$logPath) {
            $registered = self::getRegisteredLogFiles();
            foreach (array_keys($registered) as $path) {
                if (file_exists($path) && is_readable($path)) {
                    $logPath = $path;
                    break;
                }
            }
        }

        if (!$logPath) {
            $logPath = $_SERVER['DOCUMENT_ROOT'] . '/upload/bitrix_error.log';
            if (!file_exists($logPath)) {
                $logPath = $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/log.txt';
            }
        }

        if (!file_exists($logPath)) {
            return GetMessage('DEV_TOOLS_LOG_NOT_FOUND') . " (Путь: {$logPath})";
        }

        if (!is_readable($logPath)) {
            return GetMessage('DEV_TOOLS_LOG_NOT_READABLE');
        }

        try {
            $content = file_get_contents($logPath);
            if ($content === false) {
                throw new \RuntimeException("Failed to read log file");
            }

            $allLines = explode("\n", $content);
            $lastLines = array_slice($allLines, -$lines);
            return implode("\n", array_filter($lastLines));
        } catch (\Throwable $e) {
            return GetMessage('DEV_TOOLS_LOG_READ_ERROR') . ': ' . htmlspecialcharsbx($e->getMessage());
        }
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
        } catch (\Throwable $e) {
            AddMessage2Log('DevTools cleanDirectory error: ' . $e->getMessage(), 'dev.tools');
        }
    }
}

DevToolsHelper::init();