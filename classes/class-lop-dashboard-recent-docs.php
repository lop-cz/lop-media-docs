<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * LOP Recent (Group) Documents Dashboard class
 */
class LOP_Dashboard_Recent_Docs {

	protected $widget_id;
	protected $defaults;
	protected $group_exists;

	public function __construct() {
		$this->widget_id = strtolower( get_class( $this ) );
		$this->defaults = array( 'number' => 10, 'category' => -1 );
		// dashboard hook
		add_action( 'wp_dashboard_setup', array( $this, 'init' ) );
	}

	public function init() {
		// whether group access restrictions are enabled on site
		//$this->group_exists = class_exists( 'UserAccessManager' );
		// Register the dashboard widget
		$title = ( $this->group_exists )? __( 'Recent Group Documents', 'lop-mediadocs' ) : __( 'Recent Documents', 'lop-mediadocs' );
		wp_add_dashboard_widget( $this->widget_id, $title, array( $this, 'widget' ), array( $this, 'form' ) );
	}

	public function widget() {
		$lop_mediadocs = LOP_MediaDocs::get_instance();
		
		$opts = $this->get_dashboard_widget_options();
		$number = absint( $opts['number'] );
 		$category = intval( $opts['category'] );

		// output the list
		$lop_mediadocs->list_documents( array(
			'category' => $category,
			'limit' => $number,
			//'usergroup_only' => $this->group_exists,
			'item_tpl' => '<li><a href="{{url}}" title="{{filename}}" target="_blank">{{icon}}{{title}}</a><span class="meta">{{date}}</span></li>',
			'after' => sprintf( '</ul> <p class="view-all"><a href="%s">%s</a></p>', esc_url( admin_url( 'index.php?page=lop-mediadocs-overview' ) ), __( 'View all', 'lop-mediadocs' ) ),
		) );
	}

	public function form() {
		if ( 'POST' == $_SERVER['REQUEST_METHOD'] && isset( $_POST['widget-' . $this->widget_id] ) ) {
			$opts = $this->update_dashboard_widget_options( $_POST['widget-' . $this->widget_id] );
		} else {
			$opts = $this->get_dashboard_widget_options();
		}
?>
		<p><label for="<?php echo $this->get_field_id( 'number' ); ?>"><?php _e( 'Number of posts to show:', 'lop-mediadocs' ); ?></label>
		<input class="tiny-text" id="<?php echo $this->get_field_id( 'number' ); ?>" name="<?php echo $this->get_field_name( 'number' ); ?>" type="number" step="1" min="1" value="<?php echo $opts['number']; ?>" size="3" /></p>

		<p><label for="<?php echo $this->get_field_id( 'category' ); ?>"><?php _e( 'Document Category', 'lop-mediadocs' ); ?>:</label>
		<?php
			$dp_args = array(
				'id' => $this->get_field_id( 'category' ), 
				'name' => $this->get_field_name( 'category' ), 
				//'class' => 'widefat', 
				'taxonomy' => LOP_MediaDocs::TAXONOMY, 
				'show_option_all' => __( 'All Document Categories', 'lop-mediadocs' ),
				'show_option_none' => __( 'No category included', 'lop-mediadocs' ),
				'hierarchical' => 1,
				'selected' => $opts['category'],
			);
			wp_dropdown_categories( $dp_args ); ?></p>
<?php
	}

	protected function get_dashboard_widget_options() {
		$all_opts = get_option( 'dashboard_widget_options' );
		if ( isset( $all_opts[$this->widget_id] ) ) {
			return $all_opts[$this->widget_id];
		} else {
			return $this->defaults;
		}
	}

	protected function update_dashboard_widget_options( $opts ) {
		if ( !$all_opts = get_option( 'dashboard_widget_options' ) )
			$all_opts = array();
		if ( !isset( $all_opts[$this->widget_id] ) )
			$all_opts[$this->widget_id] = array();

		$all_opts[$this->widget_id]['number'] = isset( $opts['number'] ) ? absint( $opts['number'] ) : $this->defaults['number'];
		$all_opts[$this->widget_id]['category'] = isset( $opts['category'] ) ? intval( $opts['category'] ) : $this->defaults['category'];
		update_option( 'dashboard_widget_options', $all_opts );
		return $all_opts[$this->widget_id];
	}

	protected function get_field_id( $field_name ) {
		return 'widget-' . $this->widget_id . '-' . $field_name;
	}

	protected function get_field_name( $field_name ) {
		return 'widget-' . $this->widget_id . '[' . $field_name . ']';
	}
}

return new LOP_Dashboard_Recent_Docs();
