# Poweradmin [![Gitter](https://badges.gitter.im/poweradmin/poweradmin.svg)](https://gitter.im/poweradmin/poweradmin?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge)

[Poweradmin](https://www.poweradmin.org) is a friendly web-based DNS administration tool for Bert Hubert's PowerDNS server. The interface has full support for most of the features of PowerDNS. It has full support for all zone types (master,  native and  slave), for  supermasters for automatic provisioning of slave zones, full support for IPv6 and comes with multi-language support.

## Requirements
* PHP 7.2.5+
* PHP intl extension
* PHP gettext extension
* PHP openssl extension
* PHP pdo extension
* PHP pdo-mysql or pdo-pgsql extension
* PHP ldap extension (optional)
* MySQL/MariaDB, PostgreSQL or SQLite database
* PowerDNS authoritative server 4.0.0+

## Installation
Install the following dependencies:

On Debian based Systems:
```sh
apt install php-intl

For MySQL/MariaDB
apt install php-mysqlnd

For PostgreSQL
apt install php-pgsql

For SQLite
apt install php-sqlite3
```

On RHEL based Systems:
```sh
yum install -y php-intl

For MySQL/MariaDB
yum install -y php-mysqlnd

For PostgreSQL
yum install -y php-pgsql
```

Download the project files
* Via Git:
  * Clone the repository: ```git clone https://github.com/poweradmin/poweradmin.git```
  * Select latest tag (for example v2.2.2) or skip this if you want to run from master: ```git checkout tags/v2.2.2``` 
* Via releases:
  * Get the latest file from [releases](https://github.com/poweradmin/poweradmin/releases)

Go to the installed system in your browser
* Visit http(s)://URL/install/ and follow the installation steps
* Once the installation is complete, remove the `install` folder
* Point your browser to: http(s)://URL
* Log in using the credentials created during setup

## Screenshots
### Log in
![image](https://user-images.githubusercontent.com/30780133/175272317-1df9d943-c324-48da-a719-5c26de79ef03.png)
### Zone list
![image](https://user-images.githubusercontent.com/30780133/175272906-e1404629-c5e2-4d80-b3a6-7710456d3f33.png)
