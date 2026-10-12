<?php

add_action( 'add_meta_boxes', 'user_document_metabox' );
function user_document_metabox() 
{
    add_meta_box( 
        'user_document', 
        'User Document', 
        'user_document_meta_box_content', 
        'user_document', 
        'normal', 
        'high' 
    );
}

function user_document_meta_box_content( $post ) 
{
    $form_structure = get_form_user_document_form( $post->ID , false , false , true );
    echo $form_structure;
}

/* 
This action below is  not in use since documents 
should be only editable from the frontend
*/
add_action( 'save_post', 'user_document_metabox_save' );
function user_document_metabox_save( $post_id ) {
    
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    
    if ( ! current_user_can( 'edit_post' ) ) {
        return;
    }

    $post = get_post( $post_id );
    if( $post->post_type != "user_document" )
    {
        return;
    }

    if ( isset( $_POST['user_document_type'] ) ) {
        update_post_meta( $post_id, 'user_document_type', esc_attr( $_POST['user_document_type'] ) );
    }
    
    if ( isset( $_POST['user_document_name'] ) ) {
        update_post_meta( $post_id, 'user_document_name', esc_attr( $_POST['user_document_name'] ) );
    }
    
    if ( isset( $_POST['user_document_location'] ) ) {
        update_post_meta( $post_id, 'user_document_location', esc_attr( $_POST['user_document_location'] ) );
    }
    
    if ( isset( $_POST['user_document_year'] ) ) {
        update_post_meta( $post_id, 'user_document_year', esc_attr( $_POST['user_document_year'] ) );
    }

    if ( isset( $_POST['user_document_user_id'] ) ) {
        update_post_meta( $post_id, 'user_document_user_id', esc_attr( $_POST['user_document_user_id'] ) );
    }
}

add_shortcode( 'user_documents', 'user_documents' );
function user_documents()
{
	check_login();
    $current_user = wp_get_current_user();
    
    global $wpdb;
    
    $sql = get_user_documents_sql();
    
    $sql = $wpdb->prepare( $sql , $current_user->id );
    $results = $wpdb->get_results( $sql , ARRAY_A );
    $documents = build_user_documents_html( $results );
    
    $documento_html = "<h3>No hay documentos creados.</h3>";
    if( $documents != "" )
    {
        $documento_html = $documents;
    }

    return '
    '.$documento_html.'
    <br/>
    <div class="input">
        <a class="button" href="crear_documento">Crear nuevo documento</a>
    </div>
    ';
}

add_shortcode( 'create_document_form', 'create_document_form' );
function create_document_form()
{
	check_login();
	$current_user = wp_get_current_user();

    $form_structure = get_form_user_document_form( null , true , false, false );
    return
	'
    <form action="'.get_site_url().'/guardar_documento" method="POST" enctype="multipart/form-data" >
        '.$form_structure.'
	</form>
	';
}

add_shortcode( 'save_new_document', 'save_new_document' );
function save_new_document()
{
	check_login();
    $current_user = wp_get_current_user();

    $error_message = "";

    $user_document_name = $current_user->user_login." | ".$_POST["user_document_name"]." | user_document_location";
    $post_id = wp_insert_post( array(
        'post_status' => 'publish',
        'post_type' => 'user_document',
        'post_title' => $user_document_name
    ) );

    $document_result = save_user_document_file( "user_document_location",  $current_user->user_login , $_POST["user_document_type"] );
    $document_ok = true;
    if( is_array( $document_result ) )
    {
        $document_ok = false;
        if( !isset($document_result["no_document"]) )
        {
            $error_message = $document_result["error"];
        }
    }
    
    
    $fields_updated = 0;
    $fields_to_check = ["user_document_type", "user_document_name", "user_document_year", "user_document_location"];
    foreach( $fields_to_check as $field )
    {
        if( $field == "user_document_location" && $document_ok )
        {
            $user_document_location_id = $document_result;
            update_post_meta( $post_id , $field , $user_document_location_id );
        }
        else
        {
            if( isset($_POST[ $field ]) && !empty($_POST[ $field ]) )
            {
                update_post_meta( $post_id , $field , sanitize_text_field( $_POST[ $field ] ) );
            }
        }
        $fields_updated++;
    }

    $redirect = "documentos";
    if( $error_message == "" && $fields_updated == count($fields_to_check) )
    {
        update_post_meta( $post_id , "user_document_user_id" , $current_user->id );
    }
    else
    {
        //Some error
        wp_delete_post( $user_document_location_id , true );
        wp_delete_post( $post_id , true );
        $error_message = "Error al crear el documento (".$error_message.")";
    }

    if($error_message == "")
    {
        $redirect .= "?message=Datos Actualizados Correctamente.";
    }
    else
    {
        $redirect = "crear_documento?message=".$error_message."&error_process=1";
    }
    
	wp_redirect( $redirect );
    exit();
}

add_shortcode( 'view_document', 'view_document' );
function view_document()
{
    check_login();
    $current_user = wp_get_current_user();
    
    $form = get_form_user_document_form( $_GET["id"] , false , true , false  );
    return $form;
}

add_shortcode( 'remove_document', 'remove_document' );
function remove_document()
{
    check_login();
    $current_user = wp_get_current_user();

    $document_id = $_GET["id"];
    global $wpdb;
    $sql = "SELECT meta_value document_location_id FROM wp_postmeta WHERE meta_key = 'user_document_location' AND post_id = %s;";
    $sql = $wpdb->prepare( $sql , $document_id );
    $document_media_id = $wpdb->get_row( $sql , ARRAY_A );
    if( $document_media_id != null )
    {
        wp_delete_post( $document_media_id , true );
    }
    $delete_result = wp_delete_post( $document_id , true );

    $redirect = get_site_url()."/documentos?message=Datos Actualizados Correctamente";
    if( $delete_result == null || (is_bool( $delete_result ) && !$delete_result ) )
    {
        $redirect = get_site_url()."/ver_documento/?id=".$document_id."&message=Error al eliminar el documento&error_process=1";
    }
    
    wp_redirect( $redirect );
    exit();
}

add_shortcode( 'list_user_with_documents', 'list_user_with_documents' );
function list_user_with_documents()
{
    check_login();
    
    $sql = get_user_data_sql();
    global $wpdb;
    $sql = $wpdb->prepare( $sql , [] );
    $users_with_documents = $wpdb->get_results( $sql , ARRAY_A );
    $users_with_documents_html = "";
    foreach( $users_with_documents as $user )
    {
        $users_with_documents_html .= "
        <tr>
            <td data-search-key='name'>".$user["first_name"]."</td>
            <td data-search-key='name'>".$user["last_name"]."</td>
            <td data-search-key='faculty'>".$user["faculty"]."</td>
            <td><a href='".get_site_url()."/ver_profesor?id=".$user["user_id"]."'>Ver Profesor</a></td>
        </tr>
        ";
    }

    $documento_html = "<h3>No hay docentes disponibles para hacer revisión.</h3>";
    if( $users_with_documents_html != "" )
    {
        $documento_html = "
        <p>
            <b>Filtar Profesores</b>
            <br/>
            <input class='search_field' id='txt_filter_name' placeholder='Nombre' />
            <br/>
            <input class='search_field' id='txt_filter_faculty' placeholder='Facultad' />
        </p>
        <br/>
        <table id='table_users' class='table_custom'>
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Apellido</th>
                    <th>Facultad</th>                    
                    <th>Ver</th>
                </tr>
            </thead>
            <tbody>
                ".$users_with_documents_html."
            </tbody>
        </table>
        ";
    }

    return $documento_html;
}

add_shortcode( 'show_user_info', 'show_user_info' );
function show_user_info()
{
    check_login();
    
    $user_id = $_GET["id"];

    global $wpdb;
    $sql = get_user_data_sql( $user_id );
    $sql = $wpdb->prepare( $sql , $user_id );
    $user_info = $wpdb->get_row( $sql , ARRAY_A );
    $user_html = "";
    if( $user_info != null )
    {
        $user_html .= '
        <div class="input left">
            <label for="user_name">Nombre del Profesor</label>
            <input type="text" name="user_name" id="user_name" class="form-control" value="'.$user_info["first_name"]." ".$user_info["last_name"].'" />
        </div>
        ';
        $sql = get_user_documents_sql();
        $sql = $wpdb->prepare( $sql , $user_id );
        $user_documents = $wpdb->get_results( $sql , ARRAY_A );
        $documents = build_user_documents_html( $user_documents );
        
        if( $documents != "" )
        {
            $documents .= '
            <br/>
            <a href="'.get_site_url().'/profesores">Volver</a>
            ';
            $user_html .= $documents;
        }

    }
    return $user_html;
}

add_shortcode( 'set_document_valid', 'set_document_valid' );
function set_document_valid()
{
    check_login();
    $document_id = $_GET["id"];
    
    if ( isset( $_GET['valid'] ) ) {
        $update_result = update_post_meta( $document_id, 'user_document_valid', esc_attr( $_GET['valid'] ) );
        
        $redirect = get_site_url()."/ver_documento/?id=".$document_id."&message=Datos Actualizados Correctamente";
        if( is_bool( $update_result ) && !$update_result ) 
        {
            $message = !$update_result ? "false" : "other";
            $redirect = get_site_url()."/ver_documento/?id=".$document_id."&message=Error al validar el documento (".$message.").&error_process=1";
        }
    }
    else
    {
        $redirect = get_site_url()."/profesores";
    }
    wp_redirect( $redirect );
    exit();
}

function get_user_data_sql( $specific_user_id = null )
{
    $sql = "
    SELECT
        usr.user_id,
        MAX( CASE WHEN usr.meta_key = 'first_name' THEN meta_value ELSE '' END ) AS first_name,
        MAX( CASE WHEN usr.meta_key = 'last_name' THEN meta_value ELSE '' END ) AS last_name,
        MAX( CASE WHEN usr.meta_key = 'faculty' THEN meta_value ELSE '' END ) AS faculty
    FROM 
        wp_usermeta usr
        INNER JOIN (
            SELECT aux.meta_value user_id, aux.post_id
            FROM wp_postmeta aux
            WHERE aux.meta_key = 'user_document_user_id'
        ) usd ON usd.user_id = usr.user_id
    __CONDITION__
    GROUP BY
        usr.user_id;
    ";

    if( $specific_user_id == null )
    {
        $sql = str_replace( "__CONDITION__" , "" , $sql );
    }
    else
    {
        $sql = str_replace( "__CONDITION__" , " WHERE usr.user_id = %s " , $sql );
    }

    return $sql;
}

function get_user_documents_sql()
{
    return "
    SELECT
        usd.user_id,
        doc.post_id AS document_post_id,
        MAX( CASE WHEN meta_key = 'user_document_type' THEN meta_value ELSE '' END ) AS user_document_type,
        MAX( CASE WHEN meta_key = 'user_document_name' THEN meta_value ELSE '' END ) AS user_document_name,
        MAX( CASE WHEN meta_key = 'user_document_year' THEN meta_value ELSE '' END ) AS user_document_year,
        MAX( CASE WHEN meta_key = 'user_document_location' THEN meta_value ELSE '' END ) AS user_document_location_id,
        MAX( CASE WHEN meta_key = 'user_document_valid' THEN meta_value ELSE '' END ) AS user_document_valid
    FROM 
        wp_postmeta doc
        INNER JOIN (
            SELECT aux.meta_value user_id, aux.post_id
            FROM wp_postmeta aux
            WHERE aux.meta_key = 'user_document_user_id'
        ) usd ON usd.post_id = doc.post_id
    WHERE
        usd.user_id = %s
    GROUP BY
        usd.user_id,
        doc.post_id
    ";
}

function build_user_documents_html( $documents )
{
    $documents_html = "";
    $document_types = get_document_types();
    foreach( $documents as $document )
    {
        if( empty($document["document_post_id"]) )
        {
            continue;
        }

        $document_type = $document_types[$document["user_document_type"]];
        $document_name = $document["user_document_name"];
        $year = $document["user_document_year"];
        $location_id = $document["user_document_location_id"];
        $extension = get_mime_extension( get_post_mime_type($location_id) );

        $document_valid_state = "Por Validar &#8987;";
        if( $document["user_document_valid"] != "" )
        {
            $document_valid_state = $document["user_document_valid"] == 1 ? "Valido &#9989" : "Invalido &#9940";
        }

        $document = "
        <tr>
            <td>".$document_type."<span>(<a target='_blank' href='".wp_get_attachment_url($location_id)."'>".$extension."</a>)</span>"."</td>
            <td>".$document_name."</td>
            <td>".$year."</td>
            <td>".$document_valid_state."</td>
            <td><a href='".get_site_url()."/ver_documento?id=".$document["document_post_id"]."'>Ver Detalle</a></td>
        </tr>
        ";
        $documents_html .= $document;
    }

    if( $documents_html != "" )
    {
        $documents_html = "
        <table id='table_documents' class='table_custom'>
            <thead>
                <tr>
                    <th>Tipo de Documento</th>
                    <th>Nombre</th>
                    <th>Año</th>
                    <th>Estado</th>
                    <th>Ver</th>
                </tr>
            </thead>
            <tbody>
                ".$documents_html."
            </tbody>
        </table>
        ";
    }

    return $documents_html;
}

function get_mime_extension( $mime )
{
    switch( $mime )
    {
        case "application/pdf": return "pdf"; break;
        default: return "download";
    }
}

function get_document_types()
{
    $document_types = [];
    $document_types["pregrado"] = "Estudio Pre-Grado";
    $document_types["posgrado"] = "Estudio Post-Grado";
    $document_types["publicacion"] = "Publicación";
    $document_types["actualizacion"] = "Curso de Actualización";
    $document_types["creacion"] = "Creación Artística";
    $document_types["asistencia"] = "Asistencia a Evento";
    return $document_types;
}

function get_form_user_document_form( $post_id = null , $create = false , $delete = false , $is_admin_page = false )
{
    $user_document_type = "";
    $user_document_name = "";
    $user_document_location = ""; 
    $user_document_year = "";
    $user_document_url = "";
    $user_document_valid = null;

    $disabled = "";
    if( $is_admin_page )
    {
        $disabled = "disabled";
    }

    if( $post_id != null )
    {
        $values = get_post_custom( $post_id );
        $user_document_type = isset( $values['user_document_type'] ) ? esc_attr( $values['user_document_type'][0] ) : '';
        $user_document_name = isset( $values['user_document_name'] ) ? esc_attr( $values['user_document_name'][0] ) : '';
        $user_document_location = isset( $values['user_document_location'] ) ? esc_attr( $values['user_document_location'][0] ) : '';
        $user_document_url = isset( $values['user_document_url'] ) ? esc_attr( $values['user_document_url'][0] ) : '';
        $user_document_year = isset( $values['user_document_year'] ) ? esc_attr( $values['user_document_year'][0] ) : '';
        $user_document_valid = isset( $values['user_document_valid'] ) ? esc_attr( $values['user_document_valid'][0] ) : null;
    }

    $document_types = get_document_types();
    $options = "";
    foreach ($document_types as $key => $value)
    {
        $selected = ( $user_document_type == "" && $key == "pregrado" ) ? "selected" : "";
        if( $user_document_type == $key )
        {
            $selected = "selected";
        }

        $options .= '<option value="'.$key.'" '.$selected.'>'.$value.'</option>';
    }

    $has_action = false;
    $form_structure = '
    <div class="input left">
        <label for="user_document_type">Tipo de publicación</label>
        <select type="text" name="user_document_type" id="user_document_type" class="form-control" '.$disabled.'>
            '.$options.'
        </select>
    </div>
    <div class="input left">
        <label for="user_document_name">Nombre del documento</label>
        <input type="text" name="user_document_name" id="user_document_name" class="form-control" value="'.esc_html( $user_document_name ).'" '.$disabled.' />
    </div>
    <div class="input left">
        <label for="user_document_year">Año</label>
        <input type="text" name="user_document_year" id="user_document_year" class="form-control" value="'.esc_html( $user_document_year ).'" '.$disabled.' />
    </div>
    ';

    $document_valid_state = "";
    if( $user_document_valid != null )
    {
        $has_action = true;
        $document_valid_state = $user_document_valid == 1 ? "Valido" : "Invalid";
        $form_structure .= '
        <div class="input left">
            <label for="user_document_location">Estado de validación del documento</label>
            <input type="text" name="document_valid_state" id="document_valid_state" class="form-control" value="'.esc_html( $document_valid_state ).'" disabled />
        </div>';
    }

    if( $post_id == null )
    {
        $form_structure .= '
  
        <div class="input left file-field-group" id="user_document_location_container">
            <label for="user_document_location">Ubicación del archivo</label>
            <input type="file" name="user_document_location" id="user_document_location" class="form-control" accept="application/pdf,.pdf" />
        </div>';
    }
    else
    {
        $url_to_download = $user_document_location == null ? $user_document_url : wp_get_attachment_url($user_document_location);
        $text = $user_document_location == null ? "" : get_the_title($user_document_location);
        
        
        $form_structure .= '
        <div class="input left">
            <a target="_blank" href="'.$url_to_download.'">Descargar '.$text.'</a>
        </div>';
    }

    if( $create && $post_id == null )
    {
        $has_action = true;
        $form_structure .= '
        <br/>
        <div class="input">
            <button type="submit">Agregar Documento</button>
        </div>
        ';
    }

    if( $delete )
    {
        if( current_user_can('edit_others_posts') )
        {
            $delete = false;
        }
    }

    if( $delete && $post_id != null && ( $user_document_valid == null || $user_document_valid == 0 ) )
    {
        $has_action = true;
        $form_structure .= '
        <br/>
        <div class="input">
            <a href="'.esc_url(get_site_url().'/eliminar_documento?id='.sanitize_text_field($_GET["id"])).'" class="button" type="submit">Eliminar Documento</a>
        </div>
        ';
    }

    $validate = current_user_can('edit_others_posts');
    if( $validate && $user_document_valid == null )
    {
        $has_action = true;
        $form_structure .= '
        <br/>
        <div class="input">
            <a href="'.esc_url(get_site_url().'/validar_documento?id='.sanitize_text_field($_GET["id"]).'&valid=1').'" class="button confirm" type="submit">Documento Valido</a>
            <br/>
            <a href="'.esc_url(get_site_url().'/validar_documento?id='.sanitize_text_field($_GET["id"]).'&valid=0').'" class="button cancel" type="submit">Documento Invalido</a>
            <br/>
        </div>
        ';
    }

    if( $has_action )
    {
        if( $validate )
        {
            $form_structure .= '
            <a href="'.esc_url(get_site_url().'/ver_profesor/?id='.(isset($values["user_document_user_id"][0]) ? sanitize_text_field($values["user_document_user_id"][0]) : '')).'">Volver</a>
            ';
        }
        else
        {
            $form_structure .= '
            <a href="'.get_site_url().'/documentos">Volver</a>
            ';
        }
    }

    $media_rendered = "";
    
    if( $user_document_location != null )
    {
        $extension = get_mime_extension( get_post_mime_type( $user_document_location ) );
        switch( $extension )
        {
            case "jpeg":
            case "png":{
                $media_rendered = "<img class='media_rendered' src='".wp_get_attachment_url($user_document_location)."' />";
            }; break;
            case "pdf":{
                $media_rendered = '<object class="media_rendered" type="application/pdf" data="'.wp_get_attachment_url($user_document_location).'#zoom=0&scrollbar=0&toolbar=0&navpanes=0&page=1" />';
            }; break;
        }
    }
    else
    {
        $media_rendered = '<iframe class="media_rendered" src="'.$user_document_url.'" width="100%" height="300" style="border:1px solid black;"></iframe>';
    }

    $document_html = "
    <div class='document-container'>
        <div class='document-details data'>
            ".$form_structure."
        </div>
        <div class='document-details media'>
            <div class='input'>
                <label>Documento</label>
                ".$media_rendered."
            </div>
        </div>
    </div>
    ";
    
    return $document_html;
}

function save_user_document_file( $document_key , $user_login , $user_document_type )
{
    $document = $_FILES[ $document_key ];

    if( empty( $document ) )
    {
        return [ "error" => "El archivo es incorrecto." ];
    }

    if( $document['error'] )
    {
        $error = "Error";
        switch( $document['error'] )
        {
            case 0: { $error = 'El archivo fue subido correctamente'; }; break;
            case 1: { $error = 'El archivo supera el maximo de tamaño en (1).'; }; break;//ini
            case 2: { $error = 'El archivo supera el maximo de tamaño en (2).'; }; break;//form
            case 3: { $error = 'El archivo no se subió completamente'; }; break;
            case 4: { return [ "no_document" => true ]; }; break;
            case 6: { $error = 'Error 3, contácte a su administrador.'; }; break;//No temp folder
            case 7: { $error = 'Error 4, contácte a su administrador.'; }; break;//Failed to write file to disk
            case 8: { $error = 'Error 5, contácte a su administrador.'; }; break;// A PHP extension stopped the file upload
        }
        return [ "error" => $error ];
    }

    $wp_max_upload_size = wp_max_upload_size();
    if( $document['size'] > $wp_max_upload_size )
    {
        return [ "error" => "El archivo supera el tamaño máximo (".$wp_max_upload_size.")." ];
    }

    $new_file_mime = mime_content_type( $document['tmp_name'] );
   $allowed_mime_types = ['application/pdf'];
if (!in_array($new_file_mime, $allowed_mime_types, true)) {
    return [ "error" => "Solo se permite PDF. Detectado: ".$new_file_mime ];
}

    $wordpress_upload_dir = wp_upload_dir();
    $new_file_path = $wordpress_upload_dir['path'].'/'.$user_login."_".$user_document_type."_".$document['name'];

    if( file_exists($new_file_path) )
    {
        unlink( $new_file_path );
    }
    
    if( move_uploaded_file( $document['tmp_name'], $new_file_path ) ) {
 
 
        $upload_id = wp_insert_attachment( array(
            'guid'           => $new_file_path, 
            'post_mime_type' => $new_file_mime,
            'post_title'     => preg_replace( '/\.[^.]+$/', '', $document['name'] ),
            'post_content'   => '',
            'post_status'    => 'inherit'
        ), $new_file_path );
     
        // wp_generate_attachment_metadata() won't work if you do not include this file
        require_once( ABSPATH . 'wp-admin/includes/image.php' );
     
        // Generate and save the attachment metas into the database
        wp_update_attachment_metadata( $upload_id, wp_generate_attachment_metadata( $upload_id, $new_file_path ) );
     
        // Show the uploaded file in browser
        return $upload_id;
    }
    return [ "error" => "Error al guardar documento." ];
}