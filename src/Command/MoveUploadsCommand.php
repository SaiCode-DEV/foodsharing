<?php

namespace Foodsharing\Command;

use Exception;
use Foodsharing\Modules\Core\Database;
use Foodsharing\Modules\Uploads\UploadsGateway;
use Foodsharing\Modules\Uploads\UploadsTransactions;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand('foodsharing:move-uploads')]
class MoveUploadsCommand extends Command
{
    public function __construct(
        private readonly Database $db,
        private readonly UploadsGateway $uploadsGateway,
        private readonly UploadsTransactions $uploadsTransactions
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Move all uploaded files from the old to the new API');
        $this->addOption('dry', null, InputOption::VALUE_NONE, 'List the files but do not actually move them');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $oldFormats = ['', '130_q_', '50_q_', 'med_q_', 'mini_q_', 'thumb_', 'thumb_crop_', 'q_'];

        $isDryRun = $input->getOption('dry');

        // fetch all wall posts from the database
        // Old format: {"image":[{"file":"abc.jpg"},{"file":"def.png"}]}
        // New format: {"images":["\/api\/uploads\/{uuid}","\/api\/uploads\/{uuid}"]}
        $entriesWithPicture = $this->db->fetchAll('
			SELECT
				`id`,
				`foodsaver_id`,
				`attach`
			FROM
				`fs_wallpost`
			WHERE
			    attach IS NOT NULL AND attach <> "" AND attach NOT LIKE "%images%"'
        );

        // move all pictures from the old directory and update the database entries
        $movedEntries = 0;
        $movedFiles = 0;
        $invalidEntries = [];
        foreach ($entriesWithPicture as $entry) {
            $output->writeln('Moving ' . $entry['id']);
            $files = json_decode($entry['attach'], true)['image'];
            $files = array_column($files, 'file');

            $uuids = [];
            try {
                // Copy all files to the API
                foreach ($files as $file) {
                    $output->writeln('  moving ' . $file);

                    if (!$isDryRun) {
                        $uuid = $this->copyFileToNewAPI('images/wallpost/' . $file, $entry['foodsaver_id']);
                        $uuids[] = $uuid;
                    }
                    ++$movedFiles;
                }

                // If everything was successful, delete the old files and update the database entry
                if (!$isDryRun) {
                    foreach ($files as $file) {
                        foreach ($oldFormats as $format) {
                            @unlink('./images/wallpost' . $format . $file);
                        }
                    }

                    $newPaths = array_map(fn ($uuid) => '/api/uploads/' . $uuid, $uuids);
                    $attach = json_encode(['images' => $newPaths]);
                    $this->db->update('fs_wallpost', ['attach' => $attach], ['id' => $entry['id']]);
                }
                ++$movedEntries;
            } catch (Throwable $t) {
                // If anything went wrong, reset everything: delete the database entries and the destination files, set
                // the entry's picture to the previous value
                foreach ($uuids as $uuid) {
                    $this->uploadsGateway->deleteUpload($uuid);
                    @unlink($this->uploadsTransactions->generateFilePath($uuid));
                }
                $this->db->update('fs_wallpost', ['attach' => $entry['attach']], ['id' => $entry['id']]);

                $output->writeln($t->getMessage());
                $invalidEntries[] = $entry;
            }
        }

        // print statistics
        $output->writeln('Einträge gelesen: ' . count($entriesWithPicture));
        $output->writeln("{$movedEntries} Einträge bearbeitet, {$movedFiles} Dateien verschoben");
        if (sizeof($invalidEntries) > 0) {
            $output->writeln(count($invalidEntries) . ' Einträge die nicht korrigiert werden konnten: '
                . json_encode(array_column($invalidEntries, 'id')));
        }

        return 0;
    }

    /**
     * Copies a file to the upload directory and adds a database entry as if it was uploaded via the API. Does not
     * delete the source file.
     *
     * @param string $filePath current path of the file
     * @param ?int $userId id of the user who will be the owner of the uploaded file
     * @return string the new UUID
     * @throws Exception if the file could not be copied or the database entry could not be created
     */
    private function copyFileToNewAPI(string $filePath, ?int $userId): string
    {
        // add the entry to the database
        $bodyHash = hash_file('sha256', $filePath);
        $fileSize = filesize($filePath);
        $mimeType = mime_content_type($filePath);
        $uuid = $this->uploadsGateway->addFile($userId, $bodyHash, $fileSize, $mimeType);

        // copy the file
        $destination = $this->uploadsTransactions->generateFilePath($uuid);
        $dir = dirname($destination);
        if (!file_exists($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Directory was not created: ' . $dir);
        }
        $copied = copy($filePath, $destination);
        if (!$copied) {
            throw new \RuntimeException('File was not copied: ' . $filePath);
        }

        return $uuid;
    }
}
