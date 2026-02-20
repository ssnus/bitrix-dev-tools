<?

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;

Loc::loadMessages(__FILE__);

class dev_tools extends CModule
{
    var $MODULE_ID = 'dev.tools';
    var $MODULE_VERSION;
    var $MODULE_VERSION_DATE;
    var $MODULE_NAME;
    var $MODULE_DESCRIPTION;
    var $MODULE_GROUP_RIGHTS = 'Y';

    function __construct()
    {
        $arModuleVersion = [];
        include(__DIR__ . '/../version.php');

        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];

        $this->MODULE_NAME = GetMessage('DEV_TOOLS_MODULE_NAME');
        $this->MODULE_DESCRIPTION = GetMessage('DEV_TOOLS_MODULE_DESC');
    }

    function DoInstall()
    {
        global $APPLICATION;

        ModuleManager::registerModule($this->MODULE_ID);
        $this->installRights();
        $this->installAdminFiles();

        $APPLICATION->SetTitle(GetMessage('DEV_TOOLS_INSTALL_TITLE'));
        return true;
    }

    function DoUninstall()
    {
        global $APPLICATION;

        $this->uninstallAdminFiles();
        $this->uninstallRights();
        ModuleManager::unRegisterModule($this->MODULE_ID);

        $APPLICATION->SetTitle(GetMessage('DEV_TOOLS_UNINSTALL_TITLE'));
        return true;
    }

    function installAdminFiles()
    {
        $root = $_SERVER['DOCUMENT_ROOT'];
        $modulePath = $root . '/local/modules/' . $this->MODULE_ID;
        $adminPath = $root . '/bitrix/admin';

        $proxyContent = '<?php

require($_SERVER["DOCUMENT_ROOT"] . "/local/modules/dev.tools/admin/dev_tools.php");
';
        file_put_contents($adminPath . '/dev_tools.php', $proxyContent);

        // Можно добавить и другие файлы если нужно
    }

    /**
     * Удаление файлов из /bitrix/admin/
     */
    function uninstallAdminFiles()
    {
        $root = $_SERVER['DOCUMENT_ROOT'];
        $adminPath = $root . '/bitrix/admin';

        // Удаляем прокси-файл
        $proxyFile = $adminPath . '/dev_tools.php';
        if (file_exists($proxyFile)) {
            @unlink($proxyFile);
        }
    }

    function installRights()
    {
        global $DB;
        $DB->Query("DELETE FROM b_module_right WHERE MODULE_ID = '" . $DB->ForSql($this->MODULE_ID) . "'");

        $arRights = [
            ['GROUP_ID' => 'S', 'RIGHT' => 'R'],
            ['GROUP_ID' => '1', 'RIGHT' => 'W'],
        ];

        foreach ($arRights as $arRight) {
            $DB->Query(
                "INSERT INTO b_module_right (MODULE_ID, GROUP_ID, RIGHT) 
                VALUES ('" . $DB->ForSql($this->MODULE_ID) . "', 
                        '" . $DB->ForSql($arRight['GROUP_ID']) . "', 
                        '" . $DB->ForSql($arRight['RIGHT']) . "')"
            );
        }
    }

    function uninstallRights()
    {
        global $DB;
        $DB->Query("DELETE FROM b_module_right WHERE MODULE_ID = '" . $DB->ForSql($this->MODULE_ID) . "'");
    }
}