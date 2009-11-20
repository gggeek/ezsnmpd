<?php
/**
 * SNMP Handler class interface
 *
 * @author G. Giunta
 * @version $Id: ezsnmpdhandler.php 45 2009-09-10 12:08:25Z gg $
 * @copyright (C) G. Giunta 2009
 * @license code licensed under the GPL License: see README
 *
 */

interface eZsnmpdHandlerInterface {

    const NO_SUCH_OID = null;
    const LAST_OID = true;

    const SET_SUCCESFUL = 0;

    const ERROR_WRONG_TYPE = -1;
    const ERROR_WRONG_VALUE = -2;
    const ERROR_WRONG_LENGHT = -3;
    const ERROR_INCONSISTENT_VALUE = -4;
    const ERROR_NOT_WRITEABLE = -5;

    /**
    * Must return the root of the all oids handled, eg. '3.'
    * IMPORTANT: must end with a dot and not include a *
    * @return string
    */
    public function oidRoot();

    /**
    * Must return a 3-valued array on success, or NULL if key was not readable/unknown.
    * Being called with an inexisting oid that falls within the oidRoot() is not
    * to be considered an error.
    * @param string $oid note: for scalar objects, the trailing .0 will be part of $oid
    * @return array|null array keys: 'oid', 'type', 'value'. For types, see ezsnmpd constants
    */
    public function get( $oid );

    /**
    * Must return the next oid, NULL if key was not readable/unknown or TRUE if $oid is the last one supported by this handler
    * @param string $oid note: for scalar objects, the trailing .0 will be part of $oid
    * @return array|null|true array keys: 'oid', 'type', 'value'. For types, see ezsnmpd constants
    */
    public function getnext( $oid );

    /**
    * Must return 0 on success, or an error code from above
    * @return int
    */
    public function set( $oid, $value, $type );

    /**
    * Must return plaintext MIB description for the OIDs served
    * @return string
    */
    public function getMIB();
}

?>