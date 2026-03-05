<?php

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;

Loc::loadMessages(__FILE__);

if (class_exists('dev_tools')) {
    return;
}

class dev_tools extends CModule
{
    public $MODULE_ID = 'dev.tools';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $MODULE_GROUP_RIGHTS = 'Y';

    public function __construct()
    {
        $arModuleVersion = [];
        $versionFile = $_SERVER['DOCUMENT_ROOT'] . getLocalPath('modules/dev.tools/version.php');

        if ($versionFile && file_exists($versionFile)) {
            try {
                include($versionFile);
            } catch (Throwable $e) {
                AddMessage2Log('DevTools version load error: ' . $e->getMessage(), 'dev.tools');
            }
        }

        $this->MODULE_VERSION = $arModuleVersion['VERSION'] ?? '0.0.0';
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'] ?? date('Y-m-d');
        $this->MODULE_NAME = GetMessage('DEV_TOOLS_MODULE_NAME') ?: 'Dev Tools';
        $this->MODULE_DESCRIPTION = GetMessage('DEV_TOOLS_MODULE_DESC') ?: 'Инструменты разработчика';
    }

    public function DoInstall()
    {
        global $APPLICATION;

        if (ModuleManager::isModuleInstalled($this->MODULE_ID)) {
            return true;
        }

        try {
            ModuleManager::registerModule($this->MODULE_ID);
            $this->installRights();
            $this->installAdminFiles();
            $this->installFiles();
            \Bitrix\Main\Config\Option::delete($this->MODULE_ID);
            $APPLICATION->SetTitle(GetMessage('DEV_TOOLS_INSTALL_TITLE'));
            return true;
        } catch (Exception $e) {
            AddMessage2Log('DevTools install error: ' . $e->getMessage(), 'dev.tools');
            $APPLICATION->ThrowException($e->getMessage());
            return false;
        }
    }

    public function DoUninstall()
    {
        global $APPLICATION;

        try {
            $this->uninstallAdminFiles();
            $this->uninstallFiles();
            $this->uninstallRights();
            ModuleManager::unRegisterModule($this->MODULE_ID);
            $APPLICATION->SetTitle(GetMessage('DEV_TOOLS_UNINSTALL_TITLE'));
            return true;
        } catch (Exception $e) {
            AddMessage2Log('DevTools uninstall error: ' . $e->getMessage(), 'dev.tools');
            $APPLICATION->ThrowException($e->getMessage());
            return false;
        }
    }

    public function installAdminFiles()
    {
        $root = $_SERVER['DOCUMENT_ROOT'];
        $adminPath = $root . '/bitrix/admin';
        $proxyPath = $adminPath . '/dev_tools.php';

        if (!is_dir($adminPath) || !is_writable($adminPath)) {
            AddMessage2Log("DevTools: Cannot write to {$adminPath}", 'dev.tools');
            return false;
        }

        $proxyContent = "<?php\n" . 
        "/**\n * Proxy file for {$this->MODULE_ID} module\n * Auto-generated. Do not edit.\n */\n" . 
        "require_once(\$_SERVER[\"DOCUMENT_ROOT\"].\"/bitrix/modules/main/include/prolog_admin_before.php\");\n" . 
        "\$path = getLocalPath(\"modules/{$this->MODULE_ID}/admin/dev_tools.php\");\n" .
        "if (\$path) {\n" .
        "    require(\$_SERVER[\"DOCUMENT_ROOT\"] . \$path);\n" .
        "} else {\n" .
        "    ShowError(\"Module {$this->MODULE_ID} not found.\");\n" .
        "}\n";

        try {
            $result = file_put_contents($proxyPath, $proxyContent, LOCK_EX);
            if ($result === false) {
                throw new RuntimeException("Failed to write {$proxyPath}");
            }
            @chmod($proxyPath, 0644);
            return true;
        } catch (Exception $e) {
            AddMessage2Log('DevTools installAdminFiles error: ' . $e->getMessage(), 'dev.tools');
            return false;
        }
    }

    public function uninstallAdminFiles()
    {
        $proxyFile = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin/dev_tools.php';

        try {
            if (file_exists($proxyFile)) {
                $content = file_get_contents($proxyFile);
                if ($content && strpos($content, $this->MODULE_ID) !== false) {
                    @unlink($proxyFile);
                }
            }
            return true;
        } catch (Exception $e) {
            AddMessage2Log('DevTools uninstallAdminFiles error: ' . $e->getMessage(), 'dev.tools');
            return false;
        }
    }

    public function installFiles()
    {
        CopyDirFiles(__DIR__ . '/../admin/styles', $_SERVER['DOCUMENT_ROOT'] . '/bitrix/css/' . $this->MODULE_ID, true, true);
        CopyDirFiles(__DIR__ . '/../admin/scripts', $_SERVER['DOCUMENT_ROOT'] . '/bitrix/js/' . $this->MODULE_ID, true, true);
        return true;
    }

    public function uninstallFiles()
    {
        DeleteDirFilesEx('/bitrix/css/' . $this->MODULE_ID);
        DeleteDirFilesEx('/bitrix/js/' . $this->MODULE_ID);
        return true;
    }

    public function hasModuleRightsTable()
    {
        global $DB;
        $res = $DB->Query("SHOW TABLES LIKE 'b_module_right'");
        return ($res->Fetch() !== false);
    }

    public function installRights()
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
        } catch (Exception $e) {
            AddMessage2Log('DevTools installRights error: ' . $e->getMessage(), 'dev.tools');
        }

        return true;
    }

    public function uninstallRights()
    {
        if (!$this->hasModuleRightsTable()) {
            return true;
        }

        global $DB;

        try {
            $moduleId = $DB->ForSql($this->MODULE_ID);
            $DB->Query("DELETE FROM b_module_right WHERE MODULE_ID = '{$moduleId}'");
        } catch (Exception $e) {
            AddMessage2Log('DevTools uninstallRights error: ' . $e->getMessage(), 'dev.tools');
        }

        return true;
    }
}