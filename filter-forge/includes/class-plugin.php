<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FF_Plugin {

    private static ?FF_Plugin $instance = null;

    public FF_Filter_State $filter_state;
    public FF_Query_Manager $query_manager;
    public FF_Count_Service $count_service;
    public FF_Relationship_Resolver $relationship_resolver;

    public static function dependencies_met(): bool {
        return class_exists( 'WooCommerce' ) && did_action( 'elementor/loaded' );
    }

    public static function boot(): void {
        if ( ! self::dependencies_met() ) {
            ( new FF_Requirements_Notice() )->register();
            return;
        }

        self::instance();
    }

    public static function instance(): FF_Plugin {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        $this->filter_state          = new FF_Filter_State();
        $this->count_service          = new FF_Count_Service();
        $this->relationship_resolver  = new FF_Relationship_Resolver();

        $category_filter      = new FF_Category_Filter( $this->filter_state );
        $meta_filter           = new FF_Meta_Filter( $this->filter_state );
        $this->query_manager   = new FF_Query_Manager( $category_filter, $meta_filter );
        $this->query_manager->register();

        add_action( 'elementor/elements/categories_registered', array( $this, 'register_widget_category' ) );
        add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    public function enqueue_assets(): void {
        wp_enqueue_style( 'ff-filters', FF_PLUGIN_URL . 'assets/css/ff-filters.css', array(), FF_VERSION );
        wp_enqueue_script( 'ff-url', FF_PLUGIN_URL . 'assets/js/ff-url.js', array(), FF_VERSION, true );
        wp_enqueue_script( 'ff-filters', FF_PLUGIN_URL . 'assets/js/ff-filters.js', array( 'ff-url' ), FF_VERSION, true );
    }

    public function register_widget_category( $elements_manager ): void {
        $elements_manager->add_category(
            'filter-forge',
            array(
                'title' => __( 'Filter Forge', 'filter-forge' ),
                'icon'  => 'eicon-filter',
            )
        );
    }

    public function register_widgets( $widgets_manager ): void {
        $widgets_manager->register( new FF_Widget_Filter() );
        $widgets_manager->register( new FF_Widget_Price() );
        $widgets_manager->register( new FF_Widget_Reset() );
    }
}
