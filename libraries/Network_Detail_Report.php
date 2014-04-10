<?php

/**
 * Network detail report class.
 *
 * @category   apps
 * @package    network-detail-report
 * @subpackage libraries
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2013 ClearFoundation
 * @license    http://www.gnu.org/copyleft/lgpl.html GNU Lesser General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/network_detail_report/
 */

///////////////////////////////////////////////////////////////////////////////
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU Lesser General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU Lesser General Public License for more details.
//
// You should have received a copy of the GNU Lesser General Public License
// along with this program.  If not, see <http://www.gnu.org/licenses/>.
//
///////////////////////////////////////////////////////////////////////////////

///////////////////////////////////////////////////////////////////////////////
// N A M E S P A C E
///////////////////////////////////////////////////////////////////////////////

namespace clearos\apps\network_detail_report;

///////////////////////////////////////////////////////////////////////////////
// B O O T S T R A P
///////////////////////////////////////////////////////////////////////////////

$bootstrap = getenv('CLEAROS_BOOTSTRAP') ? getenv('CLEAROS_BOOTSTRAP') : '/usr/clearos/framework/shared';
require_once $bootstrap . '/bootstrap.php';

///////////////////////////////////////////////////////////////////////////////
// T R A N S L A T I O N S
///////////////////////////////////////////////////////////////////////////////

clearos_load_language('base');
clearos_load_language('network');
clearos_load_language('network_detail_report');
clearos_load_language('reports');

///////////////////////////////////////////////////////////////////////////////
// D E P E N D E N C I E S
///////////////////////////////////////////////////////////////////////////////

use \clearos\apps\base\Daemon as Daemon;
use \clearos\apps\base\File as File;
use \clearos\apps\network\Iface_Manager as Iface_Manager;
use \clearos\apps\network\Network as Network;
use \clearos\apps\network\Network_Utils as Network_Utils;
use \clearos\apps\network\Role as Role;
use \clearos\apps\reports_database\Database_Report as Database_Report;

clearos_load_library('base/Daemon');
clearos_load_library('base/File');
clearos_load_library('network/Iface_Manager');
clearos_load_library('network/Network');
clearos_load_library('network/Network_Utils');
clearos_load_library('network/Role');
clearos_load_library('reports_database/Database_Report');

///////////////////////////////////////////////////////////////////////////////
// C L A S S
///////////////////////////////////////////////////////////////////////////////

/**
 * Network detail report class.
 *
 * @category   apps
 * @package    network-detail-report
 * @subpackage libraries
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2013 ClearFoundation
 * @license    http://www.gnu.org/copyleft/lgpl.html GNU Lesser General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/network_detail_report/
 */

class Network_Detail_Report extends Database_Report
{
    ///////////////////////////////////////////////////////////////////////////////
    // C O N S T A N T S
    ///////////////////////////////////////////////////////////////////////////////

    const DEFAULT_DB_CACHE_TIME = 1200;
    const PATH_PMACCT = '/etc/pmacct';
    const CONFIG_PREFIX = 'pmacctd_';

    ///////////////////////////////////////////////////////////////////////////////
    // M E T H O D S
    ///////////////////////////////////////////////////////////////////////////////

    /**
     * Network detail report constructor.
     */

    public function __construct()
    {
        clearos_profile(__METHOD__, __LINE__);

        $options['timestamp_field'] = 'stamp_inserted';

        parent::__construct($options);
    }

    /**
     * Automatically generates configuration.
     *
     * @return void
     * @throws Engine_Exception
     */

    public function auto_configure()
    {
        clearos_profile(__METHOD__, __LINE__);

        /**
         * We need to generate a filter (tcpdump syntax) that only captures
         * Internet traffic.  We're not interested in LAN-to-LAN, LAN-to-DMZ
         * and other types of internal traffic.  An added complication is the
         * proxy filter.  This traffic looks like traffic going to the ClearOS
         * box, but it's really Internet traffic.
         *
         * Here's an example for a 192.168.4.1 LAN IP (on 192.168.4.0/24) with
         * a second LAN interface on 10.10.10.0/24.
         *
         * (dst net 192.168.4.0/24 and src net not 10.10.10.0/24 and src net not 192.168.4.0/24) or (host 192.168.4.1 and (port 3128 or port 8080))
         */

        // Grab the reports database password
        //-----------------------------------

        $db = new Database_Report();
        $sql_password = $db->get_password();

        // Grab network info from the API
        //-------------------------------

        $iface_manager = new Iface_Manager();

        $trusted_ips = $iface_manager->get_most_trusted_ips();
        $trusted_networks = $iface_manager->get_most_trusted_networks(TRUE);
        $details = $iface_manager->get_interface_details();

        foreach ($details as $iface => $info) {
            if ($info['role'] == Role::ROLE_EXTERNAL) {

                // Write configlet
                //----------------

                $file = new File(self::PATH_PMACCT . '/' . self::CONFIG_PREFIX . $iface . '.conf');
                if ($file->exists())
                    $file->delete();

                $file->create('root', 'root', '0600');

                $body = "! This file is generated by the ClearOS API.  Please do not edit.
! debug: true 
! logfile: /tmp/pmacctd.log
interface: $iface
daemonize: true
post_tag: 1
aggregate[inbound]: src_host
aggregate[outbound]: dst_host
plugins: mysql[inbound], mysql[outbound]
sql_db: reports
sql_host: 127.0.0.1
sql_table: network_detail_external
sql_table_version: 8
sql_passwd: $sql_password
sql_user: reports
sql_refresh_time: 900
sql_optimize_clauses: true
sql_history: 10m
sql_history_roundoff: mh
sql_locking_style: row
";
                $file->add_lines($body);
            } else if (in_array($info['address'], $trusted_ips)) {
                $iface_network_address = Network_Utils::get_network_address($info['address'], $info['netmask']);
                $iface_prefix = Network_Utils::get_prefix($info['netmask']);

                $iface_network = $iface_network_address . '/' . $iface_prefix;

                // Logic for filtering out internal traffic
                //-----------------------------------------

                $inbound = '(dst net ' . $iface_network;
                $outbound = '(src net ' . $iface_network;

                foreach ($trusted_networks as $trusted_network) {
                    $inbound .= ' and src net not ' . $trusted_network;
                    $outbound .= ' and dst net not ' . $trusted_network;
                }

                // Logic for proxy
                //----------------

                $inbound .= ') or (host ' . $info['address'] . ' and (port 3128 or port 8080))';
                $outbound .= ') or (host ' . $info['address'] . ' and (port 3128 or port 8080))';

                // Write configlet
                //----------------

                $file = new File(self::PATH_PMACCT . '/' . self::CONFIG_PREFIX . $iface . '.conf');
                if ($file->exists())
                    $file->delete();

                $file->create('root', 'root', '0600');

                $body = "! This file is generated by the ClearOS API.  Please do not edit.
! debug: true 
! logfile: /tmp/pmacctd.log
interface: $iface
daemonize: true
post_tag: 1 
aggregate[inbound]: dst_host
aggregate[outbound]: src_host
aggregate_filter[inbound]: $inbound
aggregate_filter[outbound]: $outbound
plugins: mysql[inbound], mysql[outbound]
sql_db: reports
sql_host: 127.0.0.1
sql_table: network_detail
sql_table_version: 8
sql_passwd: $sql_password
sql_user: reports
sql_refresh_time: 900
sql_optimize_clauses: true
sql_history: 10m 
sql_history_roundoff: mh
sql_locking_style: row
";
                $file->add_lines($body);
            }
        }
    }

    /**
     * Returns external IP summary data.
     *
     * @param string $range range information
     *
     * @return array IP summary data
     * @throws Engine_Exception
     */

    public function get_external_ip_data($range = 'today')
    {
        clearos_profile(__METHOD__, __LINE__);

        $report_data = $this->_get_data_info('top_external_ips');

        $report_data['data'] = $this->_get_data('network_detail_external', 'ip', $range);

        return $report_data;
    }

    /**
     * Returns external IP details data.
     *
     * @param string $ip    IP address
     * @param string $range range information
     *
     * @return array IP details data
     * @throws Engine_Exception
     */

    public function get_external_ip_details_data($ip, $range = 'today')
    {
        clearos_profile(__METHOD__, __LINE__);

        $ip = inet_pton($ip);

        $report_data = $this->_get_data_info('external_ips');

        $report_data['data'] = $this->_get_data_details('network_detail_external', 'ip', $ip, $range);

        return $report_data;
    }

    /**
     * Returns IP summary data.
     *
     * @param string $range range information
     *
     * @return array IP summary data
     * @throws Engine_Exception
     */

    public function get_ip_data($range = 'today')
    {
        clearos_profile(__METHOD__, __LINE__);

        $report_data = $this->_get_data_info('top_ips');

        $report_data['data'] = $this->_get_data('network_detail', 'ip', $range);

        return $report_data;
    }

    /**
     * Returns IP details data.
     *
     * @param string $ip    IP address
     * @param string $range range information
     *
     * @return array IP details data
     * @throws Engine_Exception
     */

    public function get_ip_details_data($ip, $range = 'today')
    {
        clearos_profile(__METHOD__, __LINE__);

        $ip = inet_pton($ip);

        $report_data = $this->_get_data_info('ips');

        $report_data['data'] = $this->_get_data_details('network_detail', 'ip', $ip, $range);

        return $report_data;
    }

    /**
     * Returns device type summary data.
     *
     * @param string $range range information
     *
     * @return array device type summary data
     * @throws Engine_Exception
     */

    public function get_device_type_data($range = 'today')
    {
        clearos_profile(__METHOD__, __LINE__);

        $report_data = $this->_get_data_info('top_device_types');

        $report_data['data'] = $this->_get_data('network_detail', 'device_type', $range);

        return $report_data;
    }

    /**
     * Returns device type details data.
     *
     * @param string $type  device type
     * @param string $range range information
     *
     * @return array username details data
     * @throws Engine_Exception
     */

    public function get_device_type_details_data($type, $range = 'today')
    {
        clearos_profile(__METHOD__, __LINE__);

        $report_data = $this->_get_data_info('device_types');

        $report_data['data'] = $this->_get_data_details('network_detail', 'device_type', $type, $range);

        return $report_data;
    }


    /**
     * Returns username summary data.
     *
     * @param string $range range information
     *
     * @return array username summary data
     * @throws Engine_Exception
     */

    public function get_user_data($range = 'today')
    {
        clearos_profile(__METHOD__, __LINE__);

        $report_data = $this->_get_data_info('top_users');

        $report_data['data'] = $this->_get_data('network_detail', 'username', $range);

        return $report_data;
    }

    /**
     * Returns username details data.
     *
     * @param string $username IP address
     * @param string $range    range information
     *
     * @return array username details data
     * @throws Engine_Exception
     */

    public function get_user_details_data($username, $range = 'today')
    {
        clearos_profile(__METHOD__, __LINE__);

        $report_data = $this->_get_data_info('users');

        $report_data['data'] = $this->_get_data_details('network_detail', 'username', $username, $range);

        return $report_data;
    }

    /**
     * Updates network detail data with usernames and other data.
     *
     * @return void
     * @throws Engine_Exception
     */

    public function update_mappings()
    {
        clearos_profile(__METHOD__, __LINE__);

        // Initialize
        //-----------

        $this->_initialize_tables('network_detail_report', 'network_detail');

        // Check pmacct
        //-------------

        $daemon = new Daemon('pmacctd');
        $is_running = $daemon->get_running_state();

        if (! $is_running) {
            $on_boot = $daemon->get_boot_state();
            if ($on_boot) {
                $this->auto_configure();
                $daemon->set_running_state(TRUE);
            }
        }

        // Get Network Map data
        //---------------------

        $map = array();

        if (clearos_library_installed('network_map/Network_Map')) {
            clearos_load_library('network_map/Network_Map');
            $network_map = new \clearos\apps\network_map\Network_Map();
            $map = $network_map->get_mapping_by_ip();
        }

        // Get unprocessed IPs
        //--------------------

        $tables = array('network_detail', 'network_detail_external');

        foreach ($tables as $table) { 
            $sql['select'] = 'DISTINCT ip_src,ip_dst';
            $sql['from'] = $table;
            $sql['where'] = '(ip_src = \'\' OR ip_dst = \'\')';

            $unmapped_list = $this->_run_query('network_detail', $sql);

            $ips = array();

            foreach ($unmapped_list as $unmapped)
                $ips[] = empty($unmapped['ip_src']) ? $unmapped['ip_dst'] : $unmapped['ip_src'];

            $ips = array_unique($ips);

            $sql = array();
            $sql['table'] = $table;

            foreach ($ips as $ip) {
                if (array_key_exists($ip, $map)) {
                    $username = $map[$ip]['username'];
                    $type = $map[$ip]['type'];
                    $vendor = $map[$ip]['vendor'];
                } else {
                    $username = '';
                    $type = '';
                    $vendor = '';
                }

                $hostname = gethostbyaddr($ip);

                $ip_bin = inet_pton($ip);

                $sql['set'] = "ip='$ip_bin', hostname='$hostname', username='$username', device_vendor='$vendor', device_type='$type'";
                $sql['where'] = "(ip_src='$ip' OR ip_dst='$ip') AND ip IS NULL";

                $this->_run_update('network_detail', $sql);
            }
        }
    }

    ///////////////////////////////////////////////////////////////////////////////
    // P R I V A T E   M E T H O D S
    ///////////////////////////////////////////////////////////////////////////////

    /**
     * Report engine definition.
     *
     * @param string $table database table
     * @param string $item  database column
     * @param string $range range information
     *
     * @return array report definition
     */
    
    protected function _get_data($table, $item, $range)
    {
        clearos_profile(__METHOD__, __LINE__);

        // Create temporary tables
        //------------------------

        $options['range'] = $range;

        $sql = array();
        $sql['table'] = 'upload';
        $sql['select'] = 'SUM(packets) AS packets, SUM(bytes)/1024/1024 AS size, MAX(hostname) as hostname, ' . $item;
        $sql['from'] = $table;
        $sql['where'] = "ip_src != '' AND $item IS NOT NULL";
        $sql['group_by'] = $item;

        $this->_create_temporary_table('network_detail', $sql, $options);

        $sql = array();
        $sql['table'] = 'download';
        $sql['select'] = 'SUM(packets) AS packets, SUM(bytes)/1024/1024 AS size, ' . $item;
        $sql['from'] = $table;
        $sql['where'] = "ip_dst != '' AND $item IS NOT NULL";
        $sql['group_by'] = $item;

        $this->_create_temporary_table('network_detail', $sql, $options);

        // Get report data
        //----------------

        // SQL queries are cached, so we need a unique ID for the cache.
        $options['cache_id'] = $table;

        $sql = array();
        $sql['select'] = "upload.$item as $item, upload.hostname as hostname, " .
            'upload.packets as upload_packets, upload.size as upload_size, ' .
            'download.packets as download_packets, download.size as download_size ';
        $sql['from'] = 'upload';
        $sql['order_by'] = 'download_size DESC';
        $sql['joins'] = "LEFT JOIN download ON upload.$item=download.$item";

        $entries = $this->_run_query('network_detail', $sql, $options);

        // Format report data
        //-------------------

        foreach ($entries as $entry) {
            if ($item === 'ip') {
                $report_data[] = array(
                    inet_ntop($entry[$item]),
                    $entry['hostname'],
                    (int) $entry['download_size'],
                    (int) $entry['download_packets'],
                    (int) $entry['upload_size'],
                    (int) $entry['upload_packets']
                );
            } else {
                $item_entry =
                $report_data[] = array(
                    $entry[$item],
                    (int) $entry['download_size'],
                    (int) $entry['download_packets'],
                    (int) $entry['upload_size'],
                    (int) $entry['upload_packets']
                );
            }
        }

        return $report_data;
    }

    /**
     * Report engine definition.
     *
     * @param string $table database table
     * @param string $item  database column
     * @param string $value database value
     * @param string $range range information
     *
     * @return array report definition
     */
    
    protected function _get_data_details($table, $item, $value, $range)
    {
        clearos_profile(__METHOD__, __LINE__);

        // Create temporary tables
        //------------------------

        if (($range === 'today') || ($range === 'yesterday')) {
            $timespan = 'DATE_FORMAT(stamp_inserted, \'%Y-%m-%d %H:00:00\') as stamp_inserted ';
            $group_by = 'DATE(stamp_inserted), HOUR(stamp_inserted)';
        } else {
            $timespan = 'DATE_FORMAT(stamp_inserted, \'%Y-%m-%d\') as stamp_inserted ';
            $group_by = 'DATE(stamp_inserted)';
        }

        $create_options['range'] = $range;

        $sql['table'] = 'upload';
        $sql['select'] = 'SUM(packets) AS packets, SUM(bytes)/1024/1024 AS size, ' . $timespan;
        $sql['from'] = $table;
        $sql['where'] = "$item='$value' AND ip_dst = ''";
        $sql['group_by'] = $group_by;

        $this->_create_temporary_table('network_detail', $sql, $create_options);

        $sql = array();
        $sql['table'] = 'download';
        $sql['select'] = 'SUM(packets) AS packets, SUM(bytes)/1024/1024 AS size, ' . $timespan;
        $sql['from'] = $table;
        $sql['where'] = "$item='$value' AND ip_src = ''";
        $sql['group_by'] = $group_by;

        $this->_create_temporary_table('network_detail', $sql, $create_options);

        // Get report data
        //----------------

        $options['cache_id'] = $table . $item . $value;

        $sql = array();
        $sql['select'] = 'upload.stamp_inserted, ' .
            'upload.packets as upload_packets, upload.size as upload_size, ' .
            'download.packets as download_packets, download.size as download_size';
        $sql['from'] = 'upload';
        $sql['joins'] = 'LEFT JOIN download ON upload.stamp_inserted=download.stamp_inserted';

        $entries = $this->_run_query('network_detail', $sql, $options);

        // Format report data
        //-------------------

        foreach ($entries as $entry) {
            $report_data[] = array(
                $entry['stamp_inserted'],
                (int) $entry['download_size'],
                (int) $entry['download_packets'],
                (int) $entry['upload_size'],
                (int) $entry['upload_packets']
            );
        }

        return $report_data;
    }

    /**
     * Report engine definition.
     *
     * @return array report definition
     */
    
    protected function _get_definition()
    {
        clearos_profile(__METHOD__, __LINE__);

        $network = new Network();

        $mode = $network->get_mode();

        if (!(($mode === Network::MODE_STANDALONE) || ($mode === Network::MODE_TRUSTED_STANDALONE))) {

            // Top users
            //----------
    
            $reports['top_users'] = array(
                'app' => 'network_detail_report',
                'title' => lang('reports_top_users'),
                'api_data' => 'get_user_data',
                'chart_type' => 'pie',
                'format' => array(
                    'baseline_data_points' => 10,
                ),
                'headers' => array(
                    lang('base_username'),
                    lang('network_download_size'),
                    lang('network_download_packets'),
                    lang('network_upload_size'),
                    lang('network_upload_packets')
                ),
                'types' => array(
                    'string',
                    'int',
                    'int',
                    'int',
                    'int'
                ),
                'detail' => array(
                    '/app/network_detail_report/users/index/',
                    NULL,
                    NULL,
                    NULL 
                )
            );
    
            // Top IPs
            //--------
    
            $reports['top_ips'] = array(
                'app' => 'network_detail_report',
                'title' => lang('reports_top_ips'),
                'api_data' => 'get_ip_data',
                'chart_type' => 'pie',
                'series_highlight' => 2,
                'series_sort' => 'desc',
                'format' => array(
                    'baseline_data_points' => 10,
                ),
                'headers' => array(
                    lang('network_ip'),
                    lang('network_hostname'),
                    lang('network_download_size'),
                    lang('network_download_packets'),
                    lang('network_upload_size'),
                    lang('network_upload_packets')
                ),
                'types' => array(
                    'ipv6',
                    'string',
                    'int',
                    'int',
                    'int',
                    'int'
                ),
                'detail' => array(
                    '/app/network_detail_report/ips/index/',
                    NULL,
                    NULL,
                    NULL 
                ),
            );
    
            // Top device types
            //-----------------
    
            $reports['top_device_types'] = array(
                'app' => 'network_detail_report',
                'title' => lang('reports_top_device_types'),
                'api_data' => 'get_device_type_data',
                'chart_type' => 'pie',
                'format' => array(
                    'baseline_data_points' => 10,
                ),
                'headers' => array(
                    lang('base_username'),
                    lang('network_download_size'),
                    lang('network_download_packets'),
                    lang('network_upload_size'),
                    lang('network_upload_packets')
                ),
                'types' => array(
                    'ipv6',
                    'int',
                    'int',
                    'int',
                    'int'
                ),
                'detail' => array(
                    '/app/network_detail_report/device_types/index/',
                    NULL,
                    NULL,
                    NULL,
                    NULL,
                    NULL 
                )
            );
        }

        // Top external IPs
        //-----------------

        $reports['top_external_ips'] = array(
            'app' => 'network_detail_report',
            'title' => lang('reports_top_external_ips'),
            'api_data' => 'get_external_ip_data',
            'chart_type' => 'pie',
            'series_highlight' => 2,
            'series_sort' => 'desc',
            'format' => array(
                'baseline_data_points' => 10,
            ),
            'headers' => array(
                lang('network_ip'),
                lang('network_hostname'),
                lang('network_download_size'),
                lang('network_download_packets'),
                lang('network_upload_size'),
                lang('network_upload_packets')
            ),
            'types' => array(
                'ipv6',
                'string',
                'int',
                'int',
                'int',
                'int'
            ),
            'detail' => array(
                '/app/network_detail_report/external_ips/index/',
                NULL,
                NULL,
                NULL 
            ),
        );

        // User detail
        //------------

        $reports['users'] = array(
            'app' => 'network_detail_report',
            'title' => lang('reports_user_summary'),
            'api_data' => 'get_user_details_data',
            'chart_type' => 'bar',
            'is_detail' => TRUE,
            'format' => array(
                'baseline_data_points' => 10,
            ),
            'headers' => array(
                lang('base_date'),
                lang('network_download_size'),
                lang('network_download_packets'),
                lang('network_upload_size'),
                lang('network_upload_packets')
            ),
            'types' => array(
                'timestamp',
                'int',
                'int',
                'int',
                'int'
            ),
            'chart_series' => array(
                NULL,
                TRUE,
                FALSE,
                FALSE,
                FALSE
            ),
        );

        // IP detail
        //----------

        $reports['ips'] = array(
            'app' => 'network_detail_report',
            'title' => lang('reports_ip_summary'),
            'api_data' => 'get_ip_details_data',
            'chart_type' => 'bar',
            'is_detail' => TRUE,
            'format' => array(
                'baseline_data_points' => 10,
            ),
            'headers' => array(
                lang('base_date'),
                lang('network_download_size'),
                lang('network_download_packets'),
                lang('network_upload_size'),
                lang('network_upload_packets')
            ),
            'types' => array(
                'timestamp',
                'int',
                'int',
                'int',
                'int'
            ),
            'chart_series' => array(
                NULL,
                TRUE,
                FALSE,
                FALSE,
                FALSE
            ),
        );

        // Device type detail
        //-------------------

        $reports['device_types'] = array(
            'app' => 'network_detail_report',
            'title' => lang('reports_device_type_summary'),
            'api_data' => 'get_device_type_details_data',
            'chart_type' => 'bar',
            'is_detail' => TRUE,
            'format' => array(
                'baseline_data_points' => 10,
            ),
            'headers' => array(
                lang('base_date'),
                lang('network_download_size'),
                lang('network_download_packets'),
                lang('network_upload_size'),
                lang('network_upload_packets')
            ),
            'types' => array(
                'timestamp',
                'int',
                'int',
                'int',
                'int'
            ),
            'chart_series' => array(
                NULL,
                TRUE,
                FALSE,
                FALSE,
                FALSE
            ),
        );

        // External IP detail
        //-------------------

        $reports['external_ips'] = array(
            'app' => 'network_detail_report',
            'title' => lang('reports_ip_summary'),
            'api_data' => 'get_external_ip_details_data',
            'chart_type' => 'bar',
            'is_detail' => TRUE,
            'format' => array(
                'baseline_data_points' => 10,
            ),
            'headers' => array(
                lang('base_date'),
                lang('network_download_size'),
                lang('network_download_packets'),
                lang('network_upload_size'),
                lang('network_upload_packets')
            ),
            'types' => array(
                'timestamp',
                'int',
                'int',
                'int',
                'int'
            ),
            'chart_series' => array(
                NULL,
                TRUE,
                FALSE,
                FALSE,
                FALSE
            ),
        );

        // Done
        //-----

        return $reports;
    }
}
