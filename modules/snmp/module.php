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
    'script' => 'mib.php',
    'params' => array( 'format' ),
);

$ViewList['plugins'] = array(
    'script' => 'walk.php',
    'params' => array( 'plugin' )
);

$ViewList['get'] = array(
    'script' => 'get.php',
    'params' => array( 'oid' ),
    'functions' => array( 'get' )
);

$ViewList['getnext'] = array(
    'script' => 'getnext.php',
    'params' => array( 'oid' ),
    'functions' => array( 'get' )
);

$ViewList['set'] = array(
    'script' => 'set.php',
    'params' => array( 'oid', 'type', 'value' ),
    'functions' => array( 'set' )
);

$ViewList['walk'] = array(
    'script' => 'walk.php',
    'params' => array( 'oid' ),
    'functions' => array( 'get' )
);

$FunctionList = array(
    'get' => array(),
    'set' => array()
);

?>