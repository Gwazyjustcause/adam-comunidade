<?php
/**
 * Community submission and owner portal.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;

use ADAM\Comunidade\Experience\Portal;

get_header();
?>
<main id="main" class="adam-experience"><div class="adam-experience-container"><?php Portal::render(); ?></div></main>
<?php get_footer(); ?>
