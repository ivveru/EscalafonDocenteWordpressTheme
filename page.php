<?php
/**
 * The template for displaying all pages
 *
 * This is the template that displays all pages by default.
 * Please note that this is the WordPress construct of pages
 * and that other 'pages' on your WordPress site may use a
 * different template.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package WordPress
 * @subpackage Bellas_Artes
 * @since Bellas Artes 1.0
 * @version 1.0
 */

get_header(); 
?>

<?php
$message = isset( $_GET["message"] ) ? $_GET["message"] : "";
$is_error = isset( $_GET["error_process"] ) ? "error" : "";
if( $message != "" )
{
?>
<span class="message <?php echo $is_error; ?>">
	<?php echo $message; ?>
</span>
<?php
}
global $post;
echo the_content();
?>

<?php
get_footer();
