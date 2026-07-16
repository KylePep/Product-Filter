<?php

class Test_FF_Plugin extends WP_UnitTestCase {

    public function test_dependencies_met_is_true_when_woocommerce_and_elementor_are_loaded() {
        $this->assertTrue( class_exists( 'WooCommerce' ) );
        $this->assertGreaterThan( 0, did_action( 'elementor/loaded' ) );
        $this->assertTrue( FF_Plugin::dependencies_met() );
    }
}
