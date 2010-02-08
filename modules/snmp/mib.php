<?php
/**
 * A view to display the MIB - in various formats
 *
 * @version $Id$
 * @author Gaetano Giunta
 * @copyright (c) 2009-2010 G. Giunta
 * @license code licensed under the GPL License: see README
 */

$server = new eZSNMPd();
switch( $Params['format'] )
{
    case 'html':
        $format = 'html';
        $mib = $server->getMIBArray();
        break;
    default:
        header( 'Content-Type: text/plain' );
        echo $server->getFullMIB();
        eZExecution::cleanExit();
}

require_once( "kernel/common/template.php" );
$tpl = templateInit();
$tpl->setVariable( 'mib', $mib );
$Result = array();
$Result['content'] = $tpl->fetch( "design:snmp/mib/$format.tpl" );
//$Result['left_menu'] = 'design:parts/wsdebugger/menu.tpl';
//$Result['path'] = array( array( 'url' => 'webservices/debugger',
//                                'text' => ezi18n( 'extension/webservices', 'WS Debugger' ) ) );

?>