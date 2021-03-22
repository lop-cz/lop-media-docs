<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/* Make sure the parent class is loaded. */
if ( ! class_exists( 'WP_List_Table' ) )
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );

/**
 * LOP Documents Overview List Table class
 */
class LOP_Docs_Overview_List_Table extends WP_List_Table {

	protected $fileinfo;
	protected $main_class;

	public function __construct() {
		$this->main_class = LOP_MediaDocs::get_instance();
		parent::__construct( array(
			'singular' => 'doc',
			'plural' => 'docs',
			'ajax' => false
		) );
	}

	public static function define_columns() {
		$columns = array(
			'icon'   => '',
			'title'  => __( 'Title', 'lop-mediadocs' ),
			'size'   => __( 'Size', 'lop-mediadocs' ),
			'author' => __( 'Author', 'lop-mediadocs' ),
			'date'   => __( 'Date', 'lop-mediadocs' )
		);
		// add other columns
		$columns['taxonomy-' . LOP_MediaDocs::TAXONOMY] = get_taxonomy( LOP_MediaDocs::TAXONOMY )->labels->name;
		//if ( class_exists( 'UserAccessManager' ) ) $columns['group'] = __( 'Group', 'lop-mediadocs' );
		}
		return $columns;
	}

	public function get_columns() {
		return get_column_headers( get_current_screen() );
	}

	protected function get_sortable_columns() {
		return array(
			'title' => array( 'title', false ),
			'author' => array( 'author', false ),
			'date' => array( 'date', true ),	// true means it's already sorted
		);
	}
	
	protected function get_default_primary_column_name() {
		return 'title';
	}

	public function ajax_user_can() {
		return current_user_can( 'read' );
	}

	public function prepare_items() {
		$per_page = $this->get_items_per_page( 'lop_mediadocs_overview_per_page' );
		
		$this->_column_headers = $this->get_column_info();
		
		$args = array(
			'limit' => $per_page,
			'orderby' => 'date',
			'order' => 'DESC',
			//'usergroup_only' => class_exists( 'UserAccessManager' ),
			'offset' => ( $this->get_pagenum() - 1 ) * $per_page,
			'no_found_rows' => false,
		);
		
		// search by keyword
		if ( ! empty( $_REQUEST['s'] ) )
			$args['s'] = $_REQUEST['s'];
		
		// author filter
		if ( ! empty( $_REQUEST['author'] ) )
			$args['author'] = intval( $_REQUEST['author'] );
		
		// year/month filter
		if ( ! empty( $_REQUEST['m'] ) )
			$args['m'] = intval( $_REQUEST['m'] );
		
		// category filter
		if ( ! empty( $_REQUEST['cat'] ) )
			$args['category'] = intval( $_REQUEST['cat'] );
		
		// ordering
		if ( ! empty( $_REQUEST['orderby'] ) ) {
			if ( 'title' == $_REQUEST['orderby'] )
				$args['orderby'] = 'title';
			elseif ( 'author' == $_REQUEST['orderby'] )
				$args['orderby'] = 'author';
			elseif ( 'date' == $_REQUEST['orderby'] )
				$args['orderby'] = 'date';
		}
		if ( ! empty( $_REQUEST['order'] ) ) {
			if ( 'asc' == strtolower( $_REQUEST['order'] ) )
				$args['order'] = 'ASC';
			elseif ( 'desc' == strtolower( $_REQUEST['order'] ) )
				$args['order'] = 'DESC';
		}
		
		$this->items = $this->main_class->get_documents( $args );
		
		$total_items = LOP_MediaDocs::count_docs();
		$total_pages = ceil( $total_items / $per_page );

		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'total_pages' => $total_pages,
			'per_page' => $per_page,
		) );
	}

	protected function extra_tablenav( $which ) {
		echo '<div class="alignleft actions">';
		if ( 'top' == $which && !is_singular() ) {
			// filter by date
			$this->months_dropdown( 'attachment' );
			
			$dropdown_options = array(
				'name' => 'cat',
				'taxonomy' => LOP_MediaDocs::TAXONOMY,
				'show_option_all' => __( 'All Document Categories', 'lop-mediadocs' ),
				'show_option_none' => __( 'No category included', 'lop-mediadocs' ),
				'hide_empty' => 1,
				'hierarchical' => 1,
				'show_count' => 0,
				'orderby' => 'name',
				'selected' => isset( $_GET['cat'] )? intval($_GET['cat']) : 0,
			);
			echo '<label class="screen-reader-text" for="cat">' . __( 'Filter by category', 'lop-mediadocs' ) . '</label>';
			wp_dropdown_categories( $dropdown_options );
			
			submit_button( __( 'Filter', 'lop-mediadocs' ), 'button', 'filter_action', false, array( 'id' => 'post-query-submit' ) );
		}
		echo '</div>';
	}

	public function no_items() {
		_e( 'No documents found.', 'lop-mediadocs' );
	}

	public function single_row( $item ) {
		// store file info object only once for each row
		$this->fileinfo = LOP_MediaDocs::attachment_fileinfo_factory( $item );
		parent::single_row( $item );
	}
	
	protected function handle_row_actions( $item, $column_name, $primary ) {
		if ( $primary !== $column_name ) {
			return '';
		}
		
		$actions = array();
		if ( current_user_can( 'edit_post', $item->ID ) ) {
			$actions['edit'] = '<a href="' . get_edit_post_link( $item->ID ) . '">' . __( 'Edit', 'lop-mediadocs' ) . '</a>';
		}
		return $this->row_actions( $actions );
	}

	public function column_default( $item, $column_name ) {
		switch($column_name) {
			case 'taxonomy-' . LOP_MediaDocs::TAXONOMY:
				return $this->column_taxonomy( $item );
			case 'size':
				return $this->fileinfo->$column_name;
			default:
				return print_r( $item, true ) ; //Show the whole array for troubleshooting purposes
		}
	}

	public function column_title( $item ) {
		$link = sprintf( '<strong><a class="row-title" href="%s" title="%s">%s</a></strong>', esc_url( $this->fileinfo->url ), esc_attr( $this->fileinfo->name ), esc_html( $this->fileinfo->title ) );
		$desc = ( !empty( $this->fileinfo->caption ) )? sprintf( '<p>%s</p>', esc_html( $this->fileinfo->caption ) ) : '';
		return $link . $desc;
	}

	public function column_icon( $item ) {
		return $this->main_class->get_icon_html( $item->ID, 'medium' );
	}

	public function column_author( $item ) {
		$author = get_userdata( $item->post_author );
		return sprintf( '<a href="%s">%s</a>', esc_url( add_query_arg( array( 'author' => $item->post_author ) ) ), esc_html( $author->display_name ) );
	}

	public function column_date( $item ) {
		$time = get_post_time( 'G', true, $item, false );
		$time_diff = time() - $time;
		if ( $time_diff > 0 && $time_diff < DAY_IN_SECONDS ) {
			$h_time = sprintf( __( '%s  ago', 'lop-mediadocs' ), human_time_diff( $time ) );
		} else {
			$h_time = $this->fileinfo->date;
		}
		return $h_time;
	}

	// special MediaDocs Taxonomy column handler
	public function column_taxonomy( $item ) {
		if ( $terms = get_the_terms( $item->ID, LOP_MediaDocs::TAXONOMY ) ) {
			$out = array();
			foreach ( $terms as $t ) {
				$out[] = esc_html( sanitize_term_field( 'name', $t->name, $t->term_id, LOP_MediaDocs::TAXONOMY, 'display' ) );
			}
			return join( ', ', $out );
		} else {
			return '&#8212;';
		}
	}

	/*public function column_group( $item ) {
		global $oUserAccessManager;
		
		$aUamUserGroups = $oUserAccessManager->getAccessHandler()->getUsergroupsForObject('attachment', $item->ID);
		if ( !empty($aUamUserGroups) ) {
			$out = array();
			foreach ($aUamUserGroups as $oUamUserGroup) {
				$out[] = esc_html( $oUamUserGroup->getGroupName() );
			}
			return join( ', ', $out );
		} else {
			return '&#8212;';
		}
	}*/

}
