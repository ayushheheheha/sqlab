document.addEventListener('DOMContentLoaded', () => {
  const config = window.SQLAB_SOLVE;
  const isSqlMode = (config.subjectSlug || 'sql') === 'sql';
  const editorLanguage = config.editorLanguage || (isSqlMode ? 'sql' : 'python');
  let editor;
  let hintLevel = 1;
  let expectedLoaded = false;
  let submissionsLoaded = false;
  let schemaVisualLoaded = false;
  let lastResult = null;
  let currentSuggestedChart = null;
  let chartInstance = null;
  let chartLibraryPromise = null;
  let fallbackEditor = null;

  const resultsPanel = document.getElementById('tab-results');
  const chartPanel = document.getElementById('tab-chart');
  const expectedPanel = document.getElementById('tab-expected');
  const submissionsPanel = document.getElementById('tab-submissions');
  const executionTime = document.getElementById('executionTime');
  const hintButton = document.getElementById('hintButton');
  const hintBox = document.getElementById('hintBox');
  const schemaModal = document.getElementById('schemaModal');
  const schemaVisualWrap = document.getElementById('schemaVisualWrap');
  const chartType = document.getElementById('chartType');
  const chartMessage = document.getElementById('chartMessage');
  const chartCanvas = document.getElementById('resultChart');
  const chartTabButton = document.getElementById('chartTabButton');
  const runButton = document.getElementById('runQuery');
  const editorHost = document.getElementById('editor');
  const openSchemaButton = document.getElementById('openSchema');
  const stdinInput = document.getElementById('solveStdin');

  if (!isSqlMode) {
    openSchemaButton?.setAttribute('hidden', 'hidden');
    chartTabButton?.setAttribute('hidden', 'hidden');
  }

  const currentTheme = () => document.documentElement.getAttribute('data-theme') === 'dark' ? 'vs-dark' : 'vs';
  const chartColor = () => document.documentElement.getAttribute('data-theme') === 'dark' ? 'rgba(91, 91, 214, 0.7)' : 'rgba(31, 42, 68, 0.7)';
  const chartBorder = () => document.documentElement.getAttribute('data-theme') === 'dark' ? '#5B5BD6' : '#1F2A44';

  const monacoReadyTimeout = setTimeout(() => {
    if (!editor) {
      ensureFallbackEditor('SQL editor loaded in safe mode (Monaco unavailable).');
    }
  }, 2500);

  if (typeof window.require !== 'function') {
    clearTimeout(monacoReadyTimeout);
    ensureFallbackEditor('SQL editor loaded in safe mode (Monaco script blocked).');
  } else {
    try {
      require.config({ paths: { vs: 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs' } });
      require(['vs/editor/editor.main'], () => {
        clearTimeout(monacoReadyTimeout);
        editor = monaco.editor.create(editorHost, {
          value: config.starterQuery,
          language: editorLanguage,
          theme: currentTheme(),
          fontFamily: 'Consolas, "Courier New", monospace',
          fontSize: 14,
          minimap: { enabled: false },
          automaticLayout: true,
          scrollBeyondLastLine: false,
        });

        editor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.Enter, runQuery);
      }, () => {
        clearTimeout(monacoReadyTimeout);
        ensureFallbackEditor('SQL editor loaded in safe mode (Monaco failed to initialize).');
      });
    } catch (_error) {
      clearTimeout(monacoReadyTimeout);
      ensureFallbackEditor('SQL editor loaded in safe mode (Monaco initialization error).');
    }
  }

  new MutationObserver(() => {
    if (window.monaco) {
      monaco.editor.setTheme(currentTheme());
    }

    if (lastResult) {
      renderChart();
    }
  }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });

  document.getElementById('runQuery')?.addEventListener('click', runQuery);
  document.getElementById('resetQuery')?.addEventListener('click', () => setEditorValue(config.starterQuery));
  document.getElementById('openSchema')?.addEventListener('click', () => {
    if (!isSqlMode) {
      return;
    }
    schemaModal.hidden = false;
  });
  document.getElementById('closeSchema')?.addEventListener('click', () => schemaModal.hidden = true);
  chartType?.addEventListener('change', () => renderChart());
  document.querySelectorAll('.sample-stdin-btn').forEach((button) => {
    button.addEventListener('click', () => {
      if (!stdinInput) {
        return;
      }
      stdinInput.value = String(button.dataset.sampleInput || '');
      stdinInput.focus();
    });
  });

  schemaModal?.addEventListener('click', (event) => {
    if (event.target === schemaModal) {
      schemaModal.hidden = true;
    }
  });

  document.addEventListener('keydown', (event) => {
    if ((event.ctrlKey || event.metaKey) && String(event.key).toLowerCase() === 'k') {
      event.preventDefault();
      focusEditor();
      return;
    }

    if (event.key === 'Escape') {
      if (schemaModal && !schemaModal.hidden) {
        schemaModal.hidden = true;
      }
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
      activateTab('.solve-tabs button', '.solve-tab-panel', button.dataset.tab);

      if (button.dataset.tab === 'expected' && !expectedLoaded) {
        expectedLoaded = true;
        expectedPanel.innerHTML = '<div class="empty-state">Loading expected output...</div>';
        renderResultTable(expectedPanel, await getJson(config.endpoints.expected), false);
      }

      if (button.dataset.tab === 'submissions' && !submissionsLoaded) {
        submissionsLoaded = true;
        await loadSubmissions();
      }

      if (button.dataset.tab === 'chart') {
        renderChart();
      }
    });
  });

  document.querySelectorAll('#schemaTabs button').forEach((button) => {
    button.addEventListener('click', async () => {
      activateTab('#schemaTabs button', '.schema-tab-panel', button.dataset.schemaTab, 'schema-tab-');

      if (button.dataset.schemaTab === 'visual' && !schemaVisualLoaded) {
        schemaVisualLoaded = true;
        await loadSchemaVisual();
      }
    });
  });

  async function runQuery() {
    if (runButton?.disabled) {
      return;
    }

    const query = getEditorValue().trim();

    if (!query) {
      resultsPanel.innerHTML = '<div class="solve-flash warning">Write a query before running.</div>';
      return;
    }

    setLoadingState(runButton, true, config.runLabel || 'Run Query');

    try {
      resultsPanel.innerHTML = `<div class="empty-state">${escapeHtml(isSqlMode ? 'Running query...' : 'Running code...')}</div>`;
      const result = await postJson(config.endpoints.execute, {
        problem_id: config.problemId,
        query,
        stdin: isSqlMode ? '' : String(stdinInput?.value || ''),
        test_cases: isSqlMode ? [] : (config.sampleCases || []),
      });
      executionTime.textContent = result.execution_ms ? `${result.execution_ms} ms` : '';
      lastResult = (isSqlMode && result.success && Number(result.row_count) > 0) ? result : null;
      if (chartTabButton) {
        chartTabButton.disabled = !isSqlMode || !(result.success && Number(result.row_count) > 0);
      }
      currentSuggestedChart = result.success ? suggestChartType(result) : null;

      if (!result.success) {
        if (!isSqlMode) {
          renderCodeOutput(result);
        } else {
          resultsPanel.innerHTML = `<div class="solve-flash error">${escapeHtml(result.error || 'Query failed.')}</div>`;
        }
      } else if (result.is_correct) {
        const badgeText = result.badges?.length ? ` Badges: ${result.badges.join(', ')}.` : '';
        const statusPrefix = isSqlMode ? 'Correct!' : 'Accepted!';
        resultsPanel.innerHTML = `<div class="solve-flash correct">${statusPrefix} +${result.xp_awarded || 0} XP awarded.${escapeHtml(badgeText)}</div>`;
        if (isSqlMode) {
          appendTable(resultsPanel, result);
        } else {
          renderCodeOutput(result);
        }
      } else {
        const wrongMessage = isSqlMode
          ? 'Wrong answer - try again.'
          : `Output mismatch - expected output did not match. ${escapeHtml(result.status || '')}`;
        resultsPanel.innerHTML = `<div class="solve-flash warning">${wrongMessage}</div>`;
        if (isSqlMode) {
          appendTable(resultsPanel, result);
        } else {
          renderCodeOutput(result);
        }
      }

      if (isSqlMode && currentSuggestedChart) {
        chartType.value = currentSuggestedChart;
        chartMessage.textContent = `Suggested chart: ${currentSuggestedChart}. You can override it.`;
      } else {
        chartMessage.textContent = isSqlMode ? 'No chart suggestion for this result shape.' : 'Charts are available for SQL result sets only.';
      }

      if (isSqlMode) {
        renderChart();
      }
      submissionsLoaded = false;
    } finally {
      setLoadingState(runButton, false, config.runLabel || 'Run Query');
    }
  }

  async function renderChart() {
    if (!isSqlMode) {
      chartMessage.textContent = 'Charts are available for SQL result sets only.';
      return;
    }

    if (chartInstance) {
      chartInstance.destroy();
      chartInstance = null;
    }

    if (!lastResult || !lastResult.success) {
      chartMessage.textContent = 'Run a query to get a chart suggestion.';
      return;
    }

    const rows = lastResult.rows || [];
    const columns = lastResult.columns || [];

    if (!rows.length || !columns.length) {
      chartMessage.textContent = 'No chart suggestion for this result shape.';
      return;
    }

    const selected = chartType.value || currentSuggestedChart || 'bar';
    const data = chartData(rows, columns, selected);

    if (!data) {
      chartMessage.textContent = 'No chart suggestion for this result shape.';
      return;
    }

    if (typeof window.Chart === 'undefined') {
      await ensureChartLibrary();
    }

    if (typeof window.Chart === 'undefined') {
      chartMessage.textContent = 'Chart.js unavailable; showing offline fallback chart.';
      renderFallbackChart(selected, data);
      return;
    }

    clearFallbackCanvas();
    chartMessage.textContent = '';
    chartInstance = new Chart(chartCanvas.getContext('2d'), {
      type: selected,
      data,
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: selected !== 'bar' } },
      },
    });
  }

  function chartData(rows, columns, type) {
    const numericColumns = columns.filter((column) => rows.every((row) => isNumeric(row[column])));
    const dateColumns = columns.filter((column) => rows.every((row) => isDateLike(row[column])));

    if ((type === 'bar' || type === 'line' || type === 'pie') && columns.length >= 2 && numericColumns.length >= 1) {
      const labelColumn = columns.find((column) => column !== numericColumns[0]) || columns[0];
      const valueColumn = numericColumns[0];
      const dataset = {
        label: valueColumn,
        data: rows.map((row) => Number(row[valueColumn] ?? 0)),
        backgroundColor: chartColor(),
        borderColor: chartBorder(),
        borderWidth: 1.5,
        fill: type !== 'bar' && type !== 'pie',
        tension: 0.2,
      };
      return {
        labels: rows.map((row) => String(row[labelColumn] ?? '')),
        datasets: [dataset],
      };
    }

    if (numericColumns.length === 1) {
      const values = rows.map((row) => Number(row[numericColumns[0]] ?? 0));
      const bucketCount = Math.min(8, Math.max(3, Math.ceil(Math.sqrt(values.length))));
      const min = Math.min(...values);
      const max = Math.max(...values);
      const step = Math.max(1, (max - min) / bucketCount);
      const buckets = new Array(bucketCount).fill(0);
      const labels = buckets.map((_, i) => `${(min + step * i).toFixed(1)}-${(min + step * (i + 1)).toFixed(1)}`);
      values.forEach((value) => {
        const idx = Math.min(bucketCount - 1, Math.floor((value - min) / step));
        buckets[idx] += 1;
      });
      return {
        labels,
        datasets: [{
          label: 'Frequency',
          data: buckets,
          backgroundColor: chartColor(),
          borderColor: chartBorder(),
          borderWidth: 1.5,
        }],
      };
    }

    if (dateColumns.length > 0 && numericColumns.length > 0) {
      const dateColumn = dateColumns[0];
      const valueColumn = numericColumns[0];
      const sorted = [...rows].sort((a, b) => new Date(a[dateColumn]).getTime() - new Date(b[dateColumn]).getTime());
      return {
        labels: sorted.map((row) => String(row[dateColumn] ?? '')),
        datasets: [{
          label: valueColumn,
          data: sorted.map((row) => Number(row[valueColumn] ?? 0)),
          backgroundColor: chartColor(),
          borderColor: chartBorder(),
          borderWidth: 1.5,
          fill: false,
          tension: 0.25,
        }],
      };
    }

    return null;
  }

  function suggestChartType(result) {
    const rows = result.rows || [];
    const columns = result.columns || [];
    if (!rows.length || !columns.length) {
      return null;
    }

    const numericColumns = columns.filter((column) => rows.every((row) => isNumeric(row[column])));
    const dateColumns = columns.filter((column) => rows.every((row) => isDateLike(row[column])));

    if (columns.length === 2 && isNumeric(rows[0][columns[1]])) {
      if (isDateLike(rows[0][columns[0]]) || dateColumns.includes(columns[0])) {
        return 'line';
      }
      return 'bar';
    }

    if (columns.length === 1 && numericColumns.length === 1) {
      return 'bar';
    }

    if (dateColumns.length > 0 && numericColumns.length > 0) {
      return 'line';
    }

    return null;
  }

  function renderFallbackChart(type, data) {
    const ctx = chartCanvas.getContext('2d');

    if (!ctx) {
      chartMessage.textContent = 'Unable to draw chart on this browser.';
      return;
    }

    const dpr = window.devicePixelRatio || 1;
    const width = Math.max(320, chartCanvas.clientWidth || 640);
    const height = Math.max(220, chartCanvas.clientHeight || 300);
    chartCanvas.width = Math.round(width * dpr);
    chartCanvas.height = Math.round(height * dpr);
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, width, height);

    const labels = data.labels || [];
    const values = (data.datasets?.[0]?.data || []).map((value) => Number(value) || 0);

    if (!values.length) {
      return;
    }

    const color = chartBorder();
    ctx.strokeStyle = color;
    ctx.fillStyle = chartColor();
    ctx.lineWidth = 2;
    ctx.font = '12px Inter, sans-serif';

    if (type === 'pie') {
      const sum = values.reduce((acc, value) => acc + value, 0) || 1;
      let start = -Math.PI / 2;
      const cx = width / 2;
      const cy = height / 2;
      const radius = Math.min(width, height) * 0.32;
      values.forEach((value, index) => {
        const angle = (value / sum) * Math.PI * 2;
        const alpha = 0.35 + (index % 5) * 0.12;
        ctx.beginPath();
        ctx.moveTo(cx, cy);
        ctx.fillStyle = colorToAlpha(color, Math.min(0.95, alpha));
        ctx.arc(cx, cy, radius, start, start + angle);
        ctx.closePath();
        ctx.fill();
        start += angle;
      });
      return;
    }

    const max = Math.max(...values, 1);
    const left = 36;
    const right = width - 20;
    const bottom = height - 28;
    const top = 18;
    const innerWidth = right - left;
    const innerHeight = bottom - top;
    const step = innerWidth / Math.max(1, values.length);

    ctx.strokeStyle = colorToAlpha(color, 0.35);
    ctx.beginPath();
    ctx.moveTo(left, top);
    ctx.lineTo(left, bottom);
    ctx.lineTo(right, bottom);
    ctx.stroke();

    if (type === 'line') {
      ctx.strokeStyle = color;
      ctx.fillStyle = colorToAlpha(color, 0.16);
      ctx.beginPath();
      values.forEach((value, index) => {
        const x = left + step * index + step / 2;
        const y = bottom - (value / max) * innerHeight;
        if (index === 0) {
          ctx.moveTo(x, y);
        } else {
          ctx.lineTo(x, y);
        }
      });
      ctx.stroke();
      values.forEach((value, index) => {
        const x = left + step * index + step / 2;
        const y = bottom - (value / max) * innerHeight;
        ctx.beginPath();
        ctx.arc(x, y, 3, 0, Math.PI * 2);
        ctx.fillStyle = color;
        ctx.fill();
        if (labels[index]) {
          ctx.fillStyle = colorToAlpha(color, 0.8);
          ctx.fillText(String(labels[index]).slice(0, 10), x - 14, bottom + 14);
        }
      });
      return;
    }

    ctx.fillStyle = chartColor();
    values.forEach((value, index) => {
      const x = left + step * index + step * 0.18;
      const barWidth = step * 0.64;
      const barHeight = (value / max) * innerHeight;
      const y = bottom - barHeight;
      ctx.fillRect(x, y, barWidth, barHeight);
      if (labels[index]) {
        ctx.fillStyle = colorToAlpha(color, 0.8);
        ctx.fillText(String(labels[index]).slice(0, 10), x, bottom + 14);
        ctx.fillStyle = chartColor();
      }
    });
  }

  function clearFallbackCanvas() {
    const ctx = chartCanvas.getContext('2d');
    if (!ctx) {
      return;
    }
    ctx.clearRect(0, 0, chartCanvas.width || 0, chartCanvas.height || 0);
  }

  function colorToAlpha(hexColor, alpha) {
    const map = {
      '#1F2A44': `rgba(31, 42, 68, ${alpha})`,
      '#5B5BD6': `rgba(91, 91, 214, ${alpha})`,
    };

    return map[hexColor] || `rgba(31, 42, 68, ${alpha})`;
  }

  async function ensureChartLibrary() {
    if (typeof window.Chart !== 'undefined') {
      return true;
    }

    if (!chartLibraryPromise) {
      const localCandidates = [
        config.chartLocalUrl,
        '/assets/js/chart.umd.min.js',
      ];

      chartLibraryPromise = (async () => {
        for (const src of localCandidates) {
          if (!src) {
            continue;
          }
          const loaded = await loadScript(src);
          if (loaded && typeof window.Chart !== 'undefined') {
            return true;
          }
        }
        return false;
      })();
    }

    return chartLibraryPromise;
  }

  function loadScript(src) {
    return new Promise((resolve) => {
      const existing = document.querySelector(`script[data-chart-cdn="${src}"]`);
      if (existing) {
        if (typeof window.Chart !== 'undefined') {
          resolve(true);
          return;
        }
        existing.addEventListener('load', () => resolve(true), { once: true });
        existing.addEventListener('error', () => resolve(false), { once: true });
        return;
      }

      const previousDefine = window.define;
      const hadOwnDefine = Object.prototype.hasOwnProperty.call(window, 'define');
      window.define = undefined;

      const restoreDefine = () => {
        if (hadOwnDefine) {
          window.define = previousDefine;
        } else {
          delete window.define;
        }
      };

      const script = document.createElement('script');
      script.src = src;
      script.async = true;
      script.defer = true;
      script.dataset.chartCdn = src;
      script.onload = () => {
        restoreDefine();
        resolve(true);
      };
      script.onerror = () => {
        restoreDefine();
        resolve(false);
      };
      document.head.appendChild(script);
    });
  }

  async function loadSchemaVisual() {
    if (!config.datasetId) {
      schemaVisualWrap.innerHTML = '<div class="empty-state">No dataset available for visual schema.</div>';
      return;
    }

    schemaVisualWrap.innerHTML = '<div class="empty-state">Loading schema diagram...</div>';
    const response = await getJson(config.endpoints.schemaVisual);

    if (!response.tables || !response.tables.length) {
      schemaVisualWrap.innerHTML = '<div class="empty-state">No schema data available.</div>';
      return;
    }

    const tables = response.tables;
    const cols = tables.length <= 4 ? 2 : 3;
    const boxWidth = 280;
    const rowHeight = 26;
    const headerHeight = 34;
    const gapX = 40;
    const gapY = 30;
    const boxes = [];

    tables.forEach((table, index) => {
      const rowCount = table.columns.length;
      const boxHeight = headerHeight + rowCount * rowHeight;
      const col = index % cols;
      const row = Math.floor(index / cols);
      boxes.push({
        ...table,
        x: col * (boxWidth + gapX) + 20,
        y: row * (Math.max(180, boxHeight) + gapY) + 20,
        width: boxWidth,
        height: boxHeight,
      });
    });

    const width = cols * (boxWidth + gapX) + 40;
    const rowsCount = Math.ceil(tables.length / cols);
    const height = rowsCount * 230 + 40;
    const boxMap = Object.fromEntries(boxes.map((box) => [box.name, box]));

    let svg = `<svg viewBox="0 0 ${width} ${height}" width="${width}" height="${height}" xmlns="http://www.w3.org/2000/svg"><defs><marker id="fkArrow" markerWidth="10" markerHeight="10" refX="8" refY="3" orient="auto"><path d="M0,0 L0,6 L9,3 z" fill="#9b6700"></path></marker></defs>`;

    boxes.forEach((box) => {
      box.columns.forEach((column, index) => {
        if (!column.fk_table || !boxMap[column.fk_table]) {
          return;
        }
        const sourceY = box.y + headerHeight + index * rowHeight + rowHeight / 2;
        const targetTable = boxMap[column.fk_table];
        const targetIndex = Math.max(0, targetTable.columns.findIndex((item) => item.name === column.fk_column));
        const targetY = targetTable.y + headerHeight + targetIndex * rowHeight + rowHeight / 2;
        const sourceX = box.x + box.width;
        const targetX = targetTable.x;
        svg += `<line x1="${sourceX}" y1="${sourceY}" x2="${targetX}" y2="${targetY}" stroke="#9b6700" stroke-width="1.4" marker-end="url(#fkArrow)"></line>`;
      });
    });

    boxes.forEach((box) => {
      svg += `<rect x="${box.x}" y="${box.y}" width="${box.width}" height="${box.height}" rx="10" ry="10" fill="var(--bg-surface)" stroke="var(--border)"></rect>`;
      svg += `<rect x="${box.x}" y="${box.y}" width="${box.width}" height="${headerHeight}" rx="10" ry="10" fill="#1F2A44"></rect>`;
      svg += `<text x="${box.x + 12}" y="${box.y + 22}" fill="#ffffff" font-size="13" font-weight="700">${escapeHtml(box.name)}</text>`;

      box.columns.forEach((column, index) => {
        const y = box.y + headerHeight + index * rowHeight;
        if (index % 2 === 1) {
          svg += `<rect x="${box.x + 1}" y="${y}" width="${box.width - 2}" height="${rowHeight}" fill="rgba(0,0,0,0.03)"></rect>`;
        }
        svg += `<text x="${box.x + 12}" y="${y + 17}" fill="currentColor" font-size="12">${escapeHtml(column.name)} <tspan fill="#777">${escapeHtml(column.type)}</tspan></text>`;
        if (column.is_pk) {
          svg += `<rect x="${box.x + box.width - 74}" y="${y + 6}" width="24" height="14" rx="7" fill="rgba(31,122,67,0.15)"></rect><text x="${box.x + box.width - 67}" y="${y + 16}" fill="#1f7a43" font-size="10">PK</text>`;
        }
        if (column.fk_table) {
          svg += `<rect x="${box.x + box.width - 44}" y="${y + 6}" width="24" height="14" rx="7" fill="rgba(155,103,0,0.15)"></rect><text x="${box.x + box.width - 37}" y="${y + 16}" fill="#9b6700" font-size="10">FK</text>`;
        }
      });
    });

    svg += '</svg>';
    schemaVisualWrap.innerHTML = svg;
  }

  async function loadSubmissions() {
    const response = await getJson(config.endpoints.submissions);
    const rows = response.submissions || [];

    if (!rows.length) {
      submissionsPanel.innerHTML = '<div class="empty-state"><svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path fill="currentColor" d="M6 2h9l5 5v15H6V2Zm8 1.5V8h4.5L14 3.5ZM8 11h10v2H8v-2Zm0 4h10v2H8v-2Z"/></svg><p>No submissions yet - solve a problem to get started.</p></div>';
      return;
    }

    submissionsPanel.innerHTML = `<div class="table-shell"><table><thead><tr><th>Result</th><th>Rows</th><th>Time</th><th>Submitted</th></tr></thead><tbody>${rows.map((row) => `<tr><td>${Number(row.is_correct) === 1 ? 'Correct' : 'Incorrect'}</td><td>${Number(row.row_count)}</td><td>${Number(row.execution_time_ms)} ms</td><td>${escapeHtml(row.submitted_at)}</td></tr>`).join('')}</tbody></table></div>`;
  }

  function renderResultTable(panel, result, includeFlash) {
    if (!panel) {
      return;
    }

    if (!result.success) {
      panel.innerHTML = `<div class="solve-flash error">${escapeHtml(result.error || 'Unable to load output.')}</div>`;
      return;
    }

    panel.innerHTML = includeFlash ? '<div class="solve-flash correct">Loaded.</div>' : '';
    appendTable(panel, result);
  }

  function appendTable(panel, result) {
    if (!panel) {
      return;
    }

    const columns = result.columns || [];
    const rows = result.rows || [];
    const table = document.createElement('div');
    table.className = 'table-shell solve-result-table';
    table.innerHTML = `<table><thead><tr>${columns.map((column) => `<th>${escapeHtml(column)}</th>`).join('')}</tr></thead><tbody>${rows.length ? rows.map((row) => `<tr>${columns.map((column) => `<td>${escapeHtml(row[column] ?? '')}</td>`).join('')}</tr>`).join('') : `<tr><td colspan="${Math.max(1, columns.length)}">No rows returned.</td></tr>`}</tbody></table>`;
    panel.appendChild(table);
  }

  function activateTab(buttonSelector, panelSelector, key, panelPrefix = 'tab-') {
    document.querySelectorAll(buttonSelector).forEach((button) => button.classList.remove('active'));
    document.querySelectorAll(panelSelector).forEach((panel) => panel.classList.remove('active'));
    document.querySelector(`${buttonSelector}[data-tab="${key}"], ${buttonSelector}[data-schema-tab="${key}"]`)?.classList.add('active');
    document.getElementById(`${panelPrefix}${key}`)?.classList.add('active');
  }

  function renderCodeOutput(result) {
    const stdout = String(result.stdout || '').trim();
    const stderr = String(result.stderr || '').trim();
    const compileOutput = String(result.compile_output || '').trim();
    const fallbackOutput = String(result.display_output || '').trim();
    const output = stdout || compileOutput || stderr || fallbackOutput;

    const caseResults = Array.isArray(result.case_results) ? result.case_results : [];
    let html = '';

    if (caseResults.length) {
      html += `<div class="solve-case-results">${caseResults.map((row, index) => `
        <div class="solve-case-result ${row.passed ? 'pass' : 'fail'}">
          <strong>Case ${index + 1}: ${row.passed ? 'Passed' : 'Failed'}</strong>
          <div>Input: ${escapeHtml(row.input || '')}</div>
          <div>Expected: ${escapeHtml(row.expected_output || '')}</div>
          <div>Actual: ${escapeHtml(row.actual_output || '')}</div>
        </div>`).join('')}</div>`;
    }

    if (!output) {
      resultsPanel.innerHTML = `${html}<div class="empty-state">Program finished with no output.</div>`;
      return;
    }

    resultsPanel.innerHTML = `${html}<pre class="code-block">${escapeHtml(output)}</pre>`;
  }

  function ensureFallbackEditor(message) {
    if (!editorHost || fallbackEditor) {
      return;
    }

    editorHost.innerHTML = '';
    fallbackEditor = document.createElement('textarea');
    fallbackEditor.className = 'fallback-sql-editor';
    fallbackEditor.spellcheck = false;
    fallbackEditor.value = config.starterQuery || '';
    fallbackEditor.setAttribute('aria-label', isSqlMode ? 'SQL editor' : 'Code editor');
    editorHost.appendChild(fallbackEditor);

    if (message) {
      resultsPanel.innerHTML = `<div class="solve-flash warning">${escapeHtml(message)}</div>`;
    }
  }

  function getEditorValue() {
    if (editor) {
      return editor.getValue();
    }

    if (fallbackEditor) {
      return fallbackEditor.value;
    }

    return '';
  }

  function setEditorValue(value) {
    if (editor) {
      editor.setValue(value);
      return;
    }

    if (fallbackEditor) {
      fallbackEditor.value = value;
    }
  }

  function focusEditor() {
    if (editor) {
      editor.focus();
      return;
    }

    fallbackEditor?.focus();
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

  function isNumeric(value) {
    return value !== null && value !== '' && !Number.isNaN(Number(value));
  }

  function isDateLike(value) {
    if (value === null || value === '') {
      return false;
    }
    if (/^\d{4}$/.test(String(value))) {
      return true;
    }
    return !Number.isNaN(Date.parse(String(value)));
  }

  function escapeHtml(value) {
    return String(value)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
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
    button.innerHTML = `${escapeHtml(label)} <span class="muted">(Ctrl+Enter)</span>`;
  }
});
