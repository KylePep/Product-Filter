<?php

class Test_FF_Meta_Provider extends WP_UnitTestCase {

    public function test_get_options_returns_distinct_published_product_meta_values() {
        $product_a = self::factory()->post->create( array( 'post_type' => 'product', 'post_status' => 'publish' ) );
        $product_b = self::factory()->post->create( array( 'post_type' => 'product', 'post_status' => 'publish' ) );
        update_post_meta( $product_a, 'material', 'Nylon' );
        update_post_meta( $product_b, 'material', 'Nylon' );

        $provider = new FF_Meta_Provider();
        $options  = $provider->get_options( array( 'meta_key' => 'material' ) );

        $this->assertSame( array( array( 'value' => 'Nylon', 'label' => 'Nylon' ) ), $options );
    }

    public function test_get_options_ignores_unpublished_products() {
        $draft = self::factory()->post->create( array( 'post_type' => 'product', 'post_status' => 'draft' ) );
        update_post_meta( $draft, 'material', 'Draft-Only-Value' );

        $provider = new FF_Meta_Provider();
        $options  = $provider->get_options( array( 'meta_key' => 'material' ) );

        $this->assertSame( array(), $options );
    }

    public function test_get_options_returns_empty_array_for_missing_meta_key() {
        $provider = new FF_Meta_Provider();
        $this->assertSame( array(), $provider->get_options( array( 'meta_key' => '' ) ) );
    }
}
