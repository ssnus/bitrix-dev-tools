<?php
namespace Ssnus\DevTools;

use CAgent;
use Throwable;

class AgentManager
{
    public static function runAgents($agentIds = null)
    {
        if (!method_exists('\CAgent', 'CheckAgents')) {
            return false;
        }

        if (!empty($agentIds) && is_array($agentIds)) {
            global $DB;
            $results = [];

            foreach ($agentIds as $agentId) {
                $agentId = (int)$agentId;
                $res = $DB->Query('SELECT * FROM b_agent WHERE ID = ' . $DB->ForSql($agentId) . " AND ACTIVE = 'Y'");
                if ($agent = $res->Fetch()) {
                    try {
                        $result = eval("return {$agent['NAME']};");
                        $results[$agentId] = ['success' => true, 'result' => $result];

                        $DB->Query("UPDATE b_agent SET LAST_EXEC = NOW(), NEXT_EXEC = DATE_ADD(NEXT_EXEC, INTERVAL {$agent['AGENT_INTERVAL']} SECOND) WHERE ID = {$agentId}");
                    } catch (Throwable $e) {
                        $results[$agentId] = ['success' => false, 'error' => $e->getMessage()];
                        AddMessage2Log("Agent #{$agentId} execution error: " . $e->getMessage(), 'dev.tools');
                    }
                }
            }
            return $results;
        }

        return CAgent::CheckAgents();
    }
}
