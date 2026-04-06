document.addEventListener('DOMContentLoaded', async () => {
  const me = await Madocks.requireAuth(['ADMIN']);
  if (!me) return;

  const message = document.getElementById('trade-sync-message');
  const csvForm = document.getElementById('csv-upload-form');
  const manualForm = document.getElementById('manual-trade-form');
  const fileInput = document.getElementById('csv-file');
  const browseButton = document.getElementById('browse-csv-btn');

  browseButton.addEventListener('click', () => fileInput.click());

  async function loadLogs() {
    const response = await Madocks.api('api/trade/logs.php');
    const logs = response.data.logs;
    document.getElementById('sync-log-badge').textContent = logs[0] ? `Last sync: ${Madocks.shortDate(logs[0].created_at)}` : 'No sync yet';
    document.getElementById('sync-log-body').innerHTML = logs.map((log) => `
      <tr>
        <td>${Madocks.escapeHtml(Madocks.shortDate(log.created_at))}</td>
        <td><span class="badge badge-blue">${Madocks.escapeHtml(log.method)}</span></td>
        <td>${Madocks.number(log.trades_processed)}</td>
        <td>${Madocks.number(log.total_lots, 2)}</td>
        <td>${Madocks.number(log.duplicates_count)}</td>
        <td><span class="badge badge-green">${Madocks.escapeHtml(log.status)}</span></td>
      </tr>
    `).join('') || '<tr><td colspan="6">No sync logs yet</td></tr>';
  }

  csvForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!fileInput.files.length) {
      Madocks.setMessage(message, 'Choose a CSV file first.', 'error');
      return;
    }

    const formData = new FormData();
    formData.append('csv', fileInput.files[0]);

    try {
      const response = await Madocks.api('api/trade/sync_csv.php', {
        method: 'POST',
        formData
      });
      Madocks.setMessage(message, `${response.message}. Processed ${response.processed}, duplicates ${response.duplicates}.`, 'success');
      await loadLogs();
    } catch (error) {
      Madocks.setMessage(message, error.message, 'error');
    }
  });

  manualForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const data = Object.fromEntries(new FormData(manualForm).entries());
    try {
      const response = await Madocks.api('api/trade/add_trade.php', {
        method: 'POST',
        data,
        idempotencyKey: `manual-trade-${Date.now()}`
      });
      Madocks.setMessage(message, response.message, response.duplicate ? 'info' : 'success');
      manualForm.reset();
      await loadLogs();
    } catch (error) {
      Madocks.setMessage(message, error.message, 'error');
    }
  });

  loadLogs().catch((error) => Madocks.setMessage(message, error.message, 'error'));
});
