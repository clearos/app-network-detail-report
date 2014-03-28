<?php

/////////////////////////////////////////////////////////////////////////////
// General information
/////////////////////////////////////////////////////////////////////////////

$app['basename'] = 'network_detail_report';
$app['version'] = '1.5.23';
$app['release'] = '1';
$app['vendor'] = 'ClearCenter';
$app['packager'] = 'ClearCenter';
$app['license'] = 'Proprietary';
$app['license_core'] = 'Proprietary';
$app['description'] = lang('network_detail_report_app_description');

/////////////////////////////////////////////////////////////////////////////
// App name and categories
/////////////////////////////////////////////////////////////////////////////

$app['name'] = lang('network_detail_report_app_name');
$app['category'] = lang('base_category_reports');
$app['subcategory'] = lang('base_category_network');

/////////////////////////////////////////////////////////////////////////////
// Packaging
/////////////////////////////////////////////////////////////////////////////

$app['requires'] = array(
    'app-network',
    'app-groups',
);  

$app['core_requires'] = array(
    'app-network-core >= 1:1.4.70',
    'app-reports-core >= 1:1.4.70',
    'app-reports-database-core >= 1:1.4.70',
    'pmacct',
);


$app['core_file_manifest'] = array(
    'pmacctd.php'=> array('target' => '/var/clearos/base/daemon/pmacctd.php'),
    'app-network-detail-report.cron' => array( 'target' => '/etc/cron.d/app-network-detail-report'),
    'networkdetail2db' => array(
        'target' => '/usr/sbin/networkdetail2db',
        'mode' => '0755',
    ),
    'networkdetailpurge' => array(
        'target' => '/usr/sbin/networkdetailpurge',
        'mode' => '0755',
    ),
    'network-configuration-event'=> array(
        'target' => '/var/clearos/events/network_configuration/network_detail_report',
        'mode' => '0755'
    ),
);

$app['delete_dependency'] = array(
    'app-network-device-report-core',
    'app-network-device-report',
    'pmacct',
);
