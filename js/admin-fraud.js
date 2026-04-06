document.addEventListener('DOMContentLoaded', async () => {
  const me = await Madocks.requireAuth(['ADMIN']);
  if (!me) return;

  const message = document.getElementById('fraud-message');

  try {
    const response = await Madocks.api('api/admin/fraud.php');
    const data = response.data;

    document.getElementById('fraud-open-count').textContent = `${data.flags.length} require review`;
    document.getElementById('fraud-open-list').innerHTML = data.flags.map((flag) => `
      <div class="fraud-flag">
        <div class="fraud-icon">!</div>
        <div class="fraud-id">${Madocks.escapeHtml(flag.email)}</div>
        <div class="fraud-reason">${Madocks.escapeHtml(flag.reason)}</div>
        <div style="display:flex;gap:8px;margin-left:auto;">
          <span class="badge badge-red">${Madocks.escapeHtml(flag.status)}</span>
        </div>
      </div>
    `).join('') || '<div style="color:var(--muted);">No open fraud flags.</div>';

    document.getElementById('fraud-resolved-body').innerHTML = data.excluded_accounts.map((account) => `
      <tr>
        <td>${Madocks.escapeHtml(account.email)}</td>
        <td>Eligibility review</td>
        <td><span class="badge badge-red">${Madocks.escapeHtml(account.status)}</span></td>
        <td>${Madocks.escapeHtml(account.role)}</td>
      </tr>
    `).join('') || '<tr><td colspan="4">No resolved or excluded accounts yet</td></tr>';
  } catch (error) {
    Madocks.setMessage(message, error.message, 'error');
  }
});
