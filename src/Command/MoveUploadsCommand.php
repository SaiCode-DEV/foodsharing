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
        $isDryRun = $input->getOption('dry');

        // fetch all group pictures from the database
        $entriesWithPicture = $this->db->fetchAll('
			SELECT
				`id`,
				`photo`
			FROM
				`fs_bezirk`
			WHERE
			    photo IS NOT NULL AND photo <> "" AND photo NOT LIKE "/api/uploads%"',
        );

        // sort by old or new picture path
        $invalidEntries = [];

        // move all pictures from the old directory and update the database entries
        $movedFiles = 0;
        foreach ($entriesWithPicture as $entry) {
            $uuid = null;
            $source = null;
            if (str_starts_with($entry['photo'], '/images/photo') || str_starts_with($entry['photo'], '/images/workgroup')) {
                $source = substr($entry['photo'], 1);
            } elseif (str_starts_with($entry['photo'], 'photo/') || str_starts_with($entry['photo'], 'workgroup/')) {
                $source = 'images/' . $entry['photo'];
            } else {
                $invalidEntries[] = $entry;
            }

            if (!is_null($source)) {
                try {
                    $output->writeln('moving ' . $entry['id'] . ', ' . $source);
                    if (!$isDryRun) {
                        $uuid = $this->copyFileToNewAPI($source, $entry['id']);
                        $this->db->update('fs_bezirk', ['photo' => '/api/uploads/' . $uuid], ['id' => $entry['id']]);
                    }
                    ++$movedFiles;
                } catch (Throwable $t) {
                    // If anything went wrong, reset everything: delete the database entry and the destination file, set
                    // the entry's picture to the previous value
                    if (!empty($uuid)) {
                        $this->uploadsGateway->deleteUpload($uuid);
                        @unlink($this->uploadsTransactions->generateFilePath($uuid));
                        $this->db->update('fs_bezirk', ['photo' => $source], ['id' => $entry['id']]);
                    }

                    $output->writeln($t->getMessage());
                    $invalidEntries[] = $entry;
                }
            }
        }

        // print statistics
        $output->writeln("{$movedFiles} Dateien verschoben");
        if (sizeof($invalidEntries) > 0) {
            $output->writeln(sizeof($invalidEntries) . ' Einträge die nicht korrigiert werden konnten: '
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
