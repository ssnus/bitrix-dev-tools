<?php
namespace Ssnus\DevTools;

use Throwable;

class DebugManager
{
    public static function applyDebugSettings()
    {
        if (!self::isDebugMode()) {
            return;
        }

        try {
            ini_set('display_errors', '1');
            error_reporting(E_ALL);
        } catch (Throwable $e) {
            AddMessage2Log('DevTools debug settings error: ' . $e->getMessage(), 'dev.tools');
        }

        if (!defined('DEBUG')) {
            define('DEBUG', true);
        }
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
            } catch (Throwable $e) {
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
        } catch (Throwable $e) {
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
        } catch (Throwable $e) {
            AddMessage2Log('DevTools isDebugMode error: ' . $e->getMessage(), 'dev.tools');
        }

        return false;
    }
}
