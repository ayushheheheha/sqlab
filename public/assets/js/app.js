document.addEventListener('DOMContentLoaded', () => {
  const html = document.documentElement;
  const toggle = document.querySelector('[data-theme-toggle]');
  const storedTheme = localStorage.getItem('sqlab-theme');

  if (storedTheme === 'dark') {
    html.setAttribute('data-theme', 'dark');
  }

  if (toggle) {
    toggle.addEventListener('click', () => {
      const nextTheme = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';

      if (nextTheme === 'dark') {
        html.setAttribute('data-theme', 'dark');
      } else {
        html.removeAttribute('data-theme');
      }

      localStorage.setItem('sqlab-theme', nextTheme);
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

  problemSearch?.addEventListener('input', applyProblemFilters);
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
});
