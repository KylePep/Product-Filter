<?php

class Test_FF_Query_Manager extends WP_UnitTestCase {

    private function make_manager( array $get = array() ): FF_Query_Manager {
        $state = new FF_Filter_State( $get );
        return new FF_Query_Manager( new FF_Category_Filter( $state ), new FF_Meta_Filter( $state ) );
    }

    public function test_maybe_apply_applies_filters_on_product_category_archive() {
        $term = self::factory()->term->create_and_get(
            array( 'taxonomy' => 'product_cat', 'name' => 'Rifles', 'slug' => 'rifles' )
        );
        $this->go_to( get_term_link( $term ) );

        global $wp_query;
        $this->make_manager( array( 'ff_tax_product_cat' => 'rifles' ) )->maybe_apply( $wp_query );

        $this->assertNotEmpty( $wp_query->get( 'tax_query' ) );
    }

    public function test_maybe_apply_does_nothing_on_a_non_product_page() {
        $page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
        $this->go_to( get_permalink( $page_id ) );

        global $wp_query;
        $this->make_manager( array( 'ff_tax_product_cat' => 'rifles' ) )->maybe_apply( $wp_query );

        $this->assertEmpty( $wp_query->get( 'tax_query' ) );
    }

    public function test_maybe_apply_does_nothing_for_non_main_query() {
        $term = self::factory()->term->create_and_get(
            array( 'taxonomy' => 'product_cat', 'name' => 'Shotguns', 'slug' => 'shotguns' )
        );
        $this->go_to( get_term_link( $term ) );

        $secondary_query = new WP_Query( array( 'post_type' => 'product' ) );
        $this->make_manager( array( 'ff_tax_product_cat' => 'shotguns' ) )->maybe_apply( $secondary_query );

        $this->assertEmpty( $secondary_query->get( 'tax_query' ) );
    }
}
