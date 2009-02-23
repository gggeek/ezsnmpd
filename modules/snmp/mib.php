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
include( dirname( __FILE__ ) . '/EZPUBLISH-MIB' );
echo $server->getHandlerMIBs();
echo "\nEND";

eZExecution::cleanExit();

?>