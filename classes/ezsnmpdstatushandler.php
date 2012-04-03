<?php
/**
 * SNMP Handler used to retrieve status-related information
 * Handles access to the 'status' branch of the MIB (2)
 * Initial key indicators taken from ezmunin and ggsysinfo
 *
 * @author G. Giunta
 * @copyright (C) G. Giunta 2009-2012
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
        '2.1.2.8' => 'SELECT COUNT(*) AS count FROM ezpending_actions WHERE ACTION=\'index_object\'',
        '2.1.2.9' => 'SELECT COUNT(id) AS count FROM eznotificationevent WHERE STATUS=0',

        '2.1.3.1' => 'SELECT COUNT(contentobject_id) AS count FROM ezuser', // user count

        '2.1.4.1' => 'SELECT COUNT(session_key) AS count FROM ezsession', // all sessions
        '2.1.4.2' => 'SELECT COUNT(session_key) AS count FROM ezsession where ezsession.user_id = /*anonymousId*/', // anon sessions
        '2.1.4.3' => 'SELECT COUNT(session_key) AS count FROM ezsession where ezsession.user_id != /*anonymousId*/', // registered sessions

        '2.1.6.1' => 'SELECT COUNT(*) AS count FROM ezpublishingqueueprocesses WHERE status = 1',
        '2.1.6.2' => 'SELECT COUNT(*) AS count FROM ezpublishingqueueprocesses WHERE status = 2',
        '2.1.6.3' => 'SELECT COUNT(*) AS count FROM ezpublishingqueueprocesses WHERE status = 3',
        '2.1.6.4' => 'SELECT COUNT(*) AS count FROM ezpublishingqueueprocesses WHERE status = 4',
        '2.1.6.5' => 'SELECT COUNT(*) AS count FROM ezpublishingqueueprocesses WHERE status = 5'

    );

    static $oidlist = null;
    static $cachelist = array();
    static $orderstatuslist = array();
    static $storagedirlist = array();

    function oidRoot()
    {
        return '2.';
    }

    /// cache oid list for some speedup. NB: parent::oidList invokes getMIBTree
    function oidList( )
    {
        if ( self::$oidlist === null )
        {
            self::$oidlist = parent::oidList();
            //self::$oidlist = array_merge ( array_keys( self::$simplequeries ), array_keys( self::$orderstatuslist ), array_keys( self::$cachelist ), array_keys( self::$storagedirlist ), array( '2.1.1', '2.4.1', '2.4.2', '2.4.3', '2.5.1' ) );
            //usort( self::$oidlist, 'version_compare' );
        }
        return self::$oidlist;
    }

    /**
    * @todo we should return an error if the scalar values are queried without a .0 appendeded...
    */
    function get( $oid )
    {
        // warm up list of existing oids, if not yet done
        $this->oidList();

        $internaloid = preg_replace( '/\.0$/', '', $oid );

        if ( array_key_exists( $internaloid, self::$simplequeries ) )
        {
            $count = -1;

            if ( strpos( $internaloid, '2.1.4.' ) === 0 )
            {
                // session-related queries: return -1 if not using db-based storage
                $ini = eZINI::instance();
                $sessionHandler = $ini->variable( 'Session', 'Handler' );
                if ( $sessionHandler != 'ezpSessionHandlerDB' )
                {
                    return array(
                        'oid' => $oid,
                        'type' => eZSNMPd::TYPE_INTEGER,
                        'value' => $count );
                }
            }

            if ( strpos( $internaloid, '2.1.6.' ) === 0 )
            {
                // async-publication-related queries: return -1 if not using it
                $ini = eZINI::instance( 'content.ini' );
                if ( $ini->variable( 'PublishingSettings', 'AsynchronousPublishing' ) != 'enabled' )
                {
                    return array(
                        'oid' => $oid,
                        'type' => eZSNMPd::TYPE_INTEGER,
                        'value' => $count );
                }
            }

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
                else if ( $clusterhandler == 'eZDFSFileHandler' )
                {
                    // This is even worse: we have no right to know if db connection is ok.
                    // So we replicate some code here...
                    $dbbackend = eZExtension::getHandlerClass(
                    new ezpExtensionOptions(
                    array( 'iniFile'     => 'file.ini',
                           'iniSection'  => 'eZDFSClusteringSettings',
                           'iniVariable' => 'DBBackend' ) ) );
                    try
                    {
                        $dbbackend->_connect();
                        $ok = 1;
                    }
                    catch ( exception $e )
                    {
                        $ok = 0;
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

    function getMIBTree()
    {

        // build list of oids corresponding to order status
        $db = self::eZDBinstance();
        $orderStatusIdoids = array();
        $orderStatusNameoids = array();
        $orderStatusCountoids = array();
        $orderStatusArchiveCountoids = array();
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
                    $orderStatusIdoids[$i] = array( 'name' => 'orderStatusId'.$i, 'syntax' => 'INTEGER' );
                    $orderStatusNameoids[$i] = array( 'name' => 'orderStatusname'.$i, 'syntax' => 'DisplayString' );
                    $orderStatusCountoids[$i] = array( 'name' => 'orderStatusCount'.$i, 'syntax' => 'INTEGER' );
                    $orderStatusArchiveCountoids[$i] = array( 'name' => 'orderStatusArchive'.$i, 'syntax' => 'INTEGER' );
                    $i++;
                }
            }
            //var_dump($orderStatusArchiveCountoids);
            //die();
        }
        else
        {
            // what to do in this case? db is down - maybe we should raise an exception
            // instead of producing a shortened oid list...
        }

        // build list of oids corresponding to caches and store for later their config
        $i = 1;
        $cacheoids = array();
        foreach ( eZCache::fetchList() as $cacheItem )
        {
            if ( $cacheItem['path'] != false /*&& $cacheItem['enabled']*/ )
            {
                $id = $cacheItem['id'];
                self::$cachelist =  array_merge( self::$cachelist, array( "2.2.$i.1" => $id, "2.2.$i.2" => $id, "2.2.$i.3" => $id, "2.2.$i.4" => $id ) );
                $cachename = 'cache' . ucfirst( eZSNMPd::asncleanup( $id ) );
                $cacheoids[$i] = array(
                    'name' => $cachename,
                    'children' => array(
                        1 => array(
                            'name' => "{$cachename}Name",
                            'syntax' => 'DisplayString',
                            'description' => 'The name of this cache.'
                        ),
                        2 => array(
                            'name' => "{$cachename}Status",
                            'syntax' => 'INTEGER',
                            'description' => 'Cache status: 1 for enabled, 0 for disabled.'
                        ),
                        3 => array(
                            'name' => "{$cachename}Count",
                            'syntax' => 'INTEGER',
                            'description' => 'Number of files in the cache (-1 if current cluster mode not supported).'
                        ),
                        4 => array(
                            'name' => "{$cachename}Size",
                            'syntax' => 'INTEGER',
                            'description' => 'Sum of size of all files in the cache (-1 if current cluster mode not supported).'
                        ),
                    )
                );
                $i++;
            }
        }

        // build list of oids corresponding to storage dirs
        /// @todo this way of finding storage dir is lame, as it depends on them having been created
        ///       it will also not work in cluster mode, as there will be no dirs on the fs...
        $storagedir = eZSys::storageDirectory();
        $files = @scandir( $storagedir );
        $i = 1;
        $storagediroids = array();
        foreach( $files as $file )
        {
            if ( $file != '.' && $file != '..' && is_dir( $storagedir . '/' . $file ) )
            {
                self::$storagedirlist = array_merge( self::$storagedirlist, array( "2.3.$i.1" => $storagedir . '/' . $file,  "2.3.$i.2" => $storagedir . '/' . $file, "2.3.$i.3" => $storagedir . '/' . $file ) );
                $storagedirname = 'storage' . ucfirst( eZSNMPd::asncleanup( $file ) );
                $storagediroids[$i] = array(
                    'name' => $storagedirname,
                    'children' => array(
                        1 => array(
                            'name' => "{$storagedirname}Path",
                            'syntax' => 'DisplayString',
                            'description' => 'The path of this storage dir.'
                        ),
                        2 => array(
                            'name' => "{$storagedirname}Count",
                            'syntax' => 'INTEGER',
                            'description' => 'Number of files in the dir (-1 if current cluster mode not supported).'
                        ),
                        3 => array(
                            'name' => "{$storagedirname}Size",
                            'syntax' => 'INTEGER',
                            'description' => 'Sum of size of all files in the dir (-1 if current cluster mode not supported).'
                        )
                    )
                );
                $i++;
            }
        }

        return array(
            'name' => 'eZPublish',
            'children' => array(
                2 => array(
                    'name' => 'status',
                    'children' => array(
                        1 => array(
                            'name' => 'database',
                            'children' => array(
                                1 => array(
                                    'name' => 'dbstatus',
                                    'syntax' => 'INTEGER',
                                    'description' => 'Availability of the database.'
                                ),
                                2 => array(
                                    'name' => 'content',
                                    'children' => array(
                                        1 => array(
                                            'name' => 'contentObjects',
                                            'syntax' => 'INTEGER',
                                            'description' => 'The number of content objects.'
                                        ),
                                        2 => array(
                                            'name' => 'contentObjectAttributes',
                                            'syntax' => 'INTEGER',
                                            'description' => 'The number of content object attributes.'
                                        ),
                                        3 => array(
                                            'name' => 'contentObjectNodes',
                                            'syntax' => 'INTEGER',
                                            'description' => 'The number of content nodes.'
                                        ),
                                        4 => array(
                                            'name' => 'contentObjectRelations',
                                            'syntax' => 'INTEGER',
                                            'description' => 'The number of content object relations.'
                                        ),
                                        5 => array(
                                            'name' => 'contentObjectDrafts',
                                            'syntax' => 'INTEGER',
                                            'description' => 'The number of content objects in DRAFT state.'
                                        ),
                                        6 => array(
                                            'name' => 'contentObjectClasses',
                                            'syntax' => 'INTEGER',
                                            'description' => 'The number of content object classes.'
                                        ),
                                        7 => array(
                                            'name' => 'contentObjectInfoCollections',
                                            'syntax' => 'INTEGER',
                                            'description' => 'The number of information collections.'
                                        ),
                                        8 => array(
                                            'name' => 'contentObjectsPendingIndexation',
                                            'syntax' => 'INTEGER',
                                            'description' => 'The number of objects pending a search-engine indexation.'
                                        ),
                                        9 => array(
                                            'name' => 'pendingNotificationEvents',
                                            'syntax' => 'INTEGER',
                                            'description' => 'The number of pending notification events.'
                                        )
                                    )
                                ),
                                3 => array(
                                    'name' => 'users',
                                    'children' => array(
                                        1 => array(
                                            'name' => 'registeredusers',
                                            'syntax' => 'INTEGER',
                                            'description' => 'The number of existing user accounts.'
                                        )
                                    )
                                ),
                                4 => array(
                                    'name' => 'sessions',
                                    'children' => array(
                                        1 => array(
                                            'name' => 'allSessions',
                                            'syntax' => 'INTEGER',
                                            'description' => 'The number of active sessions.'
                                        ),
                                        2 => array(
                                            'name' => 'anonSessions',
                                            'syntax' => 'INTEGER',
                                            'description' => 'The number of active anonymous users sessions.'
                                        ),
                                        3 => array(
                                            'name' => 'registeredSessions',
                                            'syntax' => 'INTEGER',
                                            'description' => 'The number of active registered users sessions.'
                                        )
                                    )
                                ),
                                5 => array(
                                    'name' => 'shop',
                                    'children' => array(
                                        1 => array(
                                            'name' => 'orderStatusTable',
                                            'access' => eZMIBTree::access_not_accessible,
                                            'syntax' => 'SEQUENCE OF OrderStatusEntry',
                                            'description' => 'A table containing the number of orders per order state.',
                                            'children' => array(
                                                0 => array(
                                                    'name' => 'OrderStatusEntry',
                                                    'syntax' => 'SEQUENCE', // this makes this entry unavailable from oid array
                                                    'items' => array(
                                                        1 => array(
                                                            'name' => 'orderStatusId',
                                                            'syntax' => 'INTEGER',
                                                        ),
                                                        2 => array(
                                                            'name' => 'orderStatusName',
                                                            'syntax' => 'DisplayString',
                                                        ),
                                                        3 => array(
                                                            'name' => 'orderStatusCount',
                                                            'syntax' => 'INTEGER',
                                                        ),
                                                        4 => array(
                                                            'name' => 'orderStatusArchiveCount',
                                                            'syntax' => 'INTEGER',
                                                        )
                                                    )
                                                ),
                                                1 => array(
                                                    'name' => 'orderStatusEntry',
                                                    'access' => eZMIBTree::access_not_accessible,
                                                    'syntax' => 'OrderStatusEntry',
                                                    'description' => 'A table row describing the set of orders in status N.',
                                                    'index' => 'orderStatusId',
                                                    'children' => array(
                                                        1 => array(
                                                            'name' => 'orderStatusId',
                                                            'syntax' => 'INTEGER (1..99)',
                                                            'description' => 'ID of this order status.',
                                                            'nochildreninmib' => true,
                                                            'children' => $orderStatusIdoids
                                                        ),
                                                        2 => array(
                                                            'name' => 'orderStatusName',
                                                            'syntax' => 'DisplayString',
                                                            'description' => 'The name of this order status.',
                                                            'nochildreninmib' => true,
                                                            'children' => $orderStatusNameoids
                                                        ),
                                                        3 => array(
                                                            'name' => 'orderStatusCount',
                                                            'syntax' => 'INTEGER',
                                                            'description' => 'Number of active orders in this status.',
                                                            'nochildreninmib' => true,
                                                            'children' => $orderStatusCountoids
                                                        ),
                                                        4 => array(
                                                            'name' => 'orderStatusArchiveCount',
                                                            'syntax' => 'INTEGER',
                                                            'description' => 'Number of archived orders in this status.',
                                                            'nochildreninmib' => true,
                                                            'children' => $orderStatusArchiveCountoids
                                                        )
                                                    )
                                                )
                                            )
                                        )
                                    )
                                ),
                                6 => array(
                                    'name' => 'asyncpublishing',
                                    'children' => array(
                                        1 => array(
                                            'name' => 'AsyncPublishingWorkingCount',
                                            'syntax' => 'INTEGER',
                                            'description' => 'Number of Asynchronous Publication events in Working status',
                                        ),
                                        2 => array(
                                            'name' => 'AsyncPublishingFinishedCount',
                                            'syntax' => 'INTEGER',
                                            'description' => 'Number of Asynchronous Publication events in Finished status',
                                        ),
                                        3 => array(
                                            'name' => 'AsyncPublishingPendingCount',
                                            'syntax' => 'INTEGER',
                                            'description' => 'Number of Asynchronous Publication events in Pending status',
                                        ),
                                        4 => array(
                                            'name' => 'AsyncPublishingDeferredCount',
                                            'syntax' => 'INTEGER',
                                            'description' => 'Number of Asynchronous Publication events in Deferred status',
                                        ),
                                        5 => array(
                                            'name' => 'AsyncPublishingUnknownCount',
                                            'syntax' => 'INTEGER',
                                            'description' => 'Number of Asynchronous Publication events in Unknown status',
                                        )
                                    )
                                )
                            )
                        ),
                        2 => array(
                            'name' => 'cache',
                            'children' => $cacheoids
                        ),
                        3 => array(
                            'name' => 'storage',
                            'children' => $storagediroids
                        ),
                        4 => array(
                            'name' => 'external',
                            'children' => array(
                                1 => array(
                                    'name' => 'ldap',
                                    'syntax' => 'INTEGER',
                                    'description' => 'Connectivity to LDAP server (-1 if not configured).'
                                ),
                                2 => array(
                                    'name' => 'web',
                                    'syntax' => 'INTEGER',
                                    'description' => 'Connectivity to the web. (probes a series of webservers defined in snmpd.ini, returns -1 if not configured).'
                                ),
                                3 => array(
                                    'name' => 'email',
                                    'syntax' => 'INTEGER',
                                    'description' => 'Connectivity to mail server (NB: will send a test mail when probed to a recipient defined in snmpd.ini, returns -1 if not configured).'
                                )
                            )
                        ),
                        5 => array(
                            'name' => 'cluster',
                            'children' => array(
                                1 => array(
                                    'name' => 'clusterdbstatus',
                                    'syntax' => 'INTEGER',
                                    'description' => 'Availability of the cluster database (-1 for NA).'
                                )
                            )
                        )
                    )
                )
            )
        );
    }

}
?>