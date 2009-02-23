<?php
/**
 * @version $Id$
 * @author Gaetano Giunta
 * @copyright (c) 2009 G. Giunta
 * @license code licensed under the GPL License: see README
 */

$Module = array( 'name' => 'snmp' );


$ViewList = array();

$ViewList['mib'] = array(
    'script' => 'mib.php'
);

$ViewList['get'] = array(
    'script' => 'get.php',
    'params' => array( 'oid' )
);

$FunctionList = array();

?>
