document.addEventListener('DOMContentLoaded', () => {
  const config = window.SQLAB_LEADERBOARD;

  if (!config) {
    return;
  }

  const toggle = document.getElementById('leaderboardToggle');
  const podium = document.getElementById('leaderboardPodium');
  const rows = document.getElementById('leaderboardRows');

  loadLeaderboard('alltime');

  toggle?.querySelectorAll('button').forEach((button) => {
    button.addEventListener('click', () => {
      toggle.querySelectorAll('button').forEach((item) => item.classList.remove('active'));
      button.classList.add('active');
      loadLeaderboard(button.dataset.period || 'alltime');
    });
  });

  async function loadLeaderboard(period) {
    rows.innerHTML = '<tr><td colspan="6">Loading leaderboard...</td></tr>';
    const response = await fetch(`${config.endpoints.leaderboard}?period=${encodeURIComponent(period)}`);
    const data = await response.json();
    renderPodium(data);
    renderRows(data);
  }

  function renderPodium(data) {
    const top = data.slice(0, 3);
    const order = [1, 0, 2];
    podium.innerHTML = top.length
      ? order.map((positionIndex) => {
        const user = top[positionIndex];
        if (!user) {
          return '';
        }
        const initials = escapeHtml((user.username || '').slice(0, 2).toUpperCase());
        const rankClass = user.rank === 1 ? 'gold' : (user.rank === 2 ? 'silver' : 'bronze');
        return `<article class="podium-item ${rankClass}"><div class="podium-avatar">${initials}</div><strong>#${user.rank} ${escapeHtml(user.username)}</strong><p>${user.xp} XP</p></article>`;
      }).join('')
      : '<div class="card empty-state">No leaderboard data yet.</div>';
  }

  function renderRows(data) {
    if (!data.length) {
      rows.innerHTML = '<tr><td colspan="6">No leaderboard entries yet.</td></tr>';
      return;
    }

    rows.innerHTML = data.map((user, index) => {
      const current = Number(user.id) === Number(config.currentUserId) ? 'current-user-row' : '';
      return `<tr class="${current}"><td>${user.rank}</td><td>${escapeHtml(user.username)}</td><td>${user.solved}</td><td>${user.xp}</td><td>${user.streak}</td><td>${user.badge_count}</td></tr>`;
    }).join('');
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
