<?php
/**
 * Displays the equivalent of snmpwalk: a list of oid names and their types + values
 *
 * @version $Id: get.php 22 2009-05-24 15:09:30Z gg $
 * @author Gaetano Giunta
 * @copyright (c) 2009 G. Giunta
 * @license code licensed under the GPL License: see README
 */

$next = $Params['oid'];

$server = new eZSNMPd();
$response = implode( "\n", $server->walk( $next ) );

header( 'Content-Type: text/plain' );
echo "$response\n";
eZExecution::cleanExit();

?>