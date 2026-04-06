document.addEventListener('DOMContentLoaded', async () => {
  const me = await Madocks.requireAuth(['ADMIN']);
  if (!me) return;

  const message = document.getElementById('exposure-message');

  try {
    const response = await Madocks.api('api/admin/exposure.php');
    const data = response.data;

    document.getElementById('exposure-spin-pool').textContent = Madocks.currency(data.spin_pool);
    document.getElementById('exposure-pending').textContent = Madocks.currency(data.pending_liability);
    document.getElementById('exposure-ratio').textContent = `${Madocks.number(data.liability_ratio, 0)}%`;
    document.getElementById('exposure-lambo').textContent = Madocks.currency(data.lambo_fund);
    document.getElementById('exposure-safe-badge').textContent = `${data.liability_status} - ${Madocks.number(data.liability_ratio, 0)}% liability ratio`;
    document.getElementById('exposure-gauge').textContent = `${Madocks.number(data.liability_ratio, 0)}%`;
    document.getElementById('exposure-total-allocated').textContent = Madocks.currency(data.spin_pool);
    document.getElementById('exposure-paid').textContent = Madocks.currency(data.paid_rewards);
    document.getElementById('exposure-expired').textContent = Madocks.currency(data.expired_reallocated);
    document.getElementById('exposure-pending-breakdown').textContent = Madocks.currency(data.pending_liability);
  } catch (error) {
    Madocks.setMessage(message, error.message, 'error');
  }
});
