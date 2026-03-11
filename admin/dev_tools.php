<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php');
$modulePath = getLocalPath('modules/dev.tools/include.php');
if ($modulePath) {
    require_once($_SERVER['DOCUMENT_ROOT'] . $modulePath);
} else {
    ShowError('dev.tools module include.php not found');
    require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php');
    die();
}

use Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

$POST_RIGHT = $APPLICATION->GetGroupRight('dev.tools');
if ($POST_RIGHT == 'D') {
    $APPLICATION->AuthForm(GetMessage('DEV_TOOLS_ACCESS_DENIED'));
    require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php');
    die();
}

$APPLICATION->SetAdditionalCSS('/bitrix/css/dev.tools/dev-tools.css');
\Bitrix\Main\Page\Asset::getInstance()->addJs('/bitrix/js/dev.tools/agents.js');

$MODULE_ID = 'dev.tools';
$aMess = [];

if (!class_exists('\Ssnus\DevTools\CacheManager')) {
    $modulePath = getLocalPath('modules/dev.tools/include.php');
    if ($modulePath) {
        require_once $_SERVER['DOCUMENT_ROOT'] . $modulePath;
    }
}

$debugMode = \Ssnus\DevTools\DebugManager::isDebugMode();
$cacheDisabled = \Ssnus\DevTools\CacheManager::isCacheDisabled();

$agentsList = [];
if (method_exists('CAgent', 'GetList')) {
    $dbAgents = CAgent::GetList(['NEXT_EXEC' => 'ASC'], ['ACTIVE' => 'Y']);
    while ($agent = $dbAgents->Fetch()) {
        $agentsList[] = $agent;
    }
}

$redisStatus = 'unavailable';
$memcachedStatus = 'unavailable';

if (class_exists('\Redis')) {
    try {
        $redisConfig = COption::GetOptionString('main', 'component_cache_redis', '');
        if ($redisConfig) {
            $config = @unserialize($redisConfig);
            if ($config && is_array($config)) {
                $redis = new Redis();
                if ($redis->connect($config['host'] ?? '127.0.0.1', $config['port'] ?? 6379, $config['timeout'] ?? 0)) {
                    if (!empty($config['password'])) {
                        $redis->auth($config['password']);
                    }
                    $redis->ping();
                    $redisStatus = 'connected';
                    $redis->close();
                }
            }
        }
    } catch (Throwable $e) {
        $redisStatus = 'error';
    }
}

if (class_exists('\Memcached')) {
    try {
        $memcachedConfig = COption::GetOptionString('main', 'component_cache_memcache', '');
        if ($memcachedConfig) {
            $config = @unserialize($memcachedConfig);
            if ($config && is_array($config)) {
                $memcached = new Memcached();
                $memcached->addServer($config['host'] ?? '127.0.0.1', $config['port'] ?? 11211);
                if ($memcached->getStats()) {
                    $memcachedStatus = 'connected';
                }
                $memcached->quit();
            }
        }
    } catch (Throwable $e) {
        $memcachedStatus = 'error';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
    $action = $_POST['dev_action'] ?? '';

    // require W rights to perform actions
    if ($POST_RIGHT >= 'W') {
        if ($action === 'clear_cache') {
            $cacheOptions = [
                'components' => isset($_POST['cache_components']),
                'managed' => isset($_POST['cache_managed']),
                'html' => isset($_POST['cache_html']),
                'menu' => isset($_POST['cache_menu']),
                'browser' => isset($_POST['cache_browser']),
                'redis' => isset($_POST['cache_redis']),
                'memcached' => isset($_POST['cache_memcached']),
            ];

            if (!array_sum(array_filter($cacheOptions))) {
                $cacheOptions = [
                    'components' => true,
                    'managed' => true,
                    'html' => true,
                    'menu' => true,
                    'browser' => true,
                ];
            }

            \Ssnus\DevTools\CacheManager::clearCache($cacheOptions);
            $aMess[] = ['type' => 'success', 'text' => GetMessage('DEV_TOOLS_CACHE_CLEARED')];
        }
        elseif ($action === 'toggle_debug') {
            $newMode = !$debugMode;
            \Ssnus\DevTools\DebugManager::setDebugMode($newMode);
            LocalRedirect($APPLICATION->GetCurPageParam('dev_status=updated', ['dev_status']));
        }
        elseif ($action === 'toggle_cache') {
            $newDisabled = !$cacheDisabled;
            \Ssnus\DevTools\CacheManager::setCacheDisabled($newDisabled);
            $cacheDisabled = $newDisabled;
            $aMess[] = [
                'type' => 'info',
                'text' => $newDisabled ? GetMessage('DEV_TOOLS_CACHE_DISABLED') : GetMessage('DEV_TOOLS_CACHE_ENABLED')
            ];
        }
        elseif ($action === 'run_agents') {
            \Ssnus\DevTools\AgentManager::runAgents();
            $aMess[] = ['type' => 'success', 'text' => GetMessage('DEV_TOOLS_AGENTS_RUN')];
        }
        elseif ($action === 'run_selected_agents') {
            $selectedAgents = $_POST['agent_ids'] ?? [];
            if (!empty($selectedAgents)) {
                $results = \Ssnus\DevTools\AgentManager::runAgents($selectedAgents);
                $successCount = count(array_filter($results, fn($r) => $r['success']));
                $aMess[] = [
                    'type' => 'success',
                    'text' => sprintf(GetMessage('DEV_TOOLS_AGENTS_RUN_SELECTED'), $successCount, count($results))
                ];
            } else {
                $aMess[] = ['type' => 'warning', 'text' => GetMessage('DEV_TOOLS_AGENTS_NONE_SELECTED')];
            }
        }
        elseif ($action === 'view_log') {}
    } else {
        $aMess[] = ['type' => 'error', 'text' => GetMessage('DEV_TOOLS_ACCESS_DENIED')];
    }
}

$APPLICATION->SetTitle(GetMessage('DEV_TOOLS_PAGE_TITLE'));
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php');
?>

    <div class="dev-tools-wrap">

        <?php foreach ($aMess as $msg): ?>
            <div class="dev-alert dev-alert--<?= $msg['type'] ?>">
                <span><?= htmlspecialcharsbx($msg['text']) ?></span>
            </div>
        <?php endforeach; ?>

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
                            <?= $cacheDisabled
                                ? GetMessage('DEV_TOOLS_CACHE_STATE_DESC_DISABLED')
                                : GetMessage('DEV_TOOLS_CACHE_STATE_DESC_ENABLED')
                            ?>
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
                    <label class="dev-checkbox">
                        <input type="checkbox" name="cache_redis" <?= $redisStatus === 'connected' ? '' : 'disabled' ?>>
                        <span class="dev-checkbox__label">
                        Redis
                        <small class="dev-badge dev-badge--<?= $redisStatus ?>">
                            <?= GetMessage('DEV_TOOLS_CACHE_BACKEND_' . strtoupper($redisStatus)) ?>
                        </small>
                    </span>
                    </label>

                    <label class="dev-checkbox">
                        <input type="checkbox" name="cache_memcached" <?= $memcachedStatus === 'connected' ? '' : 'disabled' ?>>
                        <span class="dev-checkbox__label">
                        Memcached
                        <small class="dev-badge dev-badge--<?= $memcachedStatus ?>">
                            <?= GetMessage('DEV_TOOLS_CACHE_BACKEND_' . strtoupper($memcachedStatus)) ?>
                        </small>
                    </span>
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

        <!-- 🔹 Карточка: Выборочный запуск агентов (ОПТИМИЗИРОВАННАЯ) -->
        <div class="dev-card">
            <div class="dev-card__title">
                <?= GetMessage('DEV_TOOLS_CARD_SELECTIVE_AGENTS') ?>
                <span class="dev-badge"><?= count($agentsList) ?> шт.</span>
            </div>

            <form method="POST" id="agents-form">
                <?= bitrix_sessid_post() ?>

                <?php if (!empty($agentsList)): ?>
                    <!-- Панель управления -->
                    <div class="agents-toolbar" style="display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap; align-items: center;">
                        <input type="text" id="agent-search" placeholder="🔍 Поиск по функции или модулю..."
                               style="flex: 1; min-width: 200px; padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px;">

                        <button type="button" onclick="DevToolsAgents.toggleOverdueAgents()" class="dev-btn dev-btn--warning" style="font-size: 12px; padding: 6px 12px;">
                            ⏰ Только просроченные
                        </button>
                        <button type="button" onclick="DevToolsAgents.clearSelection()" class="dev-btn dev-btn--secondary" style="font-size: 12px; padding: 6px 12px;">
                            ✕ Очистить выбор
                        </button>
                    </div>

                    <!-- Таблица агентов -->
                    <div style="overflow-x: auto; max-height: 400px; overflow-y: auto; border: 1px solid #e0e0e0; border-radius: 4px;">
                        <table class="adm-list-table" style="width: 100%; min-width: 600px;">
                            <thead>
                            <tr class="adm-list-table-heading">
                                <th style="width: 40px; text-align: center;">☑</th>
                                <th>ID</th>
                                <th>Функция</th>
                                <th>Модуль</th>
                                <th>Интервал</th>
                                <th>След. запуск</th>
                                <th style="width: 90px;">Статус</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php
                            $now = time();
                            foreach ($agentsList as $agent):
                                $nextExec = MakeTimeStamp($agent['NEXT_EXEC'], 'FULL');
                                $isOverdue = ($nextExec && $nextExec < $now);
                                $module = $agent['MODULE_ID'] ?: 'main';

                                $funcName = htmlspecialcharsbx($agent['NAME']);
                                $funcTitle = $funcName;
                                if (strlen($funcName) > 50) {
                                    $funcName = mb_substr($funcName, 0, 50) . '...';
                                }
                                ?>
                                <tr class="adm-list-table-row agent-row"
                                    data-module="<?= $module ?>"
                                    data-overdue="<?= $isOverdue ? '1' : '0' ?>"
                                    data-func="<?= htmlspecialcharsbx(strtolower($agent['NAME'])) ?>"
                                    style="<?= $isOverdue ? 'background: #fff8f8;' : '' ?>">
                                    <td style="text-align: center;">
                                        <input type="checkbox" name="agent_ids[]" value="<?= (int)$agent['ID'] ?>"
                                               class="agent-checkbox" <?= $isOverdue ? 'checked' : '' ?>>
                                    </td>
                                    <td style="font-weight: bold; color: #2480c2;">#<?= (int)$agent['ID'] ?></td>
                                    <td>
                                        <code style="background: #f5f5f5; padding: 2px 6px; border-radius: 3px; font-size: 11px;" title="<?= $funcTitle ?>">
                                            <?= $funcName ?>
                                        </code>
                                    </td>
                                    <td>
                                    <span class="dev-badge dev-badge--info" style="font-size: 10px;">
                                        <?= htmlspecialcharsbx($module) ?>
                                    </span>
                                    </td>
                                    <td style="font-size: 12px; color: #666;">
                                        <?= ($agent['AGENT_INTERVAL'] > 0) ? $agent['AGENT_INTERVAL'] . ' сек.' : '—' ?>
                                    </td>
                                    <td style="font-size: 12px; white-space: nowrap;">
                                        <?= $agent['NEXT_EXEC'] ?>
                                    </td>
                                    <td>
                                        <?php if ($isOverdue): ?>
                                            <span class="dev-badge dev-badge--error" style="font-size: 10px;">⏰ Просрочен</span>
                                        <?php else: ?>
                                            <span class="dev-badge dev-badge--success" style="font-size: 10px;">✓ В норме</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <? endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Кнопка запуска -->
                    <div class="dev-row" style="margin-top: 15px; justify-content: flex-end; align-items: center;">
                <span style="color: #666; font-size: 13px; margin-right: 15px;">
                    Выбрано: <strong id="selected-count" style="color: #2480c2;">0</strong> из <?= count($agentsList) ?>
                </span>
                        <button type="submit" name="dev_action" value="run_selected_agents" class="dev-btn dev-btn--primary" style="padding: 10px 24px;">
                            🚀 Запустить выбранные
                        </button>
                    </div>
                <? else: ?>
                    <div class="dev-alert dev-alert--info">
                        <span><?= GetMessage('DEV_TOOLS_AGENTS_EMPTY') ?></span>
                    </div>
                <? endif; ?>
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

            <form method="POST" style="margin-bottom: 15px;">
                <?= bitrix_sessid_post() ?>
                <div class="dev-row">
                    <div style="flex: 1;">
                        <input type="text" name="log_custom_path"
                               placeholder="<?= GetMessage('DEV_TOOLS_LOG_PATH_PLACEHOLDER') ?>"
                               value="<?= htmlspecialcharsbx($_POST['log_custom_path'] ?? '') ?>"
                               style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">
                    </div>
                    <button type="submit" name="dev_action" value="view_log" class="dev-btn dev-btn--secondary">
                        <?= GetMessage('DEV_TOOLS_LOG_BTN_LOAD') ?>
                    </button>
                </div>

                <?php $registeredLogs = \Ssnus\DevTools\LogManager::getRegisteredLogFiles(); ?>
                <?php if (!empty($registeredLogs)): ?>
                    <div style="margin-top: 8px; font-size: 12px; color: #666;">
                        <?= GetMessage('DEV_TOOLS_LOG_REGISTERED') ?>:
                        <?php foreach ($registeredLogs as $path => $label): ?>
                            <a href="#" onclick="document.querySelector('input[name=\'log_custom_path\']').value='<?= addslashes($path) ?>'; return false;"
                               style="margin-left: 5px; color: #2480c2;"><?= htmlspecialcharsbx($label) ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </form>

            <?php
            $logPath = $_POST['log_custom_path'] ?? null;
            echo '<div class="dev-log">' . htmlspecialcharsbx(\Ssnus\DevTools\LogManager::getLastErrorLog(30, $logPath)) . '</div>';
            ?>
        </div>

        <div class="dev-card">
            <div class="dev-card__title"><?= GetMessage('DEV_TOOLS_CARD_CACHE_BACKENDS') ?></div>
            <table class="adm-list-table">
                <tr>
                    <td>Redis</td>
                    <td>
                    <span class="dev-badge dev-badge--<?= $redisStatus ?>">
                        <?= GetMessage('DEV_TOOLS_CACHE_BACKEND_' . strtoupper($redisStatus)) ?>
                    </span>
                    </td>
                </tr>
                <tr>
                    <td>Memcached</td>
                    <td>
                    <span class="dev-badge dev-badge--<?= $memcachedStatus ?>">
                        <?= GetMessage('DEV_TOOLS_CACHE_BACKEND_' . strtoupper($memcachedStatus)) ?>
                    </span>
                    </td>
                </tr>
            </table>
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