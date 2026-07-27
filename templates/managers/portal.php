<?php
/**
 * Community Manager portal shell.
 *
 * @package ADAM_Comunidade
 */

defined( 'ABSPATH' ) || exit;

get_header();
\ADAM\Comunidade\Managers\Portal::render();
get_footer();
