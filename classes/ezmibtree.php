<?php
/**
 * The class used to work on the MIB represented as an array
 *
 * @author G. Giunta
 * @version $Id: ezsnmpd.php 83 2010-01-25 13:27:09Z gg $
 * @copyright (C) G. Giunta 2010
 * @license code licensed under the GPL License: see README
 */

/**
* Each OID in the MIB tree should be an array with the following members:
*   name => '',
*   syntax => ,
*   access => ,
*   status => eZMIBTree::status_mandatory,
*   description => '',
*   reference => '', //optional
*   children => array()
*/

class eZMIBTree {

    // enum: status
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
    static function OIDinstance( $name, $type, $description='', $access=eZMIBTree::access_read_only, $status=eZMIBTree::status_mandatory, $reference='' )
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
    }

    /**
    * Walk the tree starting with $oid, and call back $funcname on every node
    * @param oid struct $oid
    * @param callable $funcname callback function. Will receive an OID struct and an identifier string
    * @param string $prefix the OID prefix that will be used to generated the identifier passed to the callback
    * @param bool $leaves_only if true, only terminal nodes in the tree (leaves) will be passed to the callback, otherwise all nodes
    * @return null
    */
    static function walk( $oid, $funcname, $prefix='', $leaves_only=true )
    {
        if ( !$leaves_only || count( $oid['children'] ) == 0 )
        {
            call_user_func( $funcname, $oid, $prefix );
        }
        foreach(  $oid['children'] as $i => $child )
        {
            self::walk( $child, $funcname, $prefix . '.' . $i, $leaves_only );
        }
    }

    /**
    * Return the ASN.1 representation of the tree as string
    */
    static function toMIB( $oid )
    {
        self::$_mib = '';
        self::walk( $oid, array( 'eZMIBTree', 'OIDtoMIB' ), '', false );
        return self::$_mib;
    }

    /**
    * Converts to ASN.1 format the children of the given oid
    * @todo add optional 'reference' part
    * @todo escape double quotes and possibly other stuff in description
    */
    private static function OIDtoMIB( $oid, $prefix='' )
    {
        $oidname = eZSNMPd::asncleanup( $oid['name'] );
        foreach(  $oid['children'] as $i => $child )
        {
            $childname = eZSNMPd::asncleanup( $child['name'] );
            if ( count( $child['children'] ) )
            {
                self::$_mib .= str_pad( $childname, 15 ) . " OBJECT IDENTIFIER ::= { $oidname $i }\n\n";
            }
            else
            {
                $child = array_merge( array( 'access' => eZMIBTree::access_read_only, 'status' => eZMIBTree::status_mandatory, 'description' => '' ), $child );
                self::$_mib .= str_pad( $childname, 15 ) . " OBJECT-TYPE\n" .
                    "    SYNTAX          {$child['syntax']}\n" .
                    "    MAX-ACCESS      {$child['access']}\n" .
                    "    STATUS          {$child['status']}\n" .
                    "    DESCRIPTION     \"{$child['description']}\"\n" .
                    "    ::= { $oidname $i }\n\n";
            }
        }
    }

}

?>