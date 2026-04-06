document.addEventListener('DOMContentLoaded', async () => {
  const me = await Madocks.requireAuth(['CLIENT', 'IB', 'MIB']);
  if (!me) return;

  const message = document.getElementById('lambo-message');

  try {
    const [balanceResponse, ledgerResponse] = await Promise.all([
      Madocks.api('api/lambo/get_balance.php'),
      Madocks.api('api/lambo/ledger.php')
    ]);

    const data = balanceResponse.data;
    document.getElementById('lambo-badge').textContent = `${Madocks.number(data.progress_percent, 0)}% of target`;
    document.getElementById('lambo-fund-total').textContent = `${Madocks.currency(data.fund_total)} contributed`;
    document.getElementById('lambo-fund-target').textContent = `Target: ${Madocks.currency(data.target_amount)}`;
    document.getElementById('lambo-progress-bar').style.width = `${Math.min(100, data.progress_percent)}%`;
    document.getElementById('lambo-your-balance').textContent = Madocks.currency(data.user_balance);
    document.getElementById('lambo-share').textContent = `${Madocks.number(data.fund_share_percent, 2)}%`;

    document.getElementById('lambo-ledger-body').innerHTML = ledgerResponse.data.entries.map((entry) => `
      <tr>
        <td>${Madocks.escapeHtml(String(entry.created_at).slice(0, 10))}</td>
        <td>${Madocks.escapeHtml(entry.source)}</td>
        <td>${Madocks.escapeHtml(entry.reference_id)}</td>
        <td style="color:var(--green);">${Madocks.currency(entry.amount_usd)}</td>
        <td><span class="badge badge-${entry.type === 'EXPIRED_REALLOC' ? 'gold' : 'blue'}">${Madocks.escapeHtml(entry.type)}</span></td>
      </tr>
    `).join('') || '<tr><td colspan="5">No ledger entries yet</td></tr>';
  } catch (error) {
    Madocks.setMessage(message, error.message, 'error');
  }
});
