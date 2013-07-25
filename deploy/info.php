<?php

/////////////////////////////////////////////////////////////////////////////
// General information
/////////////////////////////////////////////////////////////////////////////

$app['basename'] = 'network_detail_report';
$app['version'] = '1.0.0';
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
    'app-reports-core',
    'app-reports-database-core',
    'pmacct',
);

$app['delete_dependency'] = array(
    'app-network-device-report-core',
    'app-network-device-report',
    'pmacct',
);

