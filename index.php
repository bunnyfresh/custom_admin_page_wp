<?php

/*

Plugin Name: phonics

Version: 1.0.0

Author: bunnyfresh7@gmail.com

Description:

*/
	 

define('PHONICS_TABLE','mi_phonics');  

define('PERMISSION', 'manage_options');

define('SET_AGENTS', 'edit_users');



// css on all pages :(

function register_scripts() {  

	wp_register_style( 'bootstrapcss', 'http://maxcdn.bootstrapcdn.com/bootstrap/4.1.1/css/bootstrap.min.css' );

	wp_register_style( 'maincss', plugins_url('main.css',__FILE__ ) ); 

	wp_enqueue_style( 'bootstrapcss' );

	wp_enqueue_style( 'maincss' );

}

add_action('wp_enqueue_scripts', 'register_scripts');



//

// Ajax back-end

//

function save_ajax_callback(){

	Global $wpdb;



	$_POST = wp_unslash( $_POST );



	$all_ok = false;

    if( isset($_POST['save_phonics']) ) {

	    $reservations = json_decode($_POST['save_phonics']);

        if( is_array($reservations) && count($reservations)>0 )

            $all_ok = true;

    }

    if( !$all_ok ) {

        wp_die('No data sent');

    }



    foreach($reservations as $reservation) {



	    $reservation  = (array)$reservation;

	    $values = array(); 
	    $unit_no = intval( $reservation['unit_no'] );

	    if ( $unit_no > 0 ) {

		    $r = $wpdb->get_row( 'SELECT * FROM ' . PHONICS_TABLE . ' WHERE unit_no=' . $unit_no );

		    if ( ! is_object( $r ) ) {

			    $unit_no = 0;

		    } else {

			    $values['name'] = trim( $r->name ); 

            }

	    }

	    if ( $unit_no <= 0 ) {

		    wp_die( 'Wrong save. No unit number.' );

	    }


 

	    $values['name'] = intval( $reservation['name'] );
 


        if( !$wpdb->insert( 'mi_phonics', $values ) ) {

		    wp_die( 'Error adding record into database.' );

	    }

        if( !empty( $wpdb->error ) ) {

		    wp_die( 'Error writing into database: ' . $wpdb->error->get_error_message() );

	    }

    }

    echo 'all_ok';

    return;

}

function sql_text($txt) {

    return substr(sanitize_text_field($txt),0,100);

}

function get_phonics_ajax_callback() {

    Global $wpdb;

    $wpdb->show_errors();



	// arrivals

	$list_from = array();

	$r = $wpdb->get_results('SELECT  * FROM '.PHONICS_TABLE );

	foreach($r as $f) {
		 
		array_push($list_from , $f->name);

	} 

	

	     

	// results

	$rez = (object)array(

		'from' => $list_from

	);



	echo json_encode($rez);

	wp_die();

} 

function variables(){

	$variables = array (

		'ajax_url' => admin_url('admin-ajax.php'),

		'is_mobile' => wp_is_mobile()

		// Тут обычно какие-то другие переменные

	);

	echo '<script type="text/javascript">window.wp_data = ',

        json_encode($variables),

        ';</script>';

} 
  

add_action('wp_head','variables');

add_action('wp_ajax_get_phonics_ajax'       , 'get_phonics_ajax_callback');

add_action('wp_ajax_nopriv_get_phonics_ajax', 'get_phonics_ajax_callback');  

//

// Admin

//



if ( ! class_exists( 'WP_List_Table' ) ) {

	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );

}

class Phonics_List_Table extends WP_List_Table{
	

	/** Class constructor */

	public function __construct() {

		parent::__construct( [

			'singular' => __( 'Phonics Setting', 'sp' ),  // singular name of the listed records

			'plural'   => __( 'Phonics Settings', 'sp' ), // plural name of the listed records

			'ajax'     => false //should this table support ajax?

		] );

	}



	public static function get_phonics( $per_page = 50, $page_number = 1 ) {

		global $wpdb;

        $sql = "SELECT * FROM ".PHONICS_TABLE ;

		if ( ! empty( $_REQUEST['orderby'] ) ) {

			$sql .= ' ORDER BY '. esc_sql( $_REQUEST['orderby'] );

			$sql .= ! empty( $_REQUEST['order'] ) ? ' ' . esc_sql( $_REQUEST['order'] ) : ' ASC';

		}

		$sql    .= " LIMIT $per_page";

		$sql    .= ' OFFSET ' . ( $page_number - 1 ) * $per_page;

		$results = $wpdb->get_results( $sql, 'ARRAY_A' );
		
		$final_results = array();
		
		foreach($results as $result){
			
			
			$final_results[] = array(
				'id' =>  $result['id'],
				'name' =>  $result['name'] 
			);
		}

		return $final_results;

	}



	public static function delete_phonics( $id ) {

		global $wpdb;

		$wpdb->delete(

			PHONICS_TABLE,

			[ 'id' => $id ],

			[ '%d' ]

		);

	}



	public static function record_count() {

		global $wpdb;

		$sql = "SELECT COUNT(*) FROM ".PHONICS_TABLE;

		return $wpdb->get_var( $sql );

	}



	public function no_items() {

		_e( 'No Phonics items avaliable.', 'sp' );

	}



	function column_cb( $item ) {

		return '<input type="checkbox" name="bulk-delete[]" value="'.$item['id'].'" />';

	}



	function column_actions( $item ) {

		$ret = '<a href="'.add_query_arg( array('id'=>$item['id']), menu_page_url( 'phonics-edit', false ) ).'">Edit</a>';

		return $ret;

	}



	function column_name( $item ) {

		// create a nonce

		$delete_nonce = wp_create_nonce( 'sp_delete_phonics' );

		$title = '<strong>' . $item['name'] . '</strong>';

		$actions = [

			'delete' => sprintf( '<a href="?page=%s&action=%s&phonics=%s&_wpnonce=%s">Delete</a>',

				esc_attr( $_REQUEST['page'] ), 'delete', absint( $item['id'] ), $delete_nonce ),

			'edit' => $this->column_actions($item)

		];

		return $title . $this->row_actions( $actions );

	}



	public function column_default( $item, $column_name ) {

		switch ( $column_name ) {

			case 'id':
				return $item[$column_name];

			case 'type_service':
				return $item[$column_name]; 

			default:

				return print_r( $item, true ); //Show the whole array for troubleshooting purposes

		} 
	}
 
	function get_columns() {

		$columns = [

			'cb'               => '<input type="checkbox" />',

			'id'               => __( 'id', 'sp' ),

			'name'           => __( 'Name', 'sp' )			

		];
		
		//'rate'          => __( 'Rate', 'sp' ),

		return $columns;

	}



	public function get_sortable_columns() {

		$sortable_columns = array(

			'id'               => array( 'id', true ),

			'name'             => array( 'name', true ), 
 

		);



		return $sortable_columns;

	}



	public function get_bulk_actions() {

		$actions = [

			'bulk-delete' => 'Delete'

		];

		return $actions;

	}



	public function prepare_items() {

		$this->_column_headers = $this->get_column_info();

		/** Process bulk action */

		$this->process_bulk_action();

		$per_page     = $this->get_items_per_page( 'phonics_per_page', 50 );

		$current_page = $this->get_pagenum();

		$total_items  = self::record_count();

		$this->set_pagination_args( [

			'total_items' => $total_items, //WE have to calculate the total number of items

			'per_page'    => $per_page //WE have to determine how many items to show on a page

		] );

		$this->items = self::get_phonics( $per_page, $current_page );

	}



	public function process_bulk_action() { 
		//Detect when a bulk action is being triggered...

		if ( 'delete' === $this->current_action() ) {



			// In our file that handles the request, verify the nonce.

			$nonce = esc_attr( $_REQUEST['_wpnonce'] );



			if ( ! wp_verify_nonce( $nonce, 'sp_delete_phonics' ) ) {

				die( 'Go get a life script kiddies' );

			}

			else {

				self::delete_phonics( absint( $_GET['phonics'] ) );

				wp_redirect( esc_url( add_query_arg() ) );

				exit;

			}

		}



		// If the delete bulk action is triggered

		if ( ( isset( $_POST['action'] ) && $_POST['action'] == 'bulk-delete' )

		     || ( isset( $_POST['action2'] ) && $_POST['action2'] == 'bulk-delete' )

		) {

			$delete_ids = esc_sql( $_POST['bulk-delete'] );

			// loop over the array of record IDs and delete them

			foreach ( $delete_ids as $id ) {

				self::delete_phonics( $id );

			}

			wp_redirect( esc_url( add_query_arg() ) );

		}

	}
}

 

add_action( 'admin_menu', function(){

	global $_wp_last_object_menu; 
	
	// Services
	
	$hook = add_menu_page( 'phonics', 'phonics',

			PERMISSION, 'phonics-list', 'phonics_page',

        'dashicons-email', $_wp_last_object_menu );

	add_action( 'load-'. $hook, 'phonics_page_load' );
	
	// Edit Transfers
	
	 
	
	// Edit Service
	
	
	$hook = add_submenu_page( '', 'Edit Phonics', 'Edit Phonics',

		PERMISSION, 'phonics-edit',

		'phonics_edit_form' );

	add_action( 'load-' . $hook, 'phonics_edit_form_save' );
	  
    
} );
 
function phonics_page_load(){

	$GLOBALS['Phonics_List_Table'] = new Phonics_List_Table();

}
 
 
  
function phonics_page(){

	?>

    <div class="wrap">

        <h2><?php echo get_admin_page_title() ?></h2><br/>
		
		<a href="<?php echo menu_page_url( 'phonics-edit', false ) ?>" class="page-title-action">Add New</a>


        <br/>

        <style type="text/css">

            th.column-id, td.column-id{width:50px}

        </style>

        <script>

            function show_message(mess) {

                var div;

                jQuery(document.body).append(div=jQuery('<div style="position:fixed;padding:5px 10px;'

                    + 'text-align:center;background-color:#3CD03C;color:#fff;border-radius:5px;top:170px;'

                    + 'right:70px">'+mess+'</div>').fadeIn());

                setTimeout(function(){jQuery(div).fadeOut('slow')},1500);

            }

        </script>

        <form action="" method="POST">

			<?php

			$GLOBALS['Phonics_List_Table']->prepare_items();

			$GLOBALS['Phonics_List_Table']->display();

			?>

        </form>

    </div>

	<?php

}
    

function phonics_edit_form(){

	global $wpdb;



	$title = 'Add New phonics';

	$button = $title;

	$id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

	if( $id ) {

		$title = 'View & Edit Phonics';

		$button = 'Save changes';

		$sql = "SELECT * FROM ".PHONICS_TABLE." WHERE id=".$id;

		$rows = $wpdb->get_results( $sql );

		$values = $rows[0];

	}

	else

	    $values = (object) array('name' => ''   );



	?>

    <h2><?php echo $title?></h2>

        <form action="" method="POST">

            <input type="hidden" name="id" value="<?php echo $id?>"/>

            <table class="form-table" style="width:700px">

                <tbody> 

                <tr class="form-field">

                    <th scope="row"><label for="name">Name</label></th>

                    <td><input name="name" id="name" value="<?php echo $values->name; ?>"

                               type="text"></td>

                </tr>

				<tr class="form-field">
					<th scope="row"></th>
					<td><input type="submit" class="button button-primary" value="<?php echo $button?>"/></td>
				</tr>
				
				 
                 

                </tbody>

            </table>

        </form>

    <?php

}

 
function phonics_edit_form_save() {

	global $wpdb;



	if( isset($_POST['id']) ) {

		$_POST = wp_unslash($_POST);

		$id = intval( isset($_POST['id'])?$_POST['id']:0 );

		$fields = array('name');

		$set_str = '';

		foreach($fields as $field) {

			$set_str .= (!empty($set_str)?',':'') . $wpdb->prepare($field.'="%s"', $_POST[$field] );

		}

		if( $id )

			$sql = "UPDATE ".PHONICS_TABLE." SET ".$set_str.' WHERE id='.$id;

		else

			$sql = "INSERT INTO ".PHONICS_TABLE." SET ".$set_str;

		$wpdb->show_errors();

		$wpdb->query( $sql );

		wp_safe_redirect( menu_page_url( 'phonics-list', false ) );

		exit();

	}

}  

register_activation_hook(__FILE__,'install');

function install(){

	global $wpdb;

	$wpdb->show_errors();

	$sql = @file_get_contents(plugin_dir_path( __FILE__ ).'cherry.sql');

	if( empty($sql) ) {
		global $wpdb; 

		$table_name = $wpdb->prefix . 'phonics';
		
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			time timestamp  NOT NULL,
			name tinytext NOT NULL, 
			PRIMARY KEY  (id)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql ); 
		return;
	};

	$queries = explode(';',$sql);

	foreach($queries as $sql) {

		if ( ! $wpdb->query( $sql ) ) {

			echo 'db error:' . $sql . "\n";

		}

	}

}



//

// Short code for front-end

//



function form_func( $attrs, $content ) {

	Global $wpdb;

    ob_start();
 

	require_once('mainform.php');

	$output_string = ob_get_contents();

	ob_end_clean();

	return $output_string;

}

add_shortcode('form', 'form_func');




//

// New columns in Admin users table

//



// function new_contact_methods( $contactmethods ) {

// 	$contactmethods['agent_commission'] = 'Agent commission';

// 	return $contactmethods;

// }

// add_filter( 'user_contactmethods', 'new_contact_methods', 10, 1 );

// function new_modify_user_table( $column ) {

// 	$column['agent_commission'] = 'Agent commission';

// 	return $column;

// }

// add_filter( 'manage_users_columns', 'new_modify_user_table' );

// function new_modify_user_table_row( $val, $column_name, $user_id ) {

// 	switch ($column_name) {

// 		case 'agent_commission' :

// 			if( user_can( $user_id, 'um_agent' ) ) {

// 				$com = get_the_author_meta( 'agent_commission', $user_id );

// 				return $x . '<span class="agent_commission">' . ( ! empty( $com ) ? $com . '%' : '' )

// 				       . '</span> &nbsp; <button style="cursor:pointer" onclick="agent_commission(this);return false">Set</button>';

// 			}

// 		default:

// 	}

// 	return $val;

// }

// add_filter( 'manage_users_custom_column', 'new_modify_user_table_row', 10, 3 );

// function my_enqueue($hook) {

// 	// Only add to the users.php admin page.

// 	if ($hook!=='users.php' && strpos($hook,'japan-phonics-list')===false ) {

// 		return;

// 	}

// 	wp_enqueue_script('japan_admin_script', plugin_dir_url(__FILE__) . 'admin.js');

// }

// add_action('admin_enqueue_scripts', 'my_enqueue');

 