<?php

class Test_FF_Requirements_Notice extends WP_UnitTestCase {

    public function test_maybe_render_outputs_nothing_when_dependencies_are_met() {
        // In this test environment WooCommerce and Elementor are always active,
        // so this exercises the real "met" branch. The "missing dependency" branch
        // is verified manually (Task 17) by deactivating WooCommerce/Elementor on a
        // real site, since class_exists()/did_action() can't be faked mid-suite.
        $notice = new FF_Requirements_Notice();

        ob_start();
        $notice->maybe_render();
        $output = ob_get_clean();

        $this->assertSame( '', $output );
    }
}
