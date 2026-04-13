document.addEventListener('DOMContentLoaded', () => {
  const config = window.SQLAB_ADMIN_DATASETS;
  if (!config) return;

  const form = document.getElementById('datasetForm');
  const formTitle = document.getElementById('datasetFormTitle');
  const flash = document.getElementById('datasetFormFlash');
  const resetButton = document.getElementById('resetDatasetForm');
  const modal = document.getElementById('datasetSchemaModal');
  const modalTitle = document.getElementById('datasetSchemaTitle');
  const modalCode = document.getElementById('datasetSchemaCode');
  const closeModal = document.getElementById('closeDatasetSchemaModal');
  const openCreate = document.getElementById('openDatasetCreate');

  openCreate?.addEventListener('click', () => resetForm());
  resetButton?.addEventListener('click', () => resetForm());
  closeModal?.addEventListener('click', () => {
    modal.hidden = true;
  });
  modal?.addEventListener('click', (event) => {
    if (event.target === modal) {
      modal.hidden = true;
    }
  });

  form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const payload = formDataToJson(new FormData(form));
    const result = await postJson(config.endpoints.save, payload);
    if (!result.success) {
      flash.innerHTML = `<div class="flash flash-error">${escapeHtml(result.message || 'Unable to save dataset.')}</div>`;
      return;
    }

    flash.innerHTML = `<div class="flash flash-success">${escapeHtml(result.message || 'Dataset saved.')}</div>`;
    window.location.reload();
  });

  document.addEventListener('click', async (event) => {
    const row = event.target.closest('tr[data-dataset-id]');
    if (!row) return;

    if (event.target.closest('.js-view-schema')) {
      modalTitle.textContent = `${row.dataset.name || 'Dataset'} Schema`;
      modalCode.textContent = row.dataset.schema || '';
      modal.hidden = false;
      return;
    }

    if (event.target.closest('.js-edit-dataset')) {
      formTitle.textContent = 'Edit Dataset';
      form.querySelector('#datasetId').value = row.dataset.datasetId || '0';
      form.querySelector('#datasetName').value = row.dataset.name || '';
      form.querySelector('#datasetDescription').value = row.dataset.description || '';
      form.querySelector('#datasetSchemaSql').value = row.dataset.schema || '';
      form.querySelector('#datasetSeedSql').value = row.dataset.seed || '';
      flash.innerHTML = '';
      window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
      return;
    }

    if (event.target.closest('.js-delete-dataset')) {
      if (!window.confirm('Delete this dataset? Problems linked to it will lose mapping.')) {
        return;
      }
      const result = await postJson(config.endpoints.delete, { dataset_id: Number(row.dataset.datasetId || 0) });
      if (!result.success) {
        window.showToast?.(result.message || 'Unable to delete dataset.', 'error');
        return;
      }
      window.showToast?.('Dataset deleted.', 'success');
      window.location.reload();
    }
  });

  function resetForm() {
    formTitle.textContent = 'Add Dataset';
    form?.reset();
    const idField = document.getElementById('datasetId');
    if (idField) {
      idField.value = '0';
    }
    flash.innerHTML = '';
  }

  function formDataToJson(formData) {
    const payload = {};
    for (const [key, value] of formData.entries()) {
      payload[key] = value;
    }
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
