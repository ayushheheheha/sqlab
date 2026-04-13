document.addEventListener('DOMContentLoaded', () => {
  const config = window.SQLAB_SOLVE;
  let editor;
  let hintLevel = 1;
  let expectedLoaded = false;
  let submissionsLoaded = false;

  const resultsPanel = document.getElementById('tab-results');
  const expectedPanel = document.getElementById('tab-expected');
  const submissionsPanel = document.getElementById('tab-submissions');
  const executionTime = document.getElementById('executionTime');
  const hintButton = document.getElementById('hintButton');
  const hintBox = document.getElementById('hintBox');
  const schemaModal = document.getElementById('schemaModal');

  const currentTheme = () => document.documentElement.getAttribute('data-theme') === 'dark' ? 'vs-dark' : 'vs';

  require.config({ paths: { vs: 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs' } });
  require(['vs/editor/editor.main'], () => {
    editor = monaco.editor.create(document.getElementById('editor'), {
      value: config.starterQuery,
      language: 'sql',
      theme: currentTheme(),
      fontFamily: 'Consolas, "Courier New", monospace',
      fontSize: 14,
      minimap: { enabled: false },
      automaticLayout: true,
      scrollBeyondLastLine: false,
    });

    editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.Enter, runQuery);
  });

  new MutationObserver(() => {
    if (window.monaco) {
      monaco.editor.setTheme(currentTheme());
    }
  }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });

  document.getElementById('runQuery')?.addEventListener('click', runQuery);
  document.getElementById('resetQuery')?.addEventListener('click', () => editor?.setValue(config.starterQuery));
  document.getElementById('openSchema')?.addEventListener('click', () => schemaModal.hidden = false);
  document.getElementById('closeSchema')?.addEventListener('click', () => schemaModal.hidden = true);
  schemaModal?.addEventListener('click', (event) => {
    if (event.target === schemaModal) {
      schemaModal.hidden = true;
    }
  });

  hintButton?.addEventListener('click', async () => {
    const response = await postJson(config.endpoints.hint, { problem_id: config.problemId, hint_level: hintLevel });
    hintBox.hidden = false;
    hintBox.textContent = response.hint || response.error || 'No hint available.';
    hintLevel = Math.min(3, hintLevel + 1);
    hintButton.textContent = `Hint ${hintLevel}`;
  });

  document.querySelectorAll('.solve-tabs button').forEach((button) => {
    button.addEventListener('click', async () => {
      document.querySelectorAll('.solve-tabs button').forEach((tab) => tab.classList.remove('active'));
      document.querySelectorAll('.solve-tab-panel').forEach((panel) => panel.classList.remove('active'));
      button.classList.add('active');
      document.getElementById(`tab-${button.dataset.tab}`)?.classList.add('active');

      if (button.dataset.tab === 'expected' && !expectedLoaded) {
        expectedLoaded = true;
        expectedPanel.innerHTML = '<div class="empty-state">Loading expected output...</div>';
        renderResultTable(expectedPanel, await getJson(config.endpoints.expected), false);
      }

      if (button.dataset.tab === 'submissions' && !submissionsLoaded) {
        submissionsLoaded = true;
        await loadSubmissions();
      }
    });
  });

  async function runQuery() {
    if (!editor) {
      return;
    }

    resultsPanel.innerHTML = '<div class="empty-state">Running query...</div>';
    const result = await postJson(config.endpoints.execute, { problem_id: config.problemId, query: editor.getValue() });
    executionTime.textContent = result.execution_ms ? `${result.execution_ms} ms` : '';

    if (!result.success) {
      resultsPanel.innerHTML = `<div class="solve-flash error">${escapeHtml(result.error || 'Query failed.')}</div>`;
    } else if (result.is_correct) {
      const badgeText = result.badges?.length ? ` Badges: ${result.badges.join(', ')}.` : '';
      resultsPanel.innerHTML = `<div class="solve-flash correct">Correct! +${result.xp_awarded || 0} XP awarded.${escapeHtml(badgeText)}</div>`;
      appendTable(resultsPanel, result);
    } else {
      resultsPanel.innerHTML = '<div class="solve-flash warning">Wrong answer - try again.</div>';
      appendTable(resultsPanel, result);
    }

    submissionsLoaded = false;
  }

  async function loadSubmissions() {
    const response = await getJson(config.endpoints.submissions);
    const rows = response.submissions || [];

    if (!rows.length) {
      submissionsPanel.innerHTML = '<div class="empty-state">No submissions yet.</div>';
      return;
    }

    submissionsPanel.innerHTML = `<div class="table-shell"><table><thead><tr><th>Result</th><th>Rows</th><th>Time</th><th>Submitted</th></tr></thead><tbody>${rows.map((row) => `<tr><td>${Number(row.is_correct) === 1 ? 'Correct' : 'Incorrect'}</td><td>${Number(row.row_count)}</td><td>${Number(row.execution_time_ms)} ms</td><td>${escapeHtml(row.submitted_at)}</td></tr>`).join('')}</tbody></table></div>`;
  }

  function renderResultTable(panel, result, includeFlash) {
    if (!result.success) {
      panel.innerHTML = `<div class="solve-flash error">${escapeHtml(result.error || 'Unable to load output.')}</div>`;
      return;
    }

    panel.innerHTML = includeFlash ? '<div class="solve-flash correct">Loaded.</div>' : '';
    appendTable(panel, result);
  }

  function appendTable(panel, result) {
    const columns = result.columns || [];
    const rows = result.rows || [];
    const table = document.createElement('div');
    table.className = 'table-shell solve-result-table';
    table.innerHTML = `<table><thead><tr>${columns.map((column) => `<th>${escapeHtml(column)}</th>`).join('')}</tr></thead><tbody>${rows.length ? rows.map((row) => `<tr>${columns.map((column) => `<td>${escapeHtml(row[column] ?? '')}</td>`).join('')}</tr>`).join('') : `<tr><td colspan="${Math.max(1, columns.length)}">No rows returned.</td></tr>`}</tbody></table>`;
    panel.appendChild(table);
  }

  async function postJson(url, payload) {
    const response = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    return response.json();
  }

  async function getJson(url) {
    const response = await fetch(url);
    return response.json();
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

