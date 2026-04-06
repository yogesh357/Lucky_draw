document.addEventListener('DOMContentLoaded', async () => {
  const me = await Madocks.requireAuth(['CLIENT', 'IB', 'MIB']);
  if (!me) return;

  const message = document.getElementById('spin-message');
  const resultsBody = document.getElementById('spin-results-body');

  async function loadSpinData() {
    const { data } = await Madocks.api('api/spin/summary.php');

    document.getElementById('spin-badge').textContent = `Spin Balance: ${Madocks.number(data.balance)}`;
    document.getElementById('spin-trade-credits').textContent = Madocks.number(data.balance);
    document.getElementById('spin-expiring').textContent = Madocks.number(data.expiring_spins);
    document.getElementById('jackpot-pool').textContent = Madocks.currency(data.spin_pool || 0);

    resultsBody.innerHTML = data.recent_results
      .map((item) => `
        <tr>
          <td style="color:var(--muted);">${Madocks.escapeHtml(Madocks.shortDate(item.created_at))}</td>
          <td><span class="badge badge-blue">${Madocks.escapeHtml(item.reward_type)}</span></td>
          <td>${item.reward_value ? Madocks.currency(item.reward_value) : item.surewin_points ? `+${Madocks.number(item.surewin_points)} pts` : '-'}</td>
        </tr>
      `).join('') || '<tr><td colspan="3">No spin results yet</td></tr>';
  }

  async function play(spins) {
    Madocks.setMessage(message, `Processing Spin x${spins}...`, 'info');
    try {
      const response = await Madocks.api('api/spin/spin.php', {
        method: 'POST',
        data: { spins },
        idempotencyKey: `spin-${spins}-${Date.now()}`
      });

      const reward = response.data;
      Madocks.setMessage(message, `Result: ${reward.reward_type}${reward.reward_value ? ` (${Madocks.currency(reward.reward_value)})` : reward.surewin_points ? ` (+${reward.surewin_points} pts)` : ''}`, 'success');
      await loadSpinData();
    } catch (error) {
      Madocks.setMessage(message, error.message, 'error');
    }
  }

  document.getElementById('spin-x1-btn').addEventListener('click', () => play(1));
  document.getElementById('spin-x10-btn').addEventListener('click', () => play(10));

  loadSpinData().catch((error) => Madocks.setMessage(message, error.message, 'error'));
});
