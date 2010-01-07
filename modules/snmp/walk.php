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
$next = $Params['oid'];

header( 'Content-Type: text/plain' );
$server = new eZSNMPd();
while( ( $response = $server->getnext( $next ) ) !== null )
{
    $parts = explode( "\n", $response );
    $parts[1] = strtoupper( $parts[1] );
    /// @todo more decoding of snmpd.conf formats to snmpwalk ones?
    if ( $parts[1] == 'STRING' )
    {
        /// @todo verify if we nedd substitution for " and other chars (which one?)
        $parts[2] = '"' . $parts[2] . '"';
    }
    echo "{$parts[0]} = {$parts[1]}: {$parts[2]}\n";
    $next = $parts[0];
}

eZExecution::cleanExit();

?>