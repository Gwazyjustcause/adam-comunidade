(function () {
	'use strict';
	var labels = window.adamCommunityMap || {};
	var districts = ['Aveiro', 'Viseu', 'Guarda', 'Coimbra', 'Castelo Branco', 'Leiria'];
	function normalize(value) {
		return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
	}
	function safeUrl(value) {
		try {
			var url = new URL(value, window.location.href);
			return /^(https?:)$/.test(url.protocol) ? url.href : '#';
		} catch (error) { return '#'; }
	}
	document.querySelectorAll('[data-adam-map]').forEach(function (widget) {
		var canvas = widget.querySelector('[data-map-canvas]');
		var results = widget.querySelector('[data-map-results]');
		var status = widget.querySelector('[data-map-status]');
		var errorNotice = widget.querySelector('[data-map-error]');
		var form = widget.querySelector('[data-map-filters]');
		if (!window.L) {
			errorNotice.textContent = labels.error;
			errorNotice.hidden = false;
			canvas.hidden = true;
			return;
		}
		var records;
		try { records = JSON.parse(widget.dataset.adamMap || '[]'); } catch (error) { records = []; }
		records = records.filter(function (item) {
			return ['team', 'field'].includes(item.type) && districts.includes(item.district) &&
				typeof item.latitude === 'number' && typeof item.longitude === 'number' &&
				item.latitude >= 39.15 && item.latitude <= 41.3 && item.longitude >= -9.6 && item.longitude <= -6.7;
		});
		var region = L.latLngBounds([[39.15, -9.6], [41.3, -6.7]]);
		var map = L.map(canvas, { scrollWheelZoom: false, zoomControl: false, minZoom: 7, maxZoom: 19,
			maxBounds: region.pad(0.2), maxBoundsViscosity: 0.8 });
		map.attributionControl.setPrefix(false);
		L.control.zoom({ zoomInTitle: labels.zoomIn, zoomOutTitle: labels.zoomOut }).addTo(map);
		var tiles = L.tileLayer(labels.tileUrl || 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
			maxZoom: 19, attribution: labels.tileAttribution || '&copy; OpenStreetMap'
		}).addTo(map);
		tiles.on('tileerror', function () { errorNotice.textContent = labels.tileError; errorNotice.hidden = false; });
		tiles.on('tileload', function () { errorNotice.hidden = true; });
		var layer = L.featureGroup().addTo(map);
		function overview() { map.fitBounds(region, { padding: [16, 16], animate: false }); }
		function popup(item) {
			var content = document.createElement('div');
			var name = document.createElement('strong');
			var location = document.createElement('p');
			var link = document.createElement('a');
			name.textContent = item.name;
			location.textContent = [labels.types[item.type], item.municipality, item.district].filter(Boolean).join(' · ');
			link.href = safeUrl(item.url);
			link.textContent = labels.details;
			content.append(name, location);
			if (item.approximate) {
				var note = document.createElement('small');
				note.textContent = labels.approximate;
				content.appendChild(note);
			}
			content.appendChild(link);
			return content;
		}
		function render() {
			var type = form.elements.type.value;
			var district = form.elements.district.value;
			var query = normalize(form.elements.search.value);
			var items = records.filter(function (item) {
				return (!type || item.type === type) && (!district || item.district === district) &&
					(!query || normalize([item.name, item.municipality, item.district].join(' ')).includes(query));
			});
			map.closePopup();
			layer.clearLayers();
			results.replaceChildren();
			status.textContent = items.length ? labels.count.replace('%d', items.length) : (records.length ? labels.noResults : labels.empty);
			var grouped = {};
			items.forEach(function (item) {
				var key = item.latitude + ',' + item.longitude;
				if (!grouped[key]) { grouped[key] = []; }
				grouped[key].push(item);
			});
			var markers = {};
			Object.keys(grouped).forEach(function (key) {
				var group = grouped[key];
				var item = group[0];
				var icon = L.divIcon({ className: 'adam-map-pin adam-map-pin--' + item.type,
					html: '<span aria-hidden="true">' + (group.length > 1 ? group.length : (item.type === 'team' ? 'E' : 'C')) + '</span>',
					iconSize: [32, 32], iconAnchor: [16, 16], popupAnchor: [0, -18] });
				var content = document.createElement('div');
				group.forEach(function (record) { content.appendChild(popup(record)); });
				markers[key] = L.marker([item.latitude, item.longitude], { icon: icon, title: group.map(function (record) { return record.name; }).join(', '), alt: item.name })
					.bindPopup(content, { maxHeight: 260 }).addTo(layer);
			});
			items.forEach(function (item) {
				var marker = markers[item.latitude + ',' + item.longitude];
				var result = document.createElement('article');
				var button = document.createElement('button');
				var location = document.createElement('p');
				var link = document.createElement('a');
				result.className = 'adam-map-result';
				result.dataset.type = item.type;
				button.type = 'button';
				button.textContent = item.name;
				button.addEventListener('click', function () {
					map.setView(marker.getLatLng(), (item.approximate ? 10 : Math.max(map.getZoom(), 13)), { animate: false });
					marker.openPopup();
				});
				marker.on('popupopen', function () {
					result.classList.add('is-active'); button.setAttribute('aria-pressed', 'true');
					var close = marker.getPopup().getElement().querySelector('.leaflet-popup-close-button');
					if (close) { close.setAttribute('aria-label', labels.closePopup); }
				});
				marker.on('popupclose', function () { result.classList.remove('is-active'); button.setAttribute('aria-pressed', 'false'); });
				location.textContent = [labels.types[item.type], item.municipality, item.district].filter(Boolean).join(' · ');
				link.textContent = labels.details;
				link.href = safeUrl(item.url);
				result.append(button, location);
				if (item.approximate) {
					var note = document.createElement('small');
					note.textContent = labels.approximate;
					result.appendChild(note);
				}
				result.appendChild(link);
				results.appendChild(result);
			});
			if (items.length) { map.fitBounds(layer.getBounds(), { padding: [36, 36], maxZoom: items.every(function (item) { return item.approximate; }) ? 10 : 13, animate: false }); }
			else { overview(); }
		}
		form.addEventListener('submit', function (event) { event.preventDefault(); render(); });
		form.addEventListener('change', render);
		form.elements.search.addEventListener('input', render);
		form.addEventListener('reset', function () { window.setTimeout(render, 0); });
		widget.querySelector('[data-map-reset]').addEventListener('click', overview);
		render();
		if (window.ResizeObserver) {
			new ResizeObserver(function () { map.invalidateSize({ pan: false }); }).observe(canvas);
		}
	});
}());
