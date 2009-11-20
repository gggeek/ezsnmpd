<?php
/**
 *
 * @version $Id$
 * @author Gaetano Giunta
 * @copyright (c) 2009 G. Giunta
 * @license code licensed under the GPL License: see README
 */

header( 'Content-Type: text/plain' );

$server = new eZSNMPd();
echo $server->getFullMIB( $handlerMIBs );
echo $handlerMIBs;
echo "\nEND";

eZExecution::cleanExit();

?>