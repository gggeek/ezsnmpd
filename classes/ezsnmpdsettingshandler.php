<?php
/**
 * Handles access to the 'settings' branch of the MIB (1)
 *
 * @author G. Giunta
 * @version $Id$
 * @copyright (C) G. Giunta 2009
 * @license code licensed under the GPL License: see README
 *
 * Notes:
 * - due to mib-file format limitations, group and settings names start with a
 *   lowercase letter in the mib; underscores are replaced wuth hypens
 *   and those settings that start with a number are prepended with 'xxx'
 * - the numbering of the settings is dependent on the settings present in the base
 *   'settings' dir of eZP. If new settinsg are added or old ones removed, oid
 *   numbers will be reassigned
 * - extension-added settings are not made available in this branch
 * - settings that are a list on not-previously known values (eg. image.ini) are
 *   even more subject to this problem
 *
 * @todo fix overly-long oid names
 * @todo add support for array-values
 * @todo allow write-access to settings (take into care also siteaccess/override: where do we write them?)
 * @todo test using 'Boolean' type with net-snmp tools (snmpget)
 * @todo allow read-access to know where a given setting is coming from (siteaccess/extension/override/default)
 */

class eZsnmpdSettingsHandler extends eZsnmpdHandler {

    /// For speed, we do not enumerate all oids here
    function oidList( )
    {
        return array ( '1.*' );
    }

    function get( $oid )
    {
        $result = $this->buildMIB( preg_replace( '/^1\./', '', $oid ) );

        if ( count( $result ) )
        {
            $result = reset( $result );
            $group = reset( $result['groups'] );
            $val = reset( $group['settings'] );
            return array(
                'oid' => $oid,
                'type' => $val['type'],
                'value' => $val['value'] );
        }
        return 0;

    }

    /// @todo ...
    /*function set( $oid )
    {
        $result = $this->buildMIB( preg_replace( '/^1\./', '', $oid ) );

        if ( is_array( $result ) && count( $result ) )
        {
        }
        return 0;
    }*/

    function getMIB()
    {
        $out = '

settings        OBJECT IDENTIFIER ::= {eZPublish 1}
';
        foreach ( $this->buildMIB() as $i => $file )
        {
            $filename = $file['file'];
            $out .= "\n" . str_pad ( $filename, 15 ) . " OBJECT IDENTIFIER ::= {settings  $i}\n";
            foreach( $file['groups'] as $j => $group )
            {
                $groupname = eZSNMPd::asncleanup( $group['group'] );
                $out .= "\n" . str_pad( $filename . $groupname, 15 ) . ' OBJECT IDENTIFIER ::= {'. "$filename $j}\n";
                foreach( $group['settings'] as $k => $setting )
                {
                    $name = eZSNMPd::asncleanup( $setting['name'] );
                    $out .= "
$filename$groupname$name OBJECT-TYPE
    SYNTAX          {$setting['type']}
    MAX-ACCESS      " . ( $setting['rw'] ? 'read-write' : ' read-only' ) . "
    STATUS          current
    DESCRIPTION     \"\"
    ::= { " . $filename . $groupname ." $k }\n";
                }
            }
        }
        return $out;
    }

    /**
    * Builds the mib tree, either for a single oid or for full settings
    */
    function buildMIB( $oid=null )
    {
        if ( $oid != null )
        {
            $oid = explode( '.', $oid );
            if ( count( $oid ) != 3)
            {
                return 0;
            }
        }

        // nb: should try to avoid caching these settings, since the script can
        // be running for a long time
        $rootDir = 'settings';
        $iniFiles = eZDir::recursiveFindRelative( $rootDir, '', '.ini' );
        sort( $iniFiles );
        $out = array();
        foreach( $iniFiles as $key => $file )
        {
            $file = str_replace( '/', '', $file );
            if ( $oid == null || $key == $oid[0]-1 )
            {
                $ini = ezINI::instance( $file );
                $outgroups = array();
                $j = 1;
                $groups = $ini->groups();
                ksort( $groups );
                foreach( $groups as $group => $settings )
                {
                    if ( $oid == null || $j == $oid[1] )
                    {
                        $i = 1;
                        $values = array();
                        ksort( $settings );
                        foreach( $settings as $setting => $val )
                        {
                            if ( $oid == null || $i == $oid[2] )
                            {
                                if ( is_numeric( $val ) )
                                {
                                    $type = 'INTEGER';
                                }
                                else if ( $val == 'true' || $val == 'enabled' )
                                {
                                    $type = 'Boolean';
                                    $val = 1;
                                }
                                else if ( $val == 'false' || $val == 'disabled' )
                                {
                                    $type = 'Boolean';
                                    $val = 2;
                                }
                                else
                                {
                                    $type = 'DisplayString';
                                }
                                $values[$i] = array( 'name' => $setting, 'value' => $val, 'type' => $type, 'rw' => false /* $ini->isSettingReadOnly( $file, $group, $setting )*/ );
                            }
                            $i++;
                        }
                        $outgroups[$j] = array( 'group' => $group, 'settings' => $values );
                    }
                    $j++;
                }
                // remove invalid stuff from file name (.)
                $out[$key+1] = array( 'file' => str_replace( '.ini', '', $file ), 'groups' => $outgroups );
            }
        }
        return $out;
    }

    /// used to decode from mib-types to snmpd.conf types...
    protected static $decodetype = array( 'Boolean' => 'integer', 'DisplayString' => 'string', 'INTEGER' => 'integer' );
}
?>