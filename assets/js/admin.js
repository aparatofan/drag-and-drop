/**
 * TBT Drag & Drop — wp-admin exercise builder.
 *
 * The typed gap list, unchanged in behaviour from 1.0.0. Click-to-gap is a
 * front-end interaction and is deliberately not ported here.
 */
(function () {
	'use strict';

	var config = window.TBTDDAdmin || {};
	var strings = config.strings || {};

	function t(key) {
		return typeof strings[key] === 'string' ? strings[key] : '';
	}

	/* ---------- Copy buttons on the list table ---------- */

	document.addEventListener('click', function (event) {
		var button = event.target.closest('[data-tbtdd-copy]');
		if (!button) {
			return;
		}

		event.preventDefault();
		var value = button.getAttribute('data-tbtdd-copy') || '';
		var original = button.textContent;

		var done = function () {
			button.textContent = t('copied');
			window.setTimeout(function () {
				button.textContent = original;
			}, 1600);
		};

		if (window.navigator.clipboard && window.navigator.clipboard.writeText) {
			window.navigator.clipboard.writeText(value).then(done);
		}
	});

	/* ---------- The exercise builder meta box ---------- */

	var wrap = document.querySelector('.dd-gap-admin-wrap');
	if (!wrap) {
		return;
	}

	var itemsContainer = document.getElementById('dd-gap-items');
	var addButton = document.getElementById('dd-gap-add-item');
	var textInput = document.getElementById('dd-gap-text');

	if (!itemsContainer || !addButton || !textInput) {
		return;
	}

	function rows() {
		return Array.prototype.slice.call(itemsContainer.querySelectorAll('.dd-gap-item-row'));
	}

	function values() {
		return rows().map(function (row) {
			return row.querySelector('input').value.trim();
		}).filter(Boolean);
	}

	function createRow(value) {
		var row = document.createElement('div');
		var input = document.createElement('input');
		var remove = document.createElement('button');

		row.className = 'dd-gap-item-row';

		input.type = 'text';
		input.name = 'dd_gap_items[]';
		input.className = 'regular-text dd-gap-item-input';
		input.value = value || '';

		remove.type = 'button';
		remove.className = 'button dd-gap-remove-item';
		remove.textContent = t('remove');

		row.append(input, remove);
		itemsContainer.appendChild(row);
	}

	function validateForm(event) {
		var text = textInput.value;
		var current = values();

		if (!current.length) {
			event.preventDefault();
			window.alert(t('min'));
			return;
		}

		var lowered = current.map(function (value) {
			return value.toLowerCase();
		});

		if (new Set(lowered).size !== lowered.length) {
			event.preventDefault();
			window.alert(t('duplicate'));
			return;
		}

		var missing = current.some(function (value) {
			return text.toLowerCase().indexOf(value.toLowerCase()) === -1;
		});

		if (missing) {
			event.preventDefault();
			window.alert(t('missing'));
		}
	}

	addButton.addEventListener('click', function () {
		if (rows().length >= (parseInt(config.maxItems, 10) || 15)) {
			window.alert(t('limit'));
			return;
		}
		createRow();
	});

	itemsContainer.addEventListener('click', function (event) {
		var button = event.target.closest('.dd-gap-remove-item');
		if (!button) {
			return;
		}

		var row = button.closest('.dd-gap-item-row');
		if (row) {
			row.remove();
		}
	});

	itemsContainer.addEventListener('change', function (event) {
		if (!event.target.classList.contains('dd-gap-item-input')) {
			return;
		}

		var value = event.target.value.trim().toLowerCase();
		if (!value) {
			return;
		}

		var duplicates = values().filter(function (other) {
			return other.toLowerCase() === value;
		});

		if (duplicates.length > 1) {
			window.alert(t('duplicate'));
			event.target.value = '';
			event.target.focus();
		}
	});

	var postForm = document.getElementById('post');
	if (postForm) {
		postForm.addEventListener('submit', validateForm);
	}

	if (!rows().length) {
		createRow();
	}
})();
