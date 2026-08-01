<?php
/**
 * Reusable ADAM upload component.
 *
 * @package ADAM_Comunidade
 */

namespace ADAM\Comunidade\Uploads;

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Helpers;

/**
 * Renders one upload contract for public files and wp-admin attachments.
 */
final class Component {
	/**
	 * Enqueues the shared uploader assets.
	 */
	public static function enqueue_assets(): void {
		wp_enqueue_style(
			'adam-comunidade-upload',
			Helpers::url( 'assets/css/upload.css' ),
			array(),
			ADAM_COMUNIDADE_VERSION
		);
		wp_enqueue_script(
			'adam-comunidade-upload',
			Helpers::url( 'assets/js/upload.js' ),
			array(),
			ADAM_COMUNIDADE_VERSION,
			true
		);
		wp_localize_script(
			'adam-comunidade-upload',
			'adamUpload',
			array(
				'addImage'       => __( 'Adicionar fotografia', 'adam-comunidade' ),
				'addDocument'    => __( 'Adicionar documento', 'adam-comunidade' ),
				'replace'        => __( 'Substituir', 'adam-comunidade' ),
				'remove'         => __( 'Remover', 'adam-comunidade' ),
				'dragHint'       => __( 'Arraste para alterar a ordem', 'adam-comunidade' ),
				'dropHint'       => __( 'Largue os ficheiros aqui', 'adam-comunidade' ),
				'uploading'      => __( 'A preparar o envio…', 'adam-comunidade' ),
				'imageCount'     => __( '%1$d / %2$d fotografias', 'adam-comunidade' ),
				'documentCount'  => __( '%1$d / %2$d documentos', 'adam-comunidade' ),
				'limit'          => __( 'Pode selecionar no máximo %d ficheiros.', 'adam-comunidade' ),
				'invalidType'    => __( 'Este tipo de ficheiro não é permitido.', 'adam-comunidade' ),
				'tooLarge'       => __( 'O ficheiro excede o limite de %d MB.', 'adam-comunidade' ),
				'mediaTitle'     => __( 'Selecionar ficheiros', 'adam-comunidade' ),
				'useMedia'       => __( 'Usar ficheiros selecionados', 'adam-comunidade' ),
				'unknownSize'    => __( 'Tamanho não disponível', 'adam-comunidade' ),
				'file'           => __( 'Ficheiro', 'adam-comunidade' ),
				'caption'        => __( 'Legenda', 'adam-comunidade' ),
				'moveEarlier'    => __( 'Mover para trás', 'adam-comunidade' ),
				'moveLater'      => __( 'Mover para a frente', 'adam-comunidade' ),
				'reordered'      => __( 'A ordem dos ficheiros foi alterada.', 'adam-comunidade' ),
				'duplicate'      => __( 'Este ficheiro já foi selecionado.', 'adam-comunidade' ),
				'added'          => __( 'Ficheiro adicionado.', 'adam-comunidade' ),
				'removed'        => __( 'Ficheiro removido.', 'adam-comunidade' ),
			)
		);
	}

	/**
	 * Renders the component.
	 *
	 * @param array<string,mixed> $config Component configuration.
	 */
	public static function render( array $config ): void {
		/**
		 * Filters the shared uploader configuration before defaults are applied.
		 *
		 * @param array<string,mixed> $config Component configuration.
		 */
		$config = (array) apply_filters( 'adam_upload_component_config', $config );
		$config = wp_parse_args(
			$config,
			array(
				'id'              => wp_unique_id( 'adam-upload-' ),
				'mode'            => 'file',
				'kind'            => 'image',
				'name'            => '',
				'label'           => '',
				'accept'          => '',
				'multiple'        => false,
				'max'             => 1,
				'max_size_mb'     => 10,
				'existing_count'  => 0,
				'existing_name'   => '',
				'order_name'      => '',
				'required'        => false,
				'items'           => array(),
				'error'           => '',
				'caption_pattern' => '',
				'toggle_pattern'  => '',
				'toggle_label'    => '',
			)
		);
		$id       = sanitize_html_class( (string) $config['id'] );
		$mode     = 'library' === $config['mode'] ? 'library' : 'file';
		$kind     = 'document' === $config['kind'] ? 'document' : 'image';
		$multiple = ! empty( $config['multiple'] );
		$max      = $multiple ? max( 1, absint( $config['max'] ) ) : 1;
		$items    = array_slice( (array) $config['items'], 0, $max );
		$is_full  = count( $items ) >= $max;
		$label    = trim( (string) $config['label'] ) ?: ( 'image' === $kind ? __( 'Selecionar fotografia', 'adam-comunidade' ) : __( 'Selecionar documento', 'adam-comunidade' ) );
		?>
		<div
			id="<?php echo esc_attr( $id ); ?>"
			class="adam-upload adam-upload--<?php echo esc_attr( $kind ); ?><?php echo $is_full ? ' is-full' : ''; ?><?php echo $config['error'] ? ' has-error' : ''; ?>"
			data-adam-upload
			data-mode="<?php echo esc_attr( $mode ); ?>"
			data-kind="<?php echo esc_attr( $kind ); ?>"
			data-max="<?php echo esc_attr( (string) $max ); ?>"
			data-existing-count="<?php echo esc_attr( (string) max( 0, absint( $config['existing_count'] ) ) ); ?>"
			data-name="<?php echo esc_attr( (string) $config['name'] ); ?>"
			data-accept="<?php echo esc_attr( (string) $config['accept'] ); ?>"
			data-max-size="<?php echo esc_attr( (string) max( 1, absint( $config['max_size_mb'] ) ) ); ?>"
			data-caption-pattern="<?php echo esc_attr( (string) $config['caption_pattern'] ); ?>"
			data-toggle-pattern="<?php echo esc_attr( (string) $config['toggle_pattern'] ); ?>"
			data-toggle-label="<?php echo esc_attr( (string) $config['toggle_label'] ); ?>"
			data-order-name="<?php echo esc_attr( (string) $config['order_name'] ); ?>"
		>
			<?php if ( 'file' === $mode ) : ?>
				<input class="adam-upload__input" data-adam-upload-input type="file" name="<?php echo esc_attr( (string) $config['name'] ); ?>" aria-label="<?php echo esc_attr( $label ); ?>" accept="<?php echo esc_attr( (string) $config['accept'] ); ?>" <?php echo $multiple ? 'multiple' : ''; ?> <?php echo $config['required'] ? 'required' : ''; ?> <?php echo $config['error'] ? 'aria-invalid="true" aria-describedby="' . esc_attr( $id . '-error' ) . '"' : ''; ?>>
			<?php elseif ( ! $multiple ) : ?>
				<input data-adam-upload-value type="hidden" name="<?php echo esc_attr( (string) $config['name'] ); ?>" value="<?php echo esc_attr( (string) absint( $items[0]['id'] ?? 0 ) ); ?>">
			<?php endif; ?>

			<div class="adam-upload__toolbar">
				<strong data-adam-upload-count aria-live="polite"><?php echo esc_html( self::count_label( count( $items ), $max, $kind ) ); ?></strong>
				<?php if ( $multiple ) : ?><span><?php esc_html_e( 'Arraste para alterar a ordem', 'adam-comunidade' ); ?></span><?php endif; ?>
			</div>
			<p class="screen-reader-text" data-adam-upload-live aria-live="polite"></p>

			<div class="adam-upload__list" data-adam-upload-list role="list">
				<?php foreach ( $items as $item ) : ?>
					<?php self::render_item( (array) $item, $config, $multiple, $kind ); ?>
				<?php endforeach; ?>
				<button class="adam-upload__add" type="button" data-adam-upload-add>
					<span aria-hidden="true">+</span>
					<strong><?php echo esc_html( 'image' === $kind ? __( 'Adicionar fotografia', 'adam-comunidade' ) : __( 'Adicionar documento', 'adam-comunidade' ) ); ?></strong>
					<small><?php esc_html_e( 'Clique ou arraste para aqui', 'adam-comunidade' ); ?></small>
				</button>
			</div>

			<?php if ( 'file' === $mode ) : ?>
				<div class="adam-upload__progress" data-adam-upload-progress hidden>
					<span></span><small><?php esc_html_e( 'A preparar o envio…', 'adam-comunidade' ); ?></small>
				</div>
			<?php endif; ?>
			<?php if ( $config['error'] ) : ?><span class="adam-field-error" id="<?php echo esc_attr( $id . '-error' ); ?>" role="alert"><?php echo esc_html( (string) $config['error'] ); ?></span><?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Builds attachment data for an existing Media Library item.
	 *
	 * @return array<string,mixed>
	 */
	public static function attachment( int $attachment_id, string $caption = '' ): array {
		$file = get_attached_file( $attachment_id );
		$mime = (string) get_post_mime_type( $attachment_id );
		return array(
			'id'       => $attachment_id,
			'url'      => wp_attachment_is_image( $attachment_id ) ? (string) wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) : '',
			'filename' => $file ? wp_basename( $file ) : (string) get_the_title( $attachment_id ),
			'mime'     => $mime,
			'size'     => $file && is_readable( $file ) ? size_format( (int) filesize( $file ), 1 ) : '',
			'caption'  => $caption,
		);
	}

	/**
	 * @param array<string,mixed> $item Item data.
	 * @param array<string,mixed> $config Component configuration.
	 */
	private static function render_item( array $item, array $config, bool $multiple, string $kind ): void {
		$id       = absint( $item['id'] ?? 0 );
		$filename = (string) ( $item['filename'] ?? '' );
		$mime     = (string) ( $item['mime'] ?? '' );
		?>
		<article class="adam-upload__item" data-adam-upload-item data-id="<?php echo esc_attr( (string) $id ); ?>" draggable="<?php echo $multiple ? 'true' : 'false'; ?>" role="listitem" <?php echo $multiple ? 'tabindex="0" aria-label="' . esc_attr( $filename . '. ' . __( 'Use Alt e as setas para alterar a ordem.', 'adam-comunidade' ) ) . '"' : ''; ?>>
			<?php if ( $multiple && 'library' === $config['mode'] ) : ?><input data-adam-upload-item-value type="hidden" name="<?php echo esc_attr( (string) $config['name'] ); ?>" value="<?php echo esc_attr( (string) $id ); ?>"><?php endif; ?>
			<?php if ( $multiple && 'file' === $config['mode'] && $id && ! empty( $config['existing_name'] ) ) : ?><input data-adam-upload-existing-value type="hidden" name="<?php echo esc_attr( (string) $config['existing_name'] ); ?>" value="<?php echo esc_attr( (string) $id ); ?>"><?php endif; ?>
			<?php if ( $multiple && 'file' === $config['mode'] && $id && ! empty( $config['order_name'] ) ) : ?><input data-adam-upload-order-value type="hidden" name="<?php echo esc_attr( (string) $config['order_name'] ); ?>" value="existing:<?php echo esc_attr( (string) $id ); ?>"><?php endif; ?>
			<div class="adam-upload__preview">
				<?php if ( 'image' === $kind && ! empty( $item['url'] ) ) : ?>
					<img src="<?php echo esc_url( (string) $item['url'] ); ?>" alt="">
				<?php else : ?>
					<span class="adam-upload__document-icon" aria-hidden="true">📄</span>
				<?php endif; ?>
				<div class="adam-upload__actions">
					<button type="button" data-adam-upload-replace><?php esc_html_e( 'Substituir', 'adam-comunidade' ); ?></button>
					<button type="button" data-adam-upload-remove><?php esc_html_e( 'Remover', 'adam-comunidade' ); ?></button>
				</div>
			</div>
			<div class="adam-upload__meta">
				<strong title="<?php echo esc_attr( $filename ); ?>"><?php echo esc_html( $filename ); ?></strong>
				<small><span aria-hidden="true">✓</span> <?php echo esc_html( self::file_type_label( $mime, $filename ) ); ?><?php echo ! empty( $item['size'] ) ? ' · ' . esc_html( (string) $item['size'] ) : ''; ?></small>
				<?php if ( $multiple ) : ?>
					<div class="adam-upload__order" aria-label="<?php esc_attr_e( 'Alterar posição', 'adam-comunidade' ); ?>">
						<button type="button" data-adam-upload-move="-1" aria-label="<?php echo esc_attr( sprintf( __( 'Mover %s para trás', 'adam-comunidade' ), $filename ) ); ?>">←</button>
						<button type="button" data-adam-upload-move="1" aria-label="<?php echo esc_attr( sprintf( __( 'Mover %s para a frente', 'adam-comunidade' ), $filename ) ); ?>">→</button>
					</div>
				<?php endif; ?>
				<?php if ( $multiple && ! empty( $config['caption_pattern'] ) ) : ?>
					<input type="text" name="<?php echo esc_attr( str_replace( '__ID__', (string) $id, (string) $config['caption_pattern'] ) ); ?>" value="<?php echo esc_attr( (string) ( $item['caption'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Legenda', 'adam-comunidade' ); ?>">
				<?php endif; ?>
				<?php if ( $multiple && ! empty( $config['toggle_pattern'] ) ) : ?>
					<label class="adam-upload__toggle">
						<input type="checkbox" name="<?php echo esc_attr( str_replace( '__ID__', (string) $id, (string) $config['toggle_pattern'] ) ); ?>" value="1" <?php checked( ! isset( $item['enabled'] ) || ! empty( $item['enabled'] ) ); ?>>
						<?php echo esc_html( (string) $config['toggle_label'] ); ?>
					</label>
				<?php endif; ?>
			</div>
			<button class="adam-upload__remove" type="button" data-adam-upload-remove aria-label="<?php echo esc_attr( sprintf( __( 'Remover %s', 'adam-comunidade' ), $filename ) ); ?>">&times;</button>
		</article>
		<?php
	}

	private static function count_label( int $count, int $max, string $kind ): string {
		return sprintf(
			'image' === $kind ? __( '%1$d / %2$d fotografias', 'adam-comunidade' ) : __( '%1$d / %2$d documentos', 'adam-comunidade' ),
			$count,
			$max
		);
	}

	private static function file_type_label( string $mime, string $filename ): string {
		if ( str_contains( $mime, 'pdf' ) || 'pdf' === strtolower( (string) pathinfo( $filename, PATHINFO_EXTENSION ) ) ) {
			return 'PDF';
		}
		$extension = strtoupper( (string) pathinfo( $filename, PATHINFO_EXTENSION ) );
		return '' !== $extension ? $extension : __( 'Ficheiro', 'adam-comunidade' );
	}
}
