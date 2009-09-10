<?php
/**
 * Handles access to a branch reserved for testing / provides an example of a writable oid
 *
 * @author G. Giunta
 * @version $Id$
 * @copyright (C) G. Giunta 2009
 * @license code licensed under the GPL License: see README
 */

class eZsnmpdTestHandler extends eZsnmpdHandler {

    function oidList( )
    {
        return array ( '5.1', '5.2' );
    }

    function get( $oid )
    {
        switch( preg_replace( '/\.0$/', '', $oid ) )
        {
            case '5.1':
                return array(
                    'oid' => $oid,
                    'type' => eZSNMPd::TYPE_INTEGER,
                    'value' => rand( 0, 100 ) );

            case '5.2':
                /// @todo missing here: try-catch around db connection, $db->close() at the end
                $db = eZDB::instance();
                $rows = $db->arrayQuery( 'SELECT text FROM writetest where id = 42' );
                return array(
                    'oid' => $oid,
                    'type' => eZSNMPd::TYPE_STRING,
                    'value' => $rows[0]['text'] );
        }

        return self::NO_SUCH_OID; // oid not managed
     }

    function set( $oid, $value, $type )
    {
        switch( preg_replace( '/\.0$/', '', $oid ) )
        {

            case '5.2':
                /// @todo missing here: try-catch around db connection, $db->close() at the end
                $value = trim( $value, ' "' );
                if ( $type != eZSNMPd::TYPE_STRING )
                    return self::ERROR_WRONG_TYPE;
                if ( strlen( $value ) > 45 )
                    return self::ERROR_WRONG_LENGHT;
                $db = eZDB::instance();
                $db->query( "UPDATE writetest SET text = '". $db->escapeString( $value ). "' where id = 42" );
                return self::SET_SUCCESFUL;
        }

        return self::ERROR_NOT_WRITEABLE; // oid not managed
    }

    function getMIB()
    {
        return '
scrap        OBJECT IDENTIFIER ::= {eZPublish 5}

random OBJECT-TYPE
    SYNTAX          INTEGER
    MAX-ACCESS      read-only
    STATUS          current
    DESCRIPTION
            "Returns random number."
    ::= { scrap 1 }

writetest OBJECT-TYPE
    SYNTAX          DisplayString
    MAX-ACCESS      read-write
    STATUS          current
    DESCRIPTION
            "Testing write access."
    ::= { scrap 2 }';
    }
}
?>