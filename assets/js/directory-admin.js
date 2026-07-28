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
		event.preventDefault();
		var link = this;
		var confirmAction = window.adamConfirm
			? window.adamConfirm(adamDirectoryAdmin.confirmDelete)
			: Promise.resolve(window.confirm(adamDirectoryAdmin.confirmDelete));
		confirmAction.then(function (confirmed) {
			if (confirmed) {
				window.location.assign(link.href);
			}
		});
	});

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
