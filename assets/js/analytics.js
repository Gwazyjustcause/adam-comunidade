(function () {
	'use strict';
	var config = window.adamAnalytics || {};
	function record(values) {
		var data = new FormData();
		data.append('action', 'adam_track_interaction');
		data.append('nonce', config.nonce);
		Object.keys(values).forEach(function (key) { data.append(key, values[key]); });
		if (navigator.sendBeacon) {
			navigator.sendBeacon(config.ajaxUrl, data);
		} else {
			fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data, keepalive: true }).catch(function () {});
		}
	}
	document.addEventListener('click', function (event) {
		var link = event.target.closest('.adam-community-card a[target="_blank"], .adam-contact-button');
		if (!link) { return; }
		var entity = link.closest('[data-entity-type]');
		record({ event_type: 'click', object_type: entity ? entity.dataset.entityType : 'community', object_id: entity ? entity.dataset.entityId : 0, dimension: link.href });
	});
	if ('IntersectionObserver' in window) {
		var seen = new WeakSet();
		var observer = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (entry.isIntersecting && !seen.has(entry.target)) {
					seen.add(entry.target);
					record({ event_type: 'widget', object_type: 'homepage', object_id: 0, dimension: entry.target.dataset.adamWidget });
				}
			});
		}, { threshold: 0.5 });
		document.querySelectorAll('[data-adam-widget]').forEach(function (widget) { observer.observe(widget); });
	}
}());
