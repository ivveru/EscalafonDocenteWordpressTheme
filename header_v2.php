<?php
/**
 * The header for our theme
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
			
			// System Administrator - Acceso total
			if (current_user_can('manage_options')) 
			{
				$menu_items["profesores"] = "Profesores";
				$menu_items["admin"] = "Administración";
				$menu_items["cargar-asignaciones"] = "📊 Cargar CSV";
				$menu_items["admin-calificaciones"] = "📋 Ver Calificaciones";
			}
			// Auditor - Solo lectura: ve calificaciones y convocatorias
			elseif (current_user_can('view_calificaciones') && !current_user_can('asignar_escalafon') && !current_user_can('edit_posts'))
			{
				$menu_items["admin-calificaciones"] = "📋 Ver Calificaciones";
				$menu_items["convocatorias-para-profesores"] = "📢 Convocatorias";
			}
			// Comité de Escalafón - Ver trazabilidad y asignar escalafón
			elseif (current_user_can('asignar_escalafon'))
			{
				$menu_items["admin-calificaciones"] = "📋 Ver Calificaciones y Escalafón";
			}
			// Evaluador Académico - Supervisión de docentes
			elseif (current_user_can('edit_others_posts')) 
			{
				$menu_items["profesores"] = "Profesores";
				$menu_items["mis-profesores"] = "👥 Mis Profesores";
			}
			// Docente - Documentos personales
			elseif (current_user_can('edit_posts')) 
			{
				$menu_items["perfil"] = "Mi Perfil";
				$menu_items["documentos"] = "Mis Documentos";
				$menu_items["convocatorias-para-profesores"] = "📢 Convocatorias";
				$menu_items["mis-archivos-cargados"] = "📁 Mis Archivos";
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
			
			<!-- ===== CONVOCATORIAS: SOLO PARA ADMINISTRADORES ===== -->
			<?php if (current_user_can('manage_options')): ?>
			<div class="menu_item">
				<a href="administrar-convocatorias">📢 Administrar Convocatorias</a>
			</div>
			<?php endif; ?>
			<!-- ===== FIN CONVOCATORIAS ===== -->
			
			<div class="menu_item">
				<a href="<?php echo wp_logout_url( home_url() ); ?>">Cerrar Sesión</a>
			</div>
		</div>

		<?php
		}
		?>
		<div id="content"><!-- BEGIN CONTENT -->

		