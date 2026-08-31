/**
 * TBT Drag & Drop — the player.
 *
 * Plain ES2018, no build step and no framework, matching the rest of the
 * plugin. The answers arrive in a JSON config block rather than on data-
 * attributes of the container, so there is one place to read them from.
 *
 * Desktop only, by decision: HTML5 drag plus click-to-place. No touch events.
 */
(function () {
	'use strict';

	var config = window.TBTDDGame || {};
	var strings = config.strings || {};

	function t(key) {
		return typeof strings[key] === 'string' ? strings[key] : '';
	}

	function sprintf(template, values) {
		var index = 0;
		return String(template).replace(/%(\d+\$)?[ds]/g, function (match, position) {
			var pick = position ? parseInt(position, 10) - 1 : index++;
			return typeof values[pick] === 'undefined' ? match : String(values[pick]);
		});
	}

	function normalise(value) {
		return String(value === null || typeof value === 'undefined' ? '' : value).trim().toLowerCase();
	}

	function readConfig(root) {
		var block = root.querySelector('.tbtdd-config');
		if (!block) {
			return null;
		}

		try {
			return JSON.parse(block.textContent);
		} catch (error) {
			return null;
		}
	}

	function shuffle(list) {
		for (var i = list.length - 1; i > 0; i--) {
			var j = Math.floor(Math.random() * (i + 1));
			var swap = list[i];
			list[i] = list[j];
			list[j] = swap;
		}
		return list;
	}

	function initExercise(root) {
		var settings = readConfig(root);
		if (!settings || !settings.answers) {
			return;
		}

		var answers = settings.answers;
		var bank = root.querySelector('[data-tbtdd-bank]');
		var live = root.querySelector('[data-tbtdd-live]');
		var checkButton = root.querySelector('[data-tbtdd-check]');
		var showButton = root.querySelector('[data-tbtdd-show]');
		var redoButton = root.querySelector('[data-tbtdd-redo]');
		var scoreBox = root.querySelector('[data-tbtdd-score]');
		var slots = Array.prototype.slice.call(root.querySelectorAll('.tbtdd-slot'));
		var tokens = Array.prototype.slice.call(root.querySelectorAll('.tbtdd-token'));

		if (!bank || !slots.length) {
			return;
		}

		var picked = null;

		function announce(message) {
			if (live && message) {
				live.textContent = message;
			}
		}

		function slotNumber(slot) {
			return slots.indexOf(slot) + 1;
		}

		/**
		 * Keep a slot's accessible name describing what is actually in it.
		 * Without this a screen reader keeps reading "empty" at a filled gap.
		 */
		function describeSlot(slot) {
			var token = slot.querySelector('.tbtdd-token');
			slot.setAttribute(
				'aria-label',
				token
					? sprintf(t('filledSlot'), [slotNumber(slot), token.dataset.tbtddToken])
					: sprintf(t('emptySlot'), [slotNumber(slot)])
			);
		}

		function clearPicked() {
			if (picked) {
				picked.classList.remove('is-picked');
				picked = null;
			}
		}

		/**
		 * Back into the bank at its own letter's position rather than at the
		 * end: the bank has to keep reading A, B, C … with holes where words
		 * are in use, or the letters scatter as soon as one word comes back.
		 */
		function returnToBank(token, silent) {
			var slot = token.parentElement;
			var letter = token.getAttribute('data-tbtdd-letter') || '';
			var siblings = Array.prototype.slice.call(bank.querySelectorAll('.tbtdd-token'));
			var next = null;

			for (var i = 0; i < siblings.length; i++) {
				if ((siblings[i].getAttribute('data-tbtdd-letter') || '') > letter) {
					next = siblings[i];
					break;
				}
			}

			bank.insertBefore(token, next); // insertBefore(node, null) appends.
			token.classList.remove('is-correct', 'is-wrong', 'is-picked');

			if (slot && slot.classList.contains('tbtdd-slot')) {
				slot.classList.remove('is-filled', 'is-correct', 'is-wrong');
				describeSlot(slot);
			}

			if (!silent) {
				announce(sprintf(t('returned'), [token.dataset.tbtddToken]));
			}
		}

		function place(slot, token) {
			// Dropping onto an occupied slot sends the displaced token back to
			// the bank rather than losing it.
			var existing = slot.querySelector('.tbtdd-token');
			if (existing === token) {
				clearPicked();
				return;
			}
			if (existing) {
				returnToBank(existing, true);
			}

			slot.appendChild(token);
			slot.classList.add('is-filled');
			slot.classList.remove('is-correct', 'is-wrong');
			token.classList.remove('is-picked', 'is-correct', 'is-wrong');
			describeSlot(slot);
			clearPicked();
			announce(sprintf(t('placed'), [token.dataset.tbtddToken]));
		}

		function pick(token) {
			if (picked === token) {
				clearPicked();
				return;
			}

			clearPicked();
			picked = token;
			token.classList.add('is-picked');
			announce(sprintf(t('picked'), [token.dataset.tbtddToken]));
		}

		function activateToken(token) {
			// A token already in a slot is on its way out, not on its way in.
			if (token.parentElement && token.parentElement.classList.contains('tbtdd-slot')) {
				returnToBank(token);
				return;
			}

			pick(token);
		}

		function activateSlot(slot) {
			if (picked) {
				place(slot, picked);
				return;
			}

			var token = slot.querySelector('.tbtdd-token');
			if (token) {
				returnToBank(token);
			}
		}

		tokens.forEach(function (token) {
			token.addEventListener('dragstart', function (event) {
				picked = token;
				token.classList.add('is-dragging');
				if (event.dataTransfer) {
					event.dataTransfer.effectAllowed = 'move';
					event.dataTransfer.setData('text/plain', token.dataset.tbtddToken || token.textContent);
				}
			});

			token.addEventListener('dragend', function () {
				token.classList.remove('is-dragging');
			});

			token.addEventListener('click', function (event) {
				event.stopPropagation();
				activateToken(token);
			});
		});

		slots.forEach(function (slot) {
			describeSlot(slot);

			slot.addEventListener('dragover', function (event) {
				event.preventDefault();
				if (event.dataTransfer) {
					event.dataTransfer.dropEffect = 'move';
				}
			});

			slot.addEventListener('dragenter', function () {
				slot.classList.add('is-over');
			});

			slot.addEventListener('dragleave', function (event) {
				// dragleave also fires when the pointer crosses onto a child,
				// which would flicker the highlight off under the cursor.
				if (!slot.contains(event.relatedTarget)) {
					slot.classList.remove('is-over');
				}
			});

			slot.addEventListener('drop', function (event) {
				event.preventDefault();
				slot.classList.remove('is-over');
				if (picked) {
					place(slot, picked);
				}
			});

			slot.addEventListener('click', function () {
				activateSlot(slot);
			});

			slot.addEventListener('keydown', function (event) {
				if (event.key === 'Enter' || event.key === ' ' || event.key === 'Spacebar') {
					event.preventDefault();
					activateSlot(slot);
				}
			});
		});

		// Dragging a token back out of a slot and onto the bank.
		bank.addEventListener('dragover', function (event) {
			event.preventDefault();
		});

		bank.addEventListener('drop', function (event) {
			event.preventDefault();
			if (picked) {
				returnToBank(picked);
				clearPicked();
			}
		});

		function clearMarks() {
			slots.forEach(function (slot) {
				slot.classList.remove('is-correct', 'is-wrong');
			});
			root.querySelectorAll('.tbtdd-token').forEach(function (token) {
				token.classList.remove('is-correct', 'is-wrong');
			});
		}

		function check() {
			clearMarks();

			var correct = 0;
			slots.forEach(function (slot) {
				var token = slot.querySelector('.tbtdd-token');
				var expected = answers[slot.dataset.slot];
				var ok = !!token && normalise(token.dataset.tbtddToken) === normalise(expected);

				slot.classList.add(ok ? 'is-correct' : 'is-wrong');
				if (token) {
					token.classList.add(ok ? 'is-correct' : 'is-wrong');
				}
				if (ok) {
					correct += 1;
				}
			});

			if (scoreBox) {
				scoreBox.textContent = sprintf(t('score'), [correct, slots.length]);
				scoreBox.classList.toggle('is-perfect', correct === slots.length);
				scoreBox.hidden = false;
			}

			checkButton.hidden = true;
			if (showButton) {
				showButton.hidden = false;
			}
			if (redoButton) {
				redoButton.hidden = false;
			}

			announce(sprintf(t('checked'), [correct, slots.length]));
		}

		function emptyAllSlots() {
			slots.forEach(function (slot) {
				var token = slot.querySelector('.tbtdd-token');
				if (token) {
					returnToBank(token, true);
				}
			});
		}

		function showCorrect() {
			clearPicked();
			clearMarks();
			emptyAllSlots();

			slots.forEach(function (slot) {
				var expected = normalise(answers[slot.dataset.slot]);
				var token = Array.prototype.filter.call(
					bank.querySelectorAll('.tbtdd-token'),
					function (candidate) {
						return normalise(candidate.dataset.tbtddToken) === expected;
					}
				)[0];

				if (!token) {
					return;
				}

				slot.appendChild(token);
				slot.classList.add('is-filled', 'is-correct');
				token.classList.add('is-correct');
				describeSlot(slot);
			});

			if (showButton) {
				showButton.hidden = true;
			}
			announce(t('shownAll'));
		}

		/**
		 * A redo reshuffles the bank, so the letters follow it and a fresh
		 * attempt reads A, B, C … again. This is the only place a letter
		 * changes, and every slot is empty when it runs.
		 */
		function relabel(token, index) {
			var letter = String.fromCharCode(65 + (index % 26));
			var tag = token.querySelector('.tbtdd-tag--letter');

			token.setAttribute('data-tbtdd-letter', letter);
			if (tag) {
				tag.textContent = letter;
			}
		}

		function redo() {
			clearPicked();
			clearMarks();
			emptyAllSlots();

			shuffle(Array.prototype.slice.call(bank.querySelectorAll('.tbtdd-token'))).forEach(function (token, index) {
				bank.appendChild(token);
				relabel(token, index);
			});

			if (scoreBox) {
				scoreBox.hidden = true;
				scoreBox.classList.remove('is-perfect');
			}
			checkButton.hidden = false;
			if (showButton) {
				showButton.hidden = true;
			}
			if (redoButton) {
				redoButton.hidden = true;
			}

			announce(t('restarted'));
		}

		if (checkButton) {
			checkButton.addEventListener('click', check);
		}
		if (showButton) {
			showButton.addEventListener('click', showCorrect);
		}
		if (redoButton) {
			redoButton.addEventListener('click', redo);
		}
	}

	function initialise() {
		document.querySelectorAll('.tbtdd-exercise:not([data-tbtdd-ready])').forEach(function (root) {
			root.dataset.tbtddReady = 'true';
			initExercise(root);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initialise);
	} else {
		initialise();
	}
})();
