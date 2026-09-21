
/*   script.js - Library Management System (front-end behaviour)
   Loaded by view.php with "defer", so the DOM is ready when this runs.
   */



(function () {
    'use strict';

    const $  = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

    /* ---------- Tabs ---------- */
    function showTab(name) {
        const tab   = $('.tab[data-tab="' + name + '"]');
        const panel = document.getElementById(name + 'Tab');
        if (!tab || !panel) return;

        $$('.tab').forEach(function (t) {
            const isActive = t === tab;
            t.classList.toggle('active', isActive);
            t.setAttribute('aria-selected', String(isActive));
        });
        $$('.tab-content').forEach(function (p) {
            p.classList.toggle('active', p === panel);
        });

        // Keep the chosen tab in the URL so the refresh doesn't reset it (but don't add a new history entry)
        const url = new URL(window.location.href);
        url.searchParams.set('tab', name);
        window.history.replaceState(null, '', url);
    }

    /* ---------- Modals ---------- */
    function openModal(id) {
        const modal = document.getElementById(id);
        if (!modal) return null;
        modal.classList.add('is-open');
        const firstField = $('input:not([type="hidden"]):not([readonly]), select, textarea', modal);
        if (firstField) firstField.focus();
        return modal;
    }

    function closeModal(modal) {
        if (modal) modal.classList.remove('is-open');
    }

    function closeAllModals() {
        $$('.form-modal.is-open').forEach(closeModal);
    }

    /* ---------- Member details (AJAX) ---------- */
    function el(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined && text !== null) node.textContent = text;
        return node;
    }

    function buildTable(headers, rows, emptyText) {
        if (rows.length === 0) {
            return el('p', 'muted', emptyText);
        }
        const wrap  = el('div', 'table-container');
        const table = el('table');
        const head  = el('tr');
        headers.forEach(function (h) { head.appendChild(el('th', null, h)); });
        const thead = el('thead');
        thead.appendChild(head);
        table.appendChild(thead);

        const tbody = el('tbody');
        rows.forEach(function (cells) {
            const tr = el('tr');
            cells.forEach(function (cell) { tr.appendChild(el('td', null, cell)); });
            tbody.appendChild(tr);
        });
        table.appendChild(tbody);
        wrap.appendChild(table);
        return wrap;
    }

    function renderMember(container, data) {
        const m = data.member;
        container.textContent = '';

        const info = el('dl', 'member-info');
        [
            ['Name',     m.first_name + ' ' + m.last_name],
            ['Email',    m.email || 'N/A'],
            ['Phone',    m.phone || 'N/A'],
            ['Address',  m.address || 'N/A'],
            ['Status',   m.membership_status],
            ['Borrowed', (m.current_borrowed || 0) + '/' + (m.max_borrow_limit || 0)]
        ].forEach(function (pair) {
            info.appendChild(el('dt', null, pair[0]));
            info.appendChild(el('dd', null, pair[1]));
        });
        container.appendChild(info);

        container.appendChild(el('h4', null, 'Recent borrowing history'));
        container.appendChild(buildTable(
            ['Book', 'Borrowed', 'Due', 'Returned', 'Status'],
            data.loans.map(function (l) {
                return [l.title, l.borrow_date, l.due_date, l.return_date, l.status];
            }),
            'No borrowing history.'
        ));

        container.appendChild(el('h4', null, 'Fines'));
        container.appendChild(buildTable(
            ['Fine ID', 'Amount', 'Date', 'Status'],
            data.fines.map(function (f) {
                return [String(f.fine_id), f.amount, f.fine_date, f.status];
            }),
            'No fines.'
        ));
    }

    function viewMember(memberId) {
        const container = document.getElementById('memberDetails');
        if (!container) return;

        container.textContent = 'Loading...';
        openModal('memberModal');

        const url = window.location.pathname + '?ajax=member&id=' + encodeURIComponent(memberId);
        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function (response) {
                return response.json().then(function (json) {
                    if (!response.ok) throw new Error(json.error || 'Request failed.');
                    return json;
                });
            })
            .then(function (data) { renderMember(container, data); })
            .catch(function (err) {
                container.textContent = 'Could not load member details: ' + err.message;
            });
    }

    /* ---------- a click handler for each button ---------- */
    document.addEventListener('click', function (event) {
        const target = event.target.closest(
            '[data-tab], [data-open-modal], [data-close-modal], [data-quick-borrow], ' +
            '[data-return], [data-pay-fine], [data-member], [data-close-alert]'
        );
        if (!target) return;

        if (target.dataset.tab) {
            showTab(target.dataset.tab);

        } else if (target.dataset.openModal) {
            openModal(target.dataset.openModal);

        } else if (target.hasAttribute('data-close-modal')) {
            closeModal(target.closest('.form-modal'));

        } else if (target.dataset.quickBorrow) {
            const select = document.getElementById('borrowBook');
            if (select) select.value = target.dataset.quickBorrow;
            openModal('borrowBookModal');

        } else if (target.dataset.return) {
            const select = document.getElementById('returnBorrow');
            if (select) select.value = target.dataset.return;
            openModal('returnBookModal');

        } else if (target.dataset.payFine) {
            document.getElementById('fineIdInput').value     = target.dataset.payFine;
            document.getElementById('fineAmountInput').value = target.dataset.amount || '';
            openModal('payFineModal');

        } else if (target.dataset.member) {
            viewMember(target.dataset.member);

        } else if (target.hasAttribute('data-close-alert')) {
            const alertBox = target.closest('.alert');
            if (alertBox) alertBox.remove();
        }
    });

    // Close a modal by clicking on the backdrop
    document.addEventListener('mousedown', function (event) {
        if (event.target.classList && event.target.classList.contains('form-modal')) {
            closeModal(event.target);
        }
    });

    // using the escape key to close all modals (if any are open)
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeAllModals();
    });

    // Success messages disappear after 5 seconds (errors stay until closed)
    setTimeout(function () {
        $$('.alert[data-autohide]').forEach(function (a) { a.remove(); });
    }, 5000);
})();