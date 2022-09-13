<?php

/*  Poweradmin, a friendly web-based admin tool for PowerDNS.
 *  See <https://www.poweradmin.org> for more details.
 *
 *  Copyright 2007-2010  Rejo Zenger <rejo@zenger.nl>
 *  Copyright 2010-2022  Poweradmin Development Team
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * Script that handles requests to edit zone records
 *
 * @package     Poweradmin
 * @copyright   2007-2010 Rejo Zenger <rejo@zenger.nl>
 * @copyright   2010-2022  Poweradmin Development Team
 * @license     https://opensource.org/licenses/GPL-3.0 GPL
 */

use Poweradmin\AppFactory;
use Poweradmin\DnsRecord;
use Poweradmin\Dnssec;
use Poweradmin\RecordType;
use Poweradmin\Syslog;

require_once 'inc/toolkit.inc.php';
require_once 'inc/message.inc.php';

include_once 'inc/header.inc.php';

$app = AppFactory::create();
$pdnssec_use = $app->config('pdnssec_use');

if (do_hook('verify_permission', 'zone_content_view_others')) {
    $perm_view = "all";
} elseif (do_hook('verify_permission', 'zone_content_view_own')) {
    $perm_view = "own";
} else {
    $perm_view = "none";
}

if (do_hook('verify_permission', 'zone_content_edit_others')) {
    $perm_content_edit = "all";
} elseif (do_hook('verify_permission', 'zone_content_edit_own')) {
    $perm_content_edit = "own";
} elseif (do_hook('verify_permission', 'zone_content_edit_own_as_client')) {
    $perm_content_edit = "own_as_client";
} else {
    $perm_content_edit = "none";
}

if (do_hook('verify_permission', 'zone_meta_edit_others')) {
    $perm_meta_edit = "all";
} elseif (do_hook('verify_permission', 'zone_meta_edit_own')) {
    $perm_meta_edit = "own";
} else {
    $perm_meta_edit = "none";
}

 $zid = DnsRecord::get_zone_id_from_record_id($_GET['id']);

$user_is_zone_owner = do_hook('verify_user_is_owner_zoneid' , $zid );
$zone_type = DnsRecord::get_domain_type($zid);
$zone_name = DnsRecord::get_domain_name_by_id($zid);

if (isset($_POST["commit"])) {
    if ($zone_type == "SLAVE" || $perm_content_edit == "none" || ($perm_content_edit == "own" || $perm_content_edit == "own_as_client") && $user_is_zone_owner == "0") {
        error(ERR_PERM_EDIT_RECORD);
    } else {
        $old_record_info = DnsRecord::get_record_from_id($_POST["rid"]);
        $ret_val = DnsRecord::edit_record($_POST);
        if ($ret_val == "1") {
            if ($_POST['type'] != "SOA") {
                DnsRecord::update_soa_serial($zid);
            }
            success(SUC_RECORD_UPD);
            $new_record_info = DnsRecord::get_record_from_id($_POST["rid"]);
            Syslog::log_info(sprintf('client_ip:%s user:%s operation:edit_record'
                             .' old_record_type:%s old_record:%s old_content:%s old_ttl:%s old_priority:%s'
                             .' record_type:%s record:%s content:%s ttl:%s priority:%s',
                              $_SERVER['REMOTE_ADDR'], $_SESSION["userlogin"],
                              $old_record_info['type'], $old_record_info['name'], $old_record_info['content'], $old_record_info['ttl'], $old_record_info['prio'],
                              $new_record_info['type'], $new_record_info['name'], $new_record_info['content'], $new_record_info['ttl'], $new_record_info['prio']),
                              $zid);

            if ($pdnssec_use && Dnssec::dnssec_rectify_zone($zid)) {
                success(SUC_EXEC_PDNSSEC_RECTIFY_ZONE);
            }
        }
    }
}

echo "    <h5 class=\"mb-3\">" . _('Edit record in zone') . "</h5>\n";

if ($perm_view == "none" || $perm_view == "own" && $user_is_zone_owner == "0") {
    error(ERR_PERM_VIEW_RECORD);
} else {
    $record = DnsRecord::get_record_from_id($_GET["id"]);
    echo "     <form method=\"post\" action=\"edit_record.php?domain=" . $zid . "&amp;id=" . $_GET["id"] . "\">\n";
    echo "      <table class=\"table table-striped table-hover table-sm\">\n";
    echo "      <thead>";
    echo "       <tr>\n";
    echo "        <th>" . _('Name') . "</th>\n";
    echo "        <th>&nbsp;</th>\n";
    echo "        <th>" . _('Type') . "</th>\n";
    echo "        <th>" . _('Content') . "</th>\n";
    echo "        <th>" . _('Priority') . "</th>\n";
    echo "        <th>" . _('TTL') . "</th>\n";
    echo "       </tr>\n";
    echo "      </thead>";

    if ($zone_type == "SLAVE" || $perm_content_edit == "none" || ($perm_content_edit == "own" || $perm_content_edit == "own_as_client") && $user_is_zone_owner == "0") {
        echo "      <tr>\n";
        echo "       <td>" . $record["name"] . "</td>\n";
        echo "       <td>IN</td>\n";
        echo "       <td>" . htmlspecialchars($record["type"]) . "</td>\n";
        echo "       <td>" . htmlspecialchars($record['content']) . "</td>\n";
        echo "       <td>" . htmlspecialchars($record["prio"]) . "</td>\n";
        echo "       <td>" . htmlspecialchars($record["ttl"]) . "</td>\n";
        echo "      </tr>\n";
    } else {
        echo "      <tr>\n";
        echo "       <td><input type=\"hidden\" name=\"rid\" value=\"" . $_GET["id"] . "\">\n";
        echo "       <input type=\"hidden\" name=\"zid\" value=\"" . $zid . "\">\n";
        echo "       <input class=\"form-control form-control-sm\" type=\"text\" name=\"name\" value=\"" . htmlspecialchars(trim(str_replace($zone_name, '', $record["name"]), '.')) . "\">." . $zone_name . "</td>\n";
        echo "       <td>IN</td>\n";
        echo "       <td>\n";
        echo "        <select class=\"form-select form-select-sm\" name=\"type\">\n";
        $found_selected_type = false;
        foreach (RecordType::getTypes() as $type_available) {
            if ($type_available == $record["type"]) {
                $add = " SELECTED";
                $found_selected_type = true;
            } else {
                $add = "";
            }
            echo "         <option" . $add . " value=\"" . htmlspecialchars($type_available) . "\" >" . $type_available . "</option>\n";
        }
        if (!$found_selected_type)
            echo "         <option SELECTED value=\"" . htmlspecialchars($record['type']) . "\"><i>" . $record['type'] . "</i></option>\n";
        echo "        </select>\n";
        echo "       </td>\n";
        echo "       <td><input class=\"form-control form-control-sm\" type=\"text\" name=\"content\" value=\"" . htmlspecialchars($record['content']) . "\"></td>\n";
        echo "       <td><input class=\"form-control form-control-sm\" type=\"text\" name=\"prio\" value=\"" . htmlspecialchars($record["prio"]) . "\"></td>\n";
        echo "       <td><input class=\"form-control form-control-sm\" type=\"text\" name=\"ttl\" value=\"" . htmlspecialchars($record["ttl"]) . "\"></td>\n";
        echo "      </tr>\n";
    }
    echo "      </table>\n";
    echo "       <input class=\"btn btn-primary btn-sm\" type=\"submit\" name=\"commit\" value=\"" . _('Commit changes') . "\">&nbsp;&nbsp;\n";
    echo "       <input class=\"btn btn-secondary btn-sm\" type=\"reset\" name=\"reset\" value=\"" . _('Reset changes') . "\">&nbsp;&nbsp;\n";
    echo "     </form>\n";
}


include_once("inc/footer.inc.php");
