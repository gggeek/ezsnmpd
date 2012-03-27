<?php
/**
 * A view to display the MIB - in various formats
 *
 * @author Gaetano Giunta
 * @copyright (c) 2009-2012 G. Giunta
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
//include_once( 'kernel/common/i18n.php' );
$Result['path'] = array( array( 'url' => '',
                                'text' => 'SNMP Monitoring' ),
                         array( 'url' => 'snmp/mib/html',
                                'text' => 'MIB' ) );

?>