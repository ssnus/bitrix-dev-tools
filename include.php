<?
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\EventManager;

Loc::loadMessages(__FILE__);

class DevToolsHelper
{
    public static function applyDebugSettings()
    {
        if (!self::isDebugMode()) {
            return;
        }

        @ini_set('display_errors', '1');
        error_reporting(E_ALL);

        if (!defined('DEBUG')) {
            define('DEBUG', true);
        }
    }

    public static function init()
    {
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
            $settings = include($path);
            if (!is_array($settings)) {
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
        $result = @file_put_contents($path, $content);

        if ($result === false) {
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

        $settings = include($path);

        if (
            is_array($settings) &&
            isset($settings['error_handling']['value']['debug'])
        ) {
            return $settings['error_handling']['value']['debug'] === true;
        }

        return false;
    }

    public static function runAgents()
    {
        if (method_exists('CAgent', 'CheckAgents')) {
            return \CAgent::CheckAgents();
        }
        return false;
    }

    public static function getLastErrorLog($lines = 50)
    {
        $logPath = $_SERVER['DOCUMENT_ROOT'] . '/upload/bitrix_error.log';
        if (!file_exists($logPath)) {
            $logPath = $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/log.txt';
        }

        if (!file_exists($logPath)) {
            return GetMessage('DEV_TOOLS_LOG_NOT_FOUND');
        }

        if (!is_readable($logPath)) {
            return GetMessage('DEV_TOOLS_LOG_NOT_READABLE');
        }

        $content = @file_get_contents($logPath);
        if ($content === false) {
            return GetMessage('DEV_TOOLS_LOG_READ_ERROR');
        }

        $allLines = explode("\n", $content);
        $lastLines = array_slice($allLines, -$lines);

        return implode("\n", array_filter($lastLines));
    }

    private static function cleanDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

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
    }
}

DevToolsHelper::init();