<?php

class Test_FF_Count_Service extends WP_UnitTestCase {

    public function test_get_count_returns_number_of_matching_products() {
        $products = self::factory()->post->create_many(
            3,
            array( 'post_type' => 'product', 'post_status' => 'publish' )
        );
        $term = self::factory()->term->create_and_get(
            array( 'taxonomy' => 'product_cat', 'name' => 'Pistols' )
        );
        wp_set_object_terms( $products[0], array( $term->term_id ), 'product_cat' );
        wp_set_object_terms( $products[1], array( $term->term_id ), 'product_cat' );

        $service = new FF_Count_Service();
        $count   = $service->get_count(
            array(
                'post_type' => 'product',
                'tax_query' => array(
                    array(
                        'taxonomy' => 'product_cat',
                        'field'    => 'term_id',
                        'terms'    => array( $term->term_id ),
                    ),
                ),
            )
        );

        $this->assertSame( 2, $count );
    }

    public function test_get_count_reads_from_cache_on_second_call_with_same_args() {
        $service    = new FF_Count_Service();
        $query_args = array( 'post_type' => 'product' );

        $service->get_count( $query_args );
        wp_cache_set( 'ff_count_' . md5( wp_json_encode( $query_args ) ), 999, 'filter-forge' );

        $this->assertSame( 999, $service->get_count( $query_args ) );
    }
}
