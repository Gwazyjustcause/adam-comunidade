<?php
/**
 * Public directory of legally authorised airsoft fields.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Fields\Amenity_Repository;
use ADAM\Comunidade\Fields\Options;
use ADAM\Comunidade\Fields\Repository;
use ADAM\Comunidade\Fields\Router;
use ADAM\Comunidade\Fields\View;
use ADAM\Comunidade\Experience\Portal;
use ADAM\Comunidade\Managed_Pages;

$repository            = new Repository();
$query_search          = sanitize_text_field( (string) filter_input( INPUT_GET, 'search' ) );
$query_district        = sanitize_text_field( (string) filter_input( INPUT_GET, 'district' ) );
$query_municipality    = sanitize_text_field( (string) filter_input( INPUT_GET, 'municipality' ) );
$query_style           = sanitize_key( (string) filter_input( INPUT_GET, 'playing_style' ) );
$query_amenity         = absint( filter_input( INPUT_GET, 'amenity_id', FILTER_VALIDATE_INT ) ?: 0 );
$query_associated      = sanitize_key( (string) filter_input( INPUT_GET, 'associated' ) );
$query_sort            = sanitize_key( (string) filter_input( INPUT_GET, 'sort' ) );
$route_district        = Router::archive_location();
$selected_district     = $route_district ?: $query_district;
$sorts                 = array(
	'alphabetical' => array( 'name', 'ASC' ),
	'newest'       => array( 'created_at', 'DESC' ),
	'capacity'     => array( 'max_players', 'DESC' ),
);
$selected_sort         = $sorts[ $query_sort ] ?? $sorts['alphabetical'];
$result                = $repository->query(
	array(
		'status'                 => 'published',
		'legally_authorized'     => 1,
		'prioritize_associated'  => true,
		'associated'             => 'only' === $query_associated ? 1 : '',
		'search'                 => $query_search,
		'district'               => $selected_district,
		'municipality'           => $query_municipality,
		'playing_style'          => $query_style,
		'amenity_id'             => $query_amenity,
		'orderby'                => $selected_sort[0],
		'order'                  => $selected_sort[1],
		'per_page'               => 12,
	)
);
$statistics            = $repository->public_statistics();
$districts             = $repository->distinct( 'district', 'published' );
$municipalities        = $repository->distinct( 'municipality', 'published' );
$amenities             = ( new Amenity_Repository() )->all( 'field', true );
$submission_url        = Portal::submission_url( 'field' );
$directory_title       = get_the_title( Managed_Pages::id( 'fields' ) ) ?: __( 'Campos', 'adam-comunidade' );
$associated_fields     = array_values( array_filter( $result['items'], static fn( object $field ): bool => ! empty( $field->is_associated ) ) );
$independent_fields    = array_values( array_filter( $result['items'], static fn( object $field ): bool => empty( $field->is_associated ) ) );
$hero_field            = $associated_fields[0] ?? $result['items'][0] ?? null;
$hero_image_url        = $hero_field && ! empty( $hero_field->cover_id )
	? wp_get_attachment_image_url( (int) $hero_field->cover_id, 'adam-field-cover' )
	: false;
$hero_style            = $hero_image_url ? "--adam-fields-hero-image:url('" . esc_url_raw( $hero_image_url ) . "')" : '';

get_header();
?>
<main class="adam-comunidade adam-fields-archive" id="main">
	<section class="adam-fields-directory-hero"<?php echo $hero_style ? ' style="' . esc_attr( $hero_style ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<div class="adam-fields-container">
			<div class="adam-fields-hero-copy">
				<span><?php esc_html_e( 'ADAM Comunidade', 'adam-comunidade' ); ?></span>
				<h1><?php echo $route_district ? esc_html( sprintf( __( 'Campos em %s', 'adam-comunidade' ), $route_district ) ) : esc_html( $directory_title ); ?></h1>
				<p><?php esc_html_e( 'Descobre campos de airsoft legalmente autorizados no Centro de Portugal, consulta instalações, regras e localização.', 'adam-comunidade' ); ?></p>
				<a class="adam-field-button adam-field-submit-button" href="<?php echo esc_url( $submission_url ); ?>"><?php esc_html_e( 'Adicionar Campo', 'adam-comunidade' ); ?></a>
			</div>
			<div class="adam-fields-stats" aria-label="<?php esc_attr_e( 'Directory statistics', 'adam-comunidade' ); ?>">
				<div><span><?php esc_html_e( 'Associados ADAM', 'adam-comunidade' ); ?></span><strong><?php echo esc_html( (string) $statistics['associated'] ); ?></strong><small><?php esc_html_e( 'Com prioridade na listagem', 'adam-comunidade' ); ?></small></div>
				<div><span><?php esc_html_e( 'Outros Campos', 'adam-comunidade' ); ?></span><strong><?php echo esc_html( (string) $statistics['independent'] ); ?></strong><small><?php esc_html_e( 'Independentes, privados ou de equipas', 'adam-comunidade' ); ?></small></div>
				<div><span><?php esc_html_e( 'Autorização legal', 'adam-comunidade' ); ?></span><strong>100%</strong><small><?php esc_html_e( 'Apenas campos verificados', 'adam-comunidade' ); ?></small></div>
			</div>
		</div>
	</section>

	<div class="adam-fields-container adam-fields-directory-body">
		<form class="adam-field-filters" id="adam-field-filters">
			<label class="adam-field-filter-search"><span><?php esc_html_e( 'Pesquisar', 'adam-comunidade' ); ?></span><input type="search" name="search" value="<?php echo esc_attr( $query_search ); ?>" placeholder="<?php esc_attr_e( 'Nome do campo, localização…', 'adam-comunidade' ); ?>"></label>
			<label><span><?php esc_html_e( 'Distrito', 'adam-comunidade' ); ?></span><select name="district"><option value=""><?php esc_html_e( 'Todos', 'adam-comunidade' ); ?></option><?php foreach ( $districts as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $selected_district, $value ); ?>><?php echo esc_html( $value ); ?></option><?php endforeach; ?></select></label>
			<label><span><?php esc_html_e( 'Município', 'adam-comunidade' ); ?></span><select name="municipality"><option value=""><?php esc_html_e( 'Todos', 'adam-comunidade' ); ?></option><?php foreach ( $municipalities as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $query_municipality, $value ); ?>><?php echo esc_html( $value ); ?></option><?php endforeach; ?></select></label>
			<label><span><?php esc_html_e( 'Tipo de terreno', 'adam-comunidade' ); ?></span><select name="playing_style"><option value=""><?php esc_html_e( 'Todos', 'adam-comunidade' ); ?></option><?php foreach ( Options::playing_styles() as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $query_style, $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
			<label><span><?php esc_html_e( 'Instalações', 'adam-comunidade' ); ?></span><select name="amenity_id"><option value=""><?php esc_html_e( 'Todas', 'adam-comunidade' ); ?></option><?php foreach ( $amenities as $amenity ) : ?><option value="<?php echo esc_attr( (string) $amenity->id ); ?>" <?php selected( $query_amenity, (int) $amenity->id ); ?>><?php echo esc_html( $amenity->label ); ?></option><?php endforeach; ?></select></label>
			<label><span><?php esc_html_e( 'Associação', 'adam-comunidade' ); ?></span><select name="associated"><option value=""><?php esc_html_e( 'Todos os campos', 'adam-comunidade' ); ?></option><option value="only" <?php selected( $query_associated, 'only' ); ?>><?php esc_html_e( 'Apenas Associados ADAM', 'adam-comunidade' ); ?></option></select></label>
			<label><span><?php esc_html_e( 'Ordenar', 'adam-comunidade' ); ?></span><select name="sort"><option value="alphabetical" <?php selected( $query_sort, 'alphabetical' ); ?>><?php esc_html_e( 'Mais relevantes', 'adam-comunidade' ); ?></option><option value="newest" <?php selected( $query_sort, 'newest' ); ?>><?php esc_html_e( 'Mais recentes', 'adam-comunidade' ); ?></option><option value="capacity" <?php selected( $query_sort, 'capacity' ); ?>><?php esc_html_e( 'Maior capacidade', 'adam-comunidade' ); ?></option></select></label>
			<button class="adam-field-button" type="submit"><?php esc_html_e( 'Aplicar filtros', 'adam-comunidade' ); ?></button>
		</form>

		<p class="adam-field-results-count"><span id="adam-field-total"><?php echo esc_html( (string) $result['total'] ); ?></span> <?php esc_html_e( 'campos encontrados', 'adam-comunidade' ); ?></p>
		<div id="adam-field-results" aria-live="polite">
			<?php if ( $associated_fields ) : ?>
				<section class="adam-field-group adam-field-group--associated">
					<header><h2><?php esc_html_e( 'Campos Associados', 'adam-comunidade' ); ?></h2><p><?php esc_html_e( 'Campos com associação ativa à ADAM e prioridade na listagem.', 'adam-comunidade' ); ?></p></header>
					<div class="adam-field-grid"><?php foreach ( $associated_fields as $field ) : echo View::card( $field, $repository ); endforeach; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				</section>
			<?php endif; ?>
			<?php if ( $independent_fields ) : ?>
				<section class="adam-field-group">
					<header><h2><?php esc_html_e( 'Outros Campos', 'adam-comunidade' ); ?></h2><p><?php esc_html_e( 'Campos independentes, privados ou pertencentes a equipas, todos com autorização verificada.', 'adam-comunidade' ); ?></p></header>
					<div class="adam-field-grid"><?php foreach ( $independent_fields as $field ) : echo View::card( $field, $repository ); endforeach; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				</section>
			<?php endif; ?>
			<?php if ( ! $result['items'] ) : ?><div class="adam-comunidade__empty adam-fields-empty"><?php esc_html_e( 'Nenhum campo corresponde aos filtros selecionados.', 'adam-comunidade' ); ?></div><?php endif; ?>
		</div>
		<div id="adam-field-pagination"><?php echo View::pagination( 1, $result['pages'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>

		<section class="adam-field-submission-cta">
			<div>
				<span><?php esc_html_e( 'Diretório colaborativo', 'adam-comunidade' ); ?></span>
				<h2><?php esc_html_e( 'Queres que o teu campo apareça aqui?', 'adam-comunidade' ); ?></h2>
				<p><?php esc_html_e( 'Submete as informações e a prova de autorização. A equipa ADAM verifica manualmente cada pedido antes da publicação.', 'adam-comunidade' ); ?></p>
				<div class="adam-field-legal-summary"><strong><?php esc_html_e( 'Documento obrigatório', 'adam-comunidade' ); ?></strong><span><?php esc_html_e( 'Sem prova de autorização legal, o campo não será aprovado.', 'adam-comunidade' ); ?></span></div>
				<a class="adam-field-button" href="<?php echo esc_url( $submission_url ); ?>"><?php esc_html_e( 'Submeter Campo', 'adam-comunidade' ); ?></a>
			</div>
			<ol>
				<li><strong><?php esc_html_e( 'Preenche o formulário', 'adam-comunidade' ); ?></strong><span><?php esc_html_e( 'Indica os dados e contactos do campo.', 'adam-comunidade' ); ?></span></li>
				<li><strong><?php esc_html_e( 'Anexa a autorização', 'adam-comunidade' ); ?></strong><span><?php esc_html_e( 'Envia uma cópia legível do documento.', 'adam-comunidade' ); ?></span></li>
				<li><strong><?php esc_html_e( 'Análise pela ADAM', 'adam-comunidade' ); ?></strong><span><?php esc_html_e( 'A administração verifica dados, documento e fotografias.', 'adam-comunidade' ); ?></span></li>
				<li><strong><?php esc_html_e( 'Publicação', 'adam-comunidade' ); ?></strong><span><?php esc_html_e( 'O campo só aparece no diretório depois da aprovação.', 'adam-comunidade' ); ?></span></li>
			</ol>
		</section>
	</div>
</main>
<?php get_footer(); ?>
