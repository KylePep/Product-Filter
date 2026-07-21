<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class FF_Widget_Base extends \Elementor\Widget_Base {

    /**
     * div/span/p, not just h1-h6 -- these are labels within the filter
     * component, not document headings, so a heading tag isn't the default.
     */
    private const HEADER_TAGS = array( 'div', 'span', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' );

    public function get_categories(): array {
        return array( 'filter-forge' );
    }

    protected function register_header_controls(): void {
        $this->start_controls_section(
            'ff_header',
            array(
                'label' => __( 'Filter Header', 'filter-forge' ),
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'ff_header_text',
            array(
                'label'       => __( 'Header Text', 'filter-forge' ),
                'type'        => \Elementor\Controls_Manager::TEXT,
                'default'     => '',
                'placeholder' => __( 'e.g. Category', 'filter-forge' ),
            )
        );

        $this->add_control(
            'ff_header_icon',
            array(
                'label'     => __( 'Header Icon', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::ICONS,
                'condition' => array( 'ff_header_text!' => '' ),
            )
        );

        $this->add_control(
            'ff_header_tag',
            array(
                'label'     => __( 'Header HTML Tag', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::SELECT,
                'default'   => 'div',
                'options'   => array(
                    'div'  => 'div',
                    'span' => 'span',
                    'p'    => 'p',
                    'h1'   => 'H1',
                    'h2'   => 'H2',
                    'h3'   => 'H3',
                    'h4'   => 'H4',
                    'h5'   => 'H5',
                    'h6'   => 'H6',
                ),
                'condition' => array( 'ff_header_text!' => '' ),
            )
        );

        $this->end_controls_section();
    }

    /**
     * No-op (renders nothing) when ff_header_text is empty -- the header is
     * opt-in per widget instance.
     */
    protected function render_header(): void {
        $settings = $this->get_settings_for_display();
        $text     = trim( (string) ( $settings['ff_header_text'] ?? '' ) );

        if ( '' === $text ) {
            return;
        }

        $tag  = $settings['ff_header_tag'] ?? 'div';
        $tag  = in_array( $tag, self::HEADER_TAGS, true ) ? $tag : 'div';
        $icon = $settings['ff_header_icon'] ?? array();

        printf( '<%1$s class="ff-filter__header">', esc_attr( $tag ) );

        if ( ! empty( $icon['value'] ) ) {
            \Elementor\Icons_Manager::render_icon(
                $icon,
                array(
                    'class'       => 'ff-filter__header-icon',
                    'aria-hidden' => 'true',
                )
            );
        }

        printf( '<span class="ff-filter__header-text">%1$s</span></%2$s>', esc_html( $text ), esc_attr( $tag ) );
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
