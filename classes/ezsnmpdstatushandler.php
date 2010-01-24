<?php
/**
 * SNMP Handler used to retrieve status-related information
 * Handles access to the 'status' branch of the MIB (2)
 * initial key indicators taken from ezmunin and ggsysinfo
 *
 * @author G. Giunta
 * @version $Id$
 * @copyright (C) G. Giunta 2009
 * @license code licensed under the GPL License: see README
 *
 * @todo add enum for OK/KO/NA values, use 1/2/3 instead of 1/0/-1, as it is more snmp-standard
 *
 * @todo split this module in smaller subparts?
 *
 * @todo add other metrics, such as:
 *       expired / active sessions
 *       files in the fs (storage) when in cluster mode
 *       cache files count+size when in cluster mode (right now only fs mode supported)
 *       object nr. per version (using a table)
 *       object nr. per class (using a table)
 *       inactive users / disabled users
 *       running workflows
 *       cache/storage files count per size per type (eg. 1k, 10k, 100k, 1mb)
 *       cache/storage files count per age per type (eg. 1hr, 1day, 1week, 1month)
 */

class eZsnmpdStatusHandler extends eZsnmpdHandler {

    static $simplequeries = array(
        '2.1.2.1' => 'SELECT COUNT(id) AS count FROM ezcontentobject', // eZContentObjects
        '2.1.2.2' => 'SELECT COUNT(id) AS count FROM ezcontentobject_attribute', // eZContentObjectAttributes
        '2.1.2.3' => 'SELECT COUNT(node_id) AS count FROM ezcontentobject_tree', // eZContentObjectTreeNode
        '2.1.2.4' => 'SELECT COUNT(id) AS count FROM ezcontentobject_link', // eZContentObjectRelations
        '2.1.2.5' => 'SELECT COUNT(id) AS count FROM ezcontentobject WHERE STATUS=0', // eZContentObjectDrafts
        '2.1.2.6' => 'SELECT COUNT(id) AS count FROM ezcontentclass',
        '2.1.2.7' => 'SELECT COUNT(id) AS count FROM ezinfocollection',

        '2.1.3.1' => 'SELECT COUNT(contentobject_id) AS count FROM ezuser', // user count

        '2.1.4.1' => 'SELECT COUNT(session_key) AS count FROM ezsession', // all sessions
        '2.1.4.2' => 'SELECT COUNT(session_key) AS count FROM ezsession where ezsession.user_id = /*anonymousId*/', // anon sessions
        '2.1.4.3' => 'SELECT COUNT(session_key) AS count FROM ezsession where ezsession.user_id != /*anonymousId*/', // registered sessions
    );
    static $oidlist = null;
    static $cachelist = array();
    static $orderstatuslist = array();
    static $storagedirlist = array();

    function oidRoot()
    {
        return '2.';
    }

    function oidList( )
    {
        if ( self::$oidlist === null )
        {

            // build list of oids corresponding to order status
            $db = self::eZDBinstance();
            if ( $db )
            {
                $status = $db->arrayQuery( 'select status_id, name from ezorder_status where is_active=1 order by id' );
                $db->close();
                if ( is_array( $status ) )
                {
                    $i = 1;
                    foreach( $status as $line )
                    {
                        self::$orderstatuslist = array_merge( self::$orderstatuslist, array( "2.1.5.1.1.1.$i" => $line['status_id'], "2.1.5.1.1.2.$i" => $line['name'], "2.1.5.1.1.3.$i" => $line['status_id'], "2.1.5.1.1.4.$i" => $line['status_id'] ) );
                        $i++;
                    }
                }
            }
            else
            {
                // what to do in this case? db is down - maybe we should raise an exception
                // instead of producing a shortened oid list...
            }

            // build list of oids corresponding to caches and store for later their config
            $i = 1;
            $cachemib = '';
            foreach ( eZCache::fetchList() as $cacheItem )
            {
                if ( $cacheItem['path'] != false /*&& $cacheItem['enabled']*/ )
                {
                    $id = $cacheItem['id'];
                    self::$cachelist =  array_merge( self::$cachelist, array( "2.2.$i.1" => $id, "2.2.$i.2" => $id, "2.2.$i.3" => $id, "2.2.$i.4" => $id ) );
                    $i++;
                }
            }

            // build list of oids corresponding to storage dirs
            /// @todo this way of finding storage dir is lame, as it depends on them having been created
            ///       it will also not work in cluster mode, as there will be no dirs on the fs...
            $storagedir = eZSys::storageDirectory();
            $files = @scandir( $storagedir );
            $i = 1;
            foreach( $files as $file )
            {
                if ( $file != '.' && $file != '..' && is_dir( $storagedir . '/' . $file ) )
                {
                    self::$storagedirlist = array_merge( self::$storagedirlist, array( "2.3.$i.1" => $storagedir . '/' . $file,  "2.3.$i.2" => $storagedir . '/' . $file, "2.3.$i.3" => $storagedir . '/' . $file ) );
                    $i++;
                }
            }

            self::$oidlist = array_merge ( array_keys( self::$simplequeries ), array_keys( self::$orderstatuslist ), array_keys( self::$cachelist ), array_keys( self::$storagedirlist ), array( '2.1.1', '2.4.1', '2.4.2', '2.4.3', '2.5.1' ) );
            usort( self::$oidlist, 'version_compare' );
        }
        return self::$oidlist;
    }

    /**
    * @todo we should return an error if the scalar values are queried without a .0 appendeded...
    */
    function get( $oid )
    {
        $this->oidList();

        $internaloid = preg_replace( '/\.0$/', '', $oid );

        if ( array_key_exists( $internaloid, self::$simplequeries ) )
        {
            $count = -1;
            $db = self::eZDBinstance();
            if ( $db )
            {
                $results = $db->arrayQuery( str_replace( '/*anonymousId*/', eZUser::anonymousId(), self::$simplequeries[$internaloid] ) );
                $db->close();
                if ( is_array( $results ) && count( $results ) )
                {
                    $count = $results[0]['count'];
                }
            }
            return array(
                'oid' => $oid,
                'type' => eZSNMPd::TYPE_INTEGER, // counter cannot be used, as it is monotonically increasing
                'value' => $count );
        }

        if ( array_key_exists( $internaloid, self::$orderstatuslist ) )
        {
            $oids = explode( '.', $internaloid );
            switch( $oids[5] )
            {
                case '1':
                    return array(
                        'oid' => $oid,
                        'type' => eZSNMPd::TYPE_INTEGER,
                        'value' => self::$orderstatuslist[$internaloid] );
                case '2':
                    return array(
                        'oid' => $oid,
                        'type' => eZSNMPd::TYPE_STRING,
                        'value' => self::$orderstatuslist[$internaloid] );
                case '3':
                case '4':
                    $count = -1;
                    $db = self::eZDBinstance();
                    if ( $db )
                    {
                        $status = $db->arrayQuery( 'select count(*) as num from ezorder where is_temporary=0 and is_archived=' . ( ($oids[5]+1) % 2 ) . ' and status_id='.self::$orderstatuslist[$internaloid], array( 'column' => 'num' ) );
                        $db->close();
                        if ( is_array( $status ) && count( $status ) )
                        {
                            $count = $status[0];
                        }
                    }
                    return array(
                        'oid' => $oid,
                        'type' => eZSNMPd::TYPE_INTEGER,
                        'value' => $count );
            }
        }

        if ( array_key_exists( $internaloid, self::$cachelist ) )
        {
            $cacheinfo = eZCache::fetchByID( self::$cachelist[$internaloid] );
            $oids = explode( '.', $internaloid );
            switch( $oids[3] )
            {
                case '1':
                    return array(
                        'oid' => $oid,
                        'type' => eZSNMPd::TYPE_STRING,
                        'value' => $cacheinfo['name'] );
                case '2':
                    return array(
                        'oid' => $oid,
                        'type' => eZSNMPd::TYPE_INTEGER,
                        'value' => (int)$cacheinfo['enabled'] );
                case '3':
                case '4':
                    $fileINI = eZINI::instance( 'file.ini' );
                    $handlerName = $fileINI->variable( 'ClusteringSettings', 'FileHandler' );
                    switch( $handlerName )
                    {
                        case 'ezfs':
                        case 'eZFSFileHandler':
                        case 'eZFS2FileHandler':
                            break;
                        default: // the db-based filehandlers + dfs one not yet supported
                            return array(
                                'oid' => $oid,
                                'type' => eZSNMPd::TYPE_INTEGER,
                                'value' => -1 );

                    }
                    // take care: this is hardcoded from knowledge of cache structure...
                    if ( strpos( $cacheinfo['path'], 'var/cache/' ) === 0 )
                    {
                        $cachedir = $cacheinfo['path'];
                    }
                    else
                    {
                        $cachedir = eZSys::cacheDirectory() . '/' . $cacheinfo['path'];
                    }
                    if ( $oids[3] == '3' )
                    {
                        $out = (int)eZsnmpdTools::countFilesInDir( $cachedir );
                    }
                    else
                    {
                        $out = (int)eZsnmpdTools::countFilesSizeInDir( $cachedir );
                    }
                    return array(
                        'oid' => $oid,
                        'type' => eZSNMPd::TYPE_INTEGER,
                        'value' => $out );
            }
        }

        if ( array_key_exists( $internaloid, self::$storagedirlist ) )
        {
            $oids = explode( '.', $internaloid );
            switch( $oids[3] )
            {
                case '1':
                    return array(
                        'oid' => $oid,
                        'type' => eZSNMPd::TYPE_STRING,
                        'value' => self::$storagedirlist[$internaloid] );
                case '2':
                case '3':
                    $fileINI = eZINI::instance( 'file.ini' );
                    $handlerName = $fileINI->variable( 'ClusteringSettings', 'FileHandler' );
                    switch( $handlerName )
                    {
                        case 'ezfs':
                        case 'eZFSFileHandler':
                        case 'eZFS2FileHandler':
                            break;
                        default: // the db-based filehandlers + dfs one not yet supported
                            return array(
                                'oid' => $oid,
                                'type' => eZSNMPd::TYPE_INTEGER,
                                'value' => -1 );

                    }
                    if ( $oids[3] == '2' )
                    {
                        $out = (int)eZsnmpdTools::countFilesInDir( self::$storagedirlist[$internaloid] );
                    }
                    else
                    {
                        $out = (int)eZsnmpdTools::countFilesSizeInDir( self::$storagedirlist[$internaloid] );
                    }
                    return array(
                        'oid' => $oid,
                        'type' => eZSNMPd::TYPE_INTEGER,
                        'value' => $out );
            }
        }

        switch( $internaloid )
        {
            case '2.1.1':
                // verify if db can be connected to
                $ok = 1;
                $db = self::eZDBinstance();
                if ( !$db )
                {
                    $ok = 0;
                }
                else
                {
                    $db->close();
                }
                return array(
                    'oid' => $oid,
                    'type' => eZSNMPd::TYPE_INTEGER,
                    'value' => $ok );

            /*case '2.2.1': // cache-blocks
                /// @todo ...
                $handlerName = $fileINI->variable( 'ClusteringSettings', 'FileHandler' );
                switch( $handlerName )
                {
                    case 'ezfs':
                        break;
                    case 'ezdb':
                        break;
                    default:
                }

            case '2.2.2': // view-cache
                /// @todo ...
                $handlerName = $fileINI->variable( 'ClusteringSettings', 'FileHandler' );
                switch( $handlerName )
                {
                    case 'ezfs':
                        break;
                    case 'ezdb':
                        break;
                    default:
                }*/

            case '2.4.1': // ldap connection
                $ini = eZINI::instance( 'ldap.ini' );
                if ( $ini->variable( 'LDAPSettings', 'LDAPEnabled' ) == 'true' && $ini->variable( 'LDAPSettings', 'LDAPServer' ) != '' )
                {
                    $ok = 0;

                    // code copied over from ezldapuser class...

                    $LDAPVersion = $ini->variable( 'LDAPSettings', 'LDAPVersion' );
                    $LDAPServer = $ini->variable( 'LDAPSettings', 'LDAPServer' );
                    $LDAPPort = $ini->variable( 'LDAPSettings', 'LDAPPort' );
                    $LDAPBindUser = $ini->variable( 'LDAPSettings', 'LDAPBindUser' );
                    $LDAPBindPassword = $ini->variable( 'LDAPSettings', 'LDAPBindPassword' );

                    $ds = ldap_connect( $LDAPServer, $LDAPPort );

                    if ( $ds )
                    {
                        ldap_set_option( $ds, LDAP_OPT_PROTOCOL_VERSION, $LDAPVersion );
                        if ( $LDAPBindUser == '' )
                        {
                            $r = ldap_bind( $ds );
                        }
                        else
                        {
                            $r = ldap_bind( $ds, $LDAPBindUser, $LDAPBindPassword );
                        }
                        if ( $r )
                        {
                            $ok = 1;
                        }
                        // added: release resources, be ready for next test
                        ldap_close($ds);
                    }
                }
                else
                {
                    $ok = -1;
                }
                return array(
                    'oid' => $oid,
                    'type' => eZSNMPd::TYPE_INTEGER,
                    'value' => $ok );

            case '2.4.2': // web connection
                $ini = eZINI::instance( 'snmpd.ini' );
                $websites = $ini->variable( 'StatusHandler', 'WebBeacons' );
                $ok = 0;
                if ( is_string( $websites ) )
                    $websites = array( $websites );
                foreach ( $websites as $key => $site )
                {
                    if ( trim( $site ) == '' )
                    {
                        unset( $websites[$key] );
                    }
                }
                if ( count( $websites ) )
                {
                    foreach ( $websites as $site )
                    {
                        // current eZ code is broken if no curl is installed, as it does not check for 404 or such.
                        // besides, it does not even support proxies...
                        if ( extension_loaded( 'curl' ) )
                        {
                            if ( eZHTTPTool::getDataByURL( $site, true ) )
                            {
                                $ok = 1;
                                break;
                            }
                        }
                        else
                        {
                            $data = eZHTTPTool::getDataByURL( $site, false );
                            if ( $data !== false && sysInfoTools::isHTTP200( $data) )
                            {
                                $ok = 1;
                                break;
                            }
                        }
                    }
                }
                else
                {
                    $ok = -1;
                }
                return array(
                    'oid' => $oid,
                    'type' => eZSNMPd::TYPE_INTEGER,
                    'value' => $ok );

            case '2.4.3': // email connection
                $ini = eZINI::instance( 'snmpd.ini' );
                $recipient = $ini->variable( 'StatusHandler', 'MailReceiver' );
                $ok = 0;
                $mail = new eZMail();
                if ( trim( $recipient ) != '' && $mail->validate( $recipient ) )
                {
                    $mail->setReceiver( $recipient );
                    $ini = eZINI::instance();
                    $sender = $ini->variable( 'MailSettings', 'EmailSender' );
                    $mail->setSender($sender);
                    $mail->setSubject( "Test email" );
                    $mail->setBody( "This email was automatically sent while testing eZ Publish connectivity to the mail server. Please do not reply." );
                    $mailResult = eZMailTransport::send( $mail );
                    if ( $mailResult )
                    {
                        $ok = 1;
                    }
                }
                else
                {
                    $ok = -1;
                }
                return array(
                    'oid' => $oid,
                    'type' => eZSNMPd::TYPE_INTEGER,
                    'value' => $ok );

            case '2.5.1':
                $fileINI = eZINI::instance( 'file.ini' );
                $clusterhandler = $fileINI->variable( 'ClusteringSettings', 'FileHandler' );
                if ( $clusterhandler == 'ezdb' || $clusterhandler == 'eZDBFileHandler' )
                {
                    $ok = 0;
                    $dbFileHandler = eZClusterFileHandler::instance();
                    if ( $dbFileHandler instanceof eZDBFileHandler )
                    {
                        // warning - we dig into the private parts of the cluster file handler,
                        // as no real API are provided for it (yet)
                        if ( is_resource( $dbFileHandler->backend->db ) )
                            $ok = 1;
                    }
                }
                else
                {
                    $ok = -1;
                }
                return array(
                    'oid' => $oid,
                    'type' => eZSNMPd::TYPE_INTEGER,
                    'value' => $ok );

        }

        return self::NO_SUCH_OID; // oid not managed
    }

    function getMIB()
    {
        // prepare MIB chunks
        $cachemib = '';
        $storagemib = '';

        // make sure we warm up the cache index
        $this->oidList();

        foreach( self::$cachelist as $oid => $id )
        {
            $oids = explode( '.', $oid );
            if ( $oids[3] == '1' )
            {
                $cachename = 'cache' . ucfirst( eZSNMPd::asncleanup( $id ) );
                $cachemib .= "
$cachename          OBJECT IDENTIFIER ::= { cache {$oids[2]} }

{$cachename}Name OBJECT-TYPE
    SYNTAX          DisplayString
    MAX-ACCESS      read-only
    STATUS          current
    DESCRIPTION
            \"The name of this cache.\"
    ::= { $cachename 1 }

{$cachename}Status OBJECT-TYPE
    SYNTAX          INTEGER
    MAX-ACCESS      read-only
    STATUS          current
    DESCRIPTION
            \"Cache status: 1 for enabled, 0 for disabled\"
    ::= { $cachename 2 }

{$cachename}Count OBJECT-TYPE
    SYNTAX          INTEGER
    MAX-ACCESS      read-only
    STATUS          current
    DESCRIPTION
            \"Number of files in the cache (-1 if current cluster mode not supported)\"
    ::= { $cachename 3 }

{$cachename}Size OBJECT-TYPE
    SYNTAX          INTEGER
    MAX-ACCESS      read-only
    STATUS          current
    DESCRIPTION
            \"Sum of size of all files in the cache (-1 if current cluster mode not supported)\"
    ::= { $cachename 4 }
";
            }
        }

        foreach( self::$storagedirlist as $oid => $file )
        {
            $oids = explode( '.', $oid );
            if ( $oids[3] == '1' )
            {
                $cachename = 'storage' . ucfirst( eZSNMPd::asncleanup( basename( $file ) ) );
                $storagemib .= "
$cachename          OBJECT IDENTIFIER ::= { storage {$oids[2]} }

{$cachename}Path OBJECT-TYPE
    SYNTAX          DisplayString
    MAX-ACCESS      read-only
    STATUS          current
    DESCRIPTION
            \"The path of this storage dir.\"
    ::= { $cachename 1 }

{$cachename}Count OBJECT-TYPE
    SYNTAX          INTEGER
    MAX-ACCESS      read-only
    STATUS          current
    DESCRIPTION
            \"Number of files in the dir (-1 if current cluster mode not supported)\"
    ::= { $cachename 2 }

{$cachename}Size OBJECT-TYPE
    SYNTAX          INTEGER
    MAX-ACCESS      read-only
    STATUS          current
    DESCRIPTION
            \"Sum of size of all files in the dir (-1 if current cluster mode not supported)\"
    ::= { $cachename 3 }
";
            }
        }

        return '
status          OBJECT IDENTIFIER ::= { eZPublish 2 }

database OBJECT IDENTIFIER ::= { status 1 }

dbstatus OBJECT-TYPE
    SYNTAX          INTEGER
    MAX-ACCESS      read-only
    STATUS          current
    DESCRIPTION
            "Availability of the database."
    ::= { database 1 }

content         OBJECT IDENTIFIER ::= { database 2 }

contentObjects OBJECT-TYPE
    SYNTAX          INTEGER
    MAX-ACCESS      read-only
    STATUS          current
    DESCRIPTION
            "The number of content objects."
    ::= { content 1 }

contentObjectAttributes OBJECT-TYPE
    SYNTAX          INTEGER
    MAX-ACCESS      read-only
    STATUS          current
    DESCRIPTION
            "The number of content object attributes."
    ::= { content 2 }

contentObjectNodes OBJECT-TYPE
    SYNTAX          INTEGER
    MAX-ACCESS      read-only
    STATUS          current
    DESCRIPTION
        "The number of content nodes."
    ::= { content 3 }

contentObjectRelations OBJECT-TYPE
    SYNTAX          INTEGER
    MAX-ACCESS      read-only
    STATUS          current
    DESCRIPTION
    "The number of content object relations."
    ::= { content 4 }

contentObjectDrafts OBJECT-TYPE
    SYNTAX          INTEGER
    MAX-ACCESS      read-only
    STATUS          current
    DESCRIPTION
            "The number of content objects in DRAFT state."
    ::= { content 5 }

contentObjectClasses OBJECT-TYPE
    SYNTAX          INTEGER
    MAX-ACCESS      read-only
    STATUS          current
    DESCRIPTION
            "The number of content object classes."
    ::= { content 6 }

contentObjectInfoCollections OBJECT-TYPE
    SYNTAX          INTEGER
    MAX-ACCESS      read-only
    STATUS          current
    DESCRIPTION
            "The number of information collections."
    ::= { content 7 }

users           OBJECT IDENTIFIER ::= {database 3}

registeredusers OBJECT-TYPE
    SYNTAX          INTEGER
    MAX-ACCESS      read-only
    STATUS          current
    DESCRIPTION
            "The number of existing user accounts."
    ::= { users 1 }

sessions           OBJECT IDENTIFIER ::= {database 4}

allSessions OBJECT-TYPE
    SYNTAX          INTEGER
    MAX-ACCESS      read-only
    STATUS          current
    DESCRIPTION
            "The number of active sessions."
    ::= { sessions 1 }

anonSessions OBJECT-TYPE
    SYNTAX          INTEGER
    MAX-ACCESS      read-only
    STATUS          current
    DESCRIPTION
            "The number of active anonymous users sessions."
    ::= { sessions 2 }

registeredSessions OBJECT-TYPE
    SYNTAX          INTEGER
    MAX-ACCESS      read-only
    STATUS          current
    DESCRIPTION
            "The number of active registered users sessions."
    ::= { sessions 3 }

shop            OBJECT IDENTIFIER ::= {database 5}

orderStatusTable OBJECT-TYPE
    SYNTAX          SEQUENCE OF OrderEntry
    MAX-ACCESS      not-accessible
    STATUS          current
    DESCRIPTION
              "A table containing the number of orders per order state."
    ::= { shop 1 }

orderStatusEntry OBJECT-TYPE
    SYNTAX          OrderEntry
    MAX-ACCESS      not-accessible
    STATUS          current
    DESCRIPTION
            "A table row describing the set of orders in status N."
    INDEX   { orderStatusId }
    ::= { orderStatusTable 1 }

OrderStatusEntry ::=
    SEQUENCE {
        orderStatusId
            INTEGER,
        orderStatusName
            DisplayString,
        orderStatusCount
            INTEGER,
        orderStatusArchiveCount
            INTEGER
    }

orderStatusId OBJECT-TYPE
    SYNTAX          INTEGER (1..99)
    MAX-ACCESS      read-only
    STATUS          current
    DESCRIPTION
            "ID of this order status."
    ::= { orderStatusEntry 1 }

orderStatusName OBJECT-TYPE
    SYNTAX          DisplayString
    MAX-ACCESS      read-only
    STATUS          current
    DESCRIPTION
            "The name of this order status."
    ::= { orderStatusEntry 2 }

orderStatusCount OBJECT-TYPE
    SYNTAX          INTEGER
    MAX-ACCESS      read-only
    STATUS          current
    DESCRIPTION
            "Number of active orders in this status."
    ::= { orderStatusEntry 3 }

orderStatusArchiveCount OBJECT-TYPE
    SYNTAX          INTEGER
    MAX-ACCESS      read-only
    STATUS          current
    DESCRIPTION
            "Number of acrchived orders in this status."
    ::= { orderStatusEntry 4 }

cache           OBJECT IDENTIFIER ::= {status 2}
'.$cachemib.'
storage         OBJECT IDENTIFIER ::= {status 3}
'.$storagemib.'
external        OBJECT IDENTIFIER ::= {status 4}

ldap OBJECT-TYPE
    SYNTAX          INTEGER
    MAX-ACCESS      read-only
    STATUS          current
    DESCRIPTION
            "Connectivity to LDAP server (-1 if not configured)."
    ::= { external 1 }

web OBJECT-TYPE
    SYNTAX          INTEGER
    MAX-ACCESS      read-only
    STATUS          current
    DESCRIPTION
            "Connectivity to the web. (probes a series of webservers defined in snmpd.ini, returns -1 if not configured)."
    ::= { external 2 }

email OBJECT-TYPE
    SYNTAX          INTEGER
    MAX-ACCESS      read-only
    STATUS          current
    DESCRIPTION
            "Connectivity to mail server (NB: will send a test mail when probed to a recipient defined in snmpd.ini, returns -1 if not configured)."
    ::= { external 3 }

cluster        OBJECT IDENTIFIER ::= {status 5}

clusterdbstatus OBJECT-TYPE
    SYNTAX          INTEGER
    MAX-ACCESS      read-only
    STATUS          current
    DESCRIPTION
            "Availability of the cluster database (-1 for NA)."
    ::= { cluster 1 }
';
    }

    /**
    * Make sure we use a separate DB connection from the standard one.
    * This allows us to:
    * - catch the exception raised if db is down and keep the script going
    * - run the script in damon mode without keeping a connection open (as we can close as soon as it is not needed anymore)
    */
    protected static function eZDBinstance()
    {
        try
        {
            $db = eZDB::instance( false, false, true );
            // eZP 4.0 will not raise an exception on connection errors
            if ( !$db->isConnected() )
            {
                return false;
            }
            return $db;
        }
        catch ( Exception $e )
        {
            return false;
        }
    }
}
?>