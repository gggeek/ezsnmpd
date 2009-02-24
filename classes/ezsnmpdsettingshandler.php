<?php
/**
 * Handles access to the 'settings' branch of the MIB (1)
 *
 * @author G. Giunta
 * @version $Id$
 * @copyright (C) G. Giunta 2009
 * @license code licensed under the GPL License: see README
 */

class eZsnmpdSettingsHandler extends eZsnmpdHandler {

    function oidList( )
    {
        return array ( '1.*' );
    }

    function get( $oid )
    {
        /// @todo ...
        return array(
            'oid' => $oid,
            'type' => 'counter',
            'value' => rand( 0, 100 ) );
    }

    function getMIB()
    {
        return '
settings        OBJECT IDENTIFIER ::= {eZPublish 1}
    }
}
?>