document.addEventListener('DOMContentLoaded', async () => {
  const me = await Madocks.requireAuth(['ADMIN']);
  if (!me) return;

  const form = document.getElementById('config-form');
  const message = document.getElementById('config-message');

  async function loadConfig() {
    const response = await Madocks.api('api/admin/config.php');
    Object.entries(response.data).forEach(([key, value]) => {
      const input = document.querySelector(`[name="${key}"]`);
      if (input) {
        input.value = value ?? '';
      }
    });
  }

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const data = Object.fromEntries(new FormData(form).entries());

    try {
      const response = await Madocks.api('api/admin/config.php', {
        method: 'POST',
        data
      });
      Madocks.setMessage(message, response.message, 'success');
    } catch (error) {
      Madocks.setMessage(message, error.message, 'error');
    }
  });

  loadConfig().catch((error) => Madocks.setMessage(message, error.message, 'error'));
});
