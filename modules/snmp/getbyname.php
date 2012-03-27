<?php
/**
 * Allows display of OID values via GET queries
 *
 * @author Gaetano Giunta
 * @copyright (c) 2009-2012 G. Giunta
 * @license code licensed under the GPL License: see README
 */

$response = 'NONE';
$name = $Params['name'];

if ( $name != '' )
{
    $server = new eZSNMPd();
    $ini = eZINI::instance( 'snmpd.ini' );
    $prefix = $ini->variable( 'MIB', 'PrefixName' );
    $name = preg_replace( "/^$prefix::/", '', $name );
    $response = $server->getByName( $name );
}

header( 'Content-Type: text/plain' );
echo "$response\n";
eZExecution::cleanExit();

?>