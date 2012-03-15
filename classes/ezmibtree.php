<?php
/**
 * The class used to work on the MIB represented as a tree (php nested array)
 *
 * @author G. Giunta
 * @version $Id: ezsnmpd.php 83 2010-01-25 13:27:09Z gg $
 * @copyright (C) G. Giunta 2010
 * @license code licensed under the GPL License: see README
 */

/**
* Each OID in the MIB tree should be an array with the following members:
*
* tree node (OBJECT IDENTIFIER): name => '', children => array() [, nochildreninmib = true]
* tree leaf (OBJECT TYPE): name => '', syntax => '', access => '', status => '', description => ''
* tree leaf (SEQUENCE): name => '', syntax => 'SEQUENCE', items => array()
*/

class eZMIBTree {

    // enum: status
    const status_current = 'current';

    const status_mandatory = 'mandatory';
    const status_optional = 'optional';
    const status_obsolete = 'obsolete';
    const status_deprecated = 'deprecated';

    // enum: access; read-only, read-write, write-only, not-accessible
    const access_read_only = 'read-only';
    const access_read_write = 'read-write';
    const access_write_only = 'write-only';
    const access_not_accessible = 'not-accessible';

    private static $_mib = '';

    /**
    * Creates an OID struct with all the fields at deafult value
    */
    /*static function OIDinstance( $name, $type, $description='', $access=eZMIBTree::access_read_only, $status=eZMIBTree::status_mandatory, $reference='' )
    {
        return array(
            'name' => $name,
            'syntax' => $type,
            'access' => $access,
            'status' => $status,
            'description' => $description,
            'reference' => '',
            'children' => array()
        );
    }*/

    /**
    * Walk the tree starting with $oid, and call back $funcname on every node
    *
    * @param oid struct $oid
    * @param callable $funcname callback function. Will receive an OID struct and an identifier string
    * @param string $prefix the OID prefix that will be used to generated the identifier passed to the callback
    * @param bool $leaves_only if true, only terminal nodes in the tree (leaves) will be passed to the callback, otherwise all nodes (including sequences)
    * @return null
    * @todo add support for tables...
    */
    static function walk( $oid, $funcname, $prefix='', $leaves_only=true )
    {
        if ( !$leaves_only || ( !isset( $oid['children'] ) || count( $oid['children'] ) == 0 ) )
        {
            call_user_func( $funcname, $oid, $prefix );
        }
        if ( isset( $oid['children'] ) )
        {
            foreach( $oid['children'] as $i => $child )
            {
                self::walk( $child, $funcname, $prefix . '.' . $i, $leaves_only );
            }
        }
    }

    /**
    * Return the ASN.1 representation of the tree as string
    */
    static function toMIB( $oid, $prefix='' )
    {
        self::$_mib = '';
        self::walk( $oid, array( 'eZMIBTree', 'OIDtoMIB' ), $prefix, false );
        return self::$_mib;
    }

    /**
    * return the leaves in the tree, flattened to an array indexed by oid
    */
    static function toArray( $oid, $prefix='' )
    {
        self::$_mib = array();
        self::walk( $oid, array( 'eZMIBTree', 'OIDtoArray' ), $prefix );
        return self::$_mib;
    }

    /**
     * return the leaves in the tree, flattened to an array indexed by name
     */
    static function toNamedArray( $oid, $prefix='' )
    {
        self::$_mib = array();
        self::walk( $oid, array( 'eZMIBTree', 'OIDtoNamedArray' ), $prefix );
        return self::$_mib;
    }

    /**
    * Converts to ASN.1 format the children of the given oid
    *
    *
    * @todo add optional 'reference' part
    * @todo escape double quotes and possibly other stuff in description
    */
    private static function OIDtoMIB( $oid, $prefix='' )
    {
        if ( isset( $oid['children'] ) && !isset( $oid['nochildreninmib'] ) )
        {

            $oidname = eZSNMPd::asncleanup( $oid['name'] );
            foreach( $oid['children'] as $i => $child )
            {
                $childname = eZSNMPd::asncleanup( $child['name'] );
                if ( !isset( $child['syntax'] ) || $child['syntax'] == '' )
                {
                    self::$_mib .= str_pad( $childname, 15 ) . " OBJECT IDENTIFIER ::= { $oidname $i }\n\n";
                }
                else if ( $child['syntax'] == 'SEQUENCE' )
                {
                    self::$_mib .= str_pad( ucfirst( $childname ), 15 ) . " ::=\n    SEQUENCE\n    {\n";
                    foreach ( $child['items'] as $i => $item )
                    {
                        /// @todo remove trailing comma form last iteration
                        self::$_mib .= "        {$item['name']}\n            {$item['syntax']}";
                        if ( $i < count( $child['items'] ) )
                        {
                            self::$_mib .= ",\n";
                        }
                    }
                    self::$_mib .= "\n    }\n\n";
                }
                else
                {
                    $child = array_merge( array( 'access' => eZMIBTree::access_read_only, 'status' => eZMIBTree::status_current, 'description' => '' ), $child );
                    self::$_mib .= str_pad( $childname, 15 ) . " OBJECT-TYPE\n" .
                        "    SYNTAX          {$child['syntax']}\n" .
                        "    MAX-ACCESS      {$child['access']}\n" .
                        "    STATUS          {$child['status']}\n" .
                        "    DESCRIPTION\n              \"{$child['description']}\"\n";
                   if ( isset( $child['index'] ) && $child['index'] != '' )
                   {
                       self::$_mib .= "    INDEX           { {$child['index']} }\n";
                   }
                   self::$_mib .= "    ::= { $oidname $i }\n\n";
                }
            }

        }
    }

    /**
    * The simple tree walking got dirtier for supporting tables:
    * in 'leaves only' mode, we skip SEQUENCE nodes, that are leaves
    */
    private static function OIDtoArray( $oid, $prefix='' )
    {
        if ( !isset( $oid['syntax'] ) || $oid['syntax'] != 'SEQUENCE' )
        {
            self::$_mib[$prefix] = $oid;
        }
    }

    /**
     * The simple tree walking got dirtier for supporting tables:
     * in 'leaves only' mode, we skip SEQUENCE nodes, that are leaves
     */
    private static function OIDtoNamedArray( $oid, $prefix='' )
    {
        if ( !isset( $oid['syntax'] ) || $oid['syntax'] != 'SEQUENCE' )
        {
            self::$_mib[$oid['name']] = $oid + array( 'oid' => $prefix );
            unset( self::$_mib[$oid['name']]['name'] );
        }
    }
}

?>