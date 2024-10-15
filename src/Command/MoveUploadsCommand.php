<?php

namespace Foodsharing\Command;

use Exception;
use Foodsharing\Modules\Core\Database;
use Foodsharing\Modules\Uploads\UploadsGateway;
use Foodsharing\Modules\Uploads\UploadsTransactions;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class MoveUploadsCommand extends Command
{
    protected static $defaultName = 'foodsharing:move-uploads';

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

        // fetch all posts from the database
        $postsWithPicture = $this->db->fetchAll('
			SELECT
				b.`id`,
				b.`picture`,
				fs.id as authorId
			FROM
				`fs_blog_entry` b,
				`fs_foodsaver` fs
			WHERE
				b.foodsaver_id = fs.id
			AND
			    b.picture <> ""',
        );

        // sort by old or new picture path
        $oldPosts = [];
        $invalidPosts = [];
        foreach ($postsWithPicture as $post) {
            if (str_starts_with($post['picture'], 'picture/')) {
                $oldPosts[] = $post;
            } elseif (!str_starts_with($post['picture'], '/api/uploads')) {
                $invalidPosts[] = $post;
            }
        }

        // move all pictures from old posts and update the blog entries
        $movedFiles = 0;
        foreach ($oldPosts as $post) {
            $uuid = null;
            $source = 'images/' . $post['picture'];

            try {
                $output->writeln('moving ' . $post['id'] . ', images/' . $post['picture']);
                if (!$isDryRun) {
                    $uuid = $this->copyFileToNewAPI($source, $post['authorId']);
                    $this->db->update('fs_blog_entry', ['picture' => '/api/uploads/' . $uuid], ['id' => $post['id']]);
                    @unlink($source);
                }
                ++$movedFiles;
            } catch (Throwable $t) {
                // If anything went wrong, reset everything: delete the database entry and the destination file, set
                // the blog post's picture to the previous value
                if (!empty($uuid)) {
                    $this->uploadsGateway->deleteUpload($uuid);
                    @unlink($this->uploadsTransactions->generateFilePath($uuid));
                    $this->db->update('fs_blog_entry', ['picture' => $source], ['id' => $post['id']]);
                }

                $output->writeln($t->getMessage());
                $invalidPosts[] = $post;
            }
        }

        // print statistics
        $output->writeln("    {$movedFiles} Dateien verschoben");
        if (sizeof($invalidPosts) > 0) {
            $output->writeln('    ' . sizeof($invalidPosts) . ' Einträge die nicht korrigiert werden konnten: '
                . json_encode(array_column($invalidPosts, 'id')));
        }

        return 0;
    }

    /**
     * Copies a file to the upload directory and adds a database entry as if it was uploaded via the API. Does not
     * delete the source file.
     *
     * @param string $filePath current path of the file
     * @param int $userId id of the user who will be the owner of the uploaded file
     * @return string the new UUID
     * @throws Exception if the file could not be copied or the database entry could not be created
     */
    private function copyFileToNewAPI(string $filePath, int $userId): string
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
