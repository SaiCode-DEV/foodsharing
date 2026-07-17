<?php

namespace Foodsharing\Modules\Maintenance;

use Carbon\Carbon;
use DateTime;
use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\DBConstants\Basket\Status;
use Foodsharing\Modules\Core\DBConstants\Quiz\SessionStatus;
use Foodsharing\Modules\Region\ForumTransactions;

class MaintenanceGateway extends BaseGateway
{
    /**
     * Returns the ID of all outdated baskets.
     */
    public function listOldBaskets(): array
    {
        return $this->db->fetchAllValuesByCriteria('fs_basket', 'id', [
            'status' => Status::REQUESTED_MESSAGE_READ, 'until <' => $this->db->now()
        ]);
    }

    /**
     * Makes sure that all foodsavers in regions that have master regions are also members of the master region.
     */
    public function masterRegionUpdate(): void
    {
        $foodsaver = $this->db->fetchAll('
				SELECT
				b.`id`,
				b.`name`,
				b.`type`,
				b.`master`,
				hb.foodsaver_id

				FROM 	`fs_bezirk` b,
				`fs_foodsaver_has_bezirk` hb

				WHERE 	hb.bezirk_id = b.id
				AND 	b.`master` != 0
				AND 	hb.active = 1
		');

        $data = [];
        foreach ($foodsaver as $fs) {
            if ((int)$fs['master'] > 0) {
                $data[] = [
                    'foodsaver_id' => $fs['foodsaver_id'],
                    'bezirk_id' => $fs['master'],
                    'active' => 1,
                    'added' => $this->db->now()
                ];
            }
        }
        $parts = array_chunk($data, 100);
        foreach ($parts as $part) {
            $this->db->insertMultiple('fs_foodsaver_has_bezirk', $part, ['ignore' => true]);
        }
    }

    /**
     * Updates all quiz sessions that were finished or aborted more than two weeks ago by setting
     * questions and answers to null. After this the quiz results can not be seen anymore.
     *
     * @return int the number of updated entries
     */
    public function cleanOldQuizSessionData(): int
    {
        return $this->db->update(
            'fs_quiz_session',
            [
                'quiz_result' => null,
                'quiz_questions' => null,
            ],
            [
                'status' => [SessionStatus::FAILED->value, SessionStatus::PASSED->value],
                'time_end <' => Carbon::now()->subWeeks(2)->format('Y-m-d H:i:s'),
            ]
        );
    }

    /**
     * Removes all test quiz sessions that were started more than a day ago.
     *
     * @return int the number of removed entries
     */
    public function deleteTestQuizSessions(): int
    {
        return $this->db->delete(
            'fs_quiz_session',
            [
                'is_test' => 1,
                'time_start <' => Carbon::now()->subDay()->format('Y-m-d H:i:s'),
            ]
        );
    }

    /**
     * Returns the UUIDs of all entries in the uploads table that were created in a specific interval which do not have
     * a usage type and id yet.
     *
     * @return string[]
     */
    public function listUploadsWithoutUsage(DateTime $from, DateTime $to): array
    {
        return $this->db->fetchAllValuesByCriteria('uploads', 'uuid', [
            'used_in' => null,
            'usage_id' => null,
            'uploaded_at >' => $from->format('Y-m-d H:i:s'),
            'uploaded_at <' => $to->format('Y-m-d H:i:s')
        ]);
    }

    /**
     * Deletes all forum posts that have been hidden for more than 6 months.
     *
     * @return int the number of deleted posts
     */
    public function deleteHiddenForumPosts(ForumTransactions $forumTransactions): int
    {
        // start transaction
        $this->db->beginTransaction();

        // Get all posts that have been hidden for more than 6 months
        $posts = $this->db->fetchAll('
            SELECT id, foodsaver_id
            FROM fs_theme_post tp
            WHERE tp.hidden_time < :cutoff
        ', [
            ':cutoff' => Carbon::now()->subMonths(6)->format('Y-m-d H:i:s'),
        ]);

        // Delete all hidden posts which are older than 6 months
        foreach ($posts as $post) {
            $forumTransactions->deletePostFromThread((int)$post['id'], (int)$post['foodsaver_id']);
        }

        $this->db->commit();

        return count($posts);
    }

    /**
     * Deletes password reset requests that are older than 7 days.
     *
     * @return int number of deleted entries
     */
    public function deleteOldPassRequests(): int
    {
        return $this->db->delete(
            'fs_pass_request',
            [
                'time <' => Carbon::now()->subDays(7)->format('Y-m-d H:i:s'),
            ]
        );
    }

    /**
     * Recreates the fs_bezirk_closure table that stores all parent regions for each region.
     */
    public function recreateClosure()
    {
        $this->db->beginTransaction();
        $this->db->execute('DELETE FROM fs_bezirk_closure');
        $this->db->execute('
            INSERT INTO fs_bezirk_closure (bezirk_id, ancestor_id, depth)
            SELECT a.id, a.id, 0 FROM fs_bezirk AS a WHERE a.parent_id IS NOT NULL'
        );
        $depth = 0;
        do {
            $inserted = $this->db->execute('
                INSERT INTO fs_bezirk_closure (bezirk_id, ancestor_id, depth)
                SELECT a.bezirk_id, b.parent_id, a.depth+1
                FROM fs_bezirk_closure AS a
                JOIN fs_bezirk AS b
                ON b.id = a.ancestor_id
                WHERE b.parent_id IS NOT NULL AND a.depth = :depth', [
                ':depth' => $depth
            ])->rowCount();
            ++$depth;
        } while ($inserted > 0);
        $this->db->commit();
    }

    /**
     * Deletes all outdated registration attempts.
     * Returns the number of deleted entries.
     */
    public function deleteOldRegistrationAttempts(): int
    {
        return $this->db->delete('fs_registration_attempt', [
            'valid_until <' => Carbon::now()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Deletes expired and revoked OAuth tokens and auth codes.
     * This includes:
     * - Expired/revoked access tokens (cascades to refresh tokens)
     * - Expired/revoked authorization codes
     * - Revoked user consents.
     */
    public function deleteExpiredOAuthTokens(): int
    {
        $now = Carbon::now()->format('Y-m-d H:i:s');
        $deletedCount = 0;

        // Delete expired access tokens (also deletes cascaded refresh tokens)
        $deletedCount += $this->db->delete('oauth_access_tokens', [
            'expires_at <' => $now,
        ]);

        // Delete revoked access tokens
        $deletedCount += $this->db->delete('oauth_access_tokens', [
            'revoked' => 1,
        ]);

        // Delete expired authorization codes
        $deletedCount += $this->db->delete('oauth_auth_codes', [
            'expires_at <' => $now,
        ]);

        // Delete revoked authorization codes
        $deletedCount += $this->db->delete('oauth_auth_codes', [
            'revoked' => 1,
        ]);

        // Delete revoked user consents
        $deletedCount += $this->db->execute('DELETE FROM oauth_user_consents WHERE revoked_at IS NOT NULL')->rowCount();

        return $deletedCount;
    }
}
