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

namespace Poweradmin\Infrastructure\Logger;

use PDO;
use Poweradmin\Domain\Model\UserEntity;
use Poweradmin\Infrastructure\Database\PDOCommon;

class DbUserLogger
{
    private PDOCommon $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function doLog($msg, $priority): void
    {
        $stmt = $this->db->prepare('INSERT INTO log_users (event, priority) VALUES (:msg, :priority)');
        $stmt->execute([
            ':msg' => $msg,
            ':priority' => $priority,
        ]);
    }

    public function countAllLogs()
    {
        $stmt = $this->db->query("SELECT count(*) AS number_of_logs FROM log_users");
        return $stmt->fetch()['number_of_logs'];
    }

    public function countLogsByUser($user)
    {
        $stmt = $this->db->prepare("
                    SELECT count(log_users.id) as number_of_logs
                    FROM log_users
                    WHERE log_users.event LIKE :search_by
        ");
        $name = "%'$user'%";
        $stmt->execute(['search_by' => $name]);
        return $stmt->fetch()['number_of_logs'];
    }

    public function getAllLogs($limit, $offset): array
    {
        $stmt = $this->db->prepare("
                    SELECT * FROM log_users
                    ORDER BY created_at DESC
                    LIMIT :limit
                    OFFSET :offset
        ");

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getLogsForUser($user, $limit, $offset): array
    {
        if (!(UserEntity::exists($this->db, $user))) {
            return array();
        }

        $stmt = $this->db->prepare("
            SELECT * FROM log_users
            WHERE log_users.event LIKE :search_by
            ORDER BY created_at DESC
            LIMIT :limit
            OFFSET :offset");

        $user = "%'$user'%";
        $stmt->bindValue(':search_by', $user, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
