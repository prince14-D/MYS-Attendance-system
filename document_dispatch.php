<?php
declare(strict_types=1);

$_GET['page'] = 'document_dispatch';
require_once __DIR__ . '/admin_bootstrap.php';

$pageTitle = 'Document Dispatch Register';
$extraHeadHtml = '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">';
require_once __DIR__ . '/admin_shell_start.php';

$dispatches = read_document_dispatches();
$dispatchResult = $registrationResult ?? null;
?>
<div class="dashboard-hero panel">
    <div class="dashboard-title">
        <span class="eyebrow">Records</span>
        <h1>Document Dispatch Register</h1>
        <p class="muted">Track letters, documents, or files sent out to agencies or other offices, including who released them, the date/time, and any supporting document.</p>
    </div>
</div>

<?php if ($dispatchResult !== null): ?>
    <div class="alert <?= $dispatchResult['ok'] ? 'success' : 'error' ?>"><?= h($dispatchResult['message']) ?></div>
<?php endif; ?>

<section class="admin-box mb-4">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Record Dispatch</span>
            <h2>Outgoing Document / Letter</h2>
        </div>
        <p class="muted">Log a document leaving the office and attach a supporting file if available.</p>
    </div>

    <form method="post" enctype="multipart/form-data" class="row g-3">
        <input type="hidden" name="admin_action" value="create_document_dispatch">

        <div class="col-12 col-md-4">
            <label class="form-label" for="dispatch_type">Document Type</label>
            <select class="form-select" id="dispatch_type" name="document_type" required>
                <option value="">Select type</option>
                <option>Letter</option>
                <option>Report</option>
                <option>Memo</option>
                <option>Certificate</option>
                <option>Agency Document</option>
                <option>Other</option>
            </select>
        </div>

        <div class="col-12 col-md-8">
            <label class="form-label" for="dispatch_subject">Subject / Description</label>
            <input class="form-control" id="dispatch_subject" name="subject" placeholder="Example: Quarterly Report to Ministry of Finance" required>
        </div>

        <div class="col-12 col-md-6">
            <label class="form-label" for="dispatch_recipient_name">Recipient Name</label>
            <input class="form-control" id="dispatch_recipient_name" name="recipient_name" placeholder="Person receiving the document">
        </div>

        <div class="col-12 col-md-6">
            <label class="form-label" for="dispatch_recipient_agency">Recipient Agency / Office</label>
            <input class="form-control" id="dispatch_recipient_agency" name="recipient_agency" placeholder="Example: Ministry of Finance">
        </div>

        <div class="col-12 col-md-6">
            <label class="form-label" for="dispatch_by">Released By</label>
            <input class="form-control" id="dispatch_by" name="dispatched_by" value="<?= h(current_username()) ?>" required>
        </div>

        <div class="col-12 col-md-6">
            <label class="form-label" for="dispatch_at">Date &amp; Time</label>
            <input type="datetime-local" class="form-control" id="dispatch_at" name="dispatched_at" value="<?= h(date('Y-m-d\TH:i')) ?>">
        </div>

        <div class="col-12">
            <label class="form-label" for="dispatch_notes">Notes</label>
            <textarea class="form-control" id="dispatch_notes" name="notes" rows="2" placeholder="Reference number, purpose, or any follow-up notes."></textarea>
        </div>

        <div class="col-12">
            <label class="form-label" for="dispatch_document">Supporting Document (optional)</label>
            <input type="file" class="form-control" id="dispatch_document" name="dispatch_document">
            <div class="form-text">PDF, image, Word, or Excel file, up to 10 MB.</div>
        </div>

        <div class="col-12">
            <button class="btn btn-primary" type="submit">Record Dispatch</button>
        </div>
    </form>
</section>

<section class="admin-box">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Dispatch Register</span>
            <h2>Recorded Dispatches</h2>
        </div>
        <p class="muted">Total recorded: <?= count($dispatches) ?></p>
    </div>

    <div class="records-filter-bar bootstrap-filter-bar" aria-label="Dispatch filters">
        <div class="records-filter-item search-item bootstrap-search-item">
            <label for="dispatchSearch">Search</label>
            <input class="form-control" id="dispatchSearch" type="search" placeholder="Search subject, recipient, agency, released by">
        </div>
        <div class="records-filter-item">
            <button class="btn btn-outline-secondary" type="button" id="dispatchFilterToggle" aria-expanded="false" aria-controls="dispatchFilterPanel">Filter</button>
        </div>
    </div>

    <div class="records-filter-panel row g-3 mb-3" id="dispatchFilterPanel" hidden>
        <div class="col-12 col-md-4">
            <label class="form-label" for="dispatchTypeFilter">Document Type</label>
            <select class="form-select" id="dispatchTypeFilter">
                <option value="all">All Types</option>
                <option>Letter</option>
                <option>Report</option>
                <option>Memo</option>
                <option>Certificate</option>
                <option>Agency Document</option>
                <option>Other</option>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label" for="dispatchDateFrom">From</label>
            <input type="date" class="form-control" id="dispatchDateFrom">
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label" for="dispatchDateTo">To</label>
            <input type="date" class="form-control" id="dispatchDateTo">
        </div>
        <div class="col-12 col-md-2 d-flex align-items-end">
            <button class="btn btn-outline-secondary w-100" type="button" id="dispatchFilterClear">Clear</button>
        </div>
    </div>

    <div class="records-filter-summary" id="dispatchFilterSummary" role="status" aria-live="polite"></div>
    <p class="muted small mb-2" id="dispatchLiveStatus">Live &mdash; auto-refreshes every 15s</p>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Subject</th>
                    <th>Recipient</th>
                    <th>Released By</th>
                    <th>Date &amp; Time</th>
                    <th>Supporting Document</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="dispatchTableBody">
                <?php foreach ($dispatches as $dispatch): ?>
                    <?php
                        $dispatchTimestamp = strtotime((string) ($dispatch['dispatched_at'] ?? 'now'));
                        $dispatchSearch = strtolower(implode(' ', [
                            (string) ($dispatch['document_type'] ?? ''),
                            (string) ($dispatch['subject'] ?? ''),
                            (string) ($dispatch['recipient_name'] ?? ''),
                            (string) ($dispatch['recipient_agency'] ?? ''),
                            (string) ($dispatch['dispatched_by'] ?? ''),
                        ]));
                    ?>
                    <tr data-dispatch-row data-dispatch-search="<?= h($dispatchSearch) ?>" data-dispatch-type="<?= h((string) ($dispatch['document_type'] ?? '')) ?>" data-dispatch-date="<?= h(date('Y-m-d', $dispatchTimestamp)) ?>">
                        <td><?= h((string) ($dispatch['document_type'] ?? 'Letter')) ?></td>
                        <td><?= h((string) ($dispatch['subject'] ?? '-')) ?></td>
                        <td>
                            <?= h((string) ($dispatch['recipient_name'] ?? '-')) ?>
                            <?php if (($dispatch['recipient_agency'] ?? '') !== ''): ?>
                                <br><small class="text-muted"><?= h((string) $dispatch['recipient_agency']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= h((string) ($dispatch['dispatched_by'] ?? 'Unknown')) ?></td>
                        <td><?= h(date('M j, Y H:i', $dispatchTimestamp)) ?></td>
                        <td>
                            <?php if (($dispatch['filename'] ?? '') !== ''): ?>
                                <a class="btn btn-sm btn-outline-primary" href="document_dispatch_file.php?dispatch_id=<?= h(urlencode((string) $dispatch['dispatch_id'])) ?>" target="_blank" rel="noopener">View</a>
                            <?php else: ?>
                                <span class="text-muted">None</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (in_array(current_user_role(), ['admin', 'hr'], true)): ?>
                                <form method="post" onsubmit="return confirm('Delete this dispatch record?');">
                                    <input type="hidden" name="admin_action" value="delete_document_dispatch">
                                    <input type="hidden" name="dispatch_id" value="<?= h((string) $dispatch['dispatch_id']) ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (count($dispatches) === 0): ?>
                    <tr>
                        <td class="text-center text-muted py-4" colspan="7">No document dispatches have been recorded yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="empty small-empty" id="dispatchFilterEmpty" hidden>No dispatches match your search or filters.</div>
    </div>
</section>
<script>
    (function () {
        const searchInput = document.getElementById('dispatchSearch');
        const typeFilter = document.getElementById('dispatchTypeFilter');
        const dateFromInput = document.getElementById('dispatchDateFrom');
        const dateToInput = document.getElementById('dispatchDateTo');
        const filterToggle = document.getElementById('dispatchFilterToggle');
        const filterPanel = document.getElementById('dispatchFilterPanel');
        const filterClear = document.getElementById('dispatchFilterClear');
        const filterSummary = document.getElementById('dispatchFilterSummary');
        const filterEmpty = document.getElementById('dispatchFilterEmpty');
        const liveStatus = document.getElementById('dispatchLiveStatus');
        const tableBody = document.getElementById('dispatchTableBody');

        function escapeHtml(value) {
            return String(value)
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        }

        function currentRows() {
            return Array.from(document.querySelectorAll('[data-dispatch-row]'));
        }

        function applyDispatchFilters() {
            const rows = currentRows();
            if (rows.length === 0) {
                if (filterSummary) { filterSummary.textContent = ''; }
                if (filterEmpty) { filterEmpty.hidden = true; }
                return;
            }

            const searchValue = (searchInput?.value || '').trim().toLowerCase();
            const typeValue = typeFilter?.value || 'all';
            const fromValue = dateFromInput?.value || '';
            const toValue = dateToInput?.value || '';
            let visibleCount = 0;

            rows.forEach((row) => {
                const haystack = String(row.getAttribute('data-dispatch-search') || '');
                const type = String(row.getAttribute('data-dispatch-type') || '');
                const date = String(row.getAttribute('data-dispatch-date') || '');
                const searchMatch = searchValue === '' || haystack.includes(searchValue);
                const typeMatch = typeValue === 'all' || type === typeValue;
                const fromMatch = fromValue === '' || date >= fromValue;
                const toMatch = toValue === '' || date <= toValue;
                const isVisible = searchMatch && typeMatch && fromMatch && toMatch;

                row.hidden = !isVisible;

                if (isVisible) {
                    visibleCount += 1;
                }
            });

            if (filterSummary) {
                filterSummary.textContent = `${visibleCount} of ${rows.length} dispatches shown`;
            }

            if (filterEmpty) {
                filterEmpty.hidden = visibleCount !== 0;
            }
        }

        filterToggle?.addEventListener('click', () => {
            const isHidden = filterPanel?.hasAttribute('hidden');
            if (isHidden) {
                filterPanel?.removeAttribute('hidden');
            } else {
                filterPanel?.setAttribute('hidden', '');
            }
            filterToggle.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
        });

        filterClear?.addEventListener('click', () => {
            if (typeFilter) { typeFilter.value = 'all'; }
            if (dateFromInput) { dateFromInput.value = ''; }
            if (dateToInput) { dateToInput.value = ''; }
            applyDispatchFilters();
        });

        searchInput?.addEventListener('input', applyDispatchFilters);
        typeFilter?.addEventListener('change', applyDispatchFilters);
        dateFromInput?.addEventListener('change', applyDispatchFilters);
        dateToInput?.addEventListener('change', applyDispatchFilters);
        applyDispatchFilters();

        function buildRow(dispatch) {
            const tr = document.createElement('tr');
            const dispatchedAt = dispatch.dispatched_at ? new Date(dispatch.dispatched_at.replace(' ', 'T')) : null;
            const dateOnly = dispatchedAt && !isNaN(dispatchedAt) ? dispatchedAt.toISOString().slice(0, 10) : '';
            const dateLabel = dispatchedAt && !isNaN(dispatchedAt)
                ? dispatchedAt.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' })
                : '-';
            const search = [dispatch.document_type, dispatch.subject, dispatch.recipient_name, dispatch.recipient_agency, dispatch.dispatched_by]
                .join(' ')
                .toLowerCase();

            tr.setAttribute('data-dispatch-row', '');
            tr.setAttribute('data-dispatch-search', search);
            tr.setAttribute('data-dispatch-type', dispatch.document_type || '');
            tr.setAttribute('data-dispatch-date', dateOnly);

            const recipientHtml = dispatch.recipient_agency
                ? `${escapeHtml(dispatch.recipient_name || '-')}<br><small class="text-muted">${escapeHtml(dispatch.recipient_agency)}</small>`
                : escapeHtml(dispatch.recipient_name || '-');

            const documentHtml = dispatch.has_document
                ? `<a class="btn btn-sm btn-outline-primary" href="document_dispatch_file.php?dispatch_id=${encodeURIComponent(dispatch.dispatch_id)}" target="_blank" rel="noopener">View</a>`
                : '<span class="text-muted">None</span>';

            const deleteHtml = dispatch.can_delete
                ? `<form method="post" onsubmit="return confirm('Delete this dispatch record?');">
                        <input type="hidden" name="admin_action" value="delete_document_dispatch">
                        <input type="hidden" name="dispatch_id" value="${escapeHtml(dispatch.dispatch_id)}">
                        <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                   </form>`
                : '';

            tr.innerHTML = `
                <td>${escapeHtml(dispatch.document_type || 'Letter')}</td>
                <td>${escapeHtml(dispatch.subject || '-')}</td>
                <td>${recipientHtml}</td>
                <td>${escapeHtml(dispatch.dispatched_by || 'Unknown')}</td>
                <td>${dateLabel}</td>
                <td>${documentHtml}</td>
                <td>${deleteHtml}</td>
            `;

            return tr;
        }

        async function refreshDispatches() {
            try {
                const response = await fetch('document_dispatch_feed.php', { cache: 'no-store' });
                if (!response.ok) {
                    throw new Error('Request failed');
                }
                const data = await response.json();
                if (!data.ok || !tableBody) {
                    return;
                }

                tableBody.innerHTML = '';

                if (data.dispatches.length === 0) {
                    const emptyRow = document.createElement('tr');
                    emptyRow.innerHTML = '<td class="text-center text-muted py-4" colspan="7">No document dispatches have been recorded yet.</td>';
                    tableBody.appendChild(emptyRow);
                } else {
                    data.dispatches.forEach((dispatch) => {
                        tableBody.appendChild(buildRow(dispatch));
                    });
                }

                applyDispatchFilters();

                if (liveStatus) {
                    liveStatus.textContent = 'Live \u2014 last updated ' + new Date().toLocaleTimeString();
                }
            } catch (error) {
                if (liveStatus) {
                    liveStatus.textContent = 'Live updates paused \u2014 check your connection.';
                }
            }
        }

        setInterval(refreshDispatches, 15000);
    })();
</script>
<?php require_once __DIR__ . '/admin_shell_end.php'; ?>
