<?php
/**
 * Template fetch functions for snmpd
 *
 * @author G. Giunta
 * @copyright (C) G. Giunta 2009-2012
 * @license code licensed under the GPL License: see README
 */

$FunctionList = array(
    'get' => array(
        'name' => 'get',
        'call_method' => array(
            'class'  => 'eZSNMPd',
            'method' => 'get' ),
        'parameters' => array(
            array( 'name'=> 'oid',
                   'type' => 'string',
                   'required' => true ) ) ),

    'getbyname' => array(
        'name'=> 'getbyname',
        'call_method'=> array(
            'class'=> 'eZSNMPd',
            'method' => 'getByName' ),
        'parameters' => array(
            array( 'name'=> 'name',
                   'type' => 'string',
                   'required' => true ) ) ),

    'walk' => array(
        'name' => 'walk',
        'call_method' => array(
            'class' => 'eZSNMPd',
            'method' => 'walk' ),
        'parameters' => array(
            array( 'name' => 'oid',
                   'type'  => 'string',
                   'required' => true ) ) )
);

?>