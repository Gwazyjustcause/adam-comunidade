(function ($) {
	'use strict';

	$('[data-adam-tab]').on('click', function () {
		var tab = $(this).data('adam-tab');
		$('[data-adam-tab]').removeClass('button-primary');
		$(this).addClass('button-primary');
		$('[data-adam-panel]').prop('hidden', true);
		$('[data-adam-panel="' + tab + '"]').prop('hidden', false);
	});

	$('[data-adam-check-all]').on('change', function () {
		$('input[name="entry_ids[]"]').prop('checked', this.checked);
	});

	$('.adam-directory-delete').on('click', function (event) {
		if (!window.confirm(adamDirectoryAdmin.confirmDelete)) {
			event.preventDefault();
		}
	});

	$('[data-adam-select-media]').on('click', function () {
		var container = $(this).closest('[data-adam-media]');
		var type = container.data('media-type') || 'image';
		var frame = wp.media({
			title: adamDirectoryAdmin.mediaTitle,
			button: { text: adamDirectoryAdmin.useMedia },
			library: { type: type },
			multiple: false
		});
		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			container.find('input[type="hidden"]').val(attachment.id);
			container.find('[data-adam-preview]').html(attachment.type === 'image' ? '<img src="' + attachment.url + '" alt="">' : $('<div>').text(attachment.filename).html());
		});
		frame.open();
	});

	$('[data-adam-remove-media]').on('click', function () {
		var container = $(this).closest('[data-adam-media]');
		container.find('input[type="hidden"]').val('');
		container.find('[data-adam-preview]').empty();
	});

	$('[data-adam-gallery-add]').on('click', function () {
		var gallery = $('[data-adam-gallery]');
		var frame = wp.media({
			title: adamDirectoryAdmin.galleryTitle,
			button: { text: adamDirectoryAdmin.useMedia },
			library: { type: 'image' },
			multiple: true
		});
		frame.on('select', function () {
			frame.state().get('selection').each(function (model) {
				var item = model.toJSON();
				if (gallery.find('[data-id="' + item.id + '"]').length) {
					return;
				}
				var thumb = item.sizes && item.sizes.thumbnail ? item.sizes.thumbnail.url : item.url;
				gallery.append('<li data-id="' + item.id + '"><img src="' + thumb + '" alt=""><input type="hidden" name="entry[gallery_ids][]" value="' + item.id + '"><input type="text" name="entry[gallery_captions][' + item.id + ']" placeholder="Caption"><button type="button" class="button-link-delete" data-adam-gallery-remove>×</button></li>');
			});
		});
		frame.open();
	});

	$(document).on('click', '[data-adam-gallery-remove]', function () {
		$(this).closest('li').remove();
	});

	$('[data-adam-gallery]').sortable();

	var name = $('input[name="entry[name]"]');
	var slug = $('[data-adam-slug]');
	name.on('input', function () {
		if (!slug.data('edited')) {
			slug.val($(this).val().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''));
		}
	});
	slug.on('input', function () {
		$(this).data('edited', true);
	});
}(jQuery));
