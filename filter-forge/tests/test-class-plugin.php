<?php

class Test_FF_Plugin extends WP_UnitTestCase {

    public function test_dependencies_met_is_true_when_woocommerce_and_elementor_are_loaded() {
        $this->assertTrue( class_exists( 'WooCommerce' ) );
        $this->assertGreaterThan( 0, did_action( 'elementor/loaded' ) );
        $this->assertTrue( FF_Plugin::dependencies_met() );
    }

    public function test_instance_returns_the_same_object_every_call() {
        $this->assertSame( FF_Plugin::instance(), FF_Plugin::instance() );
    }

    public function test_instance_exposes_the_shared_services() {
        $plugin = FF_Plugin::instance();

        $this->assertInstanceOf( FF_Filter_State::class, $plugin->filter_state );
        $this->assertInstanceOf( FF_Query_Manager::class, $plugin->query_manager );
        $this->assertInstanceOf( FF_Count_Service::class, $plugin->count_service );
        $this->assertInstanceOf( FF_Relationship_Resolver::class, $plugin->relationship_resolver );
    }

    public function test_boot_registers_the_pre_get_posts_hook() {
        FF_Plugin::boot();
        $plugin = FF_Plugin::instance();

        $this->assertNotFalse(
            has_action( 'pre_get_posts', array( $plugin->query_manager, 'maybe_apply' ) )
        );
    }
}
