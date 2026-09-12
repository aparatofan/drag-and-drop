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

	/* ---- Reporting to the teacher's live panel ----

	   Optional in every direction. TBT Notes owns the activity routes, and the
	   exercise must keep working when Notes is not there, so the whole surface is
	   gated on a base URL the server only supplies when Notes is active.

	   Module level, not per exercise, on purpose. A lesson page can carry several
	   exercises, and a heartbeat each would be several identical requests every
	   twenty seconds saying the same thing about the same student. One page, one
	   pulse.

	   The endpoint is read off the same module-level `config` that `t()` stakes
	   every string on. If localisation order ever broke, the strings would break
	   with it — one coherent failure rather than two half-working ones. */

	var PRESENCE_EVERY = 20000;
	var presenceTimer = null;

	function postActivity(path, body) {
		if (!config.activityBase || !config.activityNonce) {
			return;
		}

		// Reporting is a side effect of doing the exercise, never a gate on it: a
		// failed request is swallowed rather than shown. A student mid-lesson
		// cannot act on "could not reach the progress panel".
		fetch(config.activityBase + path, {
			method: 'POST',
			credentials: 'same-origin',
			cache: 'no-store',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.activityNonce
			},
			body: JSON.stringify(body || {}),
			// A learner who closes the tab on a finished board should still be
			// recorded.
			keepalive: true
		}).catch(function () {});
	}

	/* The heartbeat says "still working" and writes no history — the server keeps
	   it in a short-lived transient. It starts on the first token placed, not on
	   page load: a lesson page that merely contains an exercise must not mark
	   every student in the room as working the moment the page paints. */
	function startPresence() {
		if (presenceTimer || !config.activityBase) {
			return;
		}
		postActivity('/presence', {});
		presenceTimer = window.setInterval(function () {
			postActivity('/presence', {});
		}, PRESENCE_EVERY);
	}

	function stopPresence() {
		if (presenceTimer) {
			window.clearInterval(presenceTimer);
			presenceTimer = null;
		}
	}

	/* ---- The sticky word bank ----

	   The bank pins to the top of the viewport so a word stays reachable from a
	   gap far down the passage. game.css owns the pinning; this owns the two
	   things a stylesheet cannot measure — how tall the theme's fixed header is,
	   and whether this particular bank has grown tall enough that pinning it
	   would cost more passage than the reach is worth.

	   Every branch fails open. No sentinel, no IntersectionObserver, no header to
	   measure: the bank still pins, it simply keeps its resting shadow. Nothing
	   here can stop an exercise being played. */

	// The same number as the media query in game.css, deliberately.
	var STICKY_MIN_WIDTH = 1100;
	var STICKY_MAX_SHARE = 0.34;

	var headerMeasured = false;

	/**
	 * Write --tbtdd-header-offset from the theme's own fixed header, once.
	 *
	 * Divi's fixed nav shrinks as the page scrolls, so the height read at rest is
	 * the larger of its two. That is the one worth keeping: the bank then clears
	 * the header at every scroll position rather than tucking under it near the
	 * top of the page. Left alone when nothing qualifies, so the token keeps the
	 * 0px the stylesheet gives it.
	 */
	function measureHeaderOffset() {
		if (headerMeasured) {
			return;
		}
		headerMeasured = true;

		var tallest = 0;

		document.querySelectorAll('header, [role="banner"], #main-header, #top-header').forEach(function (node) {
			// The player's own hero is a <header>, and the admin bar has its own
			// token — neither is the theme header this is looking for.
			if (node.id === 'wpadminbar' || node.closest('.tbtdd-exercise')) {
				return;
			}

			var position = window.getComputedStyle(node).position;
			if (position !== 'fixed' && position !== 'sticky') {
				return;
			}

			// Pinned at the top of the viewport, and a plausible header height: a
			// fixed sidebar or a full-height overlay menu is not an offset.
			var rect = node.getBoundingClientRect();
			if (rect.height <= 0 || rect.height > 200 || rect.top > 60) {
				return;
			}

			tallest = Math.max(tallest, rect.height);
		});

		if (tallest > 0) {
			document.documentElement.style.setProperty('--tbtdd-header-offset', Math.round(tallest) + 'px');
		}
	}

	function setupStickyBank(root, bank) {
		var sentinel = root.querySelector('[data-tbtdd-sentinel]');
		if (!sentinel) {
			return;
		}

		// game.css pins the bank on the standalone player and nowhere else.
		// Asking the same question here keeps an embedded exercise out of all of
		// this, rather than restating the rule as a second condition.
		if (!bank.closest('.tbtdd-standalone')) {
			return;
		}

		measureHeaderOffset();

		if (typeof window.IntersectionObserver !== 'function') {
			return;
		}

		var observer = null;
		var resizeTimer = null;

		function pixels(value) {
			var parsed = parseFloat(value);
			return isNaN(parsed) ? 0 : parsed;
		}

		/* The sentinel sits immediately before the bank, so the bank's own top
		   margin is the distance between the two. The bank detaches when its top
		   edge reaches the sticky offset, by which point the sentinel is already
		   that margin further up; moving the observation root's top edge by the
		   difference lands the shadow on the pixel the bank actually pins. */
		function rootMarginTop() {
			var style = window.getComputedStyle(bank);
			return pixels(style.marginTop) - pixels(style.top);
		}

		/* A bank deep enough to eat a third of the screen is worse pinned than in
		   flow. Width alone cannot answer that — a short laptop screen at 1440px
		   fails where a tall one passes — so it is measured rather than guessed. */
		function tooTall() {
			return window.innerWidth < STICKY_MIN_WIDTH
				|| bank.getBoundingClientRect().height > window.innerHeight * STICKY_MAX_SHARE;
		}

		/* Deliberately not called as words are placed. The bank does shrink past
		   the threshold mid-exercise, but switching it to pinned at that moment
		   would jump the passage under someone already reading it. The verdict is
		   taken on the full bank and revisited only when the window changes. */
		function refresh() {
			if (observer) {
				observer.disconnect();
				observer = null;
			}

			if (tooTall()) {
				bank.classList.add('is-too-tall');
				bank.classList.remove('is-stuck');
				return;
			}

			bank.classList.remove('is-too-tall');

			observer = new window.IntersectionObserver(function (entries) {
				entries.forEach(function (entry) {
					bank.classList.toggle('is-stuck', !entry.isIntersecting);
				});
			}, { rootMargin: rootMarginTop() + 'px 0px 0px 0px', threshold: 0 });

			observer.observe(sentinel);
		}

		refresh();

		window.addEventListener('resize', function () {
			window.clearTimeout(resizeTimer);
			resizeTimer = window.setTimeout(refresh, 150);
		});
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

		setupStickyBank(root, bank);

		var picked = null;

		/* Reporting state, all three surviving redo() by design.

		   assisted: Show correct has been used at least once this page load. It
		   is never cleared — that is the whole mechanism, since redo() plus a
		   second Check would otherwise turn a revealed board into a perfect
		   score.

		   completionSent: a once-per-page-load guard, so a student who redoes a
		   reported attempt does not tell the teacher they finished twice. It also
		   keeps the heartbeat stopped: placing tokens after a completion must not
		   re-arm it.

		   startedAt: set on the first token placed, so an untouched exercise is
		   not timed. */
		var assisted = false;
		var completionSent = false;
		var startedAt = 0;

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

			// Every route a word can take into a gap — dropped, clicked, or
			// named by a typed letter — arrives here, so this is the one point
			// that means "the student has started".
			noteInteraction();

			if (existing) {
				returnToBank(existing, true);
			}

			// The word may be arriving from another gap rather than from the
			// bank — dragged out of it, or pulled out by a letter typed into
			// this one. That gap is losing it, so it has to stop looking and
			// reading as though it were still filled.
			var source = token.parentElement;

			slot.appendChild(token);
			slot.classList.add('is-filled');
			slot.classList.remove('is-correct', 'is-wrong');
			token.classList.remove('is-picked', 'is-correct', 'is-wrong');
			describeSlot(slot);

			if (source && source !== slot && source.classList.contains('tbtdd-slot')) {
				source.classList.remove('is-filled', 'is-correct', 'is-wrong');
				describeSlot(source);
			}

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

		/**
		 * The token a letter key names, or null when it names none.
		 *
		 * A letter reaches a token only while it names exactly one. Badges are
		 * dealt chr(65 + index % 26), so a 27th word would repeat A and one
		 * keystroke would name two words; both then stay drag-only rather than
		 * risk dropping the wrong one. That caps keyboard entry at 26 words,
		 * and no AA/AB double-letter badge is introduced to lift it. A bank
		 * holds at most MAX_ITEMS gaps plus MAX_DISTRACTORS extra words —
		 * twenty-two — so this is a guard against a future cap change, not a
		 * limit any exercise meets today.
		 *
		 * @param {string} letter Upper-case A–Z.
		 * @return {Element|null}
		 */
		function tokenByLetter(letter) {
			var matches = Array.prototype.filter.call(
				root.querySelectorAll('.tbtdd-token'),
				function (candidate) {
					return (candidate.getAttribute('data-tbtdd-letter') || '') === letter;
				}
			);

			return 1 === matches.length ? matches[0] : null;
		}

		/**
		 * A nudge for a key that names no word.
		 *
		 * Deliberately colourless: red is the verdict on an answer, and a key
		 * that names nothing is not an answer. Removing the class and reading
		 * a layout property before re-adding it restarts the animation when
		 * the same gap is mistyped twice running.
		 */
		function shake(slot) {
			slot.classList.remove('is-shaking');
			void slot.offsetWidth;
			slot.classList.add('is-shaking');
		}

		/**
		 * Move focus to the next gap still wanting a word: forward from the
		 * one just filled, then wrapping to the lowest-numbered empty gap.
		 *
		 * The wrap is what makes a move work. Typing a letter already sitting
		 * in another gap empties that gap, and it is usually earlier in the
		 * reading order than the one just filled. With every gap full the loop
		 * finds nothing and focus stays put.
		 */
		function focusNextEmpty(from) {
			var start = slots.indexOf(from);

			for (var step = 1; step <= slots.length; step++) {
				var candidate = slots[(start + step) % slots.length];

				if (!candidate.querySelector('.tbtdd-token')) {
					candidate.focus();
					return;
				}
			}
		}

		/**
		 * A letter typed into a focused gap.
		 *
		 * Every branch runs through place() and returnToBank(), the same two
		 * functions the pointer uses, so a typed word and a dragged word leave
		 * the page in the same state and Check cannot tell them apart. place()
		 * already returns a displaced word to the bank and empties the gap a
		 * word is moved out of.
		 */
		function typeLetter(slot, letter) {
			var token = tokenByLetter(letter);

			if (!token) {
				shake(slot);
				return;
			}

			// Already in this gap: nothing to move, nothing to announce, and
			// no advance — the student has not filled anything.
			if (token.parentElement === slot) {
				return;
			}

			place(slot, token);
			focusNextEmpty(slot);
		}

		/**
		 * Backspace or Delete on a focused gap: the word goes back to the bank
		 * and the gap keeps the focus, so the next letter can correct it.
		 */
		function clearSlot(slot) {
			var token = slot.querySelector('.tbtdd-token');
			if (!token) {
				return;
			}

			returnToBank(token);
			// The word may have been holding the focus itself; moving it out
			// of the gap would otherwise drop focus to the document.
			slot.focus();
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
					return;
				}

				// Ctrl+A, Cmd+R, Alt+Left and the rest belong to the browser.
				// Shift is not among them: it is how a capital letter is typed.
				if (event.ctrlKey || event.metaKey || event.altKey) {
					return;
				}

				if ('Backspace' === event.key || 'Delete' === event.key) {
					// Backspace still means "back" in some browsers when
					// nothing on the page is editable, so it is stopped
					// whether or not this gap had a word to clear.
					event.preventDefault();
					clearSlot(slot);
					return;
				}

				// One A–Z letter and nothing else: Tab and Shift+Tab have to
				// keep moving the focus, and every other key is left alone.
				if (!/^[a-zA-Z]$/.test(event.key)) {
					return;
				}

				event.preventDefault();
				typeLetter(slot, event.key.toUpperCase());
			});

			slot.addEventListener('animationend', function () {
				slot.classList.remove('is-shaking');
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

		/* Every gap has something in it. Not "every gap is right": the rule
		   reports a finished board and lets the score say how it went, because a
		   ten-gap exercise with distractors almost never comes out perfect first
		   time and a signal that rare is one nobody reads. A slot showing a
		   revealed answer carries a token too — that is what `assisted` is for,
		   not a reason to test the fill differently. */
		function allSlotsFilled() {
			return slots.every(function (slot) {
				return !!slot.querySelector('.tbtdd-token');
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

			if (allSlotsFilled()) {
				reportCompletion(correct);
			}
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
			// Never cleared, and deliberately not reset by redo(): revealing the
			// answers ends this sitting's claim to a reported completion.
			assisted = true;

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

		/* Called from the first token placed. Starts the clock and the heartbeat;
		   both are no-ops on every later call. */
		function noteInteraction() {
			// An exercise that has already reported never beats again. Redo is
			// not a new sitting: completionSent survives it by design, and a
			// genuinely fresh attempt is a fresh page load. Without this the
			// student reads as working on finished work until the tab closes.
			if (completionSent) {
				return;
			}
			if (!startedAt) {
				startedAt = Date.now();
			}
			startPresence();
		}

		function reportCompletion(correct) {
			if (completionSent || assisted) {
				return;
			}

			var activity = settings.activity;
			if (!activity || !activity.objectRef) {
				return;
			}

			// The server's gap count and the DOM's must agree. They always
			// should; if they ever do not, the board being scored is not the
			// board that was saved, and a completion row from it would be a lie.
			// The heartbeat is left running: the board is wrong, but the student
			// is still working.
			if (activity.gapCount && activity.gapCount !== slots.length) {
				return;
			}

			completionSent = true;
			stopPresence();

			// The real score, not n of n: a filled board is what is being
			// reported, so the score is the only thing that says how it went. A
			// zero is a genuine result and is sent like any other.
			postActivity('', {
				tool: 'dragdrop',
				object_ref: activity.objectRef,
				object_title: activity.objectTitle,
				post_id: activity.postId || 0,
				score: correct,
				score_max: slots.length,
				duration_seconds: startedAt
					? Math.max(0, Math.round((Date.now() - startedAt) / 1000))
					: null
			});
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
