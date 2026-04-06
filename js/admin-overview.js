document.addEventListener('DOMContentLoaded', async () => {
  const me = await Madocks.requireAuth(['ADMIN']);
  if (!me) return;

  const message = document.getElementById('overview-message');

  try {
    const response = await Madocks.api('api/admin/overview.php');
    const data = response.data;

    document.getElementById('overview-system-health').textContent = data.system_health;
    document.getElementById('overview-total-users').textContent = Madocks.number(data.total_users);
    document.getElementById('overview-monthly-lots').textContent = Madocks.number(data.monthly_lots, 2);
    document.getElementById('overview-spin-pool').textContent = Madocks.currency(data.spin_pool);
    document.getElementById('overview-lambo-fund').textContent = Madocks.currency(data.lambo_fund);
    document.getElementById('overview-liability-badge').textContent = data.liability_ratio >= 70 ? 'Warning' : 'Safe';
    document.getElementById('overview-liability-ratio').textContent = `${Madocks.number(data.liability_ratio, 0)}%`;
    document.getElementById('overview-pending-rewards').textContent = Madocks.currency(data.pending_liability);
    document.getElementById('overview-spin-allocated').textContent = Madocks.currency(data.spin_pool);
    document.getElementById('overview-open-flags-link').textContent = `Review Fraud Flags (${data.open_flags} open)`;
  } catch (error) {
    Madocks.setMessage(message, error.message, 'error');
  }
});
