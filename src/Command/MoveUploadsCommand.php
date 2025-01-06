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

        // fetch all food share points from the database
        $entriesWithPicture = $this->db->fetchAll('
			SELECT
				`id`,
				`picture`
			FROM
				`fs_fairteiler`
			WHERE
			    picture <> ""',
        );

        // sort by old or new picture path
        $oldPictures = [];
        $invalidPictures = [];
        foreach ($entriesWithPicture as $entry) {
            if (str_starts_with((string)$entry['picture'], 'picture/')) {
                $oldPictures[] = $entry;
            } elseif (!str_starts_with((string)$entry['picture'], '/api/uploads')) {
                $invalidPictures[] = $entry;
            }
        }

        // move all pictures from the old directory and update the database entries
        $movedFiles = 0;
        foreach ($oldPictures as $entry) {
            $uuid = null;
            $source = 'images/' . str_replace('/', '/crop_0_528_', $entry['picture']);

            try {
                $output->writeln('moving ' . $entry['id'] . ', ' . $source);
                if (!$isDryRun) {
                    $uuid = $this->copyFileToNewAPI($source, null);
                    $this->db->update('fs_fairteiler', ['picture' => '/api/uploads/' . $uuid], ['id' => $entry['id']]);
                    @unlink($source);
                }
                ++$movedFiles;
            } catch (Throwable $t) {
                // If anything went wrong, reset everything: delete the database entry and the destination file, set
                // the entry's picture to the previous value
                if (!empty($uuid)) {
                    $this->uploadsGateway->deleteUpload($uuid);
                    @unlink($this->uploadsTransactions->generateFilePath($uuid));
                    $this->db->update('fs_fairteiler', ['picture' => $source], ['id' => $entry['id']]);
                }

                $output->writeln($t->getMessage());
                $invalidPictures[] = $entry;
            }
        }

        // print statistics
        $output->writeln("    {$movedFiles} Dateien verschoben");
        if (sizeof($invalidPictures) > 0) {
            $output->writeln('    ' . sizeof($invalidPictures) . ' Einträge die nicht korrigiert werden konnten: '
                . json_encode(array_column($invalidPictures, 'id')));
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
        $fileInfoFromDatabase = $this->uploadsGateway->addFile($userId, $bodyHash, $fileSize, $mimeType);
        $uuid = $fileInfoFromDatabase['uuid'];

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
