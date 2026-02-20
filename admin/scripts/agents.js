(function() {
    'use strict';

    function toggleOverdueAgents() {
        document.querySelectorAll('.agent-row').forEach(row => {
            const cb = row.querySelector('.agent-checkbox');
            if (row.dataset.overdue === '1') {
                cb.checked = true;
                row.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            } else {
                cb.checked = false;
            }
        });
        updateSelectedCount();
    }

    function clearSelection() {
        document.querySelectorAll('.agent-checkbox').forEach(cb => {
            cb.checked = false;
        });
        updateSelectedCount();
    }

    function updateSelectedCount() {
        const count = document.querySelectorAll('.agent-checkbox:checked').length;
        const el = document.getElementById('selected-count');
        if (el) el.textContent = count;
    }

    function initAgentSearch() {
        const input = document.getElementById('agent-search');
        if (!input) return;

        input.addEventListener('input', function(e) {
            const query = e.target.value.toLowerCase().trim();
            document.querySelectorAll('.agent-row').forEach(row => {
                const func = row.dataset.func || '';
                const module = row.dataset.module || '';
                const text = row.textContent.toLowerCase();
                row.style.display = (func.includes(query) || module.includes(query) || text.includes(query))
                    ? ''
                    : 'none';
            });
        });
    }

    function initCheckboxes() {
        document.querySelectorAll('.agent-checkbox').forEach(cb => {
            cb.addEventListener('change', updateSelectedCount);
        });
    }

    function init() {
        initAgentSearch();
        initCheckboxes();
        updateSelectedCount();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.DevToolsAgents = {
        toggleOverdueAgents,
        clearSelection,
        updateSelectedCount
    };
})();