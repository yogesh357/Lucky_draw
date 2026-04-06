document.addEventListener('DOMContentLoaded', async () => {
  const role = document.body.dataset.role;
  const me = await Madocks.requireAuth([role]);
  if (!me) return;

  const message = document.getElementById('network-message');

  try {
    const response = await Madocks.api('api/user/network_dashboard.php');
    const data = response.data;

    document.getElementById('network-user-email').textContent = me.email;
    document.getElementById('own-lots').textContent = Madocks.number(data.own_lots, 2);
    document.getElementById('network-lots').textContent = Madocks.number(data.network_lots, 2);
    document.getElementById('network-count').textContent = role === 'IB' ? `From your ${Madocks.number(data.network_count)} clients` : `Across ${Madocks.number(data.network_count)} network accounts`;
    document.getElementById('own-lambo').textContent = Madocks.currency(data.own_lambo);
    document.getElementById('network-lambo').textContent = Madocks.currency(data.network_lambo);
    document.getElementById('network-leaders-body').innerHTML = data.leaders.map((item, index) => `
      <tr>
        <td>${index + 1}</td>
        <td>${Madocks.escapeHtml(item.email)}</td>
        <td>${Madocks.number(item.lots, 2)}</td>
        <td><span class="badge badge-blue">Tracked</span></td>
      </tr>
    `).join('') || '<tr><td colspan="4">No network activity yet</td></tr>';
  } catch (error) {
    Madocks.setMessage(message, error.message, 'error');
  }
});
