(function () {
	'use strict';
	document.querySelectorAll('[data-adam-map]').forEach(function (widget) {
		var canvas = widget.querySelector('.adam-map-canvas');
		var preview = widget.querySelector('.adam-map-preview');
		var markers;
		try { markers = JSON.parse(widget.dataset.adamMap || '[]'); } catch (error) { markers = []; }
		markers.forEach(function (marker) {
			var button = document.createElement('button');
			var x = Math.max(3, Math.min(97, ((marker.lng + 9.6) / 3.4) * 100));
			var y = Math.max(4, Math.min(96, (1 - ((marker.lat - 36.8) / 5.5)) * 100));
			button.type = 'button';
			button.className = 'adam-map-marker';
			button.dataset.type = marker.type;
			button.style.left = x + '%';
			button.style.top = y + '%';
			button.setAttribute('aria-label', marker.name);
			button.addEventListener('click', function () {
				preview.innerHTML = '<strong></strong><span></span><a></a>';
				preview.querySelector('strong').textContent = marker.name;
				preview.querySelector('span').textContent = marker.type;
				preview.querySelector('a').textContent = 'View details';
				preview.querySelector('a').href = marker.url;
				preview.style.left = Math.min(75, x) + '%';
				preview.style.top = Math.min(75, y + 5) + '%';
				preview.hidden = false;
			});
			canvas.appendChild(button);
		});
		if (!markers.length) {
			preview.textContent = 'No geolocated community content is available yet.';
			preview.style.left = '20px';
			preview.style.top = '20px';
			preview.hidden = false;
		}
	});
}());
