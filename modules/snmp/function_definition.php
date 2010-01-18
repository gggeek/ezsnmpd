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
        'name'            => 'get',
        //'operation_types' => array( 'read' ),
        'call_method'     => array( //'include_file' => '.../modules/helloworld/helloworldfunctioncollection.php',
                                    'class'        => 'eZSNMPd',
                                    'method'       => 'get' ),
        //'parameter_type'  => 'standard',
        'parameters'      => array( array( 'name'     => 'oid',
                                           'type'     => 'string',
                                           'required' => true ) ) ),
    'walk' => array(
        'name'            => 'walk',
        //'operation_types' => array( 'read' ),
        'call_method'     => array( //'include_file' => '.../modules/helloworld/helloworldfunctioncollection.php',
                                    'class'        => 'eZSNMPd',
                                    'method'       => 'walk' ),
        //'parameter_type'  => 'standard',
        'parameters'      => array( array( 'name'     => 'oid',
                                           'type'     => 'string',
                                           'required' => true ) ) ),
);

?>