<?php

namespace Foodsharing\Modules\Voting;

use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Core\DBConstants\Unit\UnitType;
use Foodsharing\Modules\Core\DBConstants\Voting\VotingNotificationType;
use Foodsharing\Modules\Core\DBConstants\Voting\VotingType;
use Foodsharing\Modules\Voting\DTO\Poll;
use Foodsharing\Modules\Voting\DTO\PollForListView;
use Foodsharing\Modules\Voting\DTO\PollForPreview;
use Foodsharing\Modules\Voting\DTO\PollOption;

class VotingGateway extends BaseGateway
{
    /**
     * Returns the detailed data of a poll.
     *
     * @param int $pollId a valid id of a poll
     * @param bool $includeResults whether the counted votes should be included
     *
     * @return Poll the poll object or null if this poll ID doesn't exist
     */
    public function getPoll(int $pollId, bool $includeResults): ?Poll
    {
        $data = $this->db->fetchByCriteria('fs_poll',
            ['region_id', 'scope', 'name', 'description', 'type', 'start', 'end', 'author', 'votes', 'eligible_votes_count', 'creation_timestamp', 'shuffle_options'],
            ['id' => $pollId]
        );
        if (empty($data)) {
            return null;
        }

        $options = $this->getOptions($pollId, $includeResults);

        return Poll::create($pollId, $data['name'], $data['description'],
            new \DateTime($data['start']), new \DateTime($data['end']),
            $data['region_id'], $data['scope'], $data['type'], $data['author'],
            new \DateTime($data['creation_timestamp']),
            VotingType::getNumberOfValues($data['type']),
            $includeResults ? $data['votes'] : null, $data['eligible_votes_count'], $options,
            $data['shuffle_options']);
    }

    /**
     * Returns all options of a poll without the vote counts. If the poll does not exist or does not have any
     * options an empty array is returned.
     *
     * @param int $pollId a valid id of a poll
     * @param bool $includeResults whether the counted votes should be included
     *
     * @return array associative array that maps the option indices to {@link PollOption} objects
     */
    public function getOptions(int $pollId, bool $includeResults): array
    {
        // meta-data of option
        try {
            $data = $this->db->fetchAllByCriteria('fs_poll_has_options', ['option', 'option_text'], ['poll_id' => $pollId]);
        } catch (\Exception) {
            $data = [];
        }

        // values and counted votes
        $result = [];
        foreach ($data as $d) {
            if ($includeResults) {
                $values = $this->db->fetchAllByCriteria('fs_poll_option_has_value', ['value', 'votes'], [
                    'poll_id' => $pollId, 'option' => $d['option']]);
                $mappedValues = [];
                foreach ($values as $v) {
                    $mappedValues[$v['value']] = $v['votes'];
                }
            } else {
                $values = $this->db->fetchAllValuesByCriteria('fs_poll_option_has_value', 'value', [
                    'poll_id' => $pollId, 'option' => $d['option']]);
                $mappedValues = array_combine($values, array_fill(0, sizeof($values), -1));
            }
            $result[$d['option']] = PollOption::create($pollId, $d['option'], $d['option_text'], $mappedValues);
        }

        return $result;
    }

    /**
     * Returns all polls in a region or working group. If the region does not exist an empty array
     * is returned.
     *
     * @param int $regionId a valid ID of a group or region
     * @param int $userId the id of the user for which the view is fetched
     *
     * @return PollForListView[]
     */
    public function listPolls(int $regionId, int $userId): array
    {
        $polls = $this->db->fetchAll('SELECT
                p.id, p.name, p.start, p.end,
                (hp.time IS NOT NULL) AS hasVoted,
                (hp.poll_id IS NOT NULL) AS isEligible
            FROM fs_poll p
            LEFT OUTER JOIN fs_foodsaver_has_poll hp ON hp.poll_id = p.id AND hp.foodsaver_id = :userId
            WHERE p.region_id = :regionId
            ORDER BY p.end', [
            ':regionId' => $regionId,
            ':userId' => $userId,
        ]);

        return array_map(PollForListView::createFromArray(...), $polls);
    }

    /**
     * Returns all polls a user is invited to.
     *
     * @param int $fsId a valid ID of a foodsaver
     *
     * @return Poll[] multiple {@link PollForPreview} objects
     */
    public function listCurrentPolls(int $fsId): array
    {
        $data = $this->db->fetchAll('SELECT
				p.`id`, p.`name`, p.`start`, p.`end`, p.`region_id`, 
				r.`name` as region_name, p.`scope`,
				p.`start` > NOW() as in_future
			FROM
				`fs_poll` p
			JOIN `fs_foodsaver_has_poll` f ON
				f.poll_id = p.id
            JOIN `fs_bezirk` r ON
            	r.id = p.region_id
			WHERE f.foodsaver_id = :fsId AND
				p.`end` > NOW() AND
				f.time IS NULL
			ORDER BY p.`end`
		', ['fsId' => $fsId]);

        $polls = [];
        foreach ($data as $d) {
            $polls[] = PollForPreview::create(
                $d['id'],
                $d['name'],
                new \DateTime($d['start']),
                new \DateTime($d['end']),
                $d['region_id'],
                $d['region_name'],
                $d['scope'],
                $d['in_future']
            );
        }

        return $polls;
    }

    /**
     * Returns if and when a user has voted in a specific poll or null if the user has not voted yet.
     *
     * @param int $pollId a valid id of a poll
     * @param int $userId a valid user id
     *
     * @return \DateTime the date at which the user has voted or null if the user has not voted yet
     *
     * @throws \Exception if the user is not allowed to vote in the poll
     */
    public function getVoteDatetime(int $pollId, int $userId): ?\DateTime
    {
        $value = $this->db->fetchValueByCriteria('fs_foodsaver_has_poll', 'time', [
            'poll_id' => $pollId,
            'foodsaver_id' => $userId
        ]);

        if ($value !== null) {
            $date = \DateTime::createFromFormat('Y-m-d H:i:s', $value);
        } else {
            $date = null;
        }

        return $date;
    }

    /**
     * Updates the vote counters of a poll and updates that the user has voted.
     *
     * @param int $pollId a valid id of a poll
     * @param int $userId a valid user id
     * @param array $options a map from option index to the vote value
     *
     * @throws \Exception if the poll does not exist or if one of the chosen values for an option is invalid
     */
    public function vote(int $pollId, int $userId, array $options): void
    {
        $this->db->execute('LOCK TABLES fs_poll WRITE, fs_foodsaver_has_poll WRITE, fs_poll_has_options WRITE');
        $this->db->beginTransaction();

        // update vote counts for options
        foreach ($options as $option => $voteValue) {
            // increment one of the columns depending on the vote for this option
            $this->db->execute('
				UPDATE fs_poll_option_has_value
				SET votes = votes+1
				WHERE poll_id = :pollId
				AND option = :option
				AND value = :value',
                [
                    ':pollId' => $pollId,
                    ':option' => $option,
                    ':value' => $voteValue
                ]);
        }

        // mark that user has voted
        $this->db->update('fs_foodsaver_has_poll', ['time' => $this->db->now()], [
            'foodsaver_id' => $userId,
            'poll_id' => $pollId
        ]);
        // update total vote count of poll
        $this->db->execute('UPDATE fs_poll SET votes = votes+1 WHERE id = :pollId', [
            ':pollId' => $pollId
        ]);
        $this->db->commit();
        $this->db->execute('UNLOCK TABLES');
    }

    /**
     * Inserts a new poll.
     *
     * @param Poll $poll a valid poll object
     * @param array $voterIds list of IDs of all users that will be allowed to vote
     *
     * @return int the id of the created poll
     *
     * @throws \Exception
     */
    public function insertPoll(Poll $poll, array $voterIds): int
    {
        // insert the poll
        $pollId = $this->db->insert('fs_poll', [
            'region_id' => $poll->regionId,
            'name' => $poll->name,
            'description' => $poll->description,
            'scope' => $poll->scope,
            'type' => $poll->type,
            'start' => $poll->startDate->format('Y-m-d H:i:s'),
            'end' => $poll->endDate->format('Y-m-d H:i:s'),
            'author' => $poll->authorId,
            'votes' => 0,
            'eligible_votes_count' => count($voterIds),
            'creation_timestamp' => $this->db->now(),
            'shuffle_options' => $poll->shuffleOptions,
            'notifications_sent' => VotingNotificationType::NONE
        ]);

        // insert all options
        $this->insertOptions($poll->options, $pollId);

        // add all voters (100 per query)
        $parts = array_chunk($voterIds, 100);
        foreach ($parts as $part) {
            $data = array_map(fn ($id) => [
                'foodsaver_id' => $id,
                'poll_id' => $pollId,
                'time' => null
            ], $part);
            $this->db->insertMultiple('fs_foodsaver_has_poll', $data);
        }

        return $pollId;
    }

    /**
     * Updates the name, description, and options of the poll.
     */
    public function updatePoll(Poll $poll): void
    {
        // update texts
        $this->db->update('fs_poll', [
            'name' => $poll->name,
            'description' => $poll->description,
            'shuffle_options' => $poll->shuffleOptions,
        ], ['id' => $poll->id]);

        // remove all options and create new ones
        $this->db->delete('fs_poll_has_options', ['poll_id' => $poll->id]);
        $this->db->delete('fs_poll_option_has_value', ['poll_id' => $poll->id]);
        $this->insertOptions($poll->options, $poll->id);
    }

    /**
     * Removes a poll. All options and user invitations will be deleted, too.
     *
     * @param int $pollId a valid poll ID
     *
     * @throws \Exception
     */
    public function deletePoll(int $pollId): void
    {
        $this->db->delete('fs_poll', ['id' => $pollId]);
    }

    /**
     * Returns the IDs of all users in a specific region, optionally including subregions. The results can be
     * filtered by a minimal role, verification, and home district.
     *
     * @param int $regionId ID of the region
     * @param Role $minRole minimal role of users that should be included
     * @param bool $onlyVerified only verified users should be included
     * @param bool $restrict_homeDistrict only users whose home region is the specified region or any subregion (if included)
     * @param bool $includeSubregions whether users from subregions should be included
     *
     * @return int[] user IDs
     *
     * @throws \Exception
     */
    public function listActiveRegionMemberIds(int $regionId, Role $minRole, bool $onlyVerified = true, bool $restrict_homeDistrict = false,
        bool $includeSubregions = true): array
    {
        $verifiedCondition = $onlyVerified ? 'AND fs.verified = 1' : '';

        if ($restrict_homeDistrict) {
            // fetch all subregion-IDs if they should be included
            $regionIds = [$regionId];
            if ($includeSubregions) {
                $subregions = $this->db->fetchAllValuesByCriteria('fs_bezirk_closure', 'bezirk_id',
                    ['ancestor_id' => $regionId, 'depth >=' => 1]
                );
                $regionIds = array_merge($regionIds, $subregions);
            }

            // fetch all user IDs
            $list = $this->db->fetchAll('
			SELECT DISTINCT id
			FROM fs_foodsaver fs
			WHERE fs.bezirk_id IN ( ' . implode(',', $regionIds) . ')
			AND fs.rolle >= :role
			' . $verifiedCondition, [
                ':role' => $minRole->value
            ]);
        } else {
            /* fetching all subregions is not necessary here because all users from subregions should
               also be in this region */
            $list = $this->db->fetchAll('
				SELECT DISTINCT id
				FROM fs_foodsaver fs
				INNER JOIN fs_foodsaver_has_bezirk hb
				ON fs.id = hb.foodsaver_id
				WHERE hb.bezirk_id = :regionId
				AND hb.active = 1
				AND fs.rolle >= :role
				' . $verifiedCondition, [
                ':regionId' => $regionId,
                ':role' => $minRole->value
            ]);
        }

        return array_map(fn ($x) => $x['id'], $list);
    }

    /**
     * Returns the IDs of all ambassadors of the given region and all subregions without working groups.
     * If a user is ambassador of multiple subregions the ID is only included once.
     */
    public function getAmbassadorsIDsOfSubregions(int $groupId): array
    {
        return $this->db->fetchAllValues('
			SELECT DISTINCT amb.foodsaver_id
			FROM `fs_botschafter` amb

			INNER JOIN `fs_foodsaver` fs
			ON fs.id = amb.foodsaver_id

			INNER JOIN `fs_bezirk_closure` bc
			ON bc.bezirk_id = amb.bezirk_id

			INNER JOIN `fs_bezirk` b
			ON b.id = amb.bezirk_id

			WHERE bc.ancestor_id = :regionId
			AND fs.deleted_at IS NULL
			AND b.type <> :workingGroupType',
            [':regionId' => $groupId,
             ':workingGroupType' => UnitType::WORKING_GROUP]
        );
    }

    /**
     * Inserts the options into 'fs_poll_has_options' and 'fs_poll_option_has_value' when creating or updating
     * a poll. Existing options will not be changed.
     *
     * @param array $options array of {@see PollOption} objects
     * @param int $pollId ID of the poll
     *
     * @throws \Exception
     */
    private function insertOptions(array $options, int $pollId): void
    {
        foreach ($options as $index => $option) {
            if (!($option instanceof PollOption)) {
                throw new \Exception('unexpected object type for the poll option');
            }

            $this->db->insert('fs_poll_has_options', [
                'poll_id' => $pollId,
                'option' => $option->optionIndex,
                'option_text' => $option->text
            ]);

            // insert all values for this option
            foreach (array_keys($option->values) as $value) {
                $this->db->insert('fs_poll_option_has_value', [
                    'poll_id' => $pollId,
                    'option' => $option->optionIndex,
                    'value' => $value,
                    'votes' => 0
                ]);
            }
        }
    }

    /**
     * Returns polls that have started but haven't had a start notification sent yet.
     *
     * @return Poll[] Array of Poll objects that have recently started and need notifications
     */
    public function getStartedPolls(): array
    {
        $data = $this->db->fetchAll('
            SELECT id, name, description, region_id, scope, type, start, end, author, votes, eligible_votes_count, creation_timestamp, shuffle_options
            FROM fs_poll 
            WHERE start <= NOW()
            AND end > NOW()
            AND notifications_sent = :notificationStatus
        ', [
            ':notificationStatus' => VotingNotificationType::NONE
        ]);

        $polls = [];
        foreach ($data as $d) {
            $options = $this->getOptions($d['id'], false);
            $polls[] = Poll::create(
                $d['id'],
                $d['name'],
                $d['description'],
                new \DateTime($d['start']),
                new \DateTime($d['end']),
                $d['region_id'],
                $d['scope'],
                $d['type'],
                $d['author'],
                new \DateTime($d['creation_timestamp']),
                VotingType::getNumberOfValues($d['type']),
                null,
                $d['eligible_votes_count'],
                $options,
                $d['shuffle_options']
            );
        }

        return $polls;
    }

    /**
     * Returns polls that are ending soon (in the next 24 hours) and haven't had an end notification sent yet.
     *
     * @return Poll[] Array of Poll objects that are ending soon and need notifications
     */
    public function getEndingSoonPolls(): array
    {
        $endTime = (new \DateTime())->add(new \DateInterval('PT24H'));

        $data = $this->db->fetchAll('
            SELECT id, name, description, region_id, scope, type, start, end, author, votes, eligible_votes_count, creation_timestamp, shuffle_options
            FROM fs_poll 
            WHERE end <= :until
            AND end > NOW()
            AND notifications_sent = :notificationStatus
        ', [
            ':until' => $endTime->format('Y-m-d H:i:s'),
            ':notificationStatus' => VotingNotificationType::START_SENT
        ]);

        $polls = [];
        foreach ($data as $d) {
            $options = $this->getOptions($d['id'], false);
            $polls[] = Poll::create(
                $d['id'],
                $d['name'],
                $d['description'],
                new \DateTime($d['start']),
                new \DateTime($d['end']),
                $d['region_id'],
                $d['scope'],
                $d['type'],
                $d['author'],
                new \DateTime($d['creation_timestamp']),
                VotingType::getNumberOfValues($d['type']),
                null,
                $d['eligible_votes_count'],
                $options,
                $d['shuffle_options']
            );
        }

        return $polls;
    }

    /**
     * Returns the IDs of all users eligible to vote in a specific poll.
     *
     * @param int $pollId The ID of the poll
     *
     * @return int[] Array of user IDs
     */
    public function getEligibleVotersForPoll(int $pollId): array
    {
        $users = $this->db->fetchAllValues('
            SELECT foodsaver_id
            FROM fs_foodsaver_has_poll
            WHERE poll_id = :pollId
        ', [':pollId' => $pollId]);

        return $users;
    }

    /**
     * Returns the IDs of all users who haven't voted yet in a specific poll.
     *
     * @param int $pollId The ID of the poll
     *
     * @return int[] Array of user IDs
     */
    public function getNonVotersForPoll(int $pollId): array
    {
        $users = $this->db->fetchAllValues('
            SELECT foodsaver_id
            FROM fs_foodsaver_has_poll
            WHERE poll_id = :pollId
            AND time IS NULL
        ', [':pollId' => $pollId]);

        return $users;
    }

    /**
     * Marks a poll as having sent the start notification.
     *
     * @param int $pollId The ID of the poll
     */
    public function markStartNotificationSent(int $pollId): void
    {
        $this->db->update('fs_poll', ['notifications_sent' => VotingNotificationType::START_SENT], ['id' => $pollId]);
    }

    /**
     * Marks a poll as having sent the end notification.
     *
     * @param int $pollId The ID of the poll
     */
    public function markEndNotificationSent(int $pollId): void
    {
        $this->db->update('fs_poll', ['notifications_sent' => VotingNotificationType::BOTH_SENT], ['id' => $pollId]);
    }
}
