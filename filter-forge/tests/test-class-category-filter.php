<?php

class Test_FF_Category_Filter extends WP_UnitTestCase {

    public function test_apply_adds_tax_query_for_ff_tax_prefixed_param() {
        self::factory()->term->create( array( 'taxonomy' => 'product_tag', 'name' => 'Pistols', 'slug' => 'pistols' ) );

        $state  = new FF_Filter_State( array( 'ff_tax_product_tag' => 'pistols,rifles' ) );
        $filter = new FF_Category_Filter( $state );
        $query  = new WP_Query();

        $filter->apply( $query );

        $this->assertSame(
            array(
                array(
                    'taxonomy' => 'product_tag',
                    'field'    => 'slug',
                    'terms'    => array( 'pistols', 'rifles' ),
                ),
            ),
            $query->get( 'tax_query' )
        );
    }

    public function test_apply_adds_tax_query_for_native_category_alias_param() {
        self::factory()->term->create( array( 'taxonomy' => 'product_cat', 'name' => 'Pistols', 'slug' => 'pistols' ) );

        $state  = new FF_Filter_State( array( 'category' => 'pistols,rifles' ) );
        $filter = new FF_Category_Filter( $state );
        $query  = new WP_Query();

        $filter->apply( $query );

        $this->assertSame(
            array(
                array(
                    'taxonomy' => 'product_cat',
                    'field'    => 'slug',
                    'terms'    => array( 'pistols', 'rifles' ),
                ),
            ),
            $query->get( 'tax_query' )
        );
    }

    public function test_apply_does_nothing_when_no_taxonomy_filter_present() {
        $state  = new FF_Filter_State( array() );
        $filter = new FF_Category_Filter( $state );
        $query  = new WP_Query();

        $filter->apply( $query );

        $this->assertSame( '', $query->get( 'tax_query' ) );
    }

    public function test_apply_ignores_unknown_taxonomy_in_param_name() {
        $state  = new FF_Filter_State( array( 'ff_tax_not_a_real_taxonomy' => 'x' ) );
        $filter = new FF_Category_Filter( $state );
        $query  = new WP_Query();

        $filter->apply( $query );

        $this->assertSame( '', $query->get( 'tax_query' ) );
    }

    public function test_resolve_param_uses_ff_tax_prefix_for_non_attribute_taxonomies() {
        $this->assertSame( 'ff_tax_product_tag', FF_Category_Filter::resolve_param( 'product_tag' ) );
    }

    public function test_resolve_param_uses_native_category_alias_for_product_cat() {
        $this->assertSame( 'category', FF_Category_Filter::resolve_param( 'product_cat' ) );
    }

    public function test_resolve_param_uses_native_filter_prefix_for_attribute_taxonomies() {
        $attribute_id = wc_create_attribute(
            array(
                'name' => 'Color',
                'slug' => 'color',
                'type' => 'select',
            )
        );
        $taxonomy = wc_attribute_taxonomy_name( 'color' );

        $this->assertSame( 'filter_' . $taxonomy, FF_Category_Filter::resolve_param( $taxonomy ) );

        wc_delete_attribute( $attribute_id );
    }
}
