<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FF_Widget_Reset extends \Elementor\Widget_Base {

    public function get_name(): string {
        return 'ff-reset';
    }

    public function get_title(): string {
        return __( 'Reset Filters - Forge', 'filter-forge' );
    }

    public function get_icon(): string {
        return 'ff-icon-anvil';
    }

    public function get_categories(): array {
        return array( 'filter-forge' );
    }

    protected function register_controls(): void {
        $this->start_controls_section(
            'ff_reset_content',
            array(
                'label' => __( 'Content', 'filter-forge' ),
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'ff_reset_label',
            array(
                'label'   => __( 'Label', 'filter-forge' ),
                'type'    => \Elementor\Controls_Manager::TEXT,
                'default' => __( 'Reset Filters', 'filter-forge' ),
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'ff_reset_style',
            array(
                'label' => __( 'Style', 'filter-forge' ),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'ff_reset_text_color',
            array(
                'label'     => __( 'Text Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .ff-reset' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'ff_reset_text_color_hover',
            array(
                'label'     => __( 'Hover Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .ff-reset:hover' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name'     => 'ff_reset_typography',
                'selector' => '{{WRAPPER}} .ff-reset',
            )
        );

        $this->add_control(
            'ff_reset_text_decoration',
            array(
                'label'     => __( 'Text Decoration', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::SELECT,
                'default'   => '',
                'options'   => array(
                    ''             => __( 'Default', 'filter-forge' ),
                    'none'         => __( 'None', 'filter-forge' ),
                    'underline'    => __( 'Underline', 'filter-forge' ),
                    'overline'     => __( 'Overline', 'filter-forge' ),
                    'line-through' => __( 'Line Through', 'filter-forge' ),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .ff-reset' => 'text-decoration: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();
    }

    public static function canonical_url( string $requested_url ): string {
        $parts = wp_parse_url( $requested_url );

        return ( $parts['scheme'] ?? '' ) . '://' . ( $parts['host'] ?? '' ) . ( $parts['path'] ?? '' );
    }

    public function render(): void {
        $settings = $this->get_settings_for_display();
        $url      = self::canonical_url( home_url( add_query_arg( array(), $_SERVER['REQUEST_URI'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

        printf(
            '<a class="ff-reset" href="%1$s">%2$s</a>',
            esc_url( $url ),
            esc_html( $settings['ff_reset_label'] ?? __( 'Reset Filters', 'filter-forge' ) )
        );
    }
}
