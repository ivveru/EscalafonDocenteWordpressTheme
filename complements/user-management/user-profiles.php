<?php
/**
 * User Profile Management
 * Profile forms, updates, and user data handling
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

add_shortcode( 'profile_update_form', 'profile_update_form' );
function profile_update_form()
{
	check_login();
	$current_user = wp_get_current_user();
	$first_name = get_user_meta( $current_user->id , "first_name" , true );
	$last_name = get_user_meta( $current_user->id , "last_name" , true );
	$faculty = get_user_meta( $current_user->id , "faculty" , true );
	$description = get_user_meta( $current_user->id , "description" , true );

	return
	'
	<form action="'.get_site_url().'/actualizar_perfil" method="POST" >
		<div class="input left">
			<label for="first_name">Nombre</label>
			<input type="text" name="first_name" id="first_name" value="'.$first_name.'" />
		</div>
		<div class="input left">
			<label for="last_name">Apellido</label>
			<input type="text" name="last_name" id="last_name" value="'.$last_name.'" />
		</div>
		<div class="input left">
			<label for="facultad">Facultad</label>
			<input type="text" name="faculty" id="faculty" value="'.$faculty.'" />
		</div>
		<div class="input left">
			<label for="description">Descripción</label>
			<textarea id="description" name="description" rows="10" cols="100">'.$description.'</textarea>
		</div>
		<div class="input">
			<button type="submit">Actualizar información</button>
        </div>
	</form>
	';
}

add_shortcode( 'update_profile', 'update_profile' );
function update_profile()
{
	check_login();
	$current_user = wp_get_current_user();

	$data_updated = false;
	$error = [];
	if( isset($_POST["first_name"]) && !empty($_POST["first_name"]) )
	{
		update_user_meta( $current_user->id , "first_name" , sanitize_text_field( $_POST["first_name"] ) );
		$data_updated = true;
	}
	else
	{
		$error[] = "Nombre"; 
	}
	if( isset($_POST["last_name"]) && !empty($_POST["last_name"]) )
	{
		update_user_meta( $current_user->id , "last_name" , sanitize_text_field( $_POST["last_name"] ) );
		$data_updated = true;
	}
	else
	{
		$error[] = "Apellido"; 
	}

	update_user_meta( $current_user->id , "faculty" , sanitize_text_field( $_POST["faculty"] ) );
	update_user_meta( $current_user->id , "description" , sanitize_text_field( $_POST["description"] ) );
	
	$redirect = "perfil";
	if( count($error) > 0 )
	{
		$redirect .= "?message=Estos campos no pueden estar vacios ".implode(", " , $error)."&error_process=1";
	}
	else if( $data_updated == true )
	{
		$redirect .= "?message=Datos Actualizados Correctamente";
	}
	
	wp_redirect( $redirect );
	exit();
}
