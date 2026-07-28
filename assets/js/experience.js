(function () {
	'use strict';
	var config = window.adamExperience || {};

	function safeUrl(value) {
		try {
			var url = new URL(value, window.location.origin);
			return ['http:', 'https:'].includes(url.protocol) ? url.href : '#';
		} catch (error) {
			return '#';
		}
	}

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
						heading.textContent = config.groupLabels?.[ group ] || group;
						section.appendChild(heading);
						groups[group].forEach(function (item) {
							var link = document.createElement('a');
							link.href = safeUrl(item.url);
							link.className = 'adam-search-result';
							var icon = document.createElement('span');
							icon.className = 'dashicons dashicons-' + String(item.icon || '').replace(/[^a-z0-9-]/gi, '');
							icon.setAttribute('aria-hidden', 'true');
							var copy = document.createElement('span');
							var name = document.createElement('strong');
							var description = document.createElement('small');
							name.textContent = item.name;
							description.textContent = item.description || item.district || '';
							copy.append(name, description);
							link.append(icon, copy);
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
				var type = document.createElement('span');
				type.dataset.type = String(item.type || '').replace(/[^a-z0-9_-]/gi, '');
				var copy = document.createElement('div');
				var name = document.createElement('strong');
				var location = document.createElement('small');
				var link = document.createElement('a');
				name.textContent = item.name;
				location.textContent = [item.municipality, item.district].filter(Boolean).join(', ');
				copy.append(name, location);
				link.href = safeUrl(item.url);
				link.textContent = config.labels.view;
				result.append(type, copy, link);
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

	document.querySelectorAll('.adam-portal-form').forEach(function (form, formIndex) {
		form.addEventListener('submit', function (event) {
			form.querySelectorAll('.adam-field-error--client').forEach(function (error) { error.remove(); });
			var invalid = [];
			form.querySelectorAll('input, textarea, select').forEach(function (control, index) {
				if (control.disabled || control.checkValidity()) {
					if ((control.getAttribute('aria-describedby') || '').includes('adam-client-error-')) {
						control.removeAttribute('aria-describedby');
						control.removeAttribute('aria-invalid');
					}
					return;
				}
				invalid.push(control);
				control.setAttribute('aria-invalid', 'true');
				var label = control.closest('label, .adam-portal-upload-field');
				if (!label) { return; }
				var error = document.createElement('span');
				error.className = 'adam-field-error adam-field-error--client';
				error.id = 'adam-client-error-' + formIndex + '-' + index;
				error.textContent = control.validationMessage;
				control.setAttribute('aria-describedby', error.id);
				label.appendChild(error);
			});
			if (!invalid.length) {
				form.setAttribute('aria-busy', 'true');
				window.requestAnimationFrame(function () {
					var submit = form.querySelector('button[type="submit"], input[type="submit"]');
					if (submit) {
						submit.disabled = true;
						if ('BUTTON' === submit.tagName) { submit.textContent = 'A processar…'; }
					}
				});
				return;
			}
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
