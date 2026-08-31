/**
 * TBT Teaching Tools — front-end drag-and-drop generator and exercise library.
 *
 * Plain ES2018, no build step and no framework, matching the rest of the
 * plugin. Every write goes through the owner-scoped REST API; nothing here is
 * a security boundary.
 */
(function () {
	'use strict';

	var config = window.TBTDDTools || {};
	var strings = config.strings || {};
	var MAX_ITEMS = parseInt(config.maxItems, 10) || 7;

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

	function el(tag, className, text) {
		var node = document.createElement(tag);
		if (className) {
			node.className = className;
		}
		if (typeof text !== 'undefined' && text !== null) {
			node.textContent = String(text);
		}
		return node;
	}

	function request(url, options) {
		var settings = options || {};
		var init = {
			method: settings.method || 'GET',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce
			}
		};

		if (settings.body) {
			init.body = JSON.stringify(settings.body);
		}

		return window.fetch(url, init).then(function (response) {
			return response.json().catch(function () {
				return {};
			}).then(function (payload) {
				if (response.ok) {
					return payload;
				}

				var error = new Error(payload && payload.message ? payload.message : t('networkError'));
				error.status = response.status;
				error.code = payload && payload.code ? payload.code : '';
				throw error;
			});
		});
	}

	function messageFor(error) {
		if (!error) {
			return t('networkError');
		}
		if (error.code === 'rest_cookie_invalid_nonce') {
			return t('sessionExpired');
		}
		return error.message || t('networkError');
	}

	function notify(root, message, isError) {
		var box = root.querySelector('[data-tbtdd-notice]');
		if (!box) {
			return;
		}

		box.textContent = message || '';
		box.classList.toggle('tbtdd-notice--error', !!isError);
		box.hidden = !message;
	}

	function formatDate(iso) {
		if (!iso) {
			return '';
		}
		var date = new Date(iso);
		if (isNaN(date.getTime())) {
			return '';
		}
		return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
	}

	/**
	 * Add exercise_id to a page URL without disturbing what it already carries.
	 */
	function appendExerciseId(base, id) {
		var target = String(base);
		var hash = '';
		var index = target.indexOf('#');

		if (index !== -1) {
			hash = target.slice(index);
			target = target.slice(0, index);
		}

		return target + (target.indexOf('?') === -1 ? '?' : '&') + 'exercise_id=' + encodeURIComponent(id) + hash;
	}

	/**
	 * UTF-8 byte offset of a JavaScript string index.
	 *
	 * _dd_gap_offsets is validated server-side with substr()/strlen(), which
	 * are byte operations, while a JS string is indexed in UTF-16 code units.
	 * On a Polish text the two disagree from the first accented letter onwards,
	 * so the conversion happens here rather than being left to coincide.
	 */
	var encoder = window.TextEncoder ? new window.TextEncoder() : null;

	function byteOffset(text, index) {
		var prefix = text.slice(0, index);
		if (encoder) {
			return encoder.encode(prefix).length;
		}

		// unescape(encodeURIComponent()) is the pre-TextEncoder way to measure
		// UTF-8 length; kept so an old browser degrades to a stale offset
		// rather than a wrong one.
		return unescape(encodeURIComponent(prefix)).length;
	}

	function copyToClipboard(value) {
		if (window.navigator.clipboard && window.navigator.clipboard.writeText) {
			return window.navigator.clipboard.writeText(value);
		}

		return new Promise(function (resolve, reject) {
			var field = document.createElement('textarea');
			field.value = value;
			field.setAttribute('readonly', 'readonly');
			field.style.position = 'fixed';
			field.style.opacity = '0';
			document.body.appendChild(field);
			field.select();
			try {
				document.execCommand('copy');
				resolve();
			} catch (error) {
				reject(error);
			}
			document.body.removeChild(field);
		});
	}

	/**
	 * Wire up a copy button that carries its value on data-tbtdd-copy.
	 */
	function bindCopy(button) {
		var original = button.textContent;

		button.addEventListener('click', function () {
			copyToClipboard(button.getAttribute('data-tbtdd-copy') || '').then(function () {
				button.textContent = t('copied');
				window.setTimeout(function () {
					button.textContent = original;
				}, 1600);
			});
		});
	}

	function bindCopies(scope) {
		scope.querySelectorAll('[data-tbtdd-copy]').forEach(bindCopy);
	}

	/* ==================================================================
	   The gap picker
	   ================================================================== */

	/**
	 * Split text into word and separator runs, keeping every separator
	 * verbatim so the picker can rebuild the text exactly as written.
	 *
	 * @param {string} text Exercise text.
	 * @return {Array} Runs of { type, text, start, end }.
	 */
	function tokenise(text) {
		var parts = [];
		var pattern = /[\p{L}\p{N}][\p{L}\p{N}'’-]*/gu;
		var last = 0;
		var match;

		while ((match = pattern.exec(text)) !== null) {
			if (match.index > last) {
				parts.push({ type: 'sep', text: text.slice(last, match.index), start: last, end: match.index });
			}
			parts.push({ type: 'word', text: match[0], start: match.index, end: match.index + match[0].length });
			last = match.index + match[0].length;
		}

		if (last < text.length) {
			parts.push({ type: 'sep', text: text.slice(last), start: last, end: text.length });
		}

		return parts;
	}

	/**
	 * The click-to-gap picker.
	 *
	 * Gaps are held as character ranges into the exercise text rather than as
	 * word indexes, because a range survives an edit elsewhere in the text and
	 * an index does not — and because the range is what becomes the stored
	 * offset.
	 */
	function createPicker(options) {
		var root = options.root;
		var noticeBox = options.notice;
		var chipsBox = options.chips;
		var onChange = options.onChange || function () {};

		var text = '';
		var parts = [];
		var gaps = [];
		var anchor = null;
		var marking = false;

		function setNotice(message) {
			if (!noticeBox) {
				return;
			}
			noticeBox.textContent = message || '';
			noticeBox.hidden = !message;
		}

		function gapAt(position) {
			for (var i = 0; i < gaps.length; i++) {
				if (position >= gaps[i].start && position < gaps[i].end) {
					return i;
				}
			}
			return -1;
		}

		function gapText(gap) {
			return text.slice(gap.start, gap.end);
		}

		function sortGaps() {
			gaps.sort(function (a, b) {
				return a.start - b.start;
			});
		}

		function removeGap(index) {
			gaps.splice(index, 1);
			setNotice('');
			render();
		}

		function render() {
			root.replaceChildren();
			root.classList.toggle('is-empty', parts.length === 0);

			if (!parts.length) {
				root.textContent = t('emptyPicker');
				renderChips();
				onChange();
				return;
			}

			var index = 0;
			while (index < parts.length) {
				var part = parts[index];
				var gapIndex = gapAt(part.start);

				if (gapIndex > -1 && gaps[gapIndex].start === part.start) {
					root.appendChild(gapSpan(gapIndex));
					index = skipPast(index, gaps[gapIndex].end);
					continue;
				}

				if (part.type === 'sep') {
					root.appendChild(document.createTextNode(part.text));
					index += 1;
					continue;
				}

				root.appendChild(wordSpan(part, index));
				index += 1;
			}

			renderChips();
			onChange();
		}

		function skipPast(index, end) {
			var next = index;
			while (next < parts.length && parts[next].start < end) {
				next += 1;
			}
			return next;
		}

		function gapSpan(gapIndex) {
			var span = el('span', 'tbtdd-picker__gap', gapText(gaps[gapIndex]));
			span.setAttribute('role', 'button');
			span.setAttribute('tabindex', '0');
			span.title = t('removeGap');
			span.setAttribute('aria-label', sprintf(t('removeGapNamed'), [gapText(gaps[gapIndex])]));

			span.addEventListener('click', function () {
				removeGap(gapIndex);
			});
			span.addEventListener('keydown', function (event) {
				if (event.key === 'Enter' || event.key === ' ' || event.key === 'Spacebar') {
					event.preventDefault();
					removeGap(gapIndex);
				}
			});

			return span;
		}

		function wordSpan(part, index) {
			var span = el('span', 'tbtdd-picker__word', part.text);
			span.dataset.tbtddPart = String(index);
			span.setAttribute('role', 'button');
			span.setAttribute('tabindex', '0');

			span.addEventListener('keydown', function (event) {
				if (event.key === 'Enter' || event.key === ' ' || event.key === 'Spacebar') {
					event.preventDefault();
					commit(part.start, part.end);
				}
			});

			return span;
		}

		function renderChips() {
			if (!chipsBox) {
				return;
			}

			chipsBox.replaceChildren();

			gaps.forEach(function (gap, index) {
				var chip = el('span', 'tbtdd-chip', gapText(gap));
				var remove = el('button', 'tbtdd-chip__remove', '×');
				remove.type = 'button';
				remove.setAttribute('aria-label', sprintf(t('removeGapNamed'), [gapText(gap)]));
				remove.addEventListener('click', function () {
					removeGap(index);
				});
				chip.appendChild(remove);
				chipsBox.appendChild(chip);
			});

			var counter = el('span', 'tbtdd-chips__count', sprintf(t('gapCount'), [gaps.length, MAX_ITEMS]));
			if (gaps.length >= MAX_ITEMS) {
				counter.classList.add('is-full');
			}
			chipsBox.appendChild(counter);
		}

		/**
		 * Turn a character range into a gap, or explain why it cannot be one.
		 */
		function commit(start, end) {
			clearMarking();

			if (end <= start) {
				return;
			}

			// A range that swallows an existing gap is rejected rather than
			// merged: silently absorbing a gap the teacher made is a worse
			// surprise than being told no.
			for (var i = 0; i < gaps.length; i++) {
				if (start < gaps[i].end && gaps[i].start < end) {
					setNotice(t('gapOverlap'));
					return;
				}
			}

			if (gaps.length >= MAX_ITEMS) {
				setNotice(t('gapLimit'));
				renderChips();
				return;
			}

			var candidate = text.slice(start, end);
			var duplicate = gaps.some(function (gap) {
				return gapText(gap).toLowerCase() === candidate.toLowerCase();
			});

			if (duplicate) {
				setNotice(sprintf(t('gapDuplicate'), [candidate]));
				return;
			}

			gaps.push({ start: start, end: end });
			sortGaps();
			setNotice('');
			render();
		}

		function clearMarking() {
			root.querySelectorAll('.is-marking').forEach(function (node) {
				node.classList.remove('is-marking');
			});
		}

		function paintRange(from, to) {
			var low = Math.min(from, to);
			var high = Math.max(from, to);

			root.querySelectorAll('.tbtdd-picker__word').forEach(function (node) {
				var index = parseInt(node.dataset.tbtddPart, 10);
				node.classList.toggle('is-marking', index >= low && index <= high);
			});
		}

		root.addEventListener('mousedown', function (event) {
			var word = event.target.closest('.tbtdd-picker__word');
			if (!word) {
				return;
			}

			// Suppress the browser's own text selection: the drag gesture here
			// means "gap these words", and a blue selection over the top of it
			// reads as something else entirely.
			event.preventDefault();
			anchor = parseInt(word.dataset.tbtddPart, 10);
			marking = true;
			paintRange(anchor, anchor);
		});

		root.addEventListener('mouseover', function (event) {
			if (!marking) {
				return;
			}
			var word = event.target.closest('.tbtdd-picker__word');
			if (!word) {
				return;
			}
			paintRange(anchor, parseInt(word.dataset.tbtddPart, 10));
		});

		// On document, not on the picker: a drag that ends outside the panel
		// still has to commit or the picker would stay stuck in marking mode.
		document.addEventListener('mouseup', function (event) {
			if (!marking) {
				return;
			}

			marking = false;
			var word = event.target && event.target.closest ? event.target.closest('.tbtdd-picker__word') : null;
			var end = word && root.contains(word) ? parseInt(word.dataset.tbtddPart, 10) : anchor;

			if (anchor === null || isNaN(end)) {
				clearMarking();
				anchor = null;
				return;
			}

			var low = parts[Math.min(anchor, end)];
			var high = parts[Math.max(anchor, end)];
			anchor = null;

			if (low && high) {
				commit(low.start, high.end);
			} else {
				clearMarking();
			}
		});

		/**
		 * Re-tokenise after an edit, keeping the gaps that still hold.
		 *
		 * A gap survives when its stored range still starts a word, ends a word
		 * and spans exactly the text it was made from. Anything else is a gap
		 * describing a document that no longer exists, so it is dropped — and
		 * said out loud, because a gap disappearing without explanation is how
		 * a teacher loses trust in the picker.
		 */
		function setText(next, initialGaps) {
			var previousCount = gaps.length;

			text = String(next || '');
			parts = tokenise(text);

			var starts = {};
			var ends = {};
			parts.forEach(function (part) {
				if (part.type === 'word') {
					starts[part.start] = true;
					ends[part.end] = true;
				}
			});

			var candidates = initialGaps || gaps;
			var kept = [];

			candidates.forEach(function (gap) {
				var start = typeof gap.start === 'number' ? gap.start : gap.offset;
				var end = typeof gap.end === 'number' ? gap.end : start + String(gap.text || '').length;

				if (typeof start !== 'number' || start < 0) {
					return;
				}
				if (!starts[start] || !ends[end]) {
					return;
				}
				if (typeof gap.text === 'string' && text.slice(start, end) !== gap.text) {
					return;
				}
				// Two surviving gaps cannot overlap, but a re-tokenise can make
				// one range swallow another, so the check is repeated here.
				var clash = kept.some(function (existing) {
					return start < existing.end && existing.start < end;
				});
				if (clash) {
					return;
				}

				kept.push({ start: start, end: end });
			});

			gaps = kept;
			sortGaps();

			var dropped = (initialGaps ? initialGaps.length : previousCount) - gaps.length;
			if (dropped === 1) {
				setNotice(t('gapsDroppedOne'));
			} else if (dropped > 1) {
				setNotice(sprintf(t('gapsDropped'), [dropped]));
			} else {
				setNotice('');
			}

			render();
		}

		return {
			setText: setText,
			count: function () {
				return gaps.length;
			},
			/**
			 * The gaps as the REST API wants them: texts in reading order plus
			 * index-aligned byte offsets.
			 */
			payload: function () {
				var items = [];
				var offsets = [];

				gaps.forEach(function (gap) {
					items.push(gapText(gap));
					offsets.push(byteOffset(text, gap.start));
				});

				return { items: items, offsets: offsets };
			}
		};
	}

	/* ==================================================================
	   The generator
	   ================================================================== */

	function initGenerator(root) {
		var exerciseId = parseInt(root.getAttribute('data-tbtdd-exercise-id'), 10) || 0;
		var status = root.getAttribute('data-tbtdd-status') || '';
		var libraryUrl = root.getAttribute('data-tbtdd-library-url') || '';
		var previewUrl = root.getAttribute('data-tbtdd-preview-url') || '';

		var titleField = root.querySelector('[data-tbtdd-field="title"]');
		var textField = root.querySelector('[data-tbtdd-field="text"]');
		var instructionsField = root.querySelector('[data-tbtdd-field="instructions"]');
		var distractorsField = root.querySelector('[data-tbtdd-field="distractors"]');
		var shareBox = root.querySelector('[data-tbtdd-share]');
		var saveStatus = root.querySelector('[data-tbtdd-save-status]');
		var publishButton = root.querySelector('[data-tbtdd-publish]');
		var draftButton = root.querySelector('[data-tbtdd-draft]');
		var previewButton = root.querySelector('[data-tbtdd-preview]');
		var discardRow = root.querySelector('[data-tbtdd-discard-row]');
		var discardButton = root.querySelector('[data-tbtdd-discard]');
		var discardStatus = root.querySelector('[data-tbtdd-discard-status]');
		var createNewLink = root.querySelector('[data-tbtdd-create-new]');
		// The block below stage 3, which is what is shown or hidden: the link
		// inside it is only ever given its href.
		var createNewRow = root.querySelector('[data-tbtdd-next]');

		var picker = createPicker({
			root: root.querySelector('[data-tbtdd-picker]'),
			chips: root.querySelector('[data-tbtdd-chips]'),
			notice: root.querySelector('[data-tbtdd-gap-notice]'),
			onChange: onEdit
		});

		bindCopies(root);

		function fieldValue(field) {
			return field ? field.value : '';
		}

		/**
		 * Where the teacher is in the flow. All three stages are always in the
		 * DOM; only their data-state changes, and the CSS does the rest.
		 *
		 * Stage 1 is never waiting — it is where the exercise starts, so there
		 * is nothing it could be waiting for.
		 */
		function refreshStages() {
			var hasText = fieldValue(textField).trim() !== '';
			var hasGaps = picker.count() > 0;

			setStage(1, hasText ? 'done' : 'active');
			setStage(2, hasText ? (hasGaps ? 'done' : 'active') : 'waiting');
			setStage(3, hasGaps ? ('publish' === status ? 'done' : 'active') : 'waiting');
		}

		function setStage(number, state) {
			var stage = root.querySelector('[data-tbtdd-stage="' + number + '"]');
			if (stage) {
				// 'active' is the default rim colour, but it is written out
				// anyway so the state is legible in the DOM.
				stage.setAttribute('data-state', state);
			}
		}

		/**
		 * The generator page with exercise_id dropped — an empty exercise.
		 *
		 * config.generatorUrl carries the shortcode attribute, the recorded
		 * generator page and the filter. Nothing resolved means there is no page
		 * to send the teacher to, and the link stays hidden rather than pointing
		 * at one that cannot be reached.
		 */
		function createNewUrl() {
			if (!config.generatorUrl) {
				return '';
			}

			try {
				var url = new URL(config.generatorUrl, window.location.href);
				url.searchParams.delete('exercise_id');
				return url.toString();
			} catch (error) {
				return '';
			}
		}

		function showCreateNew() {
			if (!createNewLink || !createNewRow) {
				return;
			}

			var target = createNewUrl();
			if (!target) {
				return;
			}

			createNewLink.href = target;
			createNewRow.hidden = false;
		}

		/**
		 * Any edit puts the exercise back in play: the saved state the link
		 * offered to move on from is no longer what is on screen.
		 */
		function onEdit() {
			if (createNewRow) {
				createNewRow.hidden = true;
			}
			refreshStages();
		}

		function payload(publish) {
			var gaps = picker.payload();

			return {
				title: fieldValue(titleField),
				text: fieldValue(textField),
				instructions: fieldValue(instructionsField),
				items: gaps.items,
				offsets: gaps.offsets,
				// Sent as typed. The server splits on commas, drops anything
				// that matches a gap, and caps the list.
				distractors: fieldValue(distractorsField),
				status: publish ? 'publish' : 'draft'
			};
		}

		/**
		 * Rebuild the share rows from a saved exercise.
		 */
		function renderShare(exercise) {
			if (!shareBox) {
				return;
			}

			shareBox.replaceChildren();

			if (!exercise || !exercise.id) {
				return;
			}

			if (exercise.status === 'publish' && exercise.permalink) {
				shareBox.appendChild(linkRow(exercise.permalink, t('copy')));
			} else {
				shareBox.appendChild(el('p', 'tbtdd-hint', t('draftNoLink')));
			}

			shareBox.appendChild(linkRow(exercise.shortcode, t('copy')));
		}

		function linkRow(value, label) {
			var row = el('div', 'tbtdd-linkrow');
			var code = el('code', null, value);
			var button = el('button', 'tbtdd-button tbtdd-button--small', label);

			button.type = 'button';
			button.setAttribute('data-tbtdd-copy', value);
			bindCopy(button);

			row.append(code, button);
			return row;
		}

		function setBusy(busy) {
			[publishButton, draftButton, previewButton].forEach(function (button) {
				if (button) {
					button.disabled = busy;
				}
			});
		}

		/**
		 * Publish refuses, with the reason, rather than silently saving a draft:
		 * a teacher who pressed Publish needs to know it did not happen.
		 */
		function blockedReason() {
			if (!fieldValue(titleField).trim()) {
				return t('needTitle');
			}
			if (!fieldValue(textField).trim()) {
				return t('needText');
			}
			if (picker.count() === 0) {
				return t('needGaps');
			}
			return '';
		}

		function save(publish, then) {
			if (publish) {
				var blocked = blockedReason();
				if (blocked) {
					notify(root, blocked, true);
					return;
				}
			}

			setBusy(true);
			if (saveStatus) {
				saveStatus.textContent = t('saving');
			}
			notify(root, '');

			var url = exerciseId ? config.restBase + '/' + exerciseId : config.restBase;

			request(url, { method: exerciseId ? 'PUT' : 'POST', body: payload(publish) }).then(function (response) {
				var exercise = response.exercise || {};

				if (exercise.id) {
					exerciseId = exercise.id;
					root.setAttribute('data-tbtdd-exercise-id', String(exercise.id));
				}
				if (exercise.preview) {
					previewUrl = exercise.preview;
				}

				status = response.status || status;
				root.setAttribute('data-tbtdd-status', status);

				if (saveStatus) {
					saveStatus.textContent = status === 'publish' ? t('savedPublished') : t('savedDraft');
				}
				if (response.message) {
					notify(root, response.message, true);
				}

				renderShare(exercise);
				syncExtras(exercise);
				syncDiscard();
				refreshStages();
				showCreateNew();

				if (typeof then === 'function') {
					then(exercise);
				}
			}).catch(function (error) {
				if (saveStatus) {
					saveStatus.textContent = '';
				}
				notify(root, messageFor(error), true);
			}).then(function () {
				setBusy(false);
			});
		}

		/**
		 * Show the extra words the server actually stored.
		 *
		 * It drops blanks, anything that repeats a gap, and everything past the
		 * cap, so a field left as typed would claim words the exercise does not
		 * have. Only rewritten on a save, never while the teacher is typing.
		 */
		function syncExtras(exercise) {
			if (distractorsField && exercise && Array.isArray(exercise.distractors)) {
				distractorsField.value = exercise.distractors.join(', ');
			}
		}

		function syncDiscard() {
			if (discardRow) {
				discardRow.hidden = !exerciseId || status !== 'draft';
			}
		}

		/**
		 * Preview always saves first: previewing a draft that does not yet
		 * contain what is on screen is worse than a moment's wait.
		 */
		function preview() {
			save(false, function (exercise) {
				var target = (exercise && exercise.status === 'publish' && exercise.permalink)
					? exercise.permalink
					: previewUrl;

				if (target) {
					window.open(target, '_blank', 'noopener');
				} else {
					notify(root, t('previewUnsaved'), true);
				}
			});
		}

		function discard() {
			if (!exerciseId) {
				return;
			}

			var title = fieldValue(titleField).trim();
			if (!window.confirm(title ? sprintf(t('confirmDiscard'), [title]) : t('confirmDelete'))) {
				return;
			}

			discardButton.disabled = true;
			if (discardStatus) {
				discardStatus.textContent = t('discarding');
			}
			notify(root, '');

			request(config.restBase + '/' + exerciseId, { method: 'DELETE' }).then(function () {
				if (libraryUrl) {
					window.location.href = libraryUrl;
					return;
				}

				// Nowhere to go back to, so start a clean exercise instead of
				// leaving the teacher on a page about one that no longer exists.
				var url = new URL(window.location.href);
				url.searchParams.delete('exercise_id');
				window.location.href = url.toString();
			}).catch(function (error) {
				if (discardStatus) {
					discardStatus.textContent = '';
				}
				discardButton.disabled = false;
				notify(root, messageFor(error), true);
			});
		}

		if (textField) {
			// input, not change: the picker below the field has to keep up with
			// typing, not wait for the field to be left.
			var retokeniseTimer = null;
			textField.addEventListener('input', function () {
				// Immediately, so the stage rims track the typing; the
				// re-tokenise behind it is the expensive half.
				onEdit();
				window.clearTimeout(retokeniseTimer);
				retokeniseTimer = window.setTimeout(function () {
					picker.setText(textField.value);
				}, 250);
			});
		}

		[titleField, instructionsField, distractorsField].forEach(function (field) {
			if (field) {
				field.addEventListener('input', onEdit);
			}
		});

		if (publishButton) {
			publishButton.addEventListener('click', function () {
				save(true);
			});
		}
		if (draftButton) {
			draftButton.addEventListener('click', function () {
				save(false);
			});
		}
		if (previewButton) {
			previewButton.addEventListener('click', preview);
		}
		if (discardButton) {
			discardButton.addEventListener('click', discard);
		}

		picker.setText(fieldValue(textField), initialGaps(root));
		syncDiscard();
		refreshStages();
	}

	function initialGaps(root) {
		var block = root.querySelector('[data-tbtdd-initial-gaps]');
		if (!block) {
			return [];
		}

		try {
			var parsed = JSON.parse(block.textContent);
			return Array.isArray(parsed) ? parsed : [];
		} catch (error) {
			return [];
		}
	}

	/* ==================================================================
	   The create dialog
	   ================================================================== */

	/**
	 * Name the exercise, and it exists.
	 *
	 * Mounted on <body> rather than beside the button that opened it. A tool can
	 * sit inside a Divi section carrying its own transform, and a transformed
	 * ancestor makes position: fixed resolve against that element instead of the
	 * viewport — the overlay would then cover part of the page, not the page.
	 */
	function openCreateDialog(options) {
		var max = parseInt(config.titleMax, 10) || 120;
		var uid = 'tbtdd-create-' + Math.random().toString(36).slice(2, 9);
		var overlay = el('div', 'tbt tbt-tool tbtdd-modal');
		var dialog = el('div', 'tbtdd-modal__dialog');
		var heading = el('h2', 'tbtdd-modal__title', t('createHeading'));
		var field = el('div', 'tbtdd-field');
		var label = el('label', null, t('titleLabel'));
		var input = document.createElement('input');
		var counter = el('p', 'tbtdd-modal__count');
		var errorBox = el('p', 'tbtdd-notice tbtdd-notice--error tbtdd-modal__error');
		var actions = el('div', 'tbtdd-modal__actions');
		var cancel = el('button', 'tbtdd-button', t('cancel'));
		var create = el('button', 'tbtdd-button tbtdd-button--primary', t('create'));
		var pending = false;

		dialog.setAttribute('role', 'dialog');
		dialog.setAttribute('aria-modal', 'true');
		dialog.setAttribute('aria-labelledby', uid + '-title');
		heading.id = uid + '-title';

		input.type = 'text';
		input.id = uid + '-input';
		input.maxLength = max;
		input.autocomplete = 'off';
		label.setAttribute('for', input.id);

		counter.id = uid + '-count';
		counter.setAttribute('aria-live', 'polite');
		input.setAttribute('aria-describedby', counter.id);

		errorBox.hidden = true;
		cancel.type = 'button';
		create.type = 'button';

		function showError(message) {
			errorBox.textContent = message || '';
			errorBox.hidden = !message;
		}

		function sync() {
			counter.textContent = sprintf(t('charsLeft'), [Math.max(0, max - input.value.length)]);
			create.disabled = pending || input.value.trim() === '';
		}

		function focusable() {
			return Array.prototype.filter.call(dialog.querySelectorAll('input, button'), function (node) {
				return !node.disabled;
			});
		}

		function close() {
			// A create is already on its way; letting the dialog go now would
			// leave the teacher on the library with a draft appearing behind them.
			if (pending) {
				return;
			}

			overlay.remove();
			if (options.opener && typeof options.opener.focus === 'function') {
				options.opener.focus();
			}
		}

		function submit() {
			var title = input.value.trim();
			if (!title || pending) {
				return;
			}

			pending = true;
			create.disabled = true;
			cancel.disabled = true;
			create.textContent = t('creating');
			showError('');

			request(config.restBase, { method: 'POST', body: { title: title } }).then(function (response) {
				var exercise = response && response.exercise ? response.exercise : null;
				if (!exercise || !exercise.id) {
					throw new Error(t('createFailed'));
				}

				window.location.href = appendExerciseId(options.generatorUrl, exercise.id);
			}).catch(function (error) {
				pending = false;
				cancel.disabled = false;
				create.textContent = t('create');
				sync();
				showError(messageFor(error));
				input.focus();
			});
		}

		overlay.addEventListener('click', function (event) {
			if (event.target === overlay) {
				close();
			}
		});

		overlay.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') {
				event.preventDefault();
				close();
				return;
			}

			if (event.key !== 'Tab') {
				return;
			}

			var nodes = focusable();
			if (!nodes.length) {
				event.preventDefault();
				return;
			}

			var first = nodes[0];
			var last = nodes[nodes.length - 1];

			if (event.shiftKey && document.activeElement === first) {
				event.preventDefault();
				last.focus();
			} else if (!event.shiftKey && document.activeElement === last) {
				event.preventDefault();
				first.focus();
			}
		});

		input.addEventListener('input', sync);
		input.addEventListener('keydown', function (event) {
			if (event.key === 'Enter') {
				event.preventDefault();
				submit();
			}
		});
		cancel.addEventListener('click', close);
		create.addEventListener('click', submit);

		field.append(label, input, counter);
		actions.append(cancel, create);
		dialog.append(heading, field, errorBox, actions);
		overlay.append(dialog);
		document.body.appendChild(overlay);

		sync();
		input.focus();
	}

	/* ==================================================================
	   The library
	   ================================================================== */

	function initLibrary(root) {
		var list = root.querySelector('[data-tbtdd-list]');
		var pagination = root.querySelector('[data-tbtdd-pagination]');
		var search = root.querySelector('[data-tbtdd-search]');
		var createButton = root.querySelector('[data-tbtdd-create]');
		/*
		 * The generator URL travels on the markup rather than in config: the
		 * bundle is localised once, before any shortcode has run, so a
		 * per-instance attribute cannot reach it. config.generatorUrl stays the
		 * fallback — it carries the recorded generator page and the filter.
		 */
		var generatorUrl = root.getAttribute('data-tbtdd-generator-url') || config.generatorUrl || '';
		var state = { page: 1, search: '', totalPages: 1 };
		var searchTimer = null;

		function gapLabel(count) {
			return count === 1 ? t('oneGapInExercise') : sprintf(t('gapsInExercise'), [count]);
		}

		function shareRow(exercise) {
			var panel = el('div', 'tbtdd-share__inner');

			if (exercise.status !== 'publish' || !exercise.permalink) {
				panel.appendChild(el('p', 'tbtdd-hint', t('draftNoLink')));
			} else {
				panel.appendChild(copyField(t('exerciseLink'), exercise.permalink));
			}

			panel.appendChild(copyField(t('shortcodeLabel'), exercise.shortcode));
			return panel;
		}

		function copyField(labelText, value) {
			var wrap = el('div', 'tbtdd-copy');
			var id = 'tbtdd-copy-' + Math.random().toString(36).slice(2, 9);
			var label = el('label', 'tbtdd-copy__label', labelText);
			var row = el('div', 'tbtdd-copy__row');
			var input = document.createElement('input');
			var button = el('button', 'tbtdd-button tbtdd-button--small', t('copy'));

			input.type = 'text';
			input.readOnly = true;
			input.value = value;
			input.id = id;
			label.setAttribute('for', id);

			button.type = 'button';
			button.setAttribute('data-tbtdd-copy', value);
			bindCopy(button);

			row.append(input, button);
			wrap.append(label, row);
			return wrap;
		}

		function row(exercise) {
			// The rim says the same thing as the badge, so a scanned list reads
			// without stopping on each row.
			var item = el('article', 'tbtdd-exercise-row' + ('publish' === exercise.status ? '' : ' tbtdd-exercise-row--draft'));
			var main = el('div', 'tbtdd-exercise-row__main');
			var meta = el('p', 'tbtdd-exercise-row__meta');
			var actions = el('div', 'tbtdd-exercise-row__actions');
			var share = el('div', 'tbtdd-share');
			var badge = el(
				'span',
				'tbtdd-badge ' + (exercise.status === 'publish' ? 'tbtdd-badge--published' : 'tbtdd-badge--draft'),
				exercise.status === 'publish' ? t('published') : t('draft')
			);

			var modified = formatDate(exercise.modified);

			main.appendChild(el('h3', 'tbtdd-exercise-row__title', exercise.title));
			// The date is dropped whole when there is nothing to print, rather
			// than showing "Edited" followed by a blank.
			meta.append.apply(meta, [
				badge,
				el('span', 'tbtdd-exercise-row__gaps', gapLabel(exercise.gap_count)),
				modified ? el('span', 'tbtdd-exercise-row__date', sprintf(t('modified'), [modified])) : null
			].filter(Boolean));
			main.appendChild(meta);

			/*
			 * Published exercises only. A draft's permalink 404s for a student
			 * and for the teacher alike, which is why the server sends an empty
			 * one for a draft.
			 */
			var open = null;
			if (exercise.status === 'publish' && exercise.permalink) {
				open = el('a', 'tbtdd-button tbtdd-button--small', t('open'));
				open.href = exercise.permalink;
				open.target = '_blank';
				open.rel = 'noopener';
				open.setAttribute('aria-label', sprintf(t('openNewTab'), [exercise.title]));
			}

			/*
			 * No generator page resolved means there is nowhere to edit, so the
			 * row shows no Edit action — the rule library.php already applies to
			 * Create new. A row that cannot be edited is honest; a link that
			 * reloads the library is not.
			 */
			var edit = null;
			if (generatorUrl) {
				edit = el('a', 'tbtdd-button tbtdd-button--small', t('edit'));
				edit.href = appendExerciseId(generatorUrl, exercise.id);
			}

			var shareButton = el('button', 'tbtdd-button tbtdd-button--small', t('share'));
			var duplicateButton = el('button', 'tbtdd-button tbtdd-button--small', t('duplicate'));
			var deleteButton = el('button', 'tbtdd-button tbtdd-button--small tbtdd-button--danger', t('delete'));

			[shareButton, duplicateButton, deleteButton].forEach(function (button) {
				button.type = 'button';
			});

			share.hidden = true;
			shareButton.setAttribute('aria-expanded', 'false');
			shareButton.addEventListener('click', function () {
				var opening = share.hidden;
				if (opening) {
					// Built lazily on first expand.
					share.replaceChildren(shareRow(exercise));
				}
				share.hidden = !opening;
				shareButton.setAttribute('aria-expanded', opening ? 'true' : 'false');
			});

			duplicateButton.addEventListener('click', function () {
				duplicateButton.disabled = true;
				request(config.restBase + '/' + exercise.id + '/duplicate', { method: 'POST' }).then(function () {
					notify(root, t('duplicated'));
					load();
				}).catch(function (error) {
					notify(root, messageFor(error), true);
					duplicateButton.disabled = false;
				});
			});

			deleteButton.addEventListener('click', function () {
				if (!window.confirm(t('confirmDelete'))) {
					return;
				}
				deleteButton.disabled = true;
				request(config.restBase + '/' + exercise.id, { method: 'DELETE' }).then(function () {
					notify(root, t('deleted'));
					load();
				}).catch(function (error) {
					notify(root, messageFor(error), true);
					deleteButton.disabled = false;
				});
			});

			actions.append.apply(actions, [open, edit, shareButton, duplicateButton, deleteButton].filter(Boolean));
			item.append(main, actions, share);
			return item;
		}

		function renderPagination() {
			if (!pagination) {
				return;
			}

			pagination.replaceChildren();
			if (state.totalPages < 2) {
				pagination.hidden = true;
				return;
			}

			var previous = el('button', 'tbtdd-button tbtdd-button--small', t('prevPage'));
			var next = el('button', 'tbtdd-button tbtdd-button--small', t('nextPage'));
			previous.type = 'button';
			next.type = 'button';
			previous.disabled = state.page <= 1;
			next.disabled = state.page >= state.totalPages;

			previous.addEventListener('click', function () {
				state.page -= 1;
				load();
			});
			next.addEventListener('click', function () {
				state.page += 1;
				load();
			});

			pagination.append(previous, el('span', 'tbtdd-pagination__label', sprintf(t('pageOf'), [state.page, state.totalPages])), next);
			pagination.hidden = false;
		}

		function load() {
			list.setAttribute('aria-busy', 'true');
			list.replaceChildren(el('p', 'tbtdd-hint', t('loading')));

			var url = config.restBase + '?page=' + state.page + '&per_page=20&search=' + encodeURIComponent(state.search);

			request(url).then(function (response) {
				var items = response.items || [];
				state.totalPages = response.total_pages || 1;

				// A deletion can empty the last page; step back rather than
				// leaving the teacher staring at nothing.
				if (!items.length && state.page > 1) {
					state.page -= 1;
					load();
					return;
				}

				list.replaceChildren();
				if (!items.length) {
					list.appendChild(el('p', 'tbtdd-hint', state.search ? t('emptySearch') : t('empty')));
				} else {
					items.forEach(function (exercise) {
						list.appendChild(row(exercise));
					});
				}

				renderPagination();
			}).catch(function (error) {
				list.replaceChildren();
				notify(root, messageFor(error), true);
			}).then(function () {
				list.setAttribute('aria-busy', 'false');
			});
		}

		if (search) {
			search.addEventListener('input', function () {
				window.clearTimeout(searchTimer);
				searchTimer = window.setTimeout(function () {
					state.search = search.value.trim();
					state.page = 1;
					load();
				}, 300);
			});
		}

		if (createButton && generatorUrl) {
			createButton.addEventListener('click', function () {
				openCreateDialog({ opener: createButton, generatorUrl: generatorUrl });
			});
		}

		load();
	}

	function initialise() {
		document.querySelectorAll('[data-tbtdd-tool]:not([data-tbtdd-ready])').forEach(function (root) {
			root.dataset.tbtddReady = 'true';

			if (root.getAttribute('data-tbtdd-tool') === 'generator') {
				initGenerator(root);
			} else {
				initLibrary(root);
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initialise);
	} else {
		initialise();
	}
})();
