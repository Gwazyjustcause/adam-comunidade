(function () {
	'use strict';
	var root = document.querySelector('[data-directory-type]');
	if (root) {
		var form = root.querySelector('[data-directory-filters]');
		var results = root.querySelector('[data-directory-results]');
		var pagination = root.querySelector('[data-directory-pagination]');
		var total = root.querySelector('[data-directory-total]');
		var requestController;
		var load = function (page, moveFocus) {
			if (requestController) { requestController.abort(); }
			requestController = new AbortController();
			var currentRequest = requestController;
			var data = new FormData(form);
			data.append('action', 'adam_filter_directory');
			data.append('nonce', adamDirectory.nonce);
			data.append('entity_type', root.dataset.directoryType);
			data.append('page_number', page || 1);
			results.setAttribute('aria-busy', 'true');
			form.setAttribute('aria-busy', 'true');
			fetch(adamDirectory.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data, signal: currentRequest.signal })
				.then(function (response) { if (!response.ok) { throw new Error(); } return response.json(); })
				.then(function (response) {
					if (!response.success) { throw new Error(); }
					results.innerHTML = response.data.cards;
					pagination.innerHTML = response.data.pagination;
					total.textContent = response.data.total;
					var url = new URL(window.location.href);
					url.search = '';
					new FormData(form).forEach(function (value, key) { if (value && 'all' !== value) { url.searchParams.set(key, value); } });
					if ((page || 1) > 1) { url.searchParams.set('pagina', page); }
					window.history.replaceState({}, '', url);
					if (moveFocus) {
						results.tabIndex = -1;
						results.focus({ preventScroll: true });
					}
				})
				.catch(function (error) {
					if ('AbortError' === error.name) { return; }
					var notice = document.createElement('div');
					notice.className = 'adam-comunidade__notice';
					notice.textContent = adamDirectory.error;
					results.replaceChildren(notice);
				})
				.finally(function () {
					if (requestController === currentRequest) {
						results.removeAttribute('aria-busy');
						form.removeAttribute('aria-busy');
					}
				});
		};
		form.addEventListener('submit', function (event) { event.preventDefault(); load(1); });
		form.addEventListener('change', function () { load(1); });
		pagination.addEventListener('click', function (event) {
			var button = event.target.closest('[data-page]');
			if (button) { event.preventDefault(); load(button.dataset.page, true); }
		});
	}

	var lightbox = document.querySelector('.adam-community-lightbox');
	if (lightbox) {
		var previousFocus = null;
		var closeLightbox = function () {
			lightbox.hidden = true;
			lightbox.querySelector('img').src = '';
			if (previousFocus) { previousFocus.focus(); }
		};
		document.addEventListener('click', function (event) {
			var link = event.target.closest('[data-directory-lightbox]');
			if (!link) { return; }
			event.preventDefault();
			previousFocus = link;
			lightbox.querySelector('img').src = link.href;
			lightbox.querySelector('img').alt = link.dataset.caption || '';
			lightbox.querySelector('figcaption').textContent = link.dataset.caption || '';
			lightbox.hidden = false;
			lightbox.querySelector('button').focus();
		});
		lightbox.querySelector('button').addEventListener('click', closeLightbox);
		lightbox.addEventListener('click', function (event) { if (event.target === lightbox) { closeLightbox(); } });
		document.addEventListener('keydown', function (event) { if (event.key === 'Escape' && !lightbox.hidden) { closeLightbox(); } });
		lightbox.addEventListener('keydown', function (event) {
			if (event.key === 'Tab') { event.preventDefault(); lightbox.querySelector('button').focus(); }
		});
	}
}());
