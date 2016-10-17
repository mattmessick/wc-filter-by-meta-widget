<?php
/**
 * Plugin Name: Filter by Meta Widget
 * Plugin URI: http://www.mattmessick.com/woocommerce-layered-nav-widget-with-metadata/
 * Description: Shows postmeta in a widget which lets you narrow down the list of products when viewing product categories.
 * Version: 1.0.0
 * Author: WooCommerce
 * Author URI: http://woocommerce.com/
 * Developer: Matt Messick
 * Developer URI: http://mattmessick.com/
 *
 * @package Filter_By_Meta_Widget
 * @author Matt Messick
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Check if WooCommerce is active
 **/
if (! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    return;
}

/**
 * Init Filter by Meta Widget
 */
function filter_by_meta_widget_init()
{

    /**
     * Register WooCommerce Filter by Meta Widget
     */
    function register_meta_product_filter()
    {
        register_widget('Filter_By_Meta_Widget');
    }
    add_action('widgets_init', 'register_meta_product_filter');

    /**
     * WooCommerce Filter by Meta Widget
     */
    class Filter_By_Meta_Widget extends WC_Widget
    {
        /**
         * Stores filter name.
         *
         * @var string
         */
        private $filter_name = 'filter_by';

        /**
         * Stores active filters.
         *
         * @var array
         */
        private static $filters = array(
            // Example: Filter by custom product meta.
            'clearance' => array(
                'type' => 'checkbox',
                'std' => 0,
                'label' => 'Clearance',
                'query' => array(
                    'key' => '_clearance',
                    'value' => 1,
                    'compare' => '='
                )
            ),

            // Example: Filter by WooCommerce product meta.
            'in_stock' => array(
                'type' => 'checkbox',
                'std' => 0,
                'label' => 'In Stock',
                'query' => array(
                    'key' => '_stock_status',
                    'value' => 'instock',
                    'compare' => '='
                )
            )
        );

        /**
         * Stores chosen filters.
         *
         * @var array
         */
        private static $chosen_filters;

        /**
         * Stores instance.
         *
         * @var array
         */
        private static $instance;

        /**
         * Constructor.
         */
        public function __construct()
        {
            $this->widget_cssclass = 'filter_by_meta_widget woocommerce widget_layered_nav';
            $this->widget_description = 'Shows postmeta in a widget which lets you narrow down the list of products when viewing product categories.';
            $this->widget_id = 'filter_by_meta';
            $this->widget_name = 'WooCommerce Filter by Meta';

            add_filter('woocommerce_layered_nav_link', array(&$this, 'woocommerce_layered_nav_link'));
            add_filter('woocommerce_rating_filter_link', array(&$this, 'woocommerce_rating_filter_link'));
            add_action('woocommerce_product_query', array(&$this, 'woocommerce_product_query'));

            parent::__construct();
        }

        /**
         * Get chosen filters.
         *
         * @return array
         */
        private function get_chosen_filters()
        {
            if ( ! is_array( self::$chosen_filters ) ) {
                self::$chosen_filters = ! empty( $_GET[$this->filter_name] ) ? explode( ',', sanitize_text_field( $_GET[$this->filter_name] ) ) : array();
            }

            return self::$chosen_filters;
        }

        /**
         * Chosen filters link.
         *
         * @param string $link
         *
         * @return string
         */
        private function filters_link($link)
        {
            if ( ! empty( self::$chosen_filters ) ) {
                $link = add_query_arg( $this->filter_name, implode( ',', self::$chosen_filters ), $link );
            }

            return $link;
        }

        /**
         * WooCommerce filter - Layered nav widget link.
         *
         * @param string $link
         *
         * @return string
         */
        public function woocommerce_layered_nav_link($link)
        {
            return $this->filters_link($link);
        }

        /**
         * WooCommerce filter - Rating filter widget link.
         *
         * @param string $link
         *
         * @return string
         */
        public function woocommerce_rating_filter_link($link)
        {
            return $this->filters_link($link);
        }

        /**
         * WooCommerce action - Query the WooCommerce products.
         *
         * @param mixed $q
         */
        public function woocommerce_product_query($q)
        {
            $q->set( 'meta_query', $this->get_meta_query( $q->get( 'meta_query' ) ) );
        }

        /**
         * Appends meta queries to an array.
         *
         * @param  array $meta_query
         *
         * @return array
         */
        private function get_meta_query($meta_query = array())
        {
            if ( ! is_array( $meta_query ) ) {
                $meta_query = array();
            }

            if ( $chosen_filters = $this->get_chosen_filters() ) {

                foreach ( $chosen_filters as $filter ) {
                    if (isset(self::$filters[$filter])) {
                        $meta_query[$filter] = self::$filters[$filter]['query'];
                    }
                }
            }

            return $meta_query;
        }

        /**
         * Outputs the settings update form.
         *
         * @see WC_Widget->form
         *
         * @param array $instance
         */
        public function form( $instance )
        {
            $this->init_settings();
            parent::form( $instance );
        }
        
        /**
         * Updates a particular instance of a widget.
         *
         * @see WC_Widget->update
         *
         * @param array $new_instance
         * @param array $old_instance
         *
         * @return array
         */
        public function update( $new_instance, $old_instance )
        {
            $this->init_settings();
            return parent::update( $new_instance, $old_instance );
        }

        /**
         * Init settings after post types are registered.
         */
        private function init_settings()
        {
            $this->settings = array(
                'title' => array(
                    'type'  => 'text',
                    'std'   => 'Filter by',
                    'label' => 'Title'
                )
            );
        }

        /**
         * Output widget.
         *
         * @see WC_Widget->widget
         *
         * @param array $args
         * @param array $instance
         */
        public function widget($args, $instance)
        {
            if ( ! is_post_type_archive( 'product' ) && ! is_tax( get_object_taxonomies( 'product' ) ) ) {
                return;
            }

            // Remember chosen filters for WooCommerce price filter widget.
            if ( $chosen_filters = $this->get_chosen_filters() ) {
                wc_enqueue_js("
                    var element = document.querySelector('.widget_price_filter form .price_slider_amount');
                    
                    if (element !== null) {
                        element.insertAdjacentHTML(
                            'beforeend', '<input type=\"hidden\" name=\"" . $this->filter_name . "\" value=\"" . implode( ',', $chosen_filters ) . "\">'
                        );
                    }
                ");
            }
            
            ob_start();

            echo $args['before_widget'];

            if ( $title = apply_filters( 'widget_title', empty( $instance['title'] ) ? '' : $instance['title'], $instance ) ) {
                echo $args['before_title'] . $title . $args['after_title'];
            }

            $found = $this->layered_nav_list();

            echo $args['after_widget'];

            if ( ! $found ) {
                ob_end_clean();
            } else {
                echo ob_get_clean();
            }

        }

        /**
         * Show list based layered nav.
         *
         * @see WC_Widget_Layered_Nav->layered_nav_list
         * 
         * @param array $instance
         *
         * @return bool
         */
        private function layered_nav_list()
        {
            global $wpdb;

            $found = false;

            echo '<ul>';

            $counts = $this->get_filtered_product_counts();
            
            foreach (self::$filters as $filter => $filter_data) {

                $current_filters = $this->get_chosen_filters();
                $option_is_set = in_array( $filter, $current_filters );
                $count = property_exists($counts, $filter) ? $counts->{$filter} : 0;

                if ( $count > 0 ) {
                    $found = true;
                } elseif ($count == 0 && ! $option_is_set ) {
                    continue;
                }

                if ( ! $option_is_set ) {
                    $current_filters[] = $filter;
                }

                $link = $this->get_page_base_url();

                foreach ( $current_filters as $key => $value ) {
                    if ( $option_is_set && $value === $filter ) {
                        unset( $current_filters[ $key ] );
                    }
                }

                if ( ! empty( $current_filters ) ) {
                    $link = add_query_arg( $this->filter_name, implode( ',', $current_filters ), $link );
                }

                echo '<li class="wc-layered-nav-term ' . ( $option_is_set ? 'chosen' : '' ) . '">';

                echo ( $count > 0 || $option_is_set ) ? '<a href="' . esc_url( $link ) . '">' : '<span>';

                echo esc_html( $filter_data['label'] );

                echo ( $count > 0 || $option_is_set ) ? '</a> ' : '</span> ';

                echo '<span class="count">(' . absint( $count ) . ')</span>';

                echo '</li>';
                
            }

            echo '</ul>';

            return $found;
        }

        /**
         * Count products within certain terms, taking the main WP query into consideration.
         *
         * @see WC_Widget_Layered_Nav->get_filtered_term_product_counts
         * 
         * @return array
         */
        private function get_filtered_product_counts()
        {
            global $wpdb;

            $query['select'] = 'SELECT DISTINCT ';
            $size = count(self::$filters);
            $counter = 1;

            foreach (self::$filters as $filter => $filter_data)
            {
                $tax_query  = WC_Query::get_main_tax_query();
                $meta_query = WC_Query::get_main_meta_query();

                $meta_query[$filter] = $filter_data['query'];

                $meta_query      = new WP_Meta_Query( $meta_query );
                $tax_query       = new WP_Tax_Query( $tax_query );

                $meta_query_sql  = $meta_query->get_sql( 'post', $wpdb->posts, 'ID' );
                $tax_query_sql   = $tax_query->get_sql( $wpdb->posts, 'ID' );

                $query['select'] .= "( SELECT COUNT( DISTINCT {$wpdb->posts}.ID ) FROM {$wpdb->posts}";
                $query['select'] .= $tax_query_sql['join'] . $meta_query_sql['join'];
                $query['select'] .= "
                    WHERE {$wpdb->posts}.post_type IN ( 'product' )
                    AND {$wpdb->posts}.post_status = 'publish'
                " . $tax_query_sql['where'] . $meta_query_sql['where'];

                if ( $search = WC_Query::get_main_search_query_sql() ) {
                    $query['select'] .= ' AND ' . $search;
                }

                $query['select'] .= ') AS ' . $filter;

                if ($counter < $size) {
                    $query['select'] .= ',';
                }

                $counter++;
            }

            $query['from'] = "FROM {$wpdb->posts}";

            $query = implode(' ', $query);
            $results = $wpdb->get_row( $query );

            return $results;
        }

        /**
         * Get current page URL for layered nav items.
         *
         * @see WC_Widget_Layered_Nav->get_page_base_url
         *
         * @return string
         */
        private function get_page_base_url()
        {
            if ( defined( 'SHOP_IS_ON_FRONT' ) ) {
                $link = home_url();
            } elseif ( is_post_type_archive( 'product' ) || is_page( wc_get_page_id( 'shop' ) ) ) {
                $link = get_post_type_archive_link( 'product' );
            } elseif ( is_product_category() ) {
                $link = get_term_link( get_query_var( 'product_cat' ), 'product_cat' );
            } elseif ( is_product_tag() ) {
                $link = get_term_link( get_query_var( 'product_tag' ), 'product_tag' );
            } else {
                $queried_object = get_queried_object();
                $link = get_term_link( $queried_object->slug, $queried_object->taxonomy );
            }

            // Min/Max
            if ( isset( $_GET['min_price'] ) ) {
                $link = add_query_arg( 'min_price', wc_clean( $_GET['min_price'] ), $link );
            }

            if ( isset( $_GET['max_price'] ) ) {
                $link = add_query_arg( 'max_price', wc_clean( $_GET['max_price'] ), $link );
            }

            // Orderby
            if ( isset( $_GET['orderby'] ) ) {
                $link = add_query_arg( 'orderby', wc_clean( $_GET['orderby'] ), $link );
            }

            /**
             * Search Arg.
             * To support quote characters, first they are decoded from &quot; entities, then URL encoded.
             */
            if ( get_search_query() ) {
                $link = add_query_arg( 's', rawurlencode( htmlspecialchars_decode( get_search_query() ) ), $link );
            }

            // Post Type Arg
            if ( isset( $_GET['post_type'] ) ) {
                $link = add_query_arg( 'post_type', wc_clean( $_GET['post_type'] ), $link );
            }

            // Min Rating Arg
            if ( isset( $_GET['min_rating'] ) ) {
                $link = add_query_arg( 'min_rating', wc_clean( $_GET['min_rating'] ), $link );
            }

            // Attributes
            if ( $_chosen_attributes = WC_Query::get_layered_nav_chosen_attributes() ) {
                foreach ( $_chosen_attributes as $name => $data ) {
                    $filter_name = sanitize_title( str_replace( 'pa_', '', $name ) );
                    if ( ! empty( $data['terms'] ) ) {
                        $link = add_query_arg( 'filter_' . $filter_name, implode( ',', $data['terms'] ), $link );
                    }
                    if ( 'or' == $data['query_type'] ) {
                        $link = add_query_arg( 'query_type_' . $filter_name, 'or', $link );
                    }
                }
            }

            return $link;
        }
    }
}

add_action('woocommerce_init', 'filter_by_meta_widget_init');