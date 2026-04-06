document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('login-form');
  const message = document.getElementById('login-message');

  if (!form) {
    return;
  }

  Madocks.getMe().then((me) => {
    window.location.href = Madocks.roleHome(me.role);
  }).catch(() => { });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    Madocks.setMessage(message, 'Signing you in...', 'info');

    const data = {
      email: document.getElementById('login-email').value.trim(),
      password: document.getElementById('login-password').value
    };

    try {
      const response = await Madocks.api('api/auth/login.php', {
        method: 'POST',
        data
      });

      Madocks.setMessage(message, response.message || 'Login successful', 'success');
      window.setTimeout(() => {
        window.location.href = Madocks.roleHome(response.user.role);
      }, 300);
    } catch (error) {
      Madocks.setMessage(message, error.message, 'error');
    }
  });
});
