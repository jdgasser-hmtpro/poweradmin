<?php

/*  Poweradmin, a friendly web-based admin tool for PowerDNS.
 *  See <https://www.poweradmin.org> for more details.
 *
 *  Copyright 2007-2010 Rejo Zenger <rejo@zenger.nl>
 *  Copyright 2010-2025 Poweradmin Development Team
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
 * Script that record deletion from zone template
 *
 * @package     Poweradmin
 * @copyright   2007-2010 Rejo Zenger <rejo@zenger.nl>
 * @copyright   2010-2025 Poweradmin Development Team
 * @license     https://opensource.org/licenses/GPL-3.0 GPL
 */

namespace Poweradmin\Application\Controller;

use Poweradmin\BaseController;
use Poweradmin\Domain\Model\UserManager;
use Poweradmin\Domain\Model\ZoneTemplate;
use Poweradmin\Domain\Service\Validator;
use Poweradmin\Domain\Service\ZoneTemplateSyncService;

class DeleteZoneTemplRecordController extends BaseController
{

    public function run(): void
    {
        $id = $this->getSafeRequestValue('id');
        if (empty($id) || !Validator::isNumber($id)) {
            $this->showError(_('Invalid or unexpected input given.'));
        }
        $record_id = (int)$id;

        $zone_templ_id_value = $this->getSafeRequestValue('zone_templ_id');
        if (empty($zone_templ_id_value) || !Validator::isNumber($zone_templ_id_value)) {
            $this->showError(_('Invalid or unexpected input given.'));
        }
        $zone_templ_id = (int)$zone_templ_id_value;

        $confirm = "-1";
        if (isset($_GET['confirm']) && Validator::isNumber($_GET['confirm'])) {
            $confirm = $_GET['confirm'];
        }

        $owner = ZoneTemplate::getZoneTemplIsOwner($this->db, $zone_templ_id, $_SESSION['userid']);
        $perm_godlike = UserManager::verifyPermission($this->db, 'user_is_ueberuser');
        $perm_templ_edit = UserManager::verifyPermission($this->db, 'zone_templ_edit');

        $this->checkCondition(!($perm_godlike || $perm_templ_edit && $owner), _("You do not have the permission to delete this record."));

        if ($confirm == '1') {
            $zoneTemplate = new ZoneTemplate($this->db, $this->config);
            if ($zoneTemplate->deleteZoneTemplRecord($record_id)) {
                // Mark template as modified to track sync status
                $syncService = new ZoneTemplateSyncService($this->db, $this->getConfig());
                $syncService->markTemplateAsModified($zone_templ_id);

                $this->setMessage('edit_zone_templ', 'success', _('The record has been deleted successfully.'));
                $this->redirect('/zones/templates/' . $zone_templ_id . '/edit');
            } else {
                $this->setMessage('edit_zone_templ', 'error', _('The record could not be deleted.'));
                $this->redirect('/zones/templates/' . $zone_templ_id . '/edit');
            }
        }

        $templ_details = ZoneTemplate::getZoneTemplDetails($this->db, $zone_templ_id);
        $record_info = ZoneTemplate::getZoneTemplRecordFromId($this->db, $record_id);

        $this->render('delete_zone_templ_record.html', [
            'record_id' => $record_id,
            'zone_templ_id' => $zone_templ_id,
            'templ_details' => $templ_details,
            'record_info' => $record_info,
        ]);
    }
}
