<?php
/**
 * The header for our theme
 *
 * This is the template that displays all of the <head> section and everything up until <div id="content">
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package WordPress
 * @subpackage Bellas Artes
 * @since Bellas Artes
 * @version 1.0
 */
$tmpl_root =  get_template_directory_uri();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> class="no-js no-svg">
	<head>
		<title>Sistema de Gestión de Escalafón - Bellas Artes Institución Universitaria del Valle</title>
		<meta charset="<?php bloginfo( 'charset' ); ?>">
		<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no" />
		<link rel="stylesheet" href="<?php echo get_template_directory_uri(); ?>/assets/css/main.css" />
		<?php //wp_head(); ?>
	</head>
	<body>
		<div id="header">
			<div class="logo_container">
				<img src="<?php echo $tmpl_root; ?>/assets/images/ba_logo_blanco.png" />
			</div>
		</div>
		<?php 
		if( !is_front_page() )
		{
			$current_user = wp_get_current_user();
			$menu_items = [];
			
			// System Administrator - Full access
			if (current_user_can('manage_options')) 
			{
				$menu_items["profesores"] = "Profesores";
				$menu_items["admin"] = "Administración";
			}
			// Academic Coordinator - Faculty oversight
			elseif (current_user_can('edit_others_posts')) 
			{
				$menu_items["profesores"] = "Profesores";
			}
			// Faculty/Professor - Personal documents
			elseif (current_user_can('edit_posts')) 
			{
				$menu_items["perfil"] = "Mi Perfil";
				$menu_items["documentos"] = "Mis Documentos";
			}
		?>
		<div id="menu">
			<div class="menu_item">
				<a href="bienvenida">Inicio</a>
			</div>
			<?php
			foreach( $menu_items as $key => $value )
			{
			?>
			<div class="menu_item">
				<a href="<?php echo $key ?>"><?php echo $value; ?></a>
			</div>
			<?php
			}
			?>
			<div class="menu_item">
				<a href="logout"> Cerrar Sessión</a>
			</div>
		</div>
		<?php
		}
		?>
		<div id="content"><!-- BEGIN CONTENT -->

		