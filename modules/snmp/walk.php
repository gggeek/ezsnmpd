<?php
/**
 * Displays the equivalent of snmpwalk: a list of oid names and their types + values
 *
 * @author Gaetano Giunta
 * @copyright (c) 2009-2012 G. Giunta
 * @license code licensed under the GPL License: see README
 */

$next = $Params['oid'];

$server = new eZSNMPd();
$response = implode( "\n", $server->walk( $next ) );

header( 'Content-Type: text/plain' );
echo "$response\n";
eZExecution::cleanExit();

?>