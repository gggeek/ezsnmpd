<?php
/**
 * SNMP Handler used to retrieve status-related information
 * Handles access to the 'status' branch of the MIB (2)
 * initial key indicators taken from ezmunin
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

class eZsnmpdStatusHandler extends eZsnmpdHandler {

    static $simplequeries = array(
        '2.1.1' => 'SELECT COUNT(id) AS count FROM ezcontentobject', // eZContentObjects
        '2.1.2' => 'SELECT COUNT(id) AS count FROM ezcontentobject_attribute', // eZContentObjectAttributes
        '2.1.3' => 'SELECT COUNT(node_id) AS count FROM ezcontentobject_tree', // eZContentObjectTreeNode
        '2.1.4' => 'SELECT COUNT(id) AS count FROM ezcontentobject_link', // eZContentObjectRelations
        '2.1.5' => 'SELECT COUNT(id) AS count FROM ezcontentobject WHERE STATUS=0', // eZContentObjectDrafts

        '2.3.1' => 'SELECT COUNT(contentobject_id) AS count FROM ezuser', // user count
        '2.3.2' => 'SELECT COUNT(session_key) AS count FROM ezsession', // sessions
    );

    function oidList( )
    {
        return array_merge ( array_keys( self::$simplequeries ), array( '2.2.1', '2.2.2' ) );
    }

    function get( $oid )
    {
        if ( array_key_exists( $oid, self::$simplequeries ) )
        {
            $db = eZDB::instance();
            $results = $db->arrayQuery( self::$simplequeries[$oid] );
            if ( is_array( $results) && count( $results ) )
            {
                return array(
                    'oid' => $oid,
                    'type' => eZSNMPd::TYPE_INTEGER, // counter cannot be used, as it is monotonically increasing
                    'value' => $results[0]['count'] );
            }
            else
            {
                return 0;
            }
        }

        $fileINI = eZINI::instance( 'file.ini' );
        $handlerName = $fileINI->variable( 'ClusteringSettings', 'FileHandler' );
        switch( $oid )
        {
            case '2.2.1': // cache-blocks
                /// @todo ...
                switch( $handlerName )
                {
                    case 'ezfs':
                        break;
                    case 'ezdb':
                        break;
                    default:
                }

            case '2.2.2': // view-cache
                /// @todo ...
                switch( $handlerName )
                {
                    case 'ezfs':
                        break;
                    case 'ezdb':
                        break;
                    default:
                }
        }

        return 0; // oid not managed
    }

    function getMIB()
    {
        return '
ezpStatusContentObjects OBJECT-TYPE
    SYNTAX          Unsigned32
    MAX-ACCESS      read-only
    STATUS          current
    DESCRIPTION
            "The number of content objects the server."
    ::= { apScoreBoardEntry 2 }';
    }
}
?>