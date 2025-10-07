<?php

namespace Foodsharing\Modules\Uploads;

use Exception;
use Foodsharing\Modules\Core\BaseGateway;
use Foodsharing\Modules\Core\DBConstants\Uploads\UploadUsage;
use Foodsharing\Modules\Uploads\DTO\UploadedFile;

class UploadsGateway extends BaseGateway
{
    /**
     * Returns the mimetype of the file with the specified UUID. Throws an exception if the file does not exist.
     *
     * @throws Exception
     */
    public function getMimeType(string $uuid): string
    {
        return $this->db->fetchValueByCriteria('uploads', 'mimetype', ['uuid' => $uuid]);
    }

    /**
     * Adds a file's metadata to the database. Generates and returns a UUID.
     */
    public function addFile(?int $userId, string $hash, int $size, string $mimeType): string
    {
        $uuid = $this->uuid_v4();

        $this->db->insert('uploads', [
            'uuid' => $uuid,
            'user_id' => $userId,
            'sha256hash' => $hash,
            'mimetype' => $mimeType,
            'uploaded_at' => $this->db->now(),
            'filesize' => $size,
            'used_in' => null,
            'usage_id' => null,
        ]);

        return $uuid;
    }

    /**
     * Returns the user who uploaded the file with the specific UUID.
     *
     * @param string $uuid UUID of a previously uploaded file
     *
     * @return int the foodsaver ID
     *
     * @throws Exception if the file does not exist
     */
    public function getUser(string $uuid): int
    {
        return $this->db->fetchValueByCriteria('uploads', 'user_id', ['uuid' => $uuid]);
    }

    /**
     * Returns meta data of an uploaded file. This function should not be used directly, because it does not properly
     * set the filename in the returned object. Use {@see UploadsTransactions::getUploadedFile()} instead.
     *
     * @return ?UploadedFile meta data of the file or null if the given UUID does not exist
     */
    public function getUploadedFile(string $uuid): ?UploadedFile
    {
        $upload = $this->db->fetchByCriteria('uploads', ['user_id', 'filesize', 'sha256hash', 'mimetype', 'used_in', 'usage_id'], ['uuid' => $uuid]);

        if (empty($upload)) {
            return null;
        }
        $usedIn = !is_null($upload['used_in']) ? UploadUsage::tryFrom($upload['used_in']) : null;

        return new UploadedFile('', $upload['filesize'], $upload['sha256hash'], $upload['mimetype'], $upload['user_id'],
            $usedIn, $upload['usage_id']);
    }

    /**
     * Updates in which module the files with the specified UUIDs are being used. All files in the array will be linked
     * to the same entity. This should be set after every upload.
     *
     * @param string[] $uuids
     * @param UploadUsage $usedIn in which module the files are being used
     * @param int $usageId the id of an entry corresponding to the usedIn value
     *
     * @return bool if a row was changed
     *
     * @throws Exception
     */
    public function setUsage(array $uuids, UploadUsage $usedIn, int $usageId): bool
    {
        return $this->db->update('uploads', ['used_in' => $usedIn->value, 'usage_id' => $usageId], ['uuid' => $uuids]) > 0;
    }

    // our mysql query builder doesn't offer UUID(), so we use this PHP code
    // until we moved to a new library
    private function uuid_v4(): string
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF),

            // 16 bits for "time_mid"
            mt_rand(0, 0xFFFF),

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0FFF) | 0x4000,

            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3FFF) | 0x8000,

            // 48 bits for "node"
            mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF)
        );
    }

    public function deleteUpload(string $uuid): int
    {
        return $this->db->delete('uploads', ['uuid' => $uuid]);
    }
}
