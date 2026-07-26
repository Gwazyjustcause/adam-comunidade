# ADAM Comunidade Developer Guide

ADAM Comunidade 6.x is organised as independently bootable modules. External
plugins can add a module through `adam_comunidade_register_modules` and can
disable one through `adam_comunidade_module_enabled`.

## Public API

Version 2 is available at both the WordPress REST namespace and friendly URLs:

- `/wp-json/adam-comunidade/v2/teams` or `/api/v2/teams`
- `/api/v2/fields`
- `/api/v2/partners`
- `/api/v2/brands`
- `/api/v2/institutions`
- `/api/v2/news`
- `/api/v2/search`
- `/api/v2/map`
- `/api/v2/statistics`

Collections accept `page`, `per_page`, `search`, `district`, `municipality`,
`playing_style`, `facility`, `category`, `recruitment`, `featured`, and `sort`
where applicable. The API is read-only. Use
`adam_comunidade_api_permission` to add an authentication policy and
`adam_comunidade_api_v2_response` to extend response records.

The ADAM Bot should query these endpoints instead of copying directory data.

## Shortcodes and blocks

- `[adam_community_home]`
- `[adam_community_section type="teams" number="6" order="newest"]`
- `[adam_community_map]`
- `[adam_community_statistics]`
- `[adam_latest_news number="6"]`
- `[adam_newest_teams]`, `[adam_random_team]`, `[adam_featured_field]`
- `[adam_nearby_fields]`, `[adam_popular_team]`, `[adam_random_brand]`
- Phase 4 spotlight shortcodes remain supported.
- `[adam_rich_media type="team" id="123"]`

Equivalent dynamic Gutenberg blocks are registered for community sections,
spotlights, maps, live statistics, and latest news.

## Templates

Copy a plugin template into the active theme under:

`adam-comunidade/{relative-template-path}`

For example:

`adam-comunidade/experience/community.php`

The `adam_comunidade_template_path` filter can provide a different absolute
path programmatically.

## Integration hooks

Important actions:

- `adam_comunidade_loaded`
- `adam_comunidade_register_modules`
- `adam_comunidade_experience_registered`
- `adam_comunidade_team_saved`
- `adam_comunidade_field_saved`
- `adam_comunidade_directory_entry_saved`
- `adam_comunidade_news_saved`
- `adam_comunidade_home_builder_saved`
- `adam_comunidade_import_completed`
- `adam_comunidade_reset_cache`
- `adam_comunidade_submission_received`
- `adam_comunidade_submission_moderated`
- `adam_comunidade_calendar_entry_published`
- `adam_comunidade_register_platform`
- `adam_comunidade_registry_added`
- `adam_comunidade_integrations_ready`
- `adam_comunidade_health_repaired`
- `adam_comunidade_import_batch_completed`
- `adam_comunidade_import_batch_rolled_back`

Important filters:

- `adam_comunidade_module_enabled`
- `adam_comunidade_directory_types`
- `adam_comunidade_search_results`
- `adam_comunidade_regions`
- `adam_comunidade_api_permission`
- `adam_comunidade_api_v2_response`
- `adam_comunidade_template_path`
- `adam_comunidade_image_output_formats`
- `adam_comunidade_profile_completeness`
- `adam_comunidade_calendar_types`
- `adam_comunidade_rich_media_types`
- `adam_comunidade_recruitment_statuses`
- `adam_comunidade_field_availability_statuses`

## Contribution and ownership workflow

Public submissions are stored in the moderation table and never written
directly to public directory tables. The supported states are `pending`,
`changes_requested`, `rejected`, and `published`. Claim approval creates a
verified row in the listing-owner table. Owners edit through
`/painel-comunidade`; every change creates another moderated submission.

Submission routes:

- `/submeter-equipa`
- `/submeter-campo`
- `/submeter-parceiro`
- `/submeter-instituicao`

Trust badges are deliberately excluded from public and owner payloads. Only an
administrator can assign them in the normal editors.

## Platform registries

Future modules may register content types, filters, map layers, widget types,
editor fields, or integration metadata through the shared registry:

```php
add_action(
	'adam_comunidade_register_platform',
	static function ( string $registry ): void {
		$registry::add(
			'map_layers',
			'events',
			array( 'label' => 'Events', 'provider' => My_Event_Layer::class )
		);
	}
);
```

Read definitions with
`ADAM\Comunidade\Experience\Registry::all( 'map_layers' )`. Registry names are
open-ended and filtered through `adam_comunidade_registry_{registry}`.

## Calendar and rich media

`/calendario` exposes published announcements, open days, recruitment dates,
and training sessions. Add event types with `adam_comunidade_calendar_types`.
The rich-media registry supports 360 images, YouTube, Instagram, virtual tours,
and downloads, and may be extended through
`adam_comunidade_rich_media_types`.

## Optional integrations

ADAM Members, ADAM Bot, and ADAM Events are detected without hard
dependencies. Their availability is exposed through the `integrations`
platform registry and the `adam_comunidade_integrations_ready` action.

## Shared ADAM Upload component

All public file inputs and administrative Media Library selectors use
`ADAM\Comunidade\Uploads\Component`. The component owns the markup, thumbnails,
document cards, counts, replacement/removal actions, drag ordering, local file
previews, and shared `adam-comunidade-upload` assets.

It supports four configurations through the same `render()` method:

```php
use ADAM\Comunidade\Uploads\Component;

Component::render(
	array(
		'mode'     => 'file', // `file` for a form or `library` for wp-admin.
		'kind'     => 'image', // `image` or `document`.
		'name'     => 'photos[]',
		'multiple' => true,
		'max'      => 5,
		'accept'   => '.jpg,.jpeg,.png,.webp',
	)
);
```

For Media Library mode, pass initial items created with
`Component::attachment( $attachment_id )`. Use `caption_pattern` with an
`__ID__` placeholder when gallery captions are required. Enqueue the common
assets with `Component::enqueue_assets()`.

The stable `data-adam-upload*` markup contract, asset handles, and
`adam_upload_component_config` filter are intended to be lifted into the
shared ADAM platform layer when ADAM Members adopts the uploader.

## ADAM Members

An integration can return the total player count through
`adam_comunidade_members_count`. It can determine whether the current visitor
is a verified member through `adam_comunidade_current_user_is_member`.
Member-only partner benefits are never included in public REST responses.

## Third-party modules

Implement `ADAM\Comunidade\Module_Interface` and add the instance to the module
manager:

```php
add_action(
	'adam_comunidade_register_modules',
	static function ( $manager ): void {
		$manager->add( new My_Community_Module() );
	}
);
```

Module storage should retain stable integer IDs. Cross-module relationships can
use `ADAM\Comunidade\Directory\Relationship_Repository` with custom relation
and target-type keys.
