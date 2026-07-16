<?php

class Test_FF_Meta_Filter extends WP_UnitTestCase {

    public function test_apply_adds_meta_query_for_ff_prefixed_param() {
        $state  = new FF_Filter_State( array( 'ff_material' => 'nylon,abs' ) );
        $filter = new FF_Meta_Filter( $state );
        $query  = new WP_Query();

        $filter->apply( $query );

        $this->assertSame(
            array(
                array(
                    'key'     => 'material',
                    'value'   => array( 'nylon', 'abs' ),
                    'compare' => 'IN',
                ),
            ),
            $query->get( 'meta_query' )
        );
    }

    public function test_apply_ignores_ff_tax_prefixed_params() {
        $state  = new FF_Filter_State( array( 'ff_tax_product_cat' => 'pistols' ) );
        $filter = new FF_Meta_Filter( $state );
        $query  = new WP_Query();

        $filter->apply( $query );

        $this->assertSame( '', $query->get( 'meta_query' ) );
    }

    public function test_apply_does_nothing_when_no_meta_filter_present() {
        $state  = new FF_Filter_State( array() );
        $filter = new FF_Meta_Filter( $state );
        $query  = new WP_Query();

        $filter->apply( $query );

        $this->assertSame( '', $query->get( 'meta_query' ) );
    }
}
