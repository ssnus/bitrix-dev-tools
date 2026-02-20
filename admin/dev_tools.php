<?
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/local/modules/dev.tools/include.php');


use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);


if (!$USER->IsAdmin()) {
    $APPLICATION->AuthForm(GetMessage('DEV_TOOLS_ACCESS_DENIED'));
    require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php');
    die();
}

$APPLICATION->SetAdditionalCSS('/local/modules/dev.tools/admin/styles/dev-tools.css');

$MODULE_ID = 'dev.tools';
$aMess = [];
$debugMode = DevToolsHelper::isDebugMode();
$cacheDisabled = DevToolsHelper::isCacheDisabled();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
    $action = $_POST['dev_action'] ?? '';

    if ($action === 'clear_cache') {
        $cacheOptions = [
            'components' => isset($_POST['cache_components']),
            'managed'    => isset($_POST['cache_managed']),
            'html'       => isset($_POST['cache_html']),
            'menu'       => isset($_POST['cache_menu']),
            'browser'    => isset($_POST['cache_browser']),
        ];

        if (!array_sum($cacheOptions)) {
            $cacheOptions = [
                'components' => true,
                'managed'    => true,
                'html'       => true,
                'menu'       => true,
                'browser'    => true,
            ];
        }

        DevToolsHelper::clearCache($cacheOptions);
        $aMess[] = ['type' => 'success', 'text' => GetMessage('DEV_TOOLS_CACHE_CLEARED')];
    } elseif ($action === 'toggle_debug') {
        $newMode = !$debugMode;
        DevToolsHelper::setDebugMode($newMode);
        LocalRedirect($APPLICATION->GetCurPageParam('dev_status=updated', ['dev_status']));
    } elseif ($action === 'toggle_cache') {
        $newDisabled = !$cacheDisabled;
        DevToolsHelper::setCacheDisabled($newDisabled);
        $cacheDisabled = $newDisabled;
        $aMess[] = [
            'type' => 'info',
            'text' => $newDisabled ? GetMessage('DEV_TOOLS_CACHE_DISABLED') : GetMessage('DEV_TOOLS_CACHE_ENABLED'),
        ];
    } elseif ($action === 'run_agents') {
        DevToolsHelper::runAgents();
        $aMess[] = ['type' => 'success', 'text' => GetMessage('DEV_TOOLS_AGENTS_RUN')];
    }
}

$APPLICATION->SetTitle(GetMessage('DEV_TOOLS_PAGE_TITLE'));
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php');
?>

    <div class="dev-tools-wrap">

        <!-- 🔔 Сообщения -->
        <? foreach ($aMess as $msg): ?>
            <div class="dev-alert dev-alert--<?= $msg['type'] ?>">
                <span><?= htmlspecialcharsbx($msg['text']) ?></span>
            </div>
        <? endforeach; ?>

        <div class="dev-card">
            <div class="dev-card__title"><?= GetMessage('DEV_TOOLS_CARD_CACHE_CONTROL') ?></div>

            <form method="POST">
                <?= bitrix_sessid_post() ?>

                <div class="dev-row">
                    <div>
                        <div class="dev-label">
                            <?= GetMessage('DEV_TOOLS_CACHE_STATE_LABEL') ?>
                            <span class="dev-badge dev-badge--<?= $cacheDisabled ? 'off' : 'on' ?>">
                            <?= $cacheDisabled ? GetMessage('DEV_TOOLS_CACHE_STATE_DISABLED') : GetMessage('DEV_TOOLS_CACHE_STATE_ENABLED') ?>
                        </span>
                        </div>
                        <div class="dev-desc">
                            <?= $cacheDisabled ? GetMessage('DEV_TOOLS_CACHE_STATE_DESC_DISABLED') : GetMessage('DEV_TOOLS_CACHE_STATE_DESC_ENABLED') ?>
                        </div>
                    </div>
                    <button type="submit" name="dev_action" value="toggle_cache"
                            class="dev-btn dev-btn--<?= $cacheDisabled ? 'success' : 'warning' ?>">
                        <?= $cacheDisabled ? GetMessage('DEV_TOOLS_CACHE_BTN_ENABLE') : GetMessage('DEV_TOOLS_CACHE_BTN_DISABLE') ?>
                    </button>
                </div>
            </form>
        </div>

        <div class="dev-card">
            <div class="dev-card__title"><?= GetMessage('DEV_TOOLS_CARD_CACHE_CLEAR') ?></div>

            <form method="POST">
                <?= bitrix_sessid_post() ?>

                <div class="dev-row">
                    <div>
                        <div class="dev-label"><?= GetMessage('DEV_TOOLS_CACHE_TYPES_LABEL') ?></div>
                        <div class="dev-desc"><?= GetMessage('DEV_TOOLS_CACHE_TYPES_DESC') ?></div>
                    </div>
                </div>

                <div class="dev-cache-options">
                    <label class="dev-checkbox">
                        <input type="checkbox" name="cache_components" checked>
                        <span class="dev-checkbox__label"><?= GetMessage('DEV_TOOLS_CACHE_TYPE_COMPONENTS') ?></span>
                    </label>

                    <label class="dev-checkbox">
                        <input type="checkbox" name="cache_managed" checked>
                        <span class="dev-checkbox__label"><?= GetMessage('DEV_TOOLS_CACHE_TYPE_MANAGED') ?></span>
                    </label>

                    <label class="dev-checkbox">
                        <input type="checkbox" name="cache_menu" checked>
                        <span class="dev-checkbox__label"><?= GetMessage('DEV_TOOLS_CACHE_TYPE_MENU') ?></span>
                    </label>

                    <label class="dev-checkbox">
                        <input type="checkbox" name="cache_html">
                        <span class="dev-checkbox__label"><?= GetMessage('DEV_TOOLS_CACHE_TYPE_HTML') ?></span>
                    </label>

                    <label class="dev-checkbox">
                        <input type="checkbox" name="cache_browser">
                        <span class="dev-checkbox__label"><?= GetMessage('DEV_TOOLS_CACHE_TYPE_BROWSER') ?></span>
                    </label>
                </div>

                <div class="dev-row" style="margin-top: 15px;">
                    <div></div>
                    <button type="submit" name="dev_action" value="clear_cache"
                            class="dev-btn dev-btn--primary">
                        <?= GetMessage('DEV_TOOLS_CACHE_BTN_CLEAR') ?>
                    </button>
                </div>
            </form>
        </div>

        <div class="dev-card">
            <div class="dev-card__title"><?= GetMessage('DEV_TOOLS_CARD_QUICK_ACTIONS') ?></div>

            <form method="POST">
                <?= bitrix_sessid_post() ?>
                <div class="dev-row">
                    <div>
                        <div class="dev-label">
                            <?= GetMessage('DEV_TOOLS_DEBUG_LABEL') ?>
                            <span class="dev-badge dev-badge--<?= $debugMode ? 'on' : 'off' ?>">
                            <?= $debugMode ? GetMessage('DEV_TOOLS_DEBUG_STATUS_ON') : GetMessage('DEV_TOOLS_DEBUG_STATUS_OFF') ?>
                        </span>
                        </div>
                        <div class="dev-desc"><?= GetMessage('DEV_TOOLS_DEBUG_DESC') ?></div>
                    </div>
                    <button type="submit" name="dev_action" value="toggle_debug"
                            class="dev-btn dev-btn--<?= $debugMode ? 'warning' : 'success' ?>">
                        <?= $debugMode ? GetMessage('DEV_TOOLS_DEBUG_BTN_DISABLE') : GetMessage('DEV_TOOLS_DEBUG_BTN_ENABLE') ?>
                    </button>
                </div>

                <div class="dev-row">
                    <div>
                        <div class="dev-label"><?= GetMessage('DEV_TOOLS_AGENTS_LABEL') ?></div>
                        <div class="dev-desc"><?= GetMessage('DEV_TOOLS_AGENTS_DESC') ?></div>
                    </div>
                    <button type="submit" name="dev_action" value="run_agents"
                            class="dev-btn dev-btn--primary">
                        <?= GetMessage('DEV_TOOLS_AGENTS_BTN_RUN') ?>
                    </button>
                </div>
            </form>
        </div>

        <div class="dev-card">
            <div class="dev-card__title"><?= GetMessage('DEV_TOOLS_CARD_LOGS') ?></div>
            <div class="dev-log"><?= htmlspecialcharsbx(DevToolsHelper::getLastErrorLog(30)) ?></div>
        </div>

        <div class="dev-card">
            <div class="dev-card__title"><?= GetMessage('DEV_TOOLS_CARD_USER_INFO') ?></div>
            <table class="adm-list-table dev-user-info">
                <tr>
                    <td><?= GetMessage('DEV_TOOLS_USER_ID') ?></td>
                    <td><?= (int)$USER->GetID() ?></td>
                </tr>
                <tr>
                    <td><?= GetMessage('DEV_TOOLS_USER_LOGIN') ?></td>
                    <td><?= htmlspecialcharsbx($USER->GetLogin()) ?></td>
                </tr>
                <tr>
                    <td><?= GetMessage('DEV_TOOLS_USER_GROUPS') ?></td>
                    <td><?= implode(', ', $USER->GetUserGroupArray()) ?></td>
                </tr>
                <tr>
                    <td><?= GetMessage('DEV_TOOLS_USER_IP') ?></td>
                    <td><?= htmlspecialcharsbx($_SERVER['REMOTE_ADDR']) ?></td>
                </tr>
            </table>
        </div>

    </div>

<?php require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php'); ?>