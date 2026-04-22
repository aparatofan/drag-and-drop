(function () {
  const wrap = document.querySelector('.dd-gap-admin-wrap');
  if (!wrap) return;

  const itemsContainer = document.getElementById('dd-gap-items');
  const addBtn = document.getElementById('dd-gap-add-item');
  const textInput = document.getElementById('dd-gap-text');

  function getRows() {
    return [...itemsContainer.querySelectorAll('.dd-gap-item-row')];
  }

  function normalizedValues() {
    return getRows()
      .map((row) => row.querySelector('input').value.trim().toLowerCase())
      .filter(Boolean);
  }

  function createRow(value = '') {
    const row = document.createElement('div');
    row.className = 'dd-gap-item-row';

    const input = document.createElement('input');
    input.type = 'text';
    input.name = 'dd_gap_items[]';
    input.className = 'regular-text dd-gap-item-input';
    input.value = value;

    const remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'button dd-gap-remove-item';
    remove.textContent = ddGapAdmin.removeLabel;

    row.append(input, remove);
    itemsContainer.appendChild(row);
  }

  function validateForm(e) {
    const text = textInput.value;
    const rows = getRows();
    const values = rows
      .map((row) => row.querySelector('input').value.trim())
      .filter(Boolean);

    if (!values.length) {
      e.preventDefault();
      alert(ddGapAdmin.minMessage);
      return;
    }

    const lowered = values.map((v) => v.toLowerCase());
    if (new Set(lowered).size !== lowered.length) {
      e.preventDefault();
      alert(ddGapAdmin.duplicateMessage);
      return;
    }

    const missing = values.some((value) => !text.toLowerCase().includes(value.toLowerCase()));
    if (missing) {
      e.preventDefault();
      alert(ddGapAdmin.missingMessage);
    }
  }

  addBtn.addEventListener('click', function () {
    if (getRows().length >= ddGapAdmin.maxItems) {
      alert(ddGapAdmin.limitMessage);
      return;
    }
    createRow();
  });

  itemsContainer.addEventListener('click', function (e) {
    const button = e.target.closest('.dd-gap-remove-item');
    if (!button) return;

    const row = button.closest('.dd-gap-item-row');
    if (row) row.remove();
  });

  itemsContainer.addEventListener('change', function (e) {
    if (!e.target.classList.contains('dd-gap-item-input')) return;
    const value = e.target.value.trim().toLowerCase();
    if (!value) return;

    const duplicates = normalizedValues().filter((v) => v === value);
    if (duplicates.length > 1) {
      alert(ddGapAdmin.duplicateMessage);
      e.target.value = '';
      e.target.focus();
    }
  });

  const postForm = document.getElementById('post');
  if (postForm) {
    postForm.addEventListener('submit', validateForm);
  }

  if (!getRows().length) {
    createRow();
  }
})();
