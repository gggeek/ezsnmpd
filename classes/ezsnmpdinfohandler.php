<?php
/**
 * SNMP Handler used to retrieve info-related information, eg. info that is not
 * subject to constant change. Version nr. etc...
 * Handles access to the 'info' branch of the MIB (3)
 *
 * @author G. Giunta
 * @version $Id$
 * @copyright (C) G. Giunta 2009
 * @license code licensed under the GPL License: see README
 *
 * @todo add other metrics, such as:
 *       expired sessions
 *       content classes
 *       object nr. per version
 *       inactive users
 */

class eZsnmpdInfoHandler extends eZsnmpdHandler {

    function oidList( )
    {
        return array( '3.1.1', '3.1.2' );
    }

    function get( $oid )
    {
        switch( $oid )
        {
            case '3.1.1': // cache-blocks
                /// @todo ...
                return array(
                    'oid' => $oid,
                    'type' => eZPublishSDK::version,
                    'value' => eZSNMPd::VERSION,
                );

            case '3.1.2': // view-cache
                /// @todo ...
                return array(
                    'oid' => $oid,
                    'type' => eZSNMPd::TYPE_STRING,
                    'value' => eZSNMPd::VERSION,
                );
        }

        return 0; // oid not managed
    }

    function getMIB()
    {
        return '
info            OBJECT IDENTIFIER ::= {eZPublish 3}

ezpInfoeZPVersion OBJECT-TYPE
    SYNTAX          String
    MAX-ACCESS      read-only
    STATUS          current
    DESCRIPTION
            "The eZ Publish release number."
    ::= { info 1 }

ezpInfoezsnmpdVersion OBJECT-TYPE
    SYNTAX          String
    MAX-ACCESS      read-only
    STATUS          current
    DESCRIPTION
            "The ezsnmpd extension release number."
    ::= { info 2 }';
    }
}
?>