document.addEventListener('DOMContentLoaded', () => {
  const config = window.SQLAB_LEADERBOARD;

  if (!config) {
    return;
  }

  const toggle = document.getElementById('leaderboardToggle');
  const podium = document.getElementById('leaderboardPodium');
  const rows = document.getElementById('leaderboardRows');
  const emptyCard = '<div class="card empty-state"><svg viewBox="0 0 24 24" width="26" height="26" aria-hidden="true"><path fill="currentColor" d="M7 2h10v3h3v4c0 2.2-1.8 4-4 4h-1.2A4.8 4.8 0 0 1 13 15.8V18h4v2H7v-2h4v-2.2A4.8 4.8 0 0 1 9.2 13H8c-2.2 0-4-1.8-4-4V5h3V2Zm10 5v2a2 2 0 0 0 2-2V7h-2ZM5 7v2a2 2 0 0 0 2 2V7H5Z"/></svg><p>No leaderboard data yet.</p></div>';

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
      ? order.map((positionIndex, visualIndex) => {
        const user = top[positionIndex];
        if (!user) {
          return '';
        }
        const initials = escapeHtml((user.username || '').slice(0, 2).toUpperCase());
        const rankClass = user.rank === 1 ? 'gold' : (user.rank === 2 ? 'silver' : 'bronze');
        return `<article class="podium-item ${rankClass} podium-pos-${visualIndex + 1}"><div class="podium-avatar">${initials}</div><strong>#${user.rank} ${escapeHtml(user.username)}</strong><p>${user.xp} XP</p></article>`;
      }).join('')
      : emptyCard;
  }

  function renderRows(data) {
    if (!data.length) {
      rows.innerHTML = '<tr><td colspan="6"><div class="empty-state"><svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path fill="currentColor" d="M3 3h18v2H3V3Zm2 5h14v2H5V8Zm2 5h10v2H7v-2Zm2 5h6v2H9v-2Z"/></svg><p>No leaderboard entries yet.</p></div></td></tr>';
      return;
    }

    rows.innerHTML = data.map((user) => {
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
