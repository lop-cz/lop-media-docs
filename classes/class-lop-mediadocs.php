<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * LOP Media Docs main class.
 */
class LOP_MediaDocs {
	const TAXONOMY = 'docs_category';

	public $plugin_dir;
	protected $plugin_name = 'lop-mediadocs';
	protected $admin = null;
	//public $uam = null;
	public static $icon_size  = array( 'small' => 16, 'medium' => 32, 'large' => 48 );
	protected static $rewrite_slug;
	protected static $found_docs = 0;
	protected static $instance = null;
	
	/**
	 * Initialize the plugin.
	 */
	public function __construct() {
		$this->plugin_dir = dirname( dirname( __FILE__ ) );

		// load plugin text domain
		add_action( 'init', array( $this, 'load_plugin_textdomain' ), 1 );
		
		// load other classes
		$this->includes();
		    
		add_action( 'init', array( $this, 'init' ), 10 );
		//add_action( 'uam_init', array($this, 'uam_init') );
		add_action( 'widgets_init', array( $this, 'register_widget' ) );
	}
	
	public static function get_instance() {
		if ( null == self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	protected function includes() {
		// frontend
		require_once( $this->plugin_dir . '/classes/class-lop-widget-recent-docs.php' );
		
		// backend
		if ( is_admin() ) {
			require_once( $this->plugin_dir . '/classes/class-lop-mediadocs-admin.php' );
			$this->admin = new LOP_MediaDocs_Admin( $this );
			// dashborad
			// TODO: load only if needed E.g. on screen ID
			require_once( $this->plugin_dir . '/classes/class-lop-dashboard-recent-docs.php' );
		}
	}
	
	public function init() {
		global $wp_taxonomies;
		
		self::$rewrite_slug = sanitize_title( _x( 'documents', 'Documents rewrite slug', 'lop-mediadocs' ) );
		self::$rewrite_slug = apply_filters( 'lop_mediadocs_rewrite_slug', self::$rewrite_slug );
		
		// register custom taxonomy
		$this->register_taxonomy();

		// register for the default 'Tags' taxonomy
		register_taxonomy_for_object_type( 'post_tag', 'attachment' );
		// HACK to change 'update_count_callback' in Tags taxonomy to correctly count used tags for also the non-attached media
		$wp_taxonomies['post_tag']->update_count_callback = array( $this->admin, 'update_post_term_count' );
		
		// include documents in archive query
		add_filter( 'pre_get_posts', array( $this, 'include_documents_in_archives' ) );
		
		// Shortcode for [attachments]
		add_shortcode( 'attachments', array( $this, 'attachments_shortcode' ) );
	}

	/**
	 * Load the plugin text domain for translation.
	 */
	public function load_plugin_textdomain() {
		$domain = $this->plugin_name;
		load_plugin_textdomain( $domain, false, dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/' );
	}
	
	/**
	 * Return the plugin name/slug.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * Init hook from User Access Manager plugin.
	 */
	/*public function uam_init( UserAccessManager $obj ) {
		//$this->log($obj);
		// save reference to UserAccessManager instance
		$this->uam = $obj;
	}*/
	
	/**
	 * Register taxonomy.
	 */
	protected function register_taxonomy() {
		$labels = array(
			'name'              => _x( 'Document Categories', 'taxonomy general name', 'lop-mediadocs' ),
			'singular_name'     => _x( 'Document Category', 'taxonomy singular name', 'lop-mediadocs' ),
			'search_items'      => __( 'Search Document Categories', 'lop-mediadocs' ),
			'all_items'         => __( 'All Document Categories', 'lop-mediadocs' ),
			'parent_item'       => __( 'Parent Document Category', 'lop-mediadocs' ),
			'parent_item_colon' => __( 'Parent Document Category:', 'lop-mediadocs' ),
			'edit_item'         => __( 'Edit Document Category', 'lop-mediadocs' ), 
			'update_item'       => __( 'Update Document Category', 'lop-mediadocs' ),
			'add_new_item'      => __( 'Add New Document Category', 'lop-mediadocs' ),
			'new_item_name'     => __( 'New Document Category Name', 'lop-mediadocs' ),
		);
		$args = array(
			'hierarchical' => true,
			'labels'       => $labels,
			'public'       => true,
			'show_ui'      => true,
			'query_var'    => true,
			'rewrite'      => array(
				'slug' => self::$rewrite_slug,
				'with_front' => true,
				'hierarchical' => true,
			),
			'update_count_callback' => array( $this->admin, 'update_docs_term_count' ),	// custom term counting for this tax
			'show_admin_column' => true,	// since WP 3.5
		);

		register_taxonomy( self::TAXONOMY, 'attachment', $args );
	}
	
	/**
	 * Register widget.
	 */
	public function register_widget() {
		register_widget( 'LOP_Widget_Recent_Docs' );
	}
	
	/**
	 * Include Documents in its taxonomy archive and Tags archive query.
	 */
	public function include_documents_in_archives( $query ) {
				
		// include 'attachments' in Taxonomy archive
		if ( $query->is_tax( self::TAXONOMY ) && ! is_admin() ) {	// not needed for backend
			$query->set('post_type', 'attachment');
			$query->set('post_status', 'inherit');
			return;
		}

		// include 'attachments' in Tag archive
		if ( $query->is_tag() && ! is_admin() ) {
			$types = (array) $query->get('post_type');
			if ( in_array( 'any', $types ) )
				return;
			if ( empty( $types ) )
				$types = array( 'post' );

			$types = array_filter( array_unique( array_merge( $types, array( 'attachment' ) ) ) );	// add to other post types
			$query->set('post_type', $types);
			$query->set('post_status', array( 'publish', 'inherit' ));
		}
		// fix query vars defined by other plugins in backend
		if ( $query->is_tag() && is_admin() ) {
			$types = (array) $query->get('post_type');
			if ( in_array( 'attachment', $types ) && ! in_array( 'any', $types ) ) {
				$query->set('post_type', 'attachment');	// force to single post type
			}
		}
	}
	
	/**
	 * Get all or just usergroup documents, optionally within a category.
	 * @return array  Array of posts.
	 */
	public function get_documents( $args = '' ) {
		$defaults = array(
			'category'       => 0, // 0 = 'only docs in all/any categories' | -1 = 'include docs with no category'
			'limit'          => 10,
			'orderby'        => 'date',
			'order'          => 'DESC',
			//'usergroup_only' => false,
			'no_found_rows'  => true,
		);
		$args = wp_parse_args( $args, $defaults );
		
		// taxonomy subquery
		// TODO: include WPML translated terms
		if ( empty($args['category']) || intval($args['category']) == 0 ) {
			// get all non-empty terms
			$term_ids = get_terms( self::TAXONOMY, array('fields' => 'ids', 'hide_empty' => true, 'hierarchical' => false, 'orderby' => 'id') );
			$query_children = false;	// do not include children in tax query - IDs are already selected
		} else {
			$term_ids = array( intval($args['category']) );
			$query_children = true;
		}
		//$this->log($term_ids);
		$tax_query = array(
			array(
				'taxonomy' => self::TAXONOMY,
				'terms' => $term_ids,
				'include_children' => $query_children,
				'field' => 'term_id',
			)
		);
		
		$query_args = array(
			'post_type'        => 'attachment',
			'post_status'      => 'inherit',
			'post_mime_type'   => 'application',
			'orderby'          => $args['orderby'],
			'order'            => $args['order'],
			'posts_per_page'   => intval( $args['limit'] ),
			//'cache_results'    => false,
			//'perm'             => 'readable',
			'no_found_rows'    => $args['no_found_rows'],
			'ignore_sticky_posts' => true,
			'suppress_filters' => true,
		);
		
		if ( intval($args['category']) != -1 )
			$query_args['tax_query'] = $tax_query;
		
		if ( isset($args['offset']) )
			$query_args['offset'] = intval( $args['offset'] );
		
		if ( isset($args['s']) && !empty($args['s']) )
			$query_args['s'] = trim( $args['s'] );
		
		if ( isset($args['author']) && !empty($args['author']) )
			$query_args['author'] = intval( $args['author'] );
		
		if ( isset($args['m']) && !empty($args['m']) )
			$query_args['m'] = intval( $args['m'] );
		
		// Show documents from user group only (locked)
		/*if ( $args['usergroup_only'] && $this->uam ) {
			$group_post_ids = (array) $this->uam->getAccessHandler()->getPostsForUser();
			$group_term_ids = (array) $this->uam->getAccessHandler()->getCategoriesForUser();
			if ( !empty($group_term_ids) ) {
				// include children terms
				$term_children = array();
				foreach ($group_term_ids as $term_id) {
					$term_children = array_merge( $term_children, get_term_children($term_id, self::TAXONOMY) );	// terms in other taxonomies will be skipped
				}
				$group_term_ids = array_unique( array_merge( $group_term_ids, $term_children ) );
				// get all object IDs from locked categories and subcategories
				$term_post_ids = get_objects_in_term( $group_term_ids, self::TAXONOMY );
				if ( !empty($term_post_ids) ) {
					$group_post_ids = array_unique( array_merge($group_post_ids, $term_post_ids) );
				}
			}
			//$this->log($group_post_ids);
			if ( empty($group_post_ids)  && !is_super_admin() ) {
				$group_post_ids = array(0);	// set non-existent post ID to make empty result
			}
			$query_args['post__in'] = $group_post_ids;
			//$query_args['posts_per_page'] = count($group_post_ids);	// only the number of posts included
		}*/
		
		$q = new WP_Query();
		$posts = $q->query( $query_args );
		
		self::$found_docs = $q->found_posts;

		if ( ! is_wp_error( $posts ) && is_array( $posts ) && count( $posts ) > 0 ) {
			return $posts;
		} else {
			return array();
		}
	}
	
	/**
	 * List all or just usergroup documents, optionally within a category.
	 * @return string/null  Will return html string or print if echo is true.
	 */
	public function list_documents( $args = '' ) {
		$defaults = array(
			'category'       => 0,
			'limit'          => 10,
			'orderby'        => 'date',
			'order'          => 'DESC',
			//'usergroup_only' => false,
			'before'         => '<ul>',
			'after'          => '</ul>',
			'item_tpl'       => '<li><a href="{{url}}" title="{{filename}}" target="_blank">{{icon}}{{title}}</a></li>',
			'icon_size'      => 'small',
			'echo'           => true,
			'not_found'      => __( 'No documents found.', 'lop-mediadocs' ),
		);
		$args = wp_parse_args( $args, $defaults );
		
		$html = '';
		$tpl = $args['item_tpl'];
		
		$posts = $this->get_documents( $args );
		
		if ( count( $posts ) > 0 ) {
			$html .= $args['before'];
						
			foreach ( $posts as $post ) {
				$file = self::attachment_fileinfo_factory( $post );
				$tplTags = array(
					'{{url}}'      => esc_url( $file->url ),
					'{{filename}}' => esc_attr( $file->name ),
					'{{date}}'     => $file->date,
					'{{title}}'    => esc_html( $file->title ),
					'{{caption}}'  => esc_html( $file->caption ),
					'{{icon}}'     => $this->get_icon_html( $post->ID, $args['icon_size'], $file->ext ),
					'{{type}}'     => esc_attr( $file->type ),
				);
				$html .= str_replace( array_keys($tplTags), array_values($tplTags), $tpl );
			}
			
			$html .= $args['after'];
		} else {
			$html = $args['not_found'];
		}
		
		if ( $args['echo'] != true ) {
			return $html;
		} else {
			echo $html;
		}
	}
	
	/**
	 * Get the attachments for the post.
	 * Simple wrapper for get_children() function to handle multiple attachments.
	 *
	 * @param  string/array $args Arguments for the query.
	 * @return array/boolean      Array if true, boolean if false.
	 */
	public function get_attachments( $args = '' ) {
		global $post;
		$defaults = array(
			'post_parent' => $post->ID,
			'post_type' => 'attachment',
			'post_status' => 'inherit',
			'post_mime_type' => '',		// 'application' for documents only
			'orderby' => 'menu_order ID',
			'order' => 'ASC',
			'numberposts' => -1,
			//'suppress_fun' => true
		);
		$args = wp_parse_args( $args, $defaults );
		
		$attachments = get_children($args);

		if ( empty($attachments) ) {
			return false;
		} else {
			return $attachments;
		}
	}
	
	/**
	 * Retrieve the amount of attachments a post has.
	 *
	 * @uses get_attachments()  TODO: Use lighter SQL like wp_count_attachments()
	 *
	 * @param int|WP_Post $post_id Optional. Post ID or WP_Post object. Default is global $post.
	 * @return int The number of attachments a post has.
	 */
	public function get_attachments_number( $post_id = 0 ) {
		$post = get_post( $post_id );
		$args = array(
			'post_parent' => $post->ID,
			'post_mime_type' => 'application',
		);
		
		$attachments = $this->get_attachments( $args );

		if ( ! $post || ! $attachments ) {
			$count = 0;
		} else {
			$count = count( $attachments );
		}
		return $count;
	}
	
	/**
	 * Display the list of attachments via [attachments] shortcode.
	 *
	 * @uses get_attachments()
	 */
	public function attachments_shortcode( $attr ) {
		global $post;
		$defaults = array(
			'size'      => 'medium',
			'id'        => $post->ID,
			'doctype'   => 'document',
			'orderby'   => 'title',
			'order'     => 'ASC',
			'limit'     => -1,
			'nofollow'  => true,
			//'target'    => false,
			'before'    => '',
			'after'     => '',
			'format'    => 'list',		// or 'flat'
			'separator' => "\n",
		);
		extract( shortcode_atts( $defaults, $attr ) );
		
		$id = intval($id);
		// mime_type
		switch ( $doctype ) {
			case 'document':
				$mime_type = 'application'; break;
			case 'all':
				$mime_type = ''; break;
			default:
				$mime_type = $doctype;
		}
		
		// get attachments for the post
		$args = array(
			'post_parent' => $id,
			'post_mime_type' => $mime_type,
			'orderby' => $orderby,
			'order' => $order,
			'numberposts' => $limit,
		);
		$attachments = $this->get_attachments( $args );
		
		if ( ! $attachments )
			return '';
		
		$link_attr = 'target="_blank"';
		$link_attr .= $nofollow ? ' rel="nofollow"' : '';
		$tpl = sprintf( '<a href="{{url}}" title="{{filename}}" class="{{type}}" %s><span class="icon">{{icon}}</span> {{title}} <span class="filesize">{{size}}</span><p class="caption">{{caption}}</p></a>', $link_attr );
		/**
		 * Filter the attachments item template.
		 */
		$tpl = apply_filters( 'lop_mediadocs_attachments_item_template', $tpl );
		
		$attachments_list = array();
		foreach ( $attachments as $aid => $attachment ) {
			$file = self::attachment_fileinfo_factory( $attachment );
			$tplTags = array(
				'{{url}}'      => esc_url( $file->url ),
				'{{filename}}' => esc_attr( $file->name ),
				'{{date}}'     => $file->date,
				'{{title}}'    => esc_html( $file->title ),
				'{{caption}}'  => esc_html( $file->caption ),
				'{{icon}}'     => $this->get_icon_html( $attachment->ID, $size, $file->ext ),
				'{{type}}'     => esc_attr( $file->type ),
				'{{size}}'     => $file->size,
			);
			$attachments_list[] = str_replace( array_keys($tplTags), array_values($tplTags), $tpl );
		}
		
		if ( $format == 'list' ) {
			$output = '<ul class="attachments-list attachments-' . $size . '">' . "\n<li>";
			$output .= join( "</li>\n<li>", $attachments_list );
			$output .= "</li></ul>\n";
		} else {
			$output = join( $separator, $attachments_list );
		}
		
		$output = $before . $output . $after;
		/**
		 * Filter the attachments list output.
		 */
		return apply_filters( 'lop_mediadocs_attachments', $output, $attachments );
	}
	
	/**
	 * Return <img> icon tag for the attachment mime type.
	 *
	 * By passing attachment ID the icon will be based on file extension (on top of mime type).
	 *
	 * @param string|int $mime MIME type or attachment ID.
	 * @param string|int $size Size name or explicit number.
	 * @param string     $alt  Alt text.
	 */
	public function get_icon_html( $mime, $size = 'small', $alt = '' ) {
		$width = ( is_string($size) && array_key_exists($size, self::$icon_size) ) ? self::$icon_size[$size] : $size;
		
		if ( ! $icon_url = wp_mime_type_icon( $mime ) ) {
			$icon_url = includes_url( 'images/media/default.png' );
		}
		// change icons path according to required size
		//$icon_url = preg_replace( '#/(16|32|48)/#', "/$width/", $icon_url );
		
		if ( '' == $alt && is_string($mime) ) {
			// use subtype of mime as 'alt' text by default
			$alt = str_replace( 'vnd.', '', substr($mime, strpos($mime, '/') + 1) );
		}
		
		$html = sprintf( '<img src="%s" width="%s" alt="%s" />', $icon_url, $width, $alt );
		/**
		 * Filter img tag of the attachment icon.
		 */
		return apply_filters( 'lop_mediadocs_get_icon_html', $html, $mime, $icon_url, $width, $alt );
	}
	
	/**
	 * Prepare and return file info object from attachment object.
	 *
	 * @return object     File info object.
	 */
	public static function attachment_fileinfo_factory( $attachment ) {
		$file = new StdClass();
		$file->path = get_attached_file( $attachment->ID );	// TODO: check for post_type
		$file->size = size_format( @filesize( $file->path ) );
		$file->name = wp_basename( $file->path );
		$file->date = mysql2date( get_option( 'date_format' ), $attachment->post_date, true );
		$file->ext  = preg_replace( '/^.+?\.([^.]+)$/', '$1', $file->path ); // wp_check_filetype( $file->path )
		$file->type = wp_ext2type( $file->ext );	// returns simple file type based on the extension
		/**
		 * Filter the attachment file type.
		 */
		$file->type = apply_filters( 'lop_mediadocs_attachment_type', $file->type, $attachment );
		/**
		 * Filter the attachment title.
		 */
		$file->title = apply_filters( 'lop_mediadocs_attachment_title', $attachment->post_title, $attachment->ID );
		$file->caption = wp_trim_words( strip_shortcodes( $attachment->post_excerpt ), 30 ); // trim to 30 words 
		$file->url = wp_get_attachment_url( $attachment->ID );
		return $file;
	}
	
	/**
	 * Return the found_posts from last WP_Query.
	 */
	public static function count_docs() {
		return self::$found_docs;
	}
	
	/**
	 * Return the rewrite slug of taxonomy.
	 */
	public static function slug() {
		return self::$rewrite_slug;
	}

	public static function log( $data = array() ) {
		error_log( print_r($data, 1) );
	}
}
