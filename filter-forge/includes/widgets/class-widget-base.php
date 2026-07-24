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

    protected function register_text_style_controls(): void {
        $this->start_controls_section(
            'ff_style_text',
            array(
                'label' => __( 'Text', 'filter-forge' ),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'ff_text_color',
            array(
                'label'     => __( 'Text Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}}' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name'     => 'ff_text_typography',
                'selector' => '{{WRAPPER}}',
            )
        );

        $this->end_controls_section();
    }

    protected function register_button_style_controls(): void {
        $this->start_controls_section(
            'ff_style_buttons',
            array(
                'label' => __( 'Buttons', 'filter-forge' ),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->start_controls_tabs( 'ff_button_style_tabs' );

        $this->start_controls_tab(
            'ff_button_style_normal',
            array( 'label' => __( 'Normal', 'filter-forge' ) )
        );

        $this->add_control(
            'ff_button_text_color',
            array(
                'label'     => __( 'Text Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} button' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'ff_button_bg_color',
            array(
                'label'     => __( 'Background Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} button' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            array(
                'name'     => 'ff_button_border',
                'selector' => '{{WRAPPER}} button',
            )
        );

        $this->add_control(
            'ff_button_border_radius',
            array(
                'label'      => __( 'Border Radius', 'filter-forge' ),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array( 'px', '%' ),
                'selectors'  => array(
                    '{{WRAPPER}} button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'ff_button_padding',
            array(
                'label'      => __( 'Padding', 'filter-forge' ),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array( 'px', 'em', '%' ),
                'selectors'  => array(
                    '{{WRAPPER}} button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            array(
                'name'     => 'ff_button_box_shadow',
                'selector' => '{{WRAPPER}} button',
            )
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'ff_button_style_hover',
            array( 'label' => __( 'Hover', 'filter-forge' ) )
        );

        $this->add_control(
            'ff_button_text_color_hover',
            array(
                'label'     => __( 'Text Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} button:hover' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'ff_button_bg_color_hover',
            array(
                'label'     => __( 'Background Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} button:hover' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'ff_button_border_color_hover',
            array(
                'label'     => __( 'Border Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} button:hover' => 'border-color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            array(
                'name'     => 'ff_button_box_shadow_hover',
                'selector' => '{{WRAPPER}} button:hover',
            )
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->end_controls_section();
    }

    protected function register_header_style_controls(): void {
        $this->start_controls_section(
            'ff_style_header',
            array(
                'label' => __( 'Header', 'filter-forge' ),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name'     => 'ff_header_typography',
                'selector' => '{{WRAPPER}} .ff-filter__header',
            )
        );

        $this->add_control(
            'ff_header_padding',
            array(
                'label'      => __( 'Padding', 'filter-forge' ),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array( 'px', 'em', '%' ),
                'selectors'  => array(
                    '{{WRAPPER}} .ff-filter__header' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'ff_header_border_width',
            array(
                'label'     => __( 'Border Bottom Width', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::SLIDER,
                'range'     => array(
                    'px' => array( 'min' => 0, 'max' => 20 ),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .ff-filter__header' => 'border-bottom-width: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'ff_header_border_style',
            array(
                'label'     => __( 'Border Bottom Style', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::SELECT,
                'default'   => '',
                'options'   => array(
                    ''       => __( 'Default', 'filter-forge' ),
                    'none'   => __( 'None', 'filter-forge' ),
                    'solid'  => __( 'Solid', 'filter-forge' ),
                    'dashed' => __( 'Dashed', 'filter-forge' ),
                    'dotted' => __( 'Dotted', 'filter-forge' ),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .ff-filter__header' => 'border-bottom-style: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'ff_header_border_color',
            array(
                'label'     => __( 'Border Bottom Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .ff-filter__header' => 'border-bottom-color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();
    }

    protected function register_dropdown_style_controls( array $condition ): void {
        $this->start_controls_section(
            'ff_style_dropdown',
            array(
                'label'     => __( 'Dropdown', 'filter-forge' ),
                'tab'       => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => $condition,
            )
        );

        $this->add_control(
            'ff_dropdown_trigger_heading',
            array(
                'label' => __( 'Trigger', 'filter-forge' ),
                'type'  => \Elementor\Controls_Manager::HEADING,
            )
        );

        $this->add_control(
            'ff_dropdown_trigger_text_color',
            array(
                'label'     => __( 'Text Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .ff-dropdown__trigger' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'ff_dropdown_trigger_bg_color',
            array(
                'label'     => __( 'Background Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .ff-dropdown__trigger' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'ff_dropdown_trigger_caret_color',
            array(
                'label'     => __( 'Caret Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .ff-dropdown__trigger-caret' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'ff_dropdown_trigger_width',
            array(
                'label'      => __( 'Width', 'filter-forge' ),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array( 'px', '%', 'em' ),
                'range'      => array(
                    'px' => array( 'min' => 50, 'max' => 800 ),
                    '%'  => array( 'min' => 10, 'max' => 100 ),
                ),
                'selectors'  => array(
                    '{{WRAPPER}} .ff-dropdown__trigger' => 'width: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            array(
                'name'     => 'ff_dropdown_trigger_border',
                'selector' => '{{WRAPPER}} .ff-dropdown__trigger',
            )
        );

        $this->add_control(
            'ff_dropdown_trigger_border_radius',
            array(
                'label'      => __( 'Border Radius', 'filter-forge' ),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array( 'px', '%' ),
                'selectors'  => array(
                    '{{WRAPPER}} .ff-dropdown__trigger' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'ff_dropdown_trigger_padding',
            array(
                'label'      => __( 'Padding', 'filter-forge' ),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array( 'px', 'em', '%' ),
                'selectors'  => array(
                    '{{WRAPPER}} .ff-dropdown__trigger' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name'     => 'ff_dropdown_trigger_typography',
                'selector' => '{{WRAPPER}} .ff-dropdown__trigger',
            )
        );

        $this->add_control(
            'ff_dropdown_trigger_focus_color',
            array(
                'label'     => __( 'Focus Outline Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .ff-dropdown__trigger:focus' => 'outline-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'ff_dropdown_panel_heading',
            array(
                'label'     => __( 'Panel', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            )
        );

        $this->add_control(
            'ff_dropdown_panel_bg_color',
            array(
                'label'     => __( 'Background Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .ff-dropdown__panel' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            array(
                'name'     => 'ff_dropdown_panel_border',
                'selector' => '{{WRAPPER}} .ff-dropdown__panel',
            )
        );

        $this->add_control(
            'ff_dropdown_panel_border_radius',
            array(
                'label'      => __( 'Border Radius', 'filter-forge' ),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array( 'px', '%' ),
                'selectors'  => array(
                    '{{WRAPPER}} .ff-dropdown__panel' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            array(
                'name'     => 'ff_dropdown_panel_box_shadow',
                'selector' => '{{WRAPPER}} .ff-dropdown__panel',
            )
        );

        $this->add_control(
            'ff_dropdown_panel_max_height',
            array(
                'label'     => __( 'Max Height', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::SLIDER,
                'range'     => array(
                    'px' => array( 'min' => 50, 'max' => 600 ),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .ff-dropdown__panel' => 'max-height: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'ff_dropdown_option_heading',
            array(
                'label'     => __( 'Option', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            )
        );

        $this->add_control(
            'ff_dropdown_option_padding',
            array(
                'label'      => __( 'Padding', 'filter-forge' ),
                'type'       => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array( 'px', 'em', '%' ),
                'selectors'  => array(
                    '{{WRAPPER}} .ff-dropdown__option' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name'     => 'ff_dropdown_option_typography',
                'selector' => '{{WRAPPER}} .ff-dropdown__option',
            )
        );

        $this->add_control(
            'ff_dropdown_option_hover_bg_color',
            array(
                'label'     => __( 'Hover Background Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .ff-dropdown__option:hover, {{WRAPPER}} .ff-dropdown__option--active' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'ff_dropdown_option_hover_text_color',
            array(
                'label'     => __( 'Hover Text Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .ff-dropdown__option:hover, {{WRAPPER}} .ff-dropdown__option--active' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'ff_dropdown_option_selected_bg_color',
            array(
                'label'     => __( 'Selected Background Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .ff-dropdown__option[aria-selected="true"]' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'ff_dropdown_option_selected_text_color',
            array(
                'label'     => __( 'Selected Text Color', 'filter-forge' ),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .ff-dropdown__option[aria-selected="true"]' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();
    }
}
