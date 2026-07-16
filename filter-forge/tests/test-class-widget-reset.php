<?php

class Test_FF_Widget_Reset extends WP_UnitTestCase {

    public function test_canonical_url_strips_all_query_args() {
        $this->assertSame(
            'https://example.com/product-category/airsoft-guns/',
            FF_Widget_Reset::canonical_url( 'https://example.com/product-category/airsoft-guns/?filter_pa_color=black&min_price=50&orderby=price' )
        );
    }

    public function test_canonical_url_returns_url_unchanged_when_no_query_args() {
        $this->assertSame(
            'https://example.com/shop/',
            FF_Widget_Reset::canonical_url( 'https://example.com/shop/' )
        );
    }
}
