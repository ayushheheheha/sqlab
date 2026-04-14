document.addEventListener('DOMContentLoaded', () => {
  const config = window.SQLAB_ADMIN_PROBLEMS;
  if (!config) return;

  const modal = document.getElementById('problemModal');
  const formContainer = document.getElementById('problemFormContainer');
  const openCreate = document.getElementById('openProblemCreate');
  const closeModal = document.getElementById('closeProblemModal');

  openCreate?.addEventListener('click', () => openProblemForm());
  closeModal?.addEventListener('click', () => {
    modal.hidden = true;
  });
  modal?.addEventListener('click', (event) => {
    if (event.target === modal) {
      modal.hidden = true;
    }
  });

  document.addEventListener('click', async (event) => {
    const editButton = event.target.closest('.js-edit-problem');
    const toggleButton = event.target.closest('.js-toggle-problem');
    const deleteButton = event.target.closest('.js-delete-problem');

    if (editButton) {
      await openProblemForm(Number(editButton.dataset.problemId || 0));
      return;
    }

    if (toggleButton) {
      await postJson(config.endpoints.toggle, { problem_id: Number(toggleButton.dataset.problemId || 0) });
      await refreshProblemsTable();
      return;
    }

    if (deleteButton) {
      if (!window.confirm('Delete this problem? This will deactivate it by default.')) {
        return;
      }
      await postJson(config.endpoints.delete, { problem_id: Number(deleteButton.dataset.problemId || 0) });
      await refreshProblemsTable();
    }
  });

  async function openProblemForm(problemId = 0) {
    modal.hidden = false;
    formContainer.innerHTML = '<div class="empty-state">Loading form...</div>';

    const url = problemId > 0 ? `${config.endpoints.form}?id=${problemId}` : config.endpoints.form;
    const response = await fetch(url);
    const html = await response.text();
    formContainer.innerHTML = html;
    bindProblemForm();
  }

  function bindProblemForm() {
    const form = document.getElementById('adminProblemForm');
    const subjectSelect = document.getElementById('pf_subject');
    const datasetGroup = document.getElementById('pf_dataset_group');
    const datasetSelect = document.getElementById('pf_dataset');
    const expectedLabel = document.getElementById('pf_expected_label');
    const expectedHelp = document.getElementById('pf_expected_help');
    const expectedTestWrap = document.getElementById('pf_expected_test_wrap');
    const testButton = document.getElementById('testExpectedQuery');
    const testResult = document.getElementById('problemTestResult');
    const flash = document.getElementById('problemFormFlash');

    if (!form) return;

    const getSelectedSubjectSlug = () => {
      const selected = subjectSelect?.selectedOptions?.[0];
      return String(selected?.dataset?.subjectSlug || 'sql').toLowerCase();
    };

    const applySubjectMode = () => {
      const isSql = getSelectedSubjectSlug() === 'sql';

      if (datasetGroup) {
        datasetGroup.style.display = isSql ? '' : 'none';
      }

      if (datasetSelect) {
        datasetSelect.required = isSql;
        datasetSelect.disabled = !isSql;
        if (!isSql) {
          datasetSelect.value = '';
        }
      }

      if (expectedLabel) {
        expectedLabel.textContent = isSql ? 'Expected Query' : 'Test Cases';
      }

      if (expectedHelp) {
        expectedHelp.textContent = isSql
          ? 'Provide the exact query used for correctness checks.'
          : 'Use one test case per line: input || expected_output';
      }

      if (expectedTestWrap) {
        expectedTestWrap.hidden = !isSql;
      }

      if (!isSql && testResult) {
        testResult.hidden = true;
        testResult.innerHTML = '';
      }
    };

    applySubjectMode();
    subjectSelect?.addEventListener('change', applySubjectMode);

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const payload = formDataToJson(new FormData(form));
      const response = await postJson(config.endpoints.save, payload);

      if (!response.success) {
        flash.innerHTML = `<div class="flash flash-error">${escapeHtml(response.message || 'Unable to save problem.')}</div>`;
        return;
      }

      flash.innerHTML = `<div class="flash flash-success">${escapeHtml(response.message || 'Problem saved.')}</div>`;
      await refreshProblemsTable();
    });

    testButton?.addEventListener('click', async () => {
      if (getSelectedSubjectSlug() !== 'sql') {
        return;
      }

      const datasetId = Number(form.querySelector('[name="dataset_id"]')?.value || 0);
      const query = String(form.querySelector('[name="expected_query"]')?.value || '').trim();

      if (datasetId <= 0 || query === '') {
        flash.innerHTML = '<div class="flash flash-warning">Select a dataset and add query first.</div>';
        return;
      }

      testResult.hidden = false;
      testResult.innerHTML = '<div class="empty-state">Running test query...</div>';
      const result = await postJson(config.endpoints.executeAdmin, { dataset_id: datasetId, query });

      if (!result.success) {
        testResult.innerHTML = `<div class="flash flash-error">${escapeHtml(result.error || 'Query test failed.')}</div>`;
        return;
      }

      testResult.innerHTML = renderTable(result.columns || [], result.rows || []);
    });
  }

  async function refreshProblemsTable() {
    const response = await fetch(window.location.href, { headers: { 'X-Requested-With': 'fetch' } });
    const html = await response.text();
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');
    const sourceBody = doc.getElementById('adminProblemsTable');
    const targetBody = document.getElementById('adminProblemsTable');
    if (sourceBody && targetBody) {
      targetBody.innerHTML = sourceBody.innerHTML;
    }
  }

  function renderTable(columns, rows) {
    if (!columns.length) {
      return '<div class="empty-state">No rows returned.</div>';
    }
    const head = columns.map((column) => `<th>${escapeHtml(column)}</th>`).join('');
    const body = rows.length
      ? rows.map((row) => `<tr>${columns.map((column) => `<td>${escapeHtml(row[column] ?? '')}</td>`).join('')}</tr>`).join('')
      : `<tr><td colspan="${Math.max(1, columns.length)}">No rows returned.</td></tr>`;
    return `<table><thead><tr>${head}</tr></thead><tbody>${body}</tbody></table>`;
  }

  function formDataToJson(formData) {
    const payload = {};
    for (const [key, value] of formData.entries()) {
      payload[key] = value;
    }
    payload.is_active = formData.get('is_active') ? 1 : 0;
    return payload;
  }

  async function postJson(url, payload) {
    const response = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
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
