<?php
/**
 * Allows display of OID values via GET queries
 *
 * @author Gaetano Giunta
 * @copyright (c) 2009-2012 G. Giunta
 * @license code licensed under the GPL License: see README
 */

$response = 'NONE';
$oid = $Params['oid'];

if ( $oid != '' )
{
    $server = new eZSNMPd();
    $response = $server->get( $oid );
}

header( 'Content-Type: text/plain' );
echo "$response\n";
eZExecution::cleanExit();

?>