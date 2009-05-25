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
        '2.1.2.1' => 'SELECT COUNT(id) AS count FROM ezcontentobject', // eZContentObjects
        '2.1.2.2' => 'SELECT COUNT(id) AS count FROM ezcontentobject_attribute', // eZContentObjectAttributes
        '2.1.2.3' => 'SELECT COUNT(node_id) AS count FROM ezcontentobject_tree', // eZContentObjectTreeNode
        '2.1.2.4' => 'SELECT COUNT(id) AS count FROM ezcontentobject_link', // eZContentObjectRelations
        '2.1.2.5' => 'SELECT COUNT(id) AS count FROM ezcontentobject WHERE STATUS=0', // eZContentObjectDrafts

        '2.1.3.1' => 'SELECT COUNT(contentobject_id) AS count FROM ezuser', // user count
        '2.1.3.2' => 'SELECT COUNT(session_key) AS count FROM ezsession', // sessions
    );

    function oidList( )
    {
        return array_merge ( array_keys( self::$simplequeries ), array( '2.1.1', '2.3.1', '2.3.2' ) );
    }

    function get( $oid )
    {
        if ( array_key_exists( $oid, self::$simplequeries ) )
        {
            try
            {
                $db = eZDB::instance();
                // eZP 4.0 will not raise an exception on connection errors
                if ( !$db->isConnected() )
                {
                    return 0;
                }
            }
            catch ( Exception $e )
            {
                return 0;
            }
            $results = $db->arrayQuery( self::$simplequeries[$oid] );
            $db->close();
            if ( is_array( $results ) && count( $results ) )
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
            case '2.1.1':
                // @todo verify if db can be connected to
                $ok = 1;
                try
                {
                    $db = eZDB::instance();
                    // eZP 4.0 will not raise an exception on connection errors
                    if ( !$db->isConnected() )
                    {
                        $ok = 0;
                    }
                    $db->close();
                }
                catch ( Exception $e )
                {
                    $ok = 0;
                }
                return array(
                    'oid' => $oid,
                    'type' => eZSNMPd::TYPE_INTEGER, // counter cannot be used, as it is monotonically increasing
                    'value' => $ok );
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
status          OBJECT IDENTIFIER ::= {eZPublish 2}

database OBJECT IDENTIFIER ::= { status 1 }

dbstatus OBJECT-TYPE
    SYNTAX          INTEGER
    MAX-ACCESS      read-only
    STATUS          current
    DESCRIPTION
            "Availability of the database."
    ::= { database 1 }

content         OBJECT IDENTIFIER ::= {database 2}

contentObjects OBJECT-TYPE
    SYNTAX          INTEGER
    MAX-ACCESS      read-only
    STATUS          current
    DESCRIPTION
            "The number of content objects (-1 if db cannot be connected to)."
    ::= { content 1 }

contentObjectAttributes OBJECT-TYPE
    SYNTAX          INTEGER
    MAX-ACCESS      read-only
    STATUS          current
    DESCRIPTION
            "The number of content object attributes (-1 if db cannot be connected to)."
    ::= { content 2 }

contentObjectNodes OBJECT-TYPE
    SYNTAX          INTEGER
    MAX-ACCESS      read-only
    STATUS          current
    DESCRIPTION
        "The number of content nodes (-1 if db cannot be connected to)."
    ::= { content 3 }

users           OBJECT IDENTIFIER ::= {database 3}

registeredusers OBJECT-TYPE
    SYNTAX          INTEGER
    MAX-ACCESS      read-only
    STATUS          current
    DESCRIPTION
            "The number of existing user accounts (-1 if db cannot be connected to)."
    ::= { users 1 }

sessions OBJECT-TYPE
    SYNTAX          INTEGER
    MAX-ACCESS      read-only
    STATUS          current
    DESCRIPTION
            "The number of active sessions (-1 if db cannot be connected to)."
    ::= { users 2 }

cache           OBJECT IDENTIFIER ::= {status 2}
';
    }
}
?>