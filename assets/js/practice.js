document.addEventListener('DOMContentLoaded', () => {
  const config = window.SQLAB_PRACTICE;
  if (!config) {
    return;
  }

  const resultsPanel = document.getElementById('practice-tab-results');
  const logsPanel = document.getElementById('practice-tab-logs');
  const runButton = document.getElementById('runPracticeQuery');
  const resetButton = document.getElementById('resetPracticeQuery');
  const resetDbButton = document.getElementById('resetPracticeDb');
  const executionTime = document.getElementById('practiceExecutionTime');
  const MAX_RESULT_ENTRIES = 40;

  let editor;

  require.config({ paths: { vs: 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs' } });
  require(['vs/editor/editor.main'], () => {
    const currentTheme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'vs-dark' : 'vs';
    editor = monaco.editor.create(document.getElementById('practiceEditor'), {
      value: config.starterQuery,
      language: 'sql',
      theme: currentTheme,
      fontFamily: 'Consolas, "Courier New", monospace',
      fontSize: 14,
      minimap: { enabled: false },
      automaticLayout: true,
      scrollBeyondLastLine: false,
    });

    editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.Enter, runSql);
    editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyK, () => editor.focus());
  });

  new MutationObserver(() => {
    if (window.monaco) {
      const nextTheme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'vs-dark' : 'vs';
      monaco.editor.setTheme(nextTheme);
    }
  }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });

  runButton?.addEventListener('click', runSql);
  resetButton?.addEventListener('click', () => editor?.setValue(config.starterQuery));
  resetDbButton?.addEventListener('click', resetDatabase);

  document.querySelectorAll('.solve-tabs button').forEach((button) => {
    button.addEventListener('click', () => {
      document.querySelectorAll('.solve-tabs button').forEach((item) => item.classList.remove('active'));
      button.classList.add('active');
      document.querySelectorAll('.solve-tab-panel').forEach((panel) => panel.classList.remove('active'));
      document.getElementById(`practice-tab-${button.dataset.tab}`)?.classList.add('active');
    });
  });

  document.addEventListener('keydown', (event) => {
    if ((event.ctrlKey || event.metaKey) && String(event.key).toLowerCase() === 'k') {
      event.preventDefault();
      editor?.focus();
    }
  });

  async function runSql() {
    if (!editor || runButton?.disabled) {
      return;
    }

    setLoadingState(runButton, true, 'Run SQL');
    try {
      const query = editor.getValue();
      const result = await postJson(config.endpoints.execute, { query });
      executionTime.textContent = result.execution_ms ? `${result.execution_ms} ms` : '';

      if (!result.success) {
        appendResultEntry(
          `<div class="solve-flash error">${escapeHtml(result.error || 'Execution failed.')}</div>`,
          query,
          String(result.statement_type || 'SQL')
        );
        appendLog(result.error || 'Execution failed.', 'error', query);
        return;
      }

      if ((result.columns || []).length > 0) {
        appendResultEntry(
          renderTableHtml(result.columns || [], result.rows || []),
          query,
          String(result.statement_type || 'SELECT')
        );
      } else {
        appendResultEntry(
          renderMutationOutputHtml(result),
          query,
          String(result.statement_type || 'SQL')
        );
      }

      appendLog(result.message || `Completed in ${result.execution_ms || 0} ms.`, 'success', query, result.statement_type);
      editor.setValue('');
      editor.focus();
    } finally {
      setLoadingState(runButton, false, 'Run SQL');
    }
  }

  async function resetDatabase() {
    if (resetDbButton?.disabled) {
      return;
    }

    setLoadingState(resetDbButton, true, 'Reset Database');

    try {
      const result = await postJson(config.endpoints.reset, {});
      if (!result.success) {
        appendLog(result.error || 'Could not reset database.', 'error', 'RESET DATABASE');
        window.showToast?.(result.error || 'Could not reset database.', 'error');
        return;
      }

      appendResultEntry(
        '<div class="solve-flash warning">Database reset. Run SQL to create your own schema.</div>',
        'RESET DATABASE',
        'SYSTEM'
      );
      appendLog(result.message || 'Database reset.', 'warning', 'RESET DATABASE');
      window.showToast?.(result.message || 'Database reset.', 'warning');
    } finally {
      setLoadingState(resetDbButton, false, 'Reset Database');
    }
  }

  function renderTableHtml(columns, rows) {
    const head = columns.map((column) => `<th>${escapeHtml(column)}</th>`).join('');
    const body = rows.length
      ? rows.map((row) => `<tr>${columns.map((column) => `<td>${escapeHtml(row[column] ?? '')}</td>`).join('')}</tr>`).join('')
      : `<tr><td colspan="${Math.max(1, columns.length)}">No rows returned.</td></tr>`;

    return `<div class="table-shell solve-result-table"><table><thead><tr>${head}</tr></thead><tbody>${body}</tbody></table></div>`;
  }

  function renderMutationOutputHtml(result) {
    const tables = Array.isArray(result.tables) ? result.tables : [];
    const columns = Array.isArray(result.table_columns) ? result.table_columns : [];
    const preview = result.table_preview || null;

    let html = `<div class="solve-flash correct">${escapeHtml(result.message || 'Statement executed successfully.')}</div>`;
    html += `<div class="card" style="padding:14px; margin-top:10px;"><strong>Execution Summary</strong><p class="muted" style="margin-top:6px;">Statement: ${escapeHtml(result.statement_type || 'SQL')} · Affected rows: ${Number(result.affected_rows || 0)}</p></div>`;

    if (tables.length) {
      html += `<div class="card" style="padding:14px; margin-top:10px;"><strong>Current Tables</strong><div style="display:flex; flex-wrap:wrap; gap:8px; margin-top:10px;">${tables.map((table) => `<span class="badge badge-muted">${escapeHtml(table)}</span>`).join('')}</div></div>`;
    }

    if (columns.length) {
      html += `<div class="card" style="padding:14px; margin-top:10px;"><strong>Table Structure${result.target_table ? `: ${escapeHtml(result.target_table)}` : ''}</strong><div class="table-shell" style="margin-top:10px;"><table><thead><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr></thead><tbody>${columns.map((col) => `<tr><td>${escapeHtml(col.field || '')}</td><td>${escapeHtml(col.type || '')}</td><td>${escapeHtml(col.null || '')}</td><td>${escapeHtml(col.key || '')}</td></tr>`).join('')}</tbody></table></div></div>`;
    }

    if (preview && Array.isArray(preview.columns) && Array.isArray(preview.rows)) {
      const head = preview.columns.map((column) => `<th>${escapeHtml(column)}</th>`).join('');
      const body = preview.rows.length
        ? preview.rows.map((row) => `<tr>${preview.columns.map((column) => `<td>${escapeHtml(row[column] ?? '')}</td>`).join('')}</tr>`).join('')
        : `<tr><td colspan="${Math.max(1, preview.columns.length)}">No rows in table.</td></tr>`;

      html += `<div class="card" style="padding:14px; margin-top:10px;"><strong>Data Preview${preview.table ? `: ${escapeHtml(preview.table)}` : ''}</strong><div class="table-shell" style="margin-top:10px;"><table><thead><tr>${head}</tr></thead><tbody>${body}</tbody></table></div></div>`;
    }

    return html;
  }

  function appendResultEntry(contentHtml, query, statementType = 'SQL') {
    if (!resultsPanel) {
      return;
    }

    if (resultsPanel.querySelector('.empty-state')) {
      resultsPanel.innerHTML = '';
    }

    const now = new Date();
    const entry = document.createElement('article');
    entry.className = 'card';
    entry.style.marginTop = '10px';
    entry.innerHTML = `
      <div style="display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:8px;">
        <span class="badge badge-muted">${escapeHtml(statementType || 'SQL')}</span>
        <span class="muted" style="font-size:12px;">${escapeHtml(now.toLocaleTimeString())}</span>
      </div>
      ${contentHtml}
    `;

    resultsPanel.prepend(entry);

    while (resultsPanel.children.length > MAX_RESULT_ENTRIES) {
      resultsPanel.removeChild(resultsPanel.lastElementChild);
    }
  }

  function appendLog(message, type, query = '', statementType = '') {
    const tone = type === 'error' ? 'flash-error' : (type === 'warning' ? 'flash-warning' : 'flash-success');
    const log = document.createElement('div');
    log.className = `flash ${tone}`;
    const now = new Date();
    const queryLine = String(query || '').replace(/\s+/g, ' ').trim();
    const head = statementType ? `[${statementType}]` : '[SQL]';
    log.textContent = `${now.toLocaleTimeString()} ${head} ${String(message || 'Done')}${queryLine ? `\n${queryLine}` : ''}`;
    log.style.whiteSpace = 'pre-wrap';

    if (logsPanel.querySelector('.empty-state')) {
      logsPanel.innerHTML = '';
    }

    logsPanel.prepend(log);
  }

  async function postJson(url, payload) {
    const response = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    return response.json();
  }

  function setLoadingState(button, isLoading, label) {
    if (!button) {
      return;
    }

    if (isLoading) {
      button.disabled = true;
      button.innerHTML = `<span class="spinner" aria-hidden="true"></span>${escapeHtml(label)}...`;
      return;
    }

    button.disabled = false;
    button.innerHTML = label;
  }

  function escapeHtml(value) {
    return String(value)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }
});
