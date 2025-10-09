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

namespace Poweradmin\Domain\Repository;

use Poweradmin\Domain\Model\Constants;

/**
 * Interface for DNS record repository operations
 */
interface RecordRepositoryInterface
{
    /**
     * Get records by domain ID
     *
     * @param int $domainId Domain ID
     * @param string|null $recordType Optional record type filter
     * @return array Array of records
     */
    public function getRecordsByDomainId(int $domainId, ?string $recordType = null): array;

    /**
     * Get a record by ID
     *
     * @param int $recordId Record ID
     * @return array|null Record data if found, null otherwise
     */
    public function getRecordById(int $recordId): ?array;

    /**
     * Get Zone ID from Record ID
     *
     * @param int $rid Record ID
     *
     * @return int Zone ID
     */
    public function getZoneIdFromRecordId(int $rid): int;

    /**
     * Count Zone Records for Zone ID
     *
     * @param int $zone_id Zone ID
     *
     * @return int Record count
     */
    public function countZoneRecords(int $zone_id): int;

    /**
     * Get record details from Record ID
     *
     * @param int $rid Record ID
     *
     * @return array array of record details [rid,zid,name,type,content,ttl,prio]
     */
    public function getRecordDetailsFromRecordId(int $rid): array;

    /**
     * Get a Record from a Record ID
     *
     * Retrieve all fields of the record and send it back to the function caller.
     *
     * @param int $id Record ID
     * @return array|null array of record detail, or null if nothing found
     */
    public function getRecordFromId(int $id): ?array;

    /**
     * Get all records from a domain id.
     *
     * Retrieve all fields of the records and send it back to the function caller.
     *
     * @param string $db_type Database type
     * @param int $id Domain ID
     * @param int $rowstart Starting row [default=0]
     * @param int $rowamount Number of rows to return in this query [default=9999]
     * @param string $sortby Column to sort by [default='name']
     * @param string $sortDirection Sort direction [default='ASC']
     * @param bool $fetchComments Whether to fetch record comments [default=false]
     *
     * @return array array of record details (empty array if nothing found)
     */
    public function getRecordsFromDomainId(string $db_type, int $id, int $rowstart = 0, int $rowamount = Constants::DEFAULT_MAX_ROWS, string $sortby = 'name', string $sortDirection = 'ASC', bool $fetchComments = false): array;

    /**
     * Record ID to Domain ID
     *
     * Gets the id of the domain by a given record id
     *
     * @param int $id Record ID
     * @return int Domain ID of record
     */
    public function recidToDomid(int $id): int;

    /**
     * Check if record exists
     *
     * @param string $name Record name
     *
     * @return boolean true on success, false on failure
     */
    public function recordNameExists(string $name): bool;

    /**
     * Check if a record with the given parameters already exists
     *
     * @param int $domain_id Domain ID
     * @param string $name Record name
     * @param string $type Record type
     * @param string $content Record content
     * @return bool True if record exists, false otherwise
     */
    public function recordExists(int $domain_id, string $name, string $type, string $content): bool;

    /**
     * Check if non-delegation records exist for a given name
     *
     * Per RFC 1034: Delegation NS records are NOT authoritative data.
     * Per RFC 4034: DS records are part of the delegation for DNSSEC.
     * This method checks for records other than NS/DS which indicate authoritative data.
     * Only delegation records (NS, DS) at a name should not prevent zone creation.
     *
     * @param string $name Record name
     * @return bool True if non-delegation records exist, false otherwise
     */
    public function hasNonDelegationRecords(string $name): bool;

    /**
     * Check if any PTR record exists for a given reverse domain name
     *
     * @param int $domain_id Domain ID
     * @param string $name Reverse domain name (e.g., "1.1.168.192.in-addr.arpa")
     *
     * @return bool True if any PTR record exists for this name
     */
    public function hasPtrRecord(int $domain_id, string $name): bool;

    /**
     * Check if record has similar records with same name and type
     *
     * @param int $domain_id Domain ID
     * @param string $name Record name
     * @param string $type Record type
     * @param int $record_id Current record ID to exclude from check
     * @return bool True if similar records found, false otherwise
     */
    public function hasSimilarRecords(int $domain_id, string $name, string $type, int $record_id): bool;

    /**
     * Get Serial for Zone ID
     *
     * @param int $zid Zone ID
     *
     * @return string Serial Number or false if not found
     */
    public function getSerialByZid(int $zid): string;

    /**
     * Get filtered records from a domain with search capabilities
     *
     * @param int $zone_id The zone ID
     * @param int $row_start Starting row for pagination
     * @param int $row_amount Number of rows per page
     * @param string $sort_by Column to sort by
     * @param string $sort_direction Sort direction (ASC or DESC)
     * @param bool $include_comments Whether to include comments
     * @param string $search_term Optional search term to filter by name or content
     * @param string $type_filter Optional record type filter
     * @param string $content_filter Optional content filter
     * @return array Array of filtered records
     */
    public function getFilteredRecords(
        int $zone_id,
        int $row_start,
        int $row_amount,
        string $sort_by,
        string $sort_direction,
        bool $include_comments,
        string $search_term = '',
        string $type_filter = '',
        string $content_filter = ''
    ): array;

    /**
     * Get count of filtered records
     *
     * @param int $zone_id The zone ID
     * @param bool $include_comments Whether to include comments in the search
     * @param string $search_term Optional search term to filter by name or content
     * @param string $type_filter Optional record type filter
     * @param string $content_filter Optional content filter
     * @return int Number of filtered records
     */
    public function getFilteredRecordCount(
        int $zone_id,
        bool $include_comments,
        string $search_term = '',
        string $type_filter = '',
        string $content_filter = ''
    ): int;
}
