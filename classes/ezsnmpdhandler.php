<?php
/**
 * Abstract SNMP Handler class
 *
 * @author G. Giunta
 * @version $Id$
 * @copyright (C) G. Giunta 2009
 * @license code licensed under the GPL License: see README
 */

abstract class eZsnmpdHandler {

    const ERROR_WRONG_TYPE = -1;
    const ERROR_WRONG_VALUE = -2;
    const ERROR_WRONG_LENGHT = -3;
    const ERROR_INCONSISTENT_VALUE = -4;
    const ERROR_NOT_WRITEABLE = -5;

    /**
    * Must return an array of all the OIDs handled.
    * Trailing wildcards are accepted, eg 1.1.*
    */
    abstract function oidList();

    /**
    * Must return a 3-valued array on success, or NULL if key was not readable/unknown
    */
    function get( $oid )
    {
        return null;
    }

    /**
    * Must return a string with the next oid.
    * Should be implemented when oidList returns some regexp-based oids
    */
    function getnext( $oid )
    {
        $oidList = $this->oidList();
        sort( $oidList );
        if ( ($key = array_search( $oid, $oidList )) !== false )
        {
            $key++;
            if ( $key < count( $oidList ) )
            {
                return $this->get( $oidList[$key] );
            }
        }
        return null;
    }

    /**
    * Must return 0 on success, or an error code from above
    */
    function set( $oid, $value, $type )
    {
        return self::ERROR_NOT_WRITEABLE;
    }

    /**
    * Must return plaintext MIB descroption for the OIDs served
    */
    function getMIB()
    {
        return '';
    }
}

?>