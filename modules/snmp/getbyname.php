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