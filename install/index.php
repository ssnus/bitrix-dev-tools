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
        global $APPLICATION;

        $root = $_SERVER['DOCUMENT_ROOT'];
        $adminPath = $root . '/bitrix/admin';
        $proxyPath = $adminPath . '/dev_tools.php';

        if (!is_dir($adminPath)) {
            AddMessage2Log("DevTools ERROR: admin path not found: {$adminPath}", 'dev.tools');
            return false;
        }

        if (!is_writable($adminPath)) {
            AddMessage2Log("DevTools ERROR: admin path not writable: {$adminPath}", 'dev.tools');
            if (!@touch($proxyPath)) {
                return false;
            }
            @unlink($proxyPath);
        }

        $proxyContent = '<?php' . "\n" .
            '/**' . "\n" .
            ' * Proxy file for ' . $this->MODULE_ID . ' module' . "\n" .
            ' * Auto-generated. Do not edit.' . "\n" .
            ' */' . "\n" .
            'require($_SERVER["DOCUMENT_ROOT"] . "/local/modules/' . $this->MODULE_ID . '/admin/dev_tools.php");' . "\n";

        $bytes = @file_put_contents($proxyPath, $proxyContent);

        if ($bytes === false || $bytes === 0) {
            $error = error_get_last();
            AddMessage2Log("DevTools ERROR: file_put_contents failed: " . ($error['message'] ?? 'unknown'), 'dev.tools');
            return false;
        }

        if (!file_exists($proxyPath)) {
            AddMessage2Log("DevTools ERROR: proxy file not created: {$proxyPath}", 'dev.tools');
            return false;
        }

        @chmod($proxyPath, 0644);

        AddMessage2Log("DevTools OK: proxy file created: {$proxyPath}", 'dev.tools');
        return true;
    }

    function uninstallAdminFiles()
    {
        $root = $_SERVER['DOCUMENT_ROOT'];
        $adminPath = $root . '/bitrix/admin';
        $proxyFile = $adminPath . '/dev_tools.php';

        if (file_exists($proxyFile)) {
            $content = @file_get_contents($proxyFile);
            if ($content && strpos($content, $this->MODULE_ID) !== false) {
                @unlink($proxyFile);
            }
        }

        return true;
    }

    function hasModuleRightsTable()
    {
        global $DB;
        $res = $DB->Query("SHOW TABLES LIKE 'b_module_right'");
        return ($res->Fetch() !== false);
    }

    function installRights()
    {
        if (!$this->hasModuleRightsTable()) {
            return true;
        }

        global $DB;

        try {
            $moduleId = $DB->ForSql($this->MODULE_ID);

            $DB->Query("DELETE FROM b_module_right WHERE MODULE_ID = '{$moduleId}'");

            $arRights = [
                ['GROUP_ID' => 'S', 'RIGHT' => 'R'],
                ['GROUP_ID' => '1', 'RIGHT' => 'W'],
            ];

            foreach ($arRights as $arRight) {
                $groupId = $DB->ForSql($arRight['GROUP_ID']);
                $right = $DB->ForSql($arRight['RIGHT']);

                $DB->Query(
                    "INSERT INTO b_module_right (MODULE_ID, GROUP_ID, RIGHT) 
                    VALUES ('{$moduleId}', '{$groupId}', '{$right}')"
                );
            }
        } catch (\Exception $e) {
            AddMessage2Log("DevTools installRights error: " . $e->getMessage(), 'dev.tools');
        }

        return true;
    }

    function uninstallRights()
    {
        if (!$this->hasModuleRightsTable()) {
            return true;
        }

        global $DB;

        try {
            $moduleId = $DB->ForSql($this->MODULE_ID);
            $DB->Query("DELETE FROM b_module_right WHERE MODULE_ID = '{$moduleId}'");
        } catch (\Exception $e) {
            AddMessage2Log("DevTools uninstallRights error: " . $e->getMessage(), 'dev.tools');
        }

        return true;
    }
}