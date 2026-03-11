<?php
namespace Ssnus\DevTools;

use RuntimeException;
use Throwable;

class LogManager
{
    private static $customLogFiles = [];

    public static function init()
    {
        self::registerLogFile($_SERVER['DOCUMENT_ROOT'] . '/upload/bitrix_error.log', 'Bitrix Error Log');
        self::registerLogFile($_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/log.txt', 'PHP Interface Log');
    }

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
                throw new RuntimeException('Failed to read log file');
            }

            $allLines = explode("\n", $content);
            $lastLines = array_slice($allLines, -$lines);
            return implode("\n", array_filter($lastLines));
        } catch (Throwable $e) {
            return GetMessage('DEV_TOOLS_LOG_READ_ERROR') . ': ' . htmlspecialcharsbx($e->getMessage());
        }
    }
}
