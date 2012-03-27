<?php
/**
 * Allows setting OID values via GET queries
 *
 * @author Gaetano Giunta
 * @copyright (c) 2009-2012 G. Giunta
 * @license code licensed under the GPL License: see README
 */

$response = '';
$oid = $Params['oid'];
$type = $Params['type'];
$value = $Params['value'];

if ( $oid != '' && $type != '' )
{
    $server = new eZSNMPd();
    $response = $server->set( $oid, $value, $type );
}

header( 'Content-Type: text/plain' );
echo "$response\n";
eZExecution::cleanExit();

?>