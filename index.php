<?php
/**
 * The main template file
 *
 * This is the most generic template file in a WordPress theme
 * and one of the two required files for a theme (the other being style.css).
 * It is used to display a page when nothing more specific matches a query.
 * E.g., it puts together the home page when no home.php file exists.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package WordPress
 * @subpackage Bellas_Artes
 * @since Bellas Artes 1.0
 * @version 1.0
 */

get_header(); ?>
<div id="banner-wrapper">
	<div id="banner" class="box container">
		<img src="http://formasestrategicas.com.co/wp/wp-content/uploads/2020/08/banner_2.png" />
	</div>
</div>
<!-- CONTENT -->
<div id="main-wrapper" style="display: none;">
	<div class="container">
		<div id="content">
		</div>
	</div>
</div>
<!-- CONTENT -->
<?php
get_footer();
