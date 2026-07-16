window.FFUrl = ( function () {
    function get( key ) {
        return new URLSearchParams( window.location.search ).get( key );
    }

    function set( key, value ) {
        const params = new URLSearchParams( window.location.search );
        if ( value === '' || value === null ) {
            params.delete( key );
        } else {
            params.set( key, value );
        }
        return params;
    }

    function remove( key, params ) {
        const target = params || new URLSearchParams( window.location.search );
        target.delete( key );
        return target;
    }

    function navigate( params ) {
        const query = params.toString();
        window.location.href = window.location.pathname + ( query ? '?' + query : '' );
    }

    return { get: get, set: set, remove: remove, navigate: navigate };
} )();
