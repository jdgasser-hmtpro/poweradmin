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

namespace Poweradmin\Application\Service;

use Poweradmin\Domain\Model\RecordType;
use Poweradmin\Domain\Service\DnsRecord;
use Poweradmin\Domain\Utility\DnsHelper;

class RecordCommentSyncService
{
    private RecordCommentService $commentService;

    public function __construct(RecordCommentService $commentService)
    {
        $this->commentService = $commentService;
    }

    public function syncCommentsForPtrRecord(
        int $domainId,
        int $ptrZoneId,
        string $domainFullName,
        string $ptrName,
        string $comment,
        string $account
    ): void {
        $this->commentService->createComment($domainId, $domainFullName, RecordType::A, $comment, $account);
        $this->commentService->createComment($ptrZoneId, $ptrName, RecordType::PTR, $comment, $account);
    }

    public function syncCommentsForDomainRecord(
        int $domainId,
        int $ptrZoneId,
        string $recordContent,
        string $ptrName,
        string $comment,
        string $account
    ): void {
        $this->commentService->createComment($ptrZoneId, $ptrName, RecordType::PTR, $comment, $account);
        $this->commentService->createComment($domainId, $recordContent, RecordType::A, $comment, $account);
    }

    public function updatePtrRecordComment(
        int $ptrZoneId,
        string $oldPtrName,
        string $newPtrName,
        string $comment,
        string $account
    ): void {
        $this->commentService->updateComment($ptrZoneId, $oldPtrName, RecordType::PTR, $newPtrName, RecordType::PTR, $comment, $account);
    }

    public function updateARecordComment(
        int $ptrZoneId,
        string $oldPtrName,
        string $newPtrName,
        string $comment,
        string $account
    ): void {
        $this->commentService->updateComment($ptrZoneId, $oldPtrName, RecordType::A, $newPtrName, RecordType::A, $comment, $account);
    }

    public function updateRelatedRecordComments(
        DnsRecord $dnsRecord,
        array $newRecordInfo,
        string $comment,
        string $userLogin
    ): void {
        if (in_array($newRecordInfo['type'], [RecordType::A, RecordType::AAAA])) {
            $ptrName = $newRecordInfo['type'] === RecordType::A
                ? DnsRecord::convertIPv4AddrToPtrRec($newRecordInfo['content'])
                : DnsRecord::convertIPv6AddrToPtrRec($newRecordInfo['content']);
            $ptrZoneId = $dnsRecord->getBestMatchingZoneIdFromName($ptrName);
            if ($ptrZoneId !== -1) {
                $this->updatePtrRecordComment($ptrZoneId, $ptrName, $ptrName, $comment, $userLogin);
            }
        } elseif ($newRecordInfo['type'] === RecordType::PTR) {
            $domainName = DnsHelper::getRegisteredDomain($newRecordInfo['content']);
            $contentDomainId = $dnsRecord->getDomainIdByName($domainName);
            if ($contentDomainId !== null) {
                $this->updateARecordComment($contentDomainId, $newRecordInfo['content'], $newRecordInfo['content'], $comment, $userLogin);
            }
        }
    }
}
