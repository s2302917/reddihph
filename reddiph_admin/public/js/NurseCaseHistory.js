(function () {
    var searchForm = document.getElementById('nurse-history-search');
    var query = document.getElementById('nurse-history-query');
    var tableBody = document.getElementById('nurse-history-rows');
    var emptyState = document.getElementById('nurse-history-empty');
    var pagination = document.getElementById('nurse-history-pagination');
    var previous = document.getElementById('nurse-history-previous');
    var next = document.getElementById('nurse-history-next');
    var pageStatus = document.getElementById('nurse-history-page-status');
    var feedback = document.getElementById('nurse-history-feedback');
    var resolvedRate = document.querySelector('.nurse-history-stats > div:nth-child(3) small');
    if (!searchForm || !query || !tableBody) return;

    var rows = Array.from(tableBody.querySelectorAll('tr[data-incident-id]'));
    var pageSize = 6;
    var page = 1;
    var refreshing = false;

    function matchingNurseHistoryRows() {
        var term = query.value.trim().toLocaleLowerCase();
        return rows.filter(function (row) {
            var searchableText = Array.from(row.cells)
                .filter(function (_, index) { return index !== 3; })
                .map(function (cell) { return cell.textContent; })
                .join(' ')
                .toLocaleLowerCase();
            return !term || searchableText.includes(term);
        });
    }

    function renderNurseHistory() {
        var matches = matchingNurseHistoryRows();
        var pageCount = Math.max(1, Math.ceil(matches.length / pageSize));
        page = Math.min(page, pageCount);
        var start = (page - 1) * pageSize;
        rows.forEach(function (row) {
            var index = matches.indexOf(row);
            row.hidden = index < start || index >= start + pageSize;
        });

        emptyState.hidden = matches.length > 0;
        if (matches.length) {
            emptyState.textContent = '';
        } else {
            emptyState.textContent = rows.length
                ? 'No incidents match your search.'
                : 'No cases were notified to your account for this period.';
        }
        pagination.hidden = matches.length === 0;
        pageStatus.textContent = 'Page ' + page + ' of ' + pageCount;
        previous.disabled = page <= 1;
        next.disabled = page >= pageCount;
    }

    function setNurseHistoryFeedback(message) {
        feedback.textContent = message;
        feedback.hidden = message === '';
    }

    function makeNurseHistoryRow(item) {
        var row = document.createElement('tr');
        row.dataset.incidentId = String(item.id);

        [item.date, item.case, item.patient].forEach(function (value) {
            var cell = document.createElement('td');
            cell.textContent = value || '';
            row.appendChild(cell);
        });

        var categoryCell = document.createElement('td');
        var category = document.createElement('span');
        category.className = 'history-category-value category-' + (item.triageCategory || 'unclassified');
        category.textContent = item.triageCategoryLabel || 'Unclassified';
        categoryCell.appendChild(category);
        row.appendChild(categoryCell);

        var lifecycleCell = document.createElement('td');
        var lifecycle = document.createElement('span');
        lifecycle.className = 'history-outcome status-' + item.status;
        lifecycle.textContent = item.status
            ? item.status.charAt(0).toUpperCase() + item.status.slice(1)
            : 'Unknown';
        lifecycleCell.appendChild(lifecycle);
        row.appendChild(lifecycleCell);
        return row;
    }

    function refreshNurseCaseHistory() {
        if (
            !window.nurseHistoryRefreshEndpoint ||
            refreshing ||
            document.visibilityState !== 'visible' ||
            tableBody.contains(document.activeElement)
        ) return;

        refreshing = true;
        fetch(window.nurseHistoryRefreshEndpoint, {
            headers: { Accept: 'application/json' },
            cache: 'no-store'
        }).then(function (response) {
            return response.json().then(function (result) {
                if (!response.ok || !result.success || !Array.isArray(result.cases)) {
                    throw new Error(result.message || 'Case history could not be refreshed.');
                }
                return result;
            });
        }).then(function (result) {
            var fragment = document.createDocumentFragment();
            result.cases.forEach(function (item) {
                fragment.appendChild(makeNurseHistoryRow(item));
            });
            tableBody.replaceChildren(fragment);
            rows = Array.from(tableBody.querySelectorAll('tr[data-incident-id]'));

            if (result.stats) {
                var values = document.querySelectorAll('.nurse-history-stats > div strong');
                if (values[0]) values[0].textContent = String(result.stats.received);
                if (values[1]) values[1].textContent = String(result.stats.totalNotified);
                if (values[2]) values[2].textContent = String(result.stats.resolved);
                if (resolvedRate) {
                    resolvedRate.textContent = (result.stats.resolvedPercent || '0%') +
                        ' of cases in this date range';
                }
            }

            setNurseHistoryFeedback('');
            renderNurseHistory();
        }).catch(function (error) {
            setNurseHistoryFeedback(error.message || 'Case history could not be refreshed.');
            console.error('[PulseAlert] Nurse case-history refresh failed.', error);
        }).finally(function () {
            refreshing = false;
        });
    }

    searchForm.addEventListener('submit', function (event) {
        event.preventDefault();
        page = 1;
        renderNurseHistory();
    });
    query.addEventListener('input', function () {
        page = 1;
        renderNurseHistory();
    });
    previous.addEventListener('click', function () {
        if (page > 1) {
            page -= 1;
            renderNurseHistory();
        }
    });
    next.addEventListener('click', function () {
        if (page < Math.ceil(matchingNurseHistoryRows().length / pageSize)) {
            page += 1;
            renderNurseHistory();
        }
    });

    renderNurseHistory();
    refreshNurseCaseHistory();
    window.setInterval(refreshNurseCaseHistory, 5000);
}());
