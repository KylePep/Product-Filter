window.FFUrl = ( function () {
    function get( key ) {
        return new URLSearchParams( window.location.search ).get( key );
    }

    function set( key, value, params ) {
        const target = params || new URLSearchParams( window.location.search );
        if ( value === '' || value === null ) {
            target.delete( key );
        } else {
            target.set( key, value );
        }
        return target;
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
