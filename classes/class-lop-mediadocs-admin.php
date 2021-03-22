<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * LOP Media Docs Admin class.
 */
class LOP_MediaDocs_Admin {
	
	protected $main_class;
	
	/**
	 * Configure Admin
	 */
	public function __construct( $instance ) {
		$this->main_class = $instance;
		
		add_action( 'admin_init', array($this, 'admin_init') );
		add_action( 'add_meta_boxes', array($this, 'add_meta_boxes') );
		add_action( 'admin_enqueue_scripts', array($this, 'admin_styles') );
		add_action( 'admin_menu', array($this, 'admin_menu') );
		add_filter( 'set-screen-option', array($this, 'set_screen_option'), 10, 3 );
	}
	
	public function admin_init() {
		// modify taxonomy columns in Media Library
		add_filter( 'manage_taxonomies_for_attachment_columns', array($this, 'media_tax_columns') );
		// make 'Attached' column sortable in Media Library
		if ( class_exists( 'FileUnattach' ) )
			add_filter( 'manage_upload_sortable_columns', function($cols) { $cols['fun-attach'] = 'parent'; return $cols; } );
		
		// add custom columns to Tags list for object counts of different post types
		add_filter( 'manage_edit-post_tag_columns', array($this, 'tags_column_headers') );
		add_filter( 'manage_post_tag_custom_column', array($this, 'tags_custom_columns'), 10, 3 );
		add_filter( 'manage_edit-post_tag_sortable_columns', array($this, 'tags_sortable_columns') );
		
		// add new MIME type filter to Media Library / Upload
		add_filter( 'post_mime_types', array($this, 'docs_mime_type') );
	}
	
	/**
	 * Add subpage to Dashboard.
	 */
	public function admin_menu() {
		$page_title = __( 'All Documents', 'lop-mediadocs' );
		$menu_title = __( 'All Documents', 'lop-mediadocs' );
		/*if ( class_exists( 'UserAccessManager' ) ) {
			$page_title = __( 'My Group Documents', 'lop-mediadocs' );
			$menu_title = __( 'My Groups', 'lop-mediadocs' );
		}*/
		
		$page_hook = add_dashboard_page( $page_title, $menu_title, 'read', 'lop-mediadocs-overview', array($this, 'display_docs_overview_page') );
		add_action( 'load-' . $page_hook, array($this, 'load_docs_overview_page') );
	}
	
	/**
	 * Enqueue styles for Dashboard pages.
	 */
	public function admin_styles() {
		$screen = get_current_screen();
		
		if ( in_array( $screen->id, array( 'dashboard', 'dashboard_page_lop-mediadocs-overview' ) ) ) {
			wp_enqueue_style( 'lop_mediadocs_dashboard_styles', plugins_url( '/assets/css/dashboard.css', dirname(__FILE__) ), array() );
		}
	}
	
	/**
	 * Save screen option value to usermeta.
	 */
	public function set_screen_option( $result, $option, $value ) {
		if ( in_array( $option, array( 'lop_mediadocs_overview_per_page' ) ) ) {
			$result = (int) $value;
		}
		return $result;
	}
	
	/**
	 * Load hook of All Documents (My Groups) dashboard page.
	 */
	public function load_docs_overview_page() {
		// remove query vars from url -> see wp-admin/upload.php
		if ( ! empty( $_GET['_wp_http_referer'] ) ) {
			wp_redirect( remove_query_arg( array( '_wp_http_referer', '_wpnonce' ), wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
			exit;
		}
		$current_screen = get_current_screen();
		// load table class
		if ( ! class_exists( 'LOP_Docs_Overview_List_Table' ) ) {
			require_once( $this->main_class->plugin_dir . '/classes/class-lop-docs-overview-list-table.php' );
		}
		// setup column names for hiding the columns in screen options
		add_filter( 'manage_' . $current_screen->id . '_columns', array( 'LOP_Docs_Overview_List_Table', 'define_columns' ) );
		// register 'per_page' screen option
		add_screen_option( 'per_page', array(
			'label' => __( 'Documents per page', 'lop-mediadocs' ),
			'default' => 20,
			'option' => 'lop_mediadocs_overview_per_page',
		) );
	}
	
	/**
	 * Display All Documents (My Groups) dashboard page with a list of documents.
	 */
	public function display_docs_overview_page() {
		// create list table
		$list_table = new LOP_Docs_Overview_List_Table();
		// Fetch, prepare, sort, and filter our data...
		$list_table->prepare_items();
		?>
		<div class="wrap">
			<h1><?php
				$page_title = __( 'All Documents', 'lop-mediadocs' );
				//if ( class_exists( 'UserAccessManager' ) ) $page_title = __( 'My Group Documents', 'lop-mediadocs' );
				echo $page_title;
				if ( ! empty( $_REQUEST['s'] ) ) {
					printf( ' <span class="subtitle">' . __( 'Search results for &#8220;%s&#8221;', 'lop-mediadocs' ) . '</span>', esc_html( wp_unslash( $_REQUEST['s'] ) ) );
				}
			?></h1>
			<form id="posts-filter" method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>" />
				<?php $list_table->search_box( __( 'Search Documents', 'lop-mediadocs' ), 'docs-overview' ); ?>
				<?php $list_table->display(); ?>
				<div id="ajax-response"></div>
			</form>
		</div>
		<?php
	}
	
	/**
	 * Add info metabox with current attachments to Post/Page/Custom-type edit screen.
	 */
	public function add_meta_boxes() {
		/**
		 * Filter the post types which could have attachments.
		 */
		$post_types = apply_filters( 'lop_mediadocs_post_types_with_attachments', array( 'post', 'page' ) );
		foreach ( $post_types as $type ) {
			add_meta_box( 'docs-metabox', __( 'Attachments', 'lop-mediadocs' ), array($this, 'display_docs_metabox'), $type, 'normal', 'high' );
		}
	}
	
	/**
	 * Display info metabox with list of attachments.
	 */
	public function display_docs_metabox( $post ) {
		// get attachments for the post
		$attachments = $this->main_class->get_attachments( array(
			'post_parent' => $post->ID,
			'post_mime_type' => 'application',
		) );
		//$this-main_class->log($attachments);
		$output = '';
		if ( ! $attachments ) {
			$output .= __( 'No document attached to this post/page', 'lop-mediadocs' );
		} else {
			$output .= '<p>' . __( 'Attachments available for this post/page', 'lop-mediadocs' ) . '</p>'.
			'<table class="widefat striped" style="margin: 0px auto; width: auto;">'.
				'<thead><tr>'.
					'<th>' . __( 'ID', 'lop-mediadocs' ) . '</th>'.
					'<th>' . __( 'Type', 'lop-mediadocs' ) . '</th>'.
					'<th>' . __( 'File name', 'lop-mediadocs' ) . '</th>'.
					'<th>' . __( 'Size', 'lop-mediadocs' ) . '</th>'.
					'<th>' . __( 'Date', 'lop-mediadocs' ) . '</th>'.
				'</tr></thead>'.
				'<tbody>';
			foreach ( $attachments as $aid => $attachment ) {
				$file = LOP_MediaDocs::attachment_fileinfo_factory( $attachment );
				$icon = $this->main_class->get_icon_html( $attachment->ID, 'small', $file->ext );
				$output .= '<tr>'.
					'<td>' . $attachment->ID . '</td>'.
					'<td class="num">' . $icon . '</td>'.
					'<td><a href="' . $file->url . '" title="' . esc_attr( $file->title ) . '" target="_blank">' . esc_html( $file->name ) . '</a></td>'.
					'<td>' . $file->size . '</td>'.
					'<td>' . $file->date . '</td>'.
				'</tr>';
			}
			$output .= '</tbody></table>';
		}
		$output = '<div class="attachments-box">' . $output . '</div>' . PHP_EOL;
		echo $output;
	}
	
	/**
	 * Update term count for Docs.
	 * NOTE: This is different from WP '_update_post_term_count' function ($check_attachments part) as we are counting also the non-attached media (post_status = 'inherit').
	 */
	public function update_docs_term_count($terms, $taxonomy) {
		global $wpdb;
		
		foreach ( (array) $terms as $term ) {
			$count = 0;
			$count += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->term_relationships, $wpdb->posts WHERE $wpdb->posts.ID = $wpdb->term_relationships.object_id AND ( post_status = 'publish' OR post_status = 'inherit' ) AND post_type = 'attachment' AND term_taxonomy_id = %d", $term ) );
			
			do_action( 'edit_term_taxonomy', $term, $taxonomy->name );
			$wpdb->update( $wpdb->term_taxonomy, compact( 'count' ), array( 'term_taxonomy_id' => $term ) );
			do_action( 'edited_term_taxonomy', $term, $taxonomy->name );
		}
	}
	
	/**
	 * Update term count for Tags.
	 * NOTE: This is a copy of '_update_post_term_count' function from 'wp-includes/taxonomy.php'
	 * Only difference is that we are counting also the non-attached media and not checking published status of parent post.
	 */
	public function update_post_term_count($terms, $taxonomy) {
		global $wpdb;
	
		$object_types = (array) $taxonomy->object_type;
	
		foreach ( $object_types as &$object_type )
			list( $object_type ) = explode( ':', $object_type );
	
		$object_types = array_unique( $object_types );
	
		if ( false !== ( $check_attachments = array_search( 'attachment', $object_types ) ) ) {
			unset( $object_types[ $check_attachments ] );
			$check_attachments = true;
		}
	
		if ( $object_types )
			$object_types = esc_sql( array_filter( $object_types, 'post_type_exists' ) );
	
		foreach ( (array) $terms as $term ) {
			$count = 0;
	
			// Attachments can be 'inherit' status, we need to base count off the parent's status if so
			if ( $check_attachments )
				//$count += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->term_relationships, $wpdb->posts p1 WHERE p1.ID = $wpdb->term_relationships.object_id AND ( post_status = 'publish' OR ( post_status = 'inherit' AND post_parent > 0 AND ( SELECT post_status FROM $wpdb->posts WHERE ID = p1.post_parent ) = 'publish' ) ) AND post_type = 'attachment' AND term_taxonomy_id = %d", $term ) );
				$count += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->term_relationships, $wpdb->posts WHERE $wpdb->posts.ID = $wpdb->term_relationships.object_id AND ( post_status = 'publish' OR post_status = 'inherit' ) AND post_type = 'attachment' AND term_taxonomy_id = %d", $term ) );
	
			if ( $object_types )
				$count += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->term_relationships, $wpdb->posts WHERE $wpdb->posts.ID = $wpdb->term_relationships.object_id AND post_status = 'publish' AND post_type IN ('" . implode("', '", $object_types ) . "') AND term_taxonomy_id = %d", $term ) );
	
			/** This action is documented in wp-includes/taxonomy.php */
			do_action( 'edit_term_taxonomy', $term, $taxonomy->name );
			$wpdb->update( $wpdb->term_taxonomy, compact( 'count' ), array( 'term_taxonomy_id' => $term ) );

			/** This action is documented in wp-includes/taxonomy.php */
			do_action( 'edited_term_taxonomy', $term, $taxonomy->name );
		}
	}
	
	/**
	 * Modify taxonomy columns in Media Library
	 */
	public function media_tax_columns( $taxonomies ) {
		// reverse the order of columns so category is first
		$taxonomies = array_reverse( $taxonomies );
		return $taxonomies;
	}
	
	/**
	 * Add custom columns to Tags list for object counts of different post types
	 */
	public function tags_column_headers( $cols ) {
		if ( isset($cols['posts']) ) {
			// just change column name so we can change the value later
			$ptype = get_current_screen()->post_type;
			$cols[$ptype] = $cols['posts'];
			unset($cols['posts']);
			return $cols;
		}
		
		if ( isset($cols['posts']) )
			unset($cols['posts']);
		
		$taxonomy = get_taxonomy('post_tag');
		$object_types = (array) $taxonomy->object_type;
		
		// make sure there are no 'attachment:FILETYPE' or 'attachment:MIMETYPE' pseudo post types
		foreach ( $object_types as &$object_type ) {
			if ( 0 === strpos( $object_type, 'attachment:' ) )
				list( $object_type ) = explode( ':', $object_type );
		}
		$object_types = array_unique( $object_types );
		
		if ( $object_types = array_filter( $object_types, 'post_type_exists' ) ) {
			foreach ( $object_types as $post_type ) {
				$post_type_object = get_post_type_object( $post_type );
				$cols[$post_type] = $post_type_object->labels->name;
			}
		}
		return $cols;
	}
	
	/**
	 * Show custom columns data in Tags list with object counts of different post types
	 */
	public function tags_custom_columns( $out, $column_name, $term_id ) {
		global $wpdb;
		static $current_tag_row;
		static $types_count = array();
		$taxonomy = get_taxonomy( 'post_tag' );
		$object_types = (array) get_current_screen()->post_type;	// just current post type in WP 3.5 up
		if ( !in_array( $column_name, $object_types ) )
			return $out;
		
		// run this query only once on the first column of row
		if ( $current_tag_row != $term_id ) {
			$current_tag_row = $term_id;
			// Custom counting of tagged objects for all registered post types in 'post_tag' taxonomy.
			// NOTE: This is different from WP '_update_post_term_count' function as we are counting also the non-attached media.
			$types_count = $wpdb->get_results( $wpdb->prepare( "
				SELECT p.post_type, COUNT(p.post_type) AS count 
				FROM $wpdb->posts AS p 
				INNER JOIN $wpdb->term_relationships AS tr ON p.ID = tr.object_id 
				INNER JOIN $wpdb->term_taxonomy AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id 
				WHERE (tt.term_id = %d AND tt.taxonomy = %s) AND p.post_type IN ('" . implode( "','", esc_sql( $object_types ) ) . "') AND (p.post_status = 'publish' OR p.post_status = 'inherit') 
				GROUP BY p.post_type 
				ORDER BY p.post_type ASC
			", $term_id, 'post_tag' ), OBJECT_K );
			//error_log(print_r($types_count, 1));
		}
		
		$tag = get_term( $term_id, 'post_tag' );
		if ( !empty( $types_count ) && isset( $types_count[$column_name] ) ) {
			$count = number_format_i18n( $types_count[$column_name]->count );
		} else { 
			$count = 0;
		}
		
		$ptype_object = get_post_type_object( $column_name );
		if ( $count == 0 || !$ptype_object->show_ui )
			return $count;
		
		switch ( $column_name ) {
			case 'attachment':
				$out = sprintf( '<a href="%s">%s</a> / %s',
					esc_url( add_query_arg( array( 'tag' => $tag->slug, 'post_type' => $column_name ), 'upload.php' ) ),
					$count,
					number_format_i18n( $tag->count )
				);
				break;
			case 'post':
				$out = sprintf( '<a href="%s">%s</a> / %s',
					esc_url( add_query_arg( array( 'tag' => $tag->slug ), 'edit.php' ) ),
					$count,
					number_format_i18n( $tag->count )
				);
				break;
			default:
				$out = sprintf( '<a href="%s">%s</a> / %s',
					esc_url( add_query_arg( array( 'tag' => $tag->slug, 'post_type' => $column_name ), 'edit.php' ) ),
					$count,
					number_format_i18n( $tag->count )
				);
				break;
		}
		return $out;
	}
	
	/**
	 * Make custom columns sortable in Tags list
	 */
	public function tags_sortable_columns( $columns ) {
		$ptype = get_current_screen()->post_type;
		$columns[$ptype] = 'count';		// sorting on the original 'count' for all post types
		return $columns;
	}
	
	/**
	 * Add new MIME type filter to Media Library
	 */
	public function docs_mime_type( $post_mime_types ) {
		$post_mime_types['application'] = array(
			__( 'Documents', 'lop-mediadocs' ), 
			__( 'Manage Documents', 'lop-mediadocs' ), 
			_n_noop( 'Document <span class="count">(%s)</span>', 'Documents <span class="count">(%s)</span>', 'lop-mediadocs' ),
		);
		return $post_mime_types;
	}

}
