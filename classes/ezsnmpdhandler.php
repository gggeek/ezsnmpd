<?php
/**
 * Abstract SNMP Handler class
 *
 * @author G. Giunta
 * @version $Id$
 * @copyright (C) G. Giunta 2009
 * @license code licensed under the GPL License: see README
 *
 * @todo add support for columnar objects
 */

abstract class eZsnmpdHandler {

    const NO_SUCH_OID = null;

    const SET_SUCCESFUL = 0;

    const ERROR_WRONG_TYPE = -1;
    const ERROR_WRONG_VALUE = -2;
    const ERROR_WRONG_LENGHT = -3;
    const ERROR_INCONSISTENT_VALUE = -4;
    const ERROR_NOT_WRITEABLE = -5;

    /**
    * Must return an array of all the OIDs handled.
    * NB: for scalar objects, the trailing .0 is to be omitted
    * Trailing wildcards are accepted, eg 1.1.*
    * @return array
    */
    abstract public function oidList();

    /**
    * Must return a 3-valued array on success, or NULL if key was not readable/unknown
    * @param string $oid note: for scalar objects, the trailing .0 will be part of $oid
    * @return array|null array keys: 'oid', 'type', 'value'. For types, see ezsnmpd constants
    */
    abstract public function get( $oid );

    /**
    * Must return a string with the next oid.
    * Should be reimplemented when oidList() returns some regexp-based oids
    */
    /*function getnext( $oid )
    {
        $oidList = $this->oidList();
        if ( preg_match( '/\\.0$/', $oid ) )
        {
            // next oid is taken from the list
            sort( $oidList );
            if ( ($key = array_search( preg_replace( '/\\.0$/', '', $oid ), $oidList )) !== false )
            {
                $key++;
                if ( $key < count( $oidList ) )
                {
                    return $this->get( $oidList[$key] . '.0' );
                }
            }
        }
        else
        {
            // next oid is the scalar value of the current oid
            return $this->get( $oid . '.0' );
        }
        return null;
    }*/

    /**
    * Must return 0 on success, or an error code from above
    */
    public function set( $oid, $value, $type )
    {
        return self::ERROR_NOT_WRITEABLE;
    }

    /**
    * Must return plaintext MIB description for the OIDs served
    */
    public function getMIB()
    {
        return '';
    }
}

?>