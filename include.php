<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$modulePath = getLocalPath('modules/dev.tools');

Loader::registerAutoLoadClasses(null, [
    'Ssnus\DevTools\CacheManager' => $modulePath . '/lib/CacheManager.php',
    'Ssnus\DevTools\DebugManager' => $modulePath . '/lib/DebugManager.php',
    'Ssnus\DevTools\LogManager'   => $modulePath . '/lib/LogManager.php',
    'Ssnus\DevTools\AgentManager' => $modulePath . '/lib/AgentManager.php',
]);

\Ssnus\DevTools\LogManager::init();

$eventManager = \Bitrix\Main\EventManager::getInstance();
$eventManager->addEventHandler('main', 'OnProlog', ['\Ssnus\DevTools\DebugManager', 'applyDebugSettings']);