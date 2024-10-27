<?php

namespace Foodsharing\Command;

use Foodsharing\Modules\Core\Database;
use Foodsharing\Modules\Core\DBConstants\Uploads\UploadUsage;
use Foodsharing\Modules\Uploads\UploadsGateway;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class TagUploadsCommand extends Command
{
    protected static $defaultName = 'foodsharing:tag-uploads';

    public function __construct(
        private readonly Database $db,
        private readonly UploadsGateway $uploadsGateway,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Tag all uploaded files that do not have a usage type yet');
        $this->addOption('dry', null, InputOption::VALUE_NONE, 'List the files but do not actually move them');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $isDryRun = $input->getOption('dry');

        // Fetch all posts from the database that have a valid picture in the upload API
        $postWithValidPictures = $this->db->fetchAll('
			SELECT
				b.`id`,
				b.`picture`
			FROM
				`fs_blog_entry` b
			WHERE
			    b.picture LIKE "/api/uploads%"'
        );

        $invalidPosts = [];
        $taggedFiles = 0;
        foreach ($postWithValidPictures as $post) {
            try {
                if (!$isDryRun) {
                    $this->uploadsGateway->setUsage([$post['uuid']], UploadUsage::BLOG_POST, $post['id']);
                }
                ++$taggedFiles;
            } catch (Throwable $t) {
                $output->writeln($t);
                $invalidPosts[] = $post;
            }
        }

        // print statistics
        $output->writeln("    {$taggedFiles} Dateien markiert");
        if (sizeof($invalidPosts) > 0) {
            $output->writeln('    ' . sizeof($invalidPosts) . ' Einträge die nicht korrigiert werden konnten: '
                . json_encode(array_column($invalidPosts, 'id')));
        }

        return 0;
    }
}
