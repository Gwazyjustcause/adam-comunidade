(function () {
	'use strict';
	var config = window.adamExperience || {};

	function post(action, values) {
		var data = new FormData();
		data.append('action', action);
		data.append('nonce', config.nonce);
		Object.keys(values || {}).forEach(function (key) { data.append(key, values[key]); });
		return fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data })
			.then(function (response) { return response.json(); })
			.then(function (response) { if (!response.success) { throw new Error(); } return response.data; });
	}

	document.querySelectorAll('.adam-universal-search').forEach(function (search) {
		var input = search.querySelector('[data-adam-universal-query]');
		var button = search.querySelector('[data-adam-universal-submit]');
		var results = search.querySelector('[data-adam-universal-results]');
		var run = function () {
			results.setAttribute('aria-busy', 'true');
			post('adam_universal_search', { search: input.value })
				.then(function (groups) {
					results.innerHTML = '';
					Object.keys(groups).forEach(function (group) {
						if (!groups[group].length) { return; }
						var section = document.createElement('section');
						var heading = document.createElement('h3');
						heading.textContent = group.charAt(0).toUpperCase() + group.slice(1);
						section.appendChild(heading);
						groups[group].forEach(function (item) {
							var link = document.createElement('a');
							link.href = item.url;
							link.className = 'adam-search-result';
							link.innerHTML = '<span class="dashicons dashicons-' + item.icon + '" aria-hidden="true"></span><span><strong></strong><small></small></span>';
							link.querySelector('strong').textContent = item.name;
							link.querySelector('small').textContent = item.description || item.district || '';
							section.appendChild(link);
						});
						results.appendChild(section);
					});
					if (!results.children.length) { results.textContent = config.labels.empty; }
				})
				.catch(function () { results.textContent = config.labels.error; })
				.finally(function () { results.removeAttribute('aria-busy'); });
		};
		button.addEventListener('click', run);
		input.addEventListener('keydown', function (event) { if (event.key === 'Enter') { event.preventDefault(); run(); } });
	});

	document.querySelectorAll('[data-adam-advanced-map]').forEach(function (widget) {
		var canvas = widget.querySelector('[data-adam-map-canvas]');
		var list = widget.querySelector('[data-adam-map-results]');
		var form = widget.querySelector('[data-adam-map-filters]');
		var markers;
		try { markers = JSON.parse(widget.dataset.markers || '[]'); } catch (error) { markers = []; }

		function select(id) {
			widget.querySelectorAll('[data-record-id]').forEach(function (element) {
				var active = element.dataset.recordId === id;
				element.classList.toggle('is-active', active);
				element.setAttribute('aria-selected', active ? 'true' : 'false');
			});
			var target = list.querySelector('[data-record-id="' + CSS.escape(id) + '"]');
			if (target) { target.scrollIntoView({ block: 'nearest', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' }); }
		}

		function render(items) {
			canvas.innerHTML = '';
			list.innerHTML = '';
			items.forEach(function (item) {
				var id = item.type + '-' + item.id;
				var x = Math.max(3, Math.min(97, ((item.longitude + 9.6) / 3.4) * 100));
				var y = Math.max(4, Math.min(96, (1 - ((item.latitude - 36.8) / 5.5)) * 100));
				var marker = document.createElement('button');
				marker.type = 'button';
				marker.className = 'adam-map-marker';
				marker.dataset.type = item.type;
				marker.dataset.recordId = id;
				marker.style.left = x + '%';
				marker.style.top = y + '%';
				marker.setAttribute('aria-label', item.name);
				marker.addEventListener('click', function () { select(id); });
				canvas.appendChild(marker);

				var result = document.createElement('article');
				result.className = 'adam-map-result';
				result.dataset.recordId = id;
				result.setAttribute('aria-selected', 'false');
				result.tabIndex = 0;
				result.innerHTML = '<span data-type="' + item.type + '"></span><div><strong></strong><small></small></div><a></a>';
				result.querySelector('strong').textContent = item.name;
				result.querySelector('small').textContent = [item.municipality, item.district].filter(Boolean).join(', ');
				result.querySelector('a').href = item.url;
				result.querySelector('a').textContent = config.labels.view;
				result.addEventListener('click', function (event) { if (!event.target.closest('a')) { select(id); } });
				result.addEventListener('keydown', function (event) { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); select(id); } });
				list.appendChild(result);
			});
			if (!items.length) { list.textContent = config.labels.empty; }
		}
		render(markers);
		if (form) {
			form.addEventListener('change', function () {
				var values = {};
				new FormData(form).forEach(function (value, key) { values[key] = value; });
				post('adam_community_map', values).then(render).catch(function () { list.textContent = config.labels.error; });
			});
		}
	});

	function formatUploadLabel(template, first, second) {
		return String(template || '')
			.replace('%1$d', first)
			.replace('%2$d', second)
			.replace('%d', first);
	}

	document.querySelectorAll('[data-adam-multi-upload]').forEach(function (dropZone) {
		var input = dropZone.querySelector('input[type="file"]');
		var status = dropZone.querySelector('[data-adam-upload-status]');
		var limit = parseInt(dropZone.dataset.maxFiles || '1', 10);
		var selected = [];

		function key(file) {
			return [file.name, file.size, file.lastModified].join(':');
		}

		function showError(message) {
			var error = dropZone.querySelector('.adam-field-error--client');
			if (!error) {
				error = document.createElement('span');
				error.className = 'adam-field-error adam-field-error--client';
				dropZone.appendChild(error);
			}
			error.textContent = message;
			input.setAttribute('aria-invalid', 'true');
		}

		function clearError() {
			var error = dropZone.querySelector('.adam-field-error--client');
			if (error) { error.remove(); }
			input.removeAttribute('aria-invalid');
		}

		function commit(files) {
			var known = new Set(selected.map(key));
			Array.from(files || []).forEach(function (file) {
				if (!known.has(key(file))) {
					selected.push(file);
					known.add(key(file));
				}
			});
			if (selected.length > limit) {
				selected = selected.slice(0, limit);
				showError(formatUploadLabel((config.upload || {}).limit, limit));
			} else {
				clearError();
			}
			var transfer = new DataTransfer();
			selected.forEach(function (file) { transfer.items.add(file); });
			input.files = transfer.files;
			var count = selected.length;
			var label = formatUploadLabel((config.upload || {}).selected, count, limit);
			if (count < limit) {
				label += ' ' + formatUploadLabel((config.upload || {}).remaining, limit - count);
			}
			status.textContent = label;
		}

		input.addEventListener('change', function () { commit(input.files); });
		['dragenter', 'dragover'].forEach(function (eventName) {
			dropZone.addEventListener(eventName, function (event) {
				event.preventDefault();
				dropZone.classList.add('is-dragover');
				status.textContent = (config.upload || {}).drop || status.textContent;
			});
		});
		['dragleave', 'drop'].forEach(function (eventName) {
			dropZone.addEventListener(eventName, function (event) {
				event.preventDefault();
				dropZone.classList.remove('is-dragover');
				if ('drop' === eventName) { commit(event.dataTransfer.files); }
			});
		});
	});

	document.querySelectorAll('.adam-portal-form').forEach(function (form) {
		form.addEventListener('submit', function (event) {
			form.querySelectorAll('.adam-field-error--client').forEach(function (error) { error.remove(); });
			var invalid = [];
			form.querySelectorAll('input, textarea, select').forEach(function (control) {
				if (control.disabled || control.checkValidity()) { return; }
				invalid.push(control);
				control.setAttribute('aria-invalid', 'true');
				var label = control.closest('label');
				if (!label) { return; }
				var error = document.createElement('span');
				error.className = 'adam-field-error adam-field-error--client';
				error.textContent = control.validationMessage;
				label.appendChild(error);
			});
			if (!invalid.length) { return; }
			event.preventDefault();
			invalid[0].scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'center' });
			invalid[0].focus({ preventScroll: true });
		});
	});

	var serverError = document.querySelector('.adam-portal-form [aria-invalid="true"]');
	if (serverError) {
		window.requestAnimationFrame(function () {
			serverError.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'center' });
			serverError.focus({ preventScroll: true });
		});
	}

}());
