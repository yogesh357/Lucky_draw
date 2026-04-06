document.addEventListener('DOMContentLoaded', async () => {
  const me = await Madocks.requireAuth(['CLIENT', 'IB', 'MIB']);
  if (!me) return;

  const message = document.getElementById('surewin-message');

  try {
    const [milestoneResponse, dashboardResponse] = await Promise.all([
      Madocks.api('api/surewin/milestones.php'),
      Madocks.api('api/user/dashboard.php')
    ]);

    const points = milestoneResponse.data.points;
    const milestones = milestoneResponse.data.milestones;
    const next = milestones.find((item) => item.status !== 'unlocked') || milestones[milestones.length - 1];

    document.getElementById('surewin-top-points').textContent = `${Madocks.number(points)} points`;
    document.getElementById('surewin-current-points').textContent = Madocks.number(points);
    document.getElementById('surewin-target-points').textContent = next ? Madocks.number(next.points_required) : Madocks.number(points);
    document.getElementById('surewin-next-title').textContent = next ? `Next: ${next.title}` : 'All milestones unlocked';
    document.getElementById('surewin-remaining').textContent = next ? `${Math.max(0, next.points_required - points)} pts remaining` : '0 pts remaining';
    document.getElementById('surewin-progress-bar').style.width = `${next ? Math.min(100, next.progress_percent) : 100}%`;
    document.getElementById('surewin-month-points').textContent = `+${Madocks.number(dashboardResponse.data.points_month)} pts`;

    document.getElementById('milestones-list').innerHTML = milestones.map((item) => `
      <div class="milestone-item" style="${item.status === 'claimable' ? 'border-color:var(--blue);background:rgba(79,123,255,.04);' : item.status === 'locked' ? 'opacity:.6;' : ''}">
        <div class="milestone-icon ${item.status === 'unlocked' ? 'done' : item.status === 'claimable' ? 'active' : 'locked'}">${item.status === 'unlocked' ? 'OK' : item.status === 'claimable' ? 'GO' : 'LOCK'}</div>
        <div><div style="font-weight:700;">${Madocks.escapeHtml(item.title)}</div><div style="font-size:12px;color:var(--muted);">${Madocks.currency(item.reward_value)} estimated value</div></div>
        <div class="milestone-pts"><span class="badge badge-${item.status === 'unlocked' ? 'green' : item.status === 'claimable' ? 'blue' : 'muted'}">${Madocks.escapeHtml(item.status)}</span><strong>${Madocks.number(item.points_required)} pts</strong></div>
      </div>
    `).join('');
  } catch (error) {
    Madocks.setMessage(message, error.message, 'error');
  }
});
