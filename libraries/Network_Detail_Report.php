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

use \clearos\apps\reports_database\Database_Report as Database_Report;

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

        $report_data['data'] = $this->_get_data('ip', $range);

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

        $ip = sprintf("%u", ip2long($ip)); // TODO: not IPv6 friendly

        $report_data = $this->_get_data_info('ips');

        $report_data['data'] = $this->_get_data_details('ip', $ip, $range);

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

        $report_data['data'] = $this->_get_data('device_type', $range);

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

        $report_data['data'] = $this->_get_data_details('device_type', $type, $range);

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

        $report_data['data'] = $this->_get_data('username', $range);

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

        $report_data['data'] = $this->_get_data_details('username', $username, $range);

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

        // Get unprocessed IPs
        //--------------------

        $sql['select'] = 'DISTINCT ip_src,ip_dst';
        $sql['from'] = 'network_detail';
        $sql['where'] = '(ip_src = \'\' OR ip_dst = \'\')';

        $unmapped_list = $this->_run_query('network_detail', $sql);

        $ips = array();

        foreach ($unmapped_list as $unmapped)
            $ips[] = empty($unmapped['ip_src']) ? $unmapped['ip_dst'] : $unmapped['ip_src'];

        $ips = array_unique($ips);

        // Update report mappings
        //-----------------------

        if (clearos_library_installed('network_map/Network_Map')) {
            clearos_load_library('network_map/Network_Map');
            $network_map = new \clearos\apps\network_map\Network_Map();
            $map = $network_map->get_mapping_by_ip();
        }

        // TODO: INET_ATON is not IPv6 friendly
        $sql = array();
        $sql['table'] = 'network_detail';

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

            $sql['set'] = "ip=INET_ATON('$ip'), username='$username', device_vendor='$vendor', device_type='$type'";
            $sql['where'] = "(ip_src='$ip' OR ip_dst='$ip') AND ip IS NULL";

            $this->_run_update('network_detail', $sql);
        }
    }

    ///////////////////////////////////////////////////////////////////////////////
    // P R I V A T E   M E T H O D S
    ///////////////////////////////////////////////////////////////////////////////

    /**
     * Report engine definition.
     *
     * @param string $item  database column
     * @param string $range range information
     *
     * @return array report definition
     */
    
    protected function _get_data($item, $range)
    {
        clearos_profile(__METHOD__, __LINE__);

        // Create temporary tables
        //------------------------

        $options['range'] = $range;

        $sql = array();
        $sql['table'] = 'upload';
        $sql['select'] = 'SUM(packets) AS packets, SUM(bytes)/1024/1024 AS size, ' . $item;
        $sql['from'] = 'network_detail';
        $sql['where'] = "ip_src != '' AND $item IS NOT NULL";
        $sql['group_by'] = $item;

        $this->_create_temporary_table('network_detail', $sql, $options);

        $sql = array();
        $sql['table'] = 'download';
        $sql['select'] = 'SUM(packets) AS packets, SUM(bytes)/1024/1024 AS size, ' . $item;
        $sql['from'] = 'network_detail';
        $sql['where'] = "ip_dst != '' AND $item IS NOT NULL";
        $sql['group_by'] = $item;

        $this->_create_temporary_table('network_detail', $sql, $options);

        // Get report data
        //----------------

        $sql = array();
        $sql['select'] = "upload.$item as $item, " .
            'upload.packets as upload_packets, upload.size as upload_size, ' .
            'download.packets as download_packets, download.size as download_size';
        $sql['from'] = 'upload';
        $sql['joins'] = "LEFT JOIN download ON upload.$item=download.$item";

        $entries = $this->_run_query('network_detail', $sql, $options);

        // Format report data
        //-------------------

        foreach ($entries as $entry) {
            $report_data[] = array(
                $entry[$item],
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
     * @param string $item  database column
     * @param string $value database value
     * @param string $range range information
     *
     * @return array report definition
     */
    
    protected function _get_data_details($item, $value, $range)
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
        $sql['from'] = 'network_detail';
        $sql['where'] = "$item='$value' AND ip_dst = ''";
        $sql['group_by'] = $group_by;

        $this->_create_temporary_table('network_detail', $sql, $create_options);

        $sql = array();
        $sql['table'] = 'download';
        $sql['select'] = 'SUM(packets) AS packets, SUM(bytes)/1024/1024 AS size, ' . $timespan;
        $sql['from'] = 'network_detail';
        $sql['where'] = "$item='$value' AND ip_src = ''";
        $sql['group_by'] = $group_by;

        $this->_create_temporary_table('network_detail', $sql, $create_options);

        // Get report data
        //----------------

        $sql = array();
        $sql['select'] = 'upload.stamp_inserted, ' .
            'upload.packets as upload_packets, upload.size as upload_size, ' .
            'download.packets as download_packets, download.size as download_size';
        $sql['from'] = 'upload';
        $sql['joins'] = 'LEFT JOIN download ON upload.stamp_inserted=download.stamp_inserted';

        $entries = $this->_run_query('network_detail', $sql);

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
            'format' => array(
                'baseline_data_points' => 10,
            ),
            'headers' => array(
                lang('network_ip'),
                lang('network_download_size'),
                lang('network_download_packets'),
                lang('network_upload_size'),
                lang('network_upload_packets')
            ),
            'types' => array(
                'ip',
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
                'string',
                'int',
                'int',
                'int',
                'int'
            ),
            'detail' => array(
                '/app/network_detail_report/device_types/index/',
                NULL,
                NULL,
                NULL 
            )
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
        // Done
        //-----

        return $reports;
    }
}
