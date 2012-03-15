<?php
/**
 * Template fetch functions for snmpd
 *
 * @author G. Giunta
 * @version $Id: function_definition.php 5 2009-01-26 15:41:36Z gg $
 * @copyright 2009
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