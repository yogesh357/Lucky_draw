document.addEventListener('DOMContentLoaded', async () => {
  const me = await Madocks.requireAuth(['CLIENT', 'IB', 'MIB']);
  if (!me) return;

  const message = document.getElementById('spin-message');
  const resultsBody = document.getElementById('spin-results-body');
  const spinWheel = document.getElementById('spin-wheel');
  const spinX1Button = document.getElementById('spin-x1-btn');
  const spinX10Button = document.getElementById('spin-x10-btn');

  const wheelSegments = [
    { label: 'Near miss', match: (reward) => /near miss/i.test(reward.reward_type) || (reward.surewin_points < 1 && reward.reward_value < 1) },
    { label: '+20 Sure Win Points', match: (reward) => reward.surewin_points > 0 },
    { label: 'Gift Card', match: (reward) => /gift card/i.test(reward.reward_type) },
    { label: 'AirPods Pro', match: (reward) => /airpods/i.test(reward.reward_type) },
    { label: 'iPad', match: (reward) => /ipad/i.test(reward.reward_type) }
  ];

  let currentRotation = 0;
  let isSpinning = false;

  if (spinWheel) {
    spinWheel.style.transform = 'rotate(0deg)';
  }

  function rewardValueText(item) {
    const rewardValue = Number(item.reward_value || 0);
    const surewinPoints = Number(item.surewin_points || 0);

    if (rewardValue > 0) {
      return Madocks.currency(rewardValue);
    }

    if (surewinPoints > 0) {
      return `+${Madocks.number(surewinPoints)} pts`;
    }

    return '-';
  }

  function disableButtons(disabled) {
    spinX1Button.disabled = disabled;
    spinX10Button.disabled = disabled;
  }

  function getSegmentIndex(reward) {
    const normalizedReward = {
      reward_type: String(reward.reward_type || ''),
      reward_value: Number(reward.reward_value || 0),
      surewin_points: Number(reward.surewin_points || 0)
    };

    const index = wheelSegments.findIndex((segment) => segment.match(normalizedReward));
    return index === -1 ? 0 : index;
  }

  function spinWheelToReward(reward) {
    if (!spinWheel) {
      return Promise.resolve();
    }

    const segmentIndex = getSegmentIndex(reward);
    const segmentAngle = 360 / wheelSegments.length;
    const segmentCenter = (segmentIndex * segmentAngle) + (segmentAngle / 2);
    const extraTurns = 6 * 360;
    const jitter = (Math.random() * 16) - 8;
    const targetRotation = currentRotation + extraTurns + (360 - segmentCenter) + jitter;

    spinWheel.classList.add('spinning');

    return new Promise((resolve) => {
      let finished = false;

      const finish = () => {
        if (finished) {
          return;
        }

        finished = true;
        currentRotation = targetRotation % 360;
        spinWheel.style.transform = `rotate(${targetRotation}deg)`;
        spinWheel.classList.remove('spinning');
        spinWheel.removeEventListener('transitionend', onTransitionEnd);
        window.clearTimeout(fallbackTimer);
        resolve();
      };

      const onTransitionEnd = (event) => {
        if (event.target === spinWheel && event.propertyName === 'transform') {
          finish();
        }
      };

      const fallbackTimer = window.setTimeout(finish, 5200);

      spinWheel.addEventListener('transitionend', onTransitionEnd, { once: true });
      requestAnimationFrame(() => {
        spinWheel.style.transform = `rotate(${targetRotation}deg)`;
      });
    });
  }

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
          <td>${rewardValueText(item)}</td>
        </tr>
      `).join('') || '<tr><td colspan="3">No spin results yet</td></tr>';
  }

  async function play(spins) {
    if (isSpinning) {
      return;
    }

    isSpinning = true;
    disableButtons(true);
    Madocks.setMessage(message, `Processing Spin x${spins}...`, 'info');

    try {
      const response = await Madocks.api('api/spin/spin.php', {
        method: 'POST',
        data: { spins },
        idempotencyKey: `spin-${spins}-${Date.now()}`
      });

      const reward = response.data;
      const rewardValue = Number(reward.reward_value || 0);
      const surewinPoints = Number(reward.surewin_points || 0);
      await spinWheelToReward(reward);
      Madocks.setMessage(message, `Result: ${reward.reward_type}${rewardValue > 0 ? ` (${Madocks.currency(rewardValue)})` : surewinPoints > 0 ? ` (+${Madocks.number(surewinPoints)} pts)` : ''}`, 'success');
      await loadSpinData();
    } catch (error) {
      Madocks.setMessage(message, error.message, 'error');
    } finally {
      isSpinning = false;
      disableButtons(false);
    }
  }

  spinX1Button.addEventListener('click', () => play(1));
  spinX10Button.addEventListener('click', () => play(10));

  loadSpinData().catch((error) => Madocks.setMessage(message, error.message, 'error'));
});
