<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * LOP Recent Documents widget class
 */
class LOP_Widget_Recent_Docs extends WP_Widget {

	public function __construct() {
		$widget_ops = array(
			'classname' => 'widget_recent_docs',
			'description' => __( 'The most recent documents on your site', 'lop-mediadocs' ),
			'customize_selective_refresh' => true,
		);
		parent::__construct( 'recent-docs', __( 'Recent Documents', 'lop-mediadocs' ), $widget_ops );
		//$this->alt_option_name = 'widget_recent_entries';

		//add_action( 'save_post', array($this, 'flush_widget_cache') );
		//add_action( 'deleted_post', array($this, 'flush_widget_cache') );
		//add_action( 'switch_theme', array($this, 'flush_widget_cache') );
	}

	public function widget( $args, $instance ) {
		$lop_mediadocs = LOP_MediaDocs::get_instance();
		
		//$cache = array();
		//if ( ! $this->is_preview() ) {
		//	$cache = wp_cache_get( 'widget_recent_docs', 'widget' );
		//}
		//if ( !is_array($cache) )
		//	$cache = array();

		if ( ! isset( $args['widget_id'] ) )
			$args['widget_id'] = $this->id;

		//if ( isset( $cache[ $args['widget_id'] ] ) ) {
		//	echo $cache[ $args['widget_id'] ];
		//	return;
		//}

		//ob_start();

		$title = ( ! empty( $instance['title'] ) ) ? $instance['title'] : __( 'Recent Documents', 'lop-mediadocs' );
		$title = apply_filters( 'widget_title', $title, $instance, $this->id_base );
		
		if ( empty( $instance['number'] ) || ! $number = absint( $instance['number'] ) ) {
 			$number = 10;
 		}

		echo $args['before_widget'];
		if ( $title ) {
			echo $args['before_title'] . $title . $args['after_title'];
		}
		
		/**
		 * Filter the list args.
		 */
		$lop_mediadocs->list_documents( apply_filters( 'lop_widget_recent_docs_args', array(
			'category' => $instance['category'],
			'limit' => $number,
		) ) );
		
		echo $args['after_widget'];

		//if ( ! $this->is_preview() ) {
		//	$cache[ $args['widget_id'] ] = ob_get_flush();
		//	wp_cache_set( 'widget_recent_docs', $cache, 'widget' );
		//} else {
		//	ob_end_flush();
		//}
	}

	public function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = sanitize_text_field( $new_instance['title'] );
		$instance['number'] = (int) $new_instance['number'];
		$instance['category'] = (int) $new_instance['category'];
		//$this->flush_widget_cache();

		//$alloptions = wp_cache_get( 'alloptions', 'options' );
		//if ( isset($alloptions['widget_recent_docs']) )
		//	delete_option('widget_recent_docs');

		return $instance;
	}

	//public function flush_widget_cache() {
	//	wp_cache_delete('widget_recent_docs', 'widget');
	//}

	public function form( $instance ) {
		$title = isset( $instance['title'] ) ? esc_attr( $instance['title'] ) : '';
		$number = isset( $instance['number'] ) ? absint( $instance['number'] ) : 10;
		$category = isset( $instance['category'] ) ? intval( $instance['category'] ) : -1;
?>
		<p><label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:', 'lop-mediadocs' ); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>" /></p>

		<p><label for="<?php echo $this->get_field_id( 'number' ); ?>"><?php _e( 'Number of posts to show:', 'lop-mediadocs' ); ?></label>
		<input class="tiny-text" id="<?php echo $this->get_field_id( 'number' ); ?>" name="<?php echo $this->get_field_name( 'number' ); ?>" type="number" step="1" min="1" value="<?php echo $number; ?>" size="3" /></p>

		<p><label for="<?php echo $this->get_field_id( 'category' ); ?>"><?php _e( 'Document Category', 'lop-mediadocs' ); ?>:</label>
		<?php
			$dp_args = array(
				'id' => $this->get_field_id( 'category' ), 
				'name' => $this->get_field_name( 'category' ), 
				'class' => 'widefat', 
				'taxonomy' => LOP_MediaDocs::TAXONOMY, 
				'show_option_all' => __( 'All Document Categories', 'lop-mediadocs' ),
				'show_option_none' => __( 'No category included', 'lop-mediadocs' ),
				'hierarchical' => 1,
				'selected' => $category,
			);
			wp_dropdown_categories( $dp_args ); ?></p>
<?php
	}
}
