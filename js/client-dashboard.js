document.addEventListener('DOMContentLoaded', async () => {
  const me = await Madocks.requireAuth(['CLIENT']);
  if (!me) return;

  const message = document.getElementById('dashboard-message');

  try {
    const [{ data }, lamboResponse, milestonesResponse] = await Promise.all([
      Madocks.api('api/user/dashboard.php'),
      Madocks.api('api/lambo/get_balance.php'),
      Madocks.api('api/surewin/milestones.php')
    ]);

    const lambo = lamboResponse.data;
    const milestones = milestonesResponse.data.milestones;
    const nextMilestone = milestones.find((item) => item.status !== 'unlocked') || milestones[milestones.length - 1];

    document.getElementById('client-email').textContent = me.email;
    document.getElementById('monthly-lots').textContent = Madocks.number(data.lots, 2);
    document.getElementById('spin-balance').textContent = Madocks.number(data.spins);
    document.getElementById('surewin-points').textContent = Madocks.number(data.points);
    document.getElementById('lambo-progress').textContent = `${Madocks.number(lambo.progress_percent, 0)}%`;
    document.getElementById('lambo-contribution').textContent = `${Madocks.currency(lambo.user_balance)} contributed`;
    document.getElementById('lambo-progress-fill').style.width = `${Math.min(100, lambo.progress_percent)}%`;
    document.getElementById('surewin-next').textContent = nextMilestone ? `Next milestone: ${nextMilestone.points_required}` : 'All milestones unlocked';
    document.getElementById('surewin-progress-fill').style.width = `${nextMilestone ? Math.min(100, nextMilestone.progress_percent) : 100}%`;
    document.getElementById('expiring-spins-alert').textContent = Madocks.number(data.expiring_spins);
    document.getElementById('expiring-spins-card').textContent = Madocks.number(data.expiring_spins);

    const activityBody = document.getElementById('activity-history-body');
    activityBody.innerHTML = data.recent_activity.length ? data.recent_activity.map((item) => `
      <tr>
        <td>${Madocks.escapeHtml(Madocks.shortDate(item.created_at))}</td>
        <td><span class="badge badge-${item.entry_type === 'TRADE' ? 'blue' : item.entry_type === 'SPIN' ? 'green' : 'gold'}">${Madocks.escapeHtml(item.entry_type)}</span></td>
        <td>${Madocks.escapeHtml(item.detail || '-')}</td>
        <td>${item.metric_b ? `+${Madocks.number(item.metric_b)} pts` : item.metric_a ? Madocks.currency(item.metric_a) : '-'}</td>
      </tr>
    `).join('') : '<tr><td colspan="4">No activity yet</td></tr>';
  } catch (error) {
    Madocks.setMessage(message, error.message, 'error');
  }
});
