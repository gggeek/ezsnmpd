<?php
/**
 * Bridge that allows any value exposed in the MIB to be traced via the eZPerformanceLogger
 * framework
 *
 * @author G. Giunta
 * @copyright (C) G. Giunta 2012
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

class eZsnmpdPerfLogger implements eZPerfLoggerProvider
{

        /**
     * This method is called (by the framework) to allow this class to provide
     * values for the variables it caters to.
     * @param string $output current page output
     * @return array variable name => value
     */
    public static function measure( $output )
    {
        $out = array();
        $ini = eZINI::instance( 'ezperformancelogger.ini' );
        $vars = $ini->variable( 'GeneralSettings', 'TrackVariables' );
        $snmpd = null;
        foreach ( $vars as $var )
        {
            if ( strpos( $var, 'snmp/' ) === 0 )
            {
                $parts = explode( '/', $var, 2 );
                $oid = $parts[1];
//var_dump( $oid );
                $snmpd = ( $snmpd == null ) ? new eZSNMPd() : $snmpd;
                $value = ( strpos( $oid, '.' ) !== false ) ? $snmpd->get( $oid ) : $snmpd->getByName( str_replace( 'eZSystems::', '', $oid ) );
//var_dump( $value );
                $value = explode( "\n", $value );
                $out[$var] = $value[2];
//die();
            }
        }

        return $out;
    }

    /**
     * Returns the list of variables this Provider can measure
     * @return array varname => type
     */
    public static function supportedVariables()
    {
        return array( 'snmp/*' => 'mixed' );
    }
}

?>