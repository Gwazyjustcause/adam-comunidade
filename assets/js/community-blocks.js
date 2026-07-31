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
		{ label: 'Equipas', value: 'teams' },
		{ label: 'Campos', value: 'fields' },
		{ label: 'Parceiros', value: 'partners' },
		{ label: 'Instituições', value: 'institutions' },
	];

	blocks.registerBlockType('adam-comunidade/community-section', {
		title: i18n.__('Secção da Comunidade ADAM', 'adam-comunidade'),
		icon: 'screenoptions',
		category: 'widgets',
		edit: function (props) {
			var a = props.attributes;
			var selectedType = typeOptions.filter(function (item) { return item.value === a.type; })[0] || { label: 'Parceiros' };
			return el(element.Fragment, {},
				el(InspectorControls, {}, el(PanelBody, { title: i18n.__('Conteúdo da Comunidade', 'adam-comunidade') },
					el(SelectControl, { label: i18n.__('Módulo', 'adam-comunidade'), value: a.type, options: typeOptions, onChange: function (value) { props.setAttributes({ type: value }); } }),
					el(RangeControl, { label: i18n.__('Número de cartões', 'adam-comunidade'), min: 1, max: 24, value: a.number, onChange: function (value) { props.setAttributes({ number: value }); } }),
					el(SelectControl, { label: i18n.__('Ordem', 'adam-comunidade'), value: a.order, options: [{ label: 'Mais recentes', value: 'newest' }, { label: 'Alfabética', value: 'alphabetical' }, { label: 'Prioridade', value: 'priority' }], onChange: function (value) { props.setAttributes({ order: value }); } }),
					el(TextControl, { label: i18n.__('Chave da categoria', 'adam-comunidade'), value: a.category, onChange: function (value) { props.setAttributes({ category: value }); } }),
					el(ToggleControl, { label: i18n.__('Apenas em destaque', 'adam-comunidade'), checked: a.featured, onChange: function (value) { props.setAttributes({ featured: value }); } })
				)),
				el('div', { className: 'adam-block-placeholder' }, el('strong', {}, i18n.__('Secção da Comunidade ADAM', 'adam-comunidade')), el('p', {}, selectedType.label + ' · ' + a.number + ' cartões'))
			);
		},
		save: function () { return null; }
	});

	blocks.registerBlockType('adam-comunidade/community-highlight', {
		title: i18n.__('Destaque da Comunidade ADAM', 'adam-comunidade'),
		icon: 'star-filled',
		category: 'widgets',
		edit: function (props) {
			return el('div', {}, el(SelectControl, { label: i18n.__('Destaque', 'adam-comunidade'), value: props.attributes.type, options: [
				{ label: 'Parceiro em destaque', value: 'featured_partner' }, { label: 'Parceiro mais recente', value: 'newest_partner' }, { label: 'Parceiro aleatório', value: 'random_partner' }, { label: 'Instituição em destaque', value: 'institution_spotlight' }
			], onChange: function (value) { props.setAttributes({ type: value }); } }));
		},
		save: function () { return null; }
	});

	blocks.registerBlockType('adam-comunidade/community-map', {
		title: i18n.__('Mapa da Comunidade ADAM', 'adam-comunidade'),
		icon: 'location-alt',
		category: 'widgets',
		edit: function () { return el('div', { className: 'adam-block-placeholder' }, el('strong', {}, i18n.__('Mapa da Comunidade ADAM (Beta)', 'adam-comunidade')), el('p', {}, i18n.__('Equipas, campos, parceiros e instituições com coordenadas.', 'adam-comunidade'))); },
		save: function () { return null; }
	});

	blocks.registerBlockType('adam-comunidade/live-statistics', {
		title: i18n.__('Estatísticas ADAM', 'adam-comunidade'),
		icon: 'chart-bar',
		category: 'widgets',
		edit: function () { return el('div', { className: 'adam-block-placeholder' }, el('strong', {}, i18n.__('Estatísticas da Comunidade', 'adam-comunidade'))); },
		save: function () { return null; }
	});

	blocks.registerBlockType('adam-comunidade/community-search', {
		title: i18n.__('Pesquisa da Comunidade ADAM', 'adam-comunidade'),
		icon: 'search',
		category: 'widgets',
		edit: function () { return el('div', { className: 'adam-block-placeholder' }, el('strong', {}, i18n.__('Pesquisa da Comunidade', 'adam-comunidade')), el('p', {}, i18n.__('Pesquisa equipas, campos, parceiros, instituições, notícias e eventos.', 'adam-comunidade'))); },
		save: function () { return null; }
	});

	function cardCountBlock(name, title, icon, description, maximum) {
		blocks.registerBlockType(name, {
			title: title,
			icon: icon,
			category: 'widgets',
			edit: function (props) {
				return el('div', { className: 'adam-block-placeholder' },
					el('strong', {}, title),
					el('p', {}, description),
					el(RangeControl, { label: i18n.__('Número de cartões', 'adam-comunidade'), min: 1, max: maximum, value: props.attributes.number, onChange: function (value) { props.setAttributes({ number: value }); } })
				);
			},
			save: function () { return null; }
		});
	}

	cardCountBlock('adam-comunidade/recent-records', i18n.__('Registos recentes da Comunidade', 'adam-comunidade'), 'clock', i18n.__('Apresenta os registos mais recentes de cada diretório.', 'adam-comunidade'), 6);
	cardCountBlock('adam-comunidade/newest-teams', i18n.__('Equipas recentes', 'adam-comunidade'), 'groups', i18n.__('Apresenta as equipas publicadas mais recentemente.', 'adam-comunidade'), 24);
	cardCountBlock('adam-comunidade/featured-fields', i18n.__('Campos destacados', 'adam-comunidade'), 'location', i18n.__('Apresenta apenas campos assinalados como destaque.', 'adam-comunidade'), 24);

	blocks.registerBlockType('adam-comunidade/latest-news', {
		title: i18n.__('Notícias recentes da ADAM', 'adam-comunidade'),
		icon: 'megaphone',
		category: 'widgets',
		edit: function (props) {
			return el('div', {}, el(RangeControl, { label: i18n.__('Número de artigos', 'adam-comunidade'), min: 1, max: 12, value: props.attributes.number, onChange: function (value) { props.setAttributes({ number: value }); } }));
		},
		save: function () { return null; }
	});

	blocks.registerBlockType('adam-comunidade/community-home', {
		title: i18n.__('Página inicial da Comunidade ADAM', 'adam-comunidade'),
		icon: 'admin-home',
		category: 'widgets',
		edit: function () { return el('div', { className: 'adam-block-placeholder' }, el('strong', {}, i18n.__('Página inicial da Comunidade', 'adam-comunidade')), el('p', {}, i18n.__('As secções são preenchidas automaticamente pelo ADAM Comunidade.', 'adam-comunidade'))); },
		save: function () { return null; }
	});
}(window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor, window.wp.i18n));
