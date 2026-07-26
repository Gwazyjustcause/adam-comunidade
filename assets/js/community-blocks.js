(function (blocks, element, components, blockEditor, i18n) {
	'use strict';
	var el = element.createElement;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var SelectControl = components.SelectControl;
	var RangeControl = components.RangeControl;
	var TextControl = components.TextControl;
	var ToggleControl = components.ToggleControl;
	var typeOptions = [
		{ label: 'Equipas Associadas', value: 'teams' },
		{ label: 'Campos', value: 'fields' },
		{ label: 'Parceiros', value: 'partners' },
		{ label: 'Instituições', value: 'institutions' },
		{ label: 'Marcas', value: 'brands' }
	];

	blocks.registerBlockType('adam-comunidade/community-section', {
		title: i18n.__('ADAM Community Section', 'adam-comunidade'),
		icon: 'screenoptions',
		category: 'widgets',
		edit: function (props) {
			var a = props.attributes;
			return el(element.Fragment, {},
				el(InspectorControls, {}, el(PanelBody, { title: i18n.__('Community content', 'adam-comunidade') },
					el(SelectControl, { label: i18n.__('Module', 'adam-comunidade'), value: a.type, options: typeOptions, onChange: function (value) { props.setAttributes({ type: value }); } }),
					el(RangeControl, { label: i18n.__('Number of cards', 'adam-comunidade'), min: 1, max: 24, value: a.number, onChange: function (value) { props.setAttributes({ number: value }); } }),
					el(SelectControl, { label: i18n.__('Order', 'adam-comunidade'), value: a.order, options: [{ label: 'Newest', value: 'newest' }, { label: 'Alphabetical', value: 'alphabetical' }, { label: 'Priority', value: 'priority' }], onChange: function (value) { props.setAttributes({ order: value }); } }),
					el(TextControl, { label: i18n.__('Category key', 'adam-comunidade'), value: a.category, onChange: function (value) { props.setAttributes({ category: value }); } }),
					el(ToggleControl, { label: i18n.__('Featured only', 'adam-comunidade'), checked: a.featured, onChange: function (value) { props.setAttributes({ featured: value }); } })
				)),
				el('div', { className: 'adam-block-placeholder' }, el('strong', {}, i18n.__('ADAM Community Section', 'adam-comunidade')), el('p', {}, typeOptions.filter(function (item) { return item.value === a.type; })[0].label + ' · ' + a.number + ' cards'))
			);
		},
		save: function () { return null; }
	});

	blocks.registerBlockType('adam-comunidade/community-highlight', {
		title: i18n.__('ADAM Community Spotlight', 'adam-comunidade'),
		icon: 'star-filled',
		category: 'widgets',
		edit: function (props) {
			return el('div', {}, el(SelectControl, { label: i18n.__('Spotlight', 'adam-comunidade'), value: props.attributes.type, options: [
				{ label: 'Featured Partner', value: 'featured_partner' }, { label: 'Newest Partner', value: 'newest_partner' }, { label: 'Random Partner', value: 'random_partner' }, { label: 'Featured Brand', value: 'featured_brand' }, { label: 'Institution Spotlight', value: 'institution_spotlight' }
			], onChange: function (value) { props.setAttributes({ type: value }); } }));
		},
		save: function () { return null; }
	});

	blocks.registerBlockType('adam-comunidade/community-map', {
		title: i18n.__('ADAM Community Map', 'adam-comunidade'),
		icon: 'location-alt',
		category: 'widgets',
		edit: function () { return el('div', { className: 'adam-block-placeholder' }, el('strong', {}, i18n.__('ADAM Community Map (Beta)', 'adam-comunidade')), el('p', {}, i18n.__('Teams, fields, partners, and institutions with coordinates.', 'adam-comunidade'))); },
		save: function () { return null; }
	});

	blocks.registerBlockType('adam-comunidade/live-statistics', {
		title: i18n.__('ADAM Live Statistics', 'adam-comunidade'),
		icon: 'chart-bar',
		category: 'widgets',
		edit: function () { return el('div', { className: 'adam-block-placeholder' }, el('strong', {}, i18n.__('Live Community Statistics', 'adam-comunidade'))); },
		save: function () { return null; }
	});

	blocks.registerBlockType('adam-comunidade/latest-news', {
		title: i18n.__('ADAM Latest News', 'adam-comunidade'),
		icon: 'megaphone',
		category: 'widgets',
		edit: function (props) {
			return el('div', {}, el(RangeControl, { label: i18n.__('Number of articles', 'adam-comunidade'), min: 1, max: 12, value: props.attributes.number, onChange: function (value) { props.setAttributes({ number: value }); } }));
		},
		save: function () { return null; }
	});

	blocks.registerBlockType('adam-comunidade/community-home', {
		title: i18n.__('ADAM Community Homepage', 'adam-comunidade'),
		icon: 'admin-home',
		category: 'widgets',
		edit: function () { return el('div', { className: 'adam-block-placeholder' }, el('strong', {}, i18n.__('Configured Community Homepage', 'adam-comunidade')), el('p', {}, i18n.__('Sections use the order configured under ADAM Comunidade → Homepage Builder.', 'adam-comunidade'))); },
		save: function () { return null; }
	});
}(window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor, window.wp.i18n));
