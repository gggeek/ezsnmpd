<?php
/**
 * Allows display of OID values via GET queries
 *
 * @version $Id$
 * @author Gaetano Giunta
 * @copyright (c) 2009 G. Giunta
 * @license code licensed under the GPL License: see README
 */

$response = 'NONE';
$oid = $Params['oid'];

if ( $oid != '' )
{
    $server = new eZSNMPd();
    $response = $server->$mode( $oid );
}

header( 'Content-Type: text/plain' );
echo "$response\n";
eZExecution::cleanExit();

?>