(function () {
  function shuffleNodeList(nodes) {
    const arr = [...nodes];
    for (let i = arr.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [arr[i], arr[j]] = [arr[j], arr[i]];
    }
    return arr;
  }

  function initExercise(exercise) {
    const bank = exercise.querySelector('.dd-gap-bank');
    const checkBtn = exercise.querySelector('.dd-gap-check');
    const resetBtn = exercise.querySelector('.dd-gap-reset');
    const showBtn = exercise.querySelector('.dd-gap-show');
    const scoreBox = exercise.querySelector('.dd-gap-score');
    const slots = [...exercise.querySelectorAll('.dd-gap-slot')];
    const answers = JSON.parse(exercise.dataset.answers || '{}');

    let activeToken = null;

    function setTokenDraggable(token) {
      token.addEventListener('dragstart', function (event) {
        activeToken = token;
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', token.dataset.token || token.textContent);
      });
    }

    function attachToken(token) {
      token.classList.remove('is-correct', 'is-wrong');
      setTokenDraggable(token);
    }

    function placeTokenInSlot(slot, token) {
      const existing = slot.querySelector('.dd-gap-token');
      if (existing) {
        bank.appendChild(existing);
      }
      slot.innerHTML = '';
      slot.appendChild(token);
      slot.classList.add('has-token');
    }

    function clearResults() {
      slots.forEach((slot) => {
        slot.classList.remove('is-correct', 'is-wrong');
      });
      exercise.querySelectorAll('.dd-gap-token').forEach((token) => {
        token.classList.remove('is-correct', 'is-wrong');
      });
    }

    function showCorrect() {
      clearResults();

      slots.forEach((slot) => {
        const token = slot.querySelector('.dd-gap-token');
        if (token) {
          bank.appendChild(token);
        }
        slot.innerHTML = '';
        slot.classList.remove('has-token');
      });

      slots.forEach((slot) => {
        const slotId = slot.dataset.slotId;
        const expected = answers[slotId];
        const token = bank.querySelector(`.dd-gap-token[data-token="${CSS.escape(expected)}"]`);
        if (!token) return;

        placeTokenInSlot(slot, token);
        slot.classList.add('is-correct');
        token.classList.add('is-correct');
      });

      showBtn.hidden = true;
    }

    function resetExercise() {
      clearResults();
      scoreBox.hidden = true;
      resetBtn.hidden = true;
      showBtn.hidden = true;
      checkBtn.hidden = false;

      slots.forEach((slot) => {
        const token = slot.querySelector('.dd-gap-token');
        if (token) {
          bank.appendChild(token);
        }
        slot.innerHTML = '';
        slot.classList.remove('has-token');
      });

      const shuffled = shuffleNodeList(bank.querySelectorAll('.dd-gap-token'));
      bank.innerHTML = '';
      shuffled.forEach((token) => bank.appendChild(token));
    }

    bank.querySelectorAll('.dd-gap-token').forEach(attachToken);

    slots.forEach((slot) => {
      slot.addEventListener('dragover', function (event) {
        event.preventDefault();
      });

      slot.addEventListener('drop', function (event) {
        event.preventDefault();

        const fallbackText = event.dataTransfer.getData('text/plain');
        const token = activeToken || bank.querySelector(`.dd-gap-token[data-token="${CSS.escape(fallbackText)}"]`);
        if (!token) return;

        placeTokenInSlot(slot, token);
      });

      slot.addEventListener('click', function () {
        const token = slot.querySelector('.dd-gap-token');
        if (!token) return;
        slot.classList.remove('has-token');
        bank.appendChild(token);
      });
    });

    bank.addEventListener('dragover', function (event) {
      event.preventDefault();
    });

    bank.addEventListener('drop', function (event) {
      event.preventDefault();
      if (!activeToken) return;
      bank.appendChild(activeToken);
      slots.forEach((slot) => {
        if (!slot.querySelector('.dd-gap-token')) {
          slot.classList.remove('has-token');
        }
      });
    });

    checkBtn.addEventListener('click', function () {
      clearResults();
      let correct = 0;
      const total = Object.keys(answers).length;

      slots.forEach((slot) => {
        const token = slot.querySelector('.dd-gap-token');
        const slotId = slot.dataset.slotId;
        const expected = (answers[slotId] || '').trim().toLowerCase();
        const actual = token ? token.textContent.trim().toLowerCase() : '';

        if (actual && actual === expected) {
          correct += 1;
          slot.classList.add('is-correct');
          token.classList.add('is-correct');
        } else {
          slot.classList.add('is-wrong');
          if (token) token.classList.add('is-wrong');
        }
      });

      scoreBox.textContent = `${correct}/${total}`;
      scoreBox.hidden = false;
      checkBtn.hidden = true;
      resetBtn.hidden = false;
      showBtn.hidden = false;
    });

    resetBtn.addEventListener('click', resetExercise);
    showBtn.addEventListener('click', showCorrect);
  }

  document.querySelectorAll('.dd-gap-exercise').forEach(initExercise);
})();
