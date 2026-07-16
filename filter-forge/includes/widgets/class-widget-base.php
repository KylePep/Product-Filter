<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class FF_Widget_Base extends \Elementor\Widget_Base {

    public function get_categories(): array {
        return array( 'filter-forge' );
    }

    protected function register_relationship_controls(): void {
        $this->start_controls_section(
            'ff_relationships',
            array(
                'label' => __( 'Filter Relationships', 'filter-forge' ),
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'ff_filter_key',
            array(
                'label'       => __( 'Filter Key', 'filter-forge' ),
                'type'        => \Elementor\Controls_Manager::TEXT,
                'default'     => '',
                'description' => __( 'A short identifier for this filter, e.g. "color". Other filters reference this to declare it as their parent.', 'filter-forge' ),
            )
        );

        $this->add_control(
            'ff_parent_filter_key',
            array(
                'label'       => __( 'Parent Filter Key', 'filter-forge' ),
                'type'        => \Elementor\Controls_Manager::TEXT,
                'default'     => '',
                'description' => __( 'The Filter Key of another filter on this page that this filter depends on.', 'filter-forge' ),
            )
        );

        $this->add_control(
            'ff_reset_on_parent_change',
            array(
                'label'     => __( 'Reset on parent change', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::SWITCHER,
                'default'   => '',
                'condition' => array( 'ff_parent_filter_key!' => '' ),
            )
        );

        $this->add_control(
            'ff_hide_until_parent_selected',
            array(
                'label'     => __( 'Hide until parent has a selection', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::SWITCHER,
                'default'   => '',
                'condition' => array( 'ff_parent_filter_key!' => '' ),
            )
        );

        $this->end_controls_section();
    }

    protected function get_relationship_config(): array {
        $settings = $this->get_settings_for_display();

        return array(
            'parent_key'          => $settings['ff_parent_filter_key'] ?? '',
            'reset_on_change'     => 'yes' === ( $settings['ff_reset_on_parent_change'] ?? '' ),
            'hide_until_selected' => 'yes' === ( $settings['ff_hide_until_parent_selected'] ?? '' ),
        );
    }
}
