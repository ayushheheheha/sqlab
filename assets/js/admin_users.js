document.addEventListener('DOMContentLoaded', () => {
  const config = window.SQLAB_ADMIN_USERS;
  if (!config) return;

  const search = document.getElementById('adminUserSearch');
  const tableRows = () => Array.from(document.querySelectorAll('#adminUsersTable tbody tr'));
  const modal = document.getElementById('adminTempPasswordModal');
  const modalValue = document.getElementById('tempPasswordValue');
  const closeModal = document.getElementById('closeTempPasswordModal');

  search?.addEventListener('input', () => {
    const needle = String(search.value || '').trim().toLowerCase();
    tableRows().forEach((row) => {
      row.hidden = needle !== '' && !(row.dataset.search || '').includes(needle);
    });
  });

  closeModal?.addEventListener('click', () => {
    modal.hidden = true;
  });
  modal?.addEventListener('click', (event) => {
    if (event.target === modal) {
      modal.hidden = true;
    }
  });

  document.addEventListener('click', async (event) => {
    const toggleRole = event.target.closest('.js-toggle-role');
    const resetPassword = event.target.closest('.js-reset-password');
    const deleteUser = event.target.closest('.js-delete-user');

    if (toggleRole) {
      await postJson(config.endpoints.toggleRole, { user_id: Number(toggleRole.dataset.userId || 0) });
      window.location.reload();
      return;
    }

    if (resetPassword) {
      const result = await postJson(config.endpoints.resetPassword, { user_id: Number(resetPassword.dataset.userId || 0) });
      if (result.success) {
        modalValue.textContent = result.temp_password || '';
        modal.hidden = false;
      } else {
        window.showToast?.(result.message || 'Unable to reset password.', 'error');
      }
      return;
    }

    if (deleteUser) {
      if (!window.confirm('Delete this user? This cannot be undone.')) {
        return;
      }
      const result = await postJson(config.endpoints.deleteUser, { user_id: Number(deleteUser.dataset.userId || 0) });
      if (!result.success) {
        window.showToast?.(result.message || 'Unable to delete user.', 'error');
        return;
      }
      window.showToast?.('User deleted.', 'success');
      window.location.reload();
    }
  });

  async function postJson(url, payload) {
    const response = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });
    return response.json();
  }
});
