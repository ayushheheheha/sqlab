document.addEventListener('DOMContentLoaded', () => {
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const originalFetch = window.fetch.bind(window);
  let pendingFetches = 0;

  const globalSpinner = document.createElement('div');
  globalSpinner.className = 'global-fetch-spinner';
  globalSpinner.hidden = true;
  globalSpinner.innerHTML = '<span class="spinner" aria-hidden="true"></span><span>Loading...</span>';
  document.body.appendChild(globalSpinner);

  window.fetch = async (input, init = {}) => {
    const nextInit = { ...init };
    const headers = new Headers(nextInit.headers || {});
    if (csrfToken && shouldAttachCsrf(input, nextInit) && !headers.has('X-CSRF-Token')) {
      headers.set('X-CSRF-Token', csrfToken);
    }
    nextInit.headers = headers;

    pendingFetches += 1;
    globalSpinner.hidden = false;

    try {
      return await originalFetch(input, nextInit);
    } finally {
      pendingFetches = Math.max(0, pendingFetches - 1);
      if (pendingFetches === 0) {
        globalSpinner.hidden = true;
      }
    }
  };

  window.showToast = (message, type = 'success', duration = 3000) => {
    let stack = document.querySelector('.toast-stack');
    if (!stack) {
      stack = document.createElement('div');
      stack.className = 'toast-stack';
      document.body.appendChild(stack);
    }

    const toast = document.createElement('div');
    toast.className = `flash toast flash-${type === 'error' ? 'error' : (type === 'warning' ? 'warning' : 'success')}`;
    toast.textContent = String(message || '');
    stack.appendChild(toast);

    setTimeout(() => {
      toast.remove();
      if (stack && stack.children.length === 0) {
        stack.remove();
      }
    }, Math.max(800, Number(duration) || 3000));
  };

  const html = document.documentElement;
  const toggle = document.querySelector('[data-theme-toggle]');
  const storedTheme = localStorage.getItem('sqlab-theme');

  const moonIcon = `
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path d="M20 14.5A8.5 8.5 0 1 1 9.5 4 7 7 0 1 0 20 14.5z"></path>
    </svg>
  `;
  const sunIcon = `
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <circle cx="12" cy="12" r="4"></circle>
      <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"></path>
    </svg>
  `;

  function syncThemeToggleIcon() {
    if (!toggle) {
      return;
    }

    const isDark = html.getAttribute('data-theme') === 'dark';
    toggle.innerHTML = isDark ? sunIcon : moonIcon;
    toggle.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
  }

  if (storedTheme === 'dark') {
    html.setAttribute('data-theme', 'dark');
  }

  syncThemeToggleIcon();

  if (toggle) {
    toggle.addEventListener('click', () => {
      const nextTheme = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';

      if (nextTheme === 'dark') {
        html.setAttribute('data-theme', 'dark');
      } else {
        html.removeAttribute('data-theme');
      }

      localStorage.setItem('sqlab-theme', nextTheme);
      syncThemeToggleIcon();
    });
  }

  const hintsInput = document.getElementById('hintsUsed');

  document.querySelectorAll('.hint-toggle').forEach((button) => {
    button.addEventListener('click', () => {
      const targetId = button.getAttribute('data-hint-target');
      const target = targetId ? document.getElementById(targetId) : null;

      if (!target) {
        return;
      }

      const currentlyHidden = target.hasAttribute('hidden');
      target.toggleAttribute('hidden');

      if (currentlyHidden && hintsInput) {
        const nextValue = Math.min(3, Number(hintsInput.value || '0') + 1);
        hintsInput.value = String(nextValue);
      }
    });
  });

  const problemCards = Array.from(document.querySelectorAll('.problem-card'));
  const problemSearch = document.getElementById('problemSearch');
  const problemCategory = document.getElementById('problemCategory');
  const emptyState = document.getElementById('problemEmptyState');
  const filters = { difficulty: 'all', status: 'all' };
  let filterDebounceTimer = null;

  function applyProblemFilters() {
    if (!problemCards.length) {
      return;
    }

    const search = (problemSearch?.value || '').trim().toLowerCase();
    const category = problemCategory?.value || 'all';
    let visibleCount = 0;

    problemCards.forEach((card) => {
      const matches = (!search || card.dataset.title.includes(search))
        && (filters.difficulty === 'all' || card.dataset.difficulty === filters.difficulty)
        && (category === 'all' || card.dataset.category === category)
        && (filters.status === 'all' || card.dataset.status === filters.status);

      card.hidden = !matches;
      visibleCount += matches ? 1 : 0;
    });

    if (emptyState) {
      emptyState.hidden = visibleCount > 0;
    }
  }

  problemSearch?.addEventListener('input', () => {
    if (filterDebounceTimer !== null) {
      window.clearTimeout(filterDebounceTimer);
    }

    filterDebounceTimer = window.setTimeout(() => {
      applyProblemFilters();
      filterDebounceTimer = null;
    }, 120);
  });
  problemCategory?.addEventListener('change', applyProblemFilters);

  document.querySelectorAll('[data-problem-filter]').forEach((group) => {
    group.querySelectorAll('button').forEach((button) => {
      button.addEventListener('click', () => {
        group.querySelectorAll('button').forEach((item) => item.classList.remove('active'));
        button.classList.add('active');
        filters[group.dataset.problemFilter] = button.dataset.value || 'all';
        applyProblemFilters();
      });
    });
  });

  function shouldAttachCsrf(input, init) {
    const method = String(init?.method || 'GET').toUpperCase();

    if (method === 'GET' || method === 'HEAD' || method === 'OPTIONS') {
      return false;
    }

    try {
      const requestUrl = input instanceof Request
        ? new URL(input.url, window.location.origin)
        : new URL(String(input), window.location.origin);

      return requestUrl.origin === window.location.origin;
    } catch (_error) {
      return false;
    }
  }
});
