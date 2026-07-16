document.addEventListener( 'change', function ( event ) {
    const input = event.target.closest( '[data-ff-param]' );
    if ( ! input ) {
        return;
    }

    const list = input.closest( '.ff-filter' );
    let params = new URLSearchParams( window.location.search );

    if ( input.type === 'checkbox' ) {
        const param    = input.getAttribute( 'data-ff-param' );
        const existing = ( params.get( param ) || '' ).split( ',' ).filter( Boolean );
        const value    = input.value;
        const next     = input.checked
            ? existing.concat( [ value ] )
            : existing.filter( function ( v ) { return v !== value; } );

        params = FFUrl.set( param, next.join( ',' ) );
    }

    if ( list ) {
        const filterKey = list.getAttribute( 'data-ff-filter-key' );
        document.querySelectorAll( '[data-ff-parent-key="' + filterKey + '"][data-ff-reset-on-change="yes"]' ).forEach(
            function ( child ) {
                params = FFUrl.remove( child.getAttribute( 'data-ff-param' ), params );
            }
        );
    }

    FFUrl.navigate( params );
} );

document.addEventListener( 'click', function ( event ) {
    const clearBtn = event.target.closest( '.ff-filter__clear, .ff-price__clear' );
    if ( ! clearBtn ) {
        return;
    }

    let params = FFUrl.remove( clearBtn.getAttribute( 'data-ff-param' ) );

    const secondary = clearBtn.getAttribute( 'data-ff-param-secondary' );
    if ( secondary ) {
        params = FFUrl.remove( secondary, params );
    }

    FFUrl.navigate( params );
} );
