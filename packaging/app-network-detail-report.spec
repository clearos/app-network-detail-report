
Name: app-network-detail-report
Epoch: 1
Version: 1.5.26
Release: 1%{dist}
Summary: Network Detail Report
License: Proprietary
Group: ClearOS/Apps
Source: %{name}-%{version}.tar.gz
Buildarch: noarch
Requires: %{name}-core = 1:%{version}-%{release}
Requires: app-base
Requires: app-network
Requires: app-groups

%description
The Network Detail Report provides size and packet information.

%package core
Summary: Network Detail Report - Core
License: Proprietary
Group: ClearOS/Libraries
Requires: app-base-core
Requires: app-network-core >= 1:1.4.70
Requires: app-reports-core >= 1:1.4.70
Requires: app-reports-database-core >= 1:1.4.70
Requires: pmacct

%description core
The Network Detail Report provides size and packet information.

This package provides the core API and libraries.

%prep
%setup -q
%build

%install
mkdir -p -m 755 %{buildroot}/usr/clearos/apps/network_detail_report
cp -r * %{buildroot}/usr/clearos/apps/network_detail_report/

install -D -m 0644 packaging/app-network-detail-report.cron %{buildroot}/etc/cron.d/app-network-detail-report
install -D -m 0755 packaging/network-configuration-event %{buildroot}/var/clearos/events/network_configuration/network_detail_report
install -D -m 0755 packaging/networkdetail2db %{buildroot}/usr/sbin/networkdetail2db
install -D -m 0755 packaging/networkdetailpurge %{buildroot}/usr/sbin/networkdetailpurge
install -D -m 0644 packaging/pmacctd.php %{buildroot}/var/clearos/base/daemon/pmacctd.php

%post
logger -p local6.notice -t installer 'app-network-detail-report - installing'

%post core
logger -p local6.notice -t installer 'app-network-detail-report-core - installing'

if [ $1 -eq 1 ]; then
    [ -x /usr/clearos/apps/network_detail_report/deploy/install ] && /usr/clearos/apps/network_detail_report/deploy/install
fi

[ -x /usr/clearos/apps/network_detail_report/deploy/upgrade ] && /usr/clearos/apps/network_detail_report/deploy/upgrade

exit 0

%preun
if [ $1 -eq 0 ]; then
    logger -p local6.notice -t installer 'app-network-detail-report - uninstalling'
fi

%preun core
if [ $1 -eq 0 ]; then
    logger -p local6.notice -t installer 'app-network-detail-report-core - uninstalling'
    [ -x /usr/clearos/apps/network_detail_report/deploy/uninstall ] && /usr/clearos/apps/network_detail_report/deploy/uninstall
fi

exit 0

%files
%defattr(-,root,root)
/usr/clearos/apps/network_detail_report/controllers
/usr/clearos/apps/network_detail_report/htdocs

%files core
%defattr(-,root,root)
%exclude /usr/clearos/apps/network_detail_report/packaging
%dir /usr/clearos/apps/network_detail_report
/usr/clearos/apps/network_detail_report/deploy
/usr/clearos/apps/network_detail_report/language
/usr/clearos/apps/network_detail_report/libraries
/etc/cron.d/app-network-detail-report
/var/clearos/events/network_configuration/network_detail_report
/usr/sbin/networkdetail2db
/usr/sbin/networkdetailpurge
/var/clearos/base/daemon/pmacctd.php
