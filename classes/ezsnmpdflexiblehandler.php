<?php
/**
 * Generic Handler class which takes its root oid from ini settings.
 *
 * Implements flexible root-oid logic to avoid clashes between handlers
 *
 * @author G. Giunta
 * @copyright (C) G. Giunta 2012
 * @license code licensed under the GPL License: see README
 */

abstract class eZsnmpdFlexibleHandler extends eZsnmpdHandler
{
    function oidRoot()
    {
        $oids = array();
        $selfClass = get_class( $this );
        $ini = eZINI::instance( 'snmpd.ini' );
        foreach( $ini->variable( 'MIB', 'SNMPFlexibleHandlerOIDs' ) as $oid => $handlerClass )
        {
            if ( $handlerClass == $selfClass )
            {
                $oids[] = rtrim( $oid, '.' ) . '.';
            }
        }
        if ( empty( $oids ) )
        {
            eZDebug::writeError( "No oid assigned to flexbile handler $selfClass", __METHOD__ );
        }
        return $oids;
    }

}

?>