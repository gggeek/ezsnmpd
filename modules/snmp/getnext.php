<?php
/**
 * Allows display of OID values via GET queries
 *
 * @version $Id: get.php 22 2009-05-24 15:09:30Z gg $
 * @author Gaetano Giunta
 * @copyright (c) 2009 G. Giunta
 * @license code licensed under the GPL License: see README
 */

$response = 'NONE';
$oid = $Params['oid'];

if ( $oid != '' )
{
    $server = new eZSNMPd();
    $response = $server->getnext( $oid );
}

header( 'Content-Type: text/plain' );
echo "$response\n";
eZExecution::cleanExit();

?>