<?php

namespace Foodsharing\Command;

use Foodsharing\Modules\Core\Database;
use Foodsharing\Modules\Core\DBConstants\Uploads\UploadUsage;
use Foodsharing\Modules\Uploads\UploadsGateway;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand('foodsharing:tag-uploads')]
class TagUploadsCommand extends Command
{
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

        // Fetch all entries from the database that have a valid picture in the upload API
        $entriesWithValidPictures = $this->db->fetchAll('
			SELECT
				`id`,
				`photo`
			FROM
				`fs_foodsaver`
			WHERE
			    photo LIKE "/api/uploads%"'
        );

        $invalidPictures = [];
        $taggedFiles = 0;
        foreach ($entriesWithValidPictures as $entry) {
            try {
                if (!$isDryRun) {
                    $uuid = substr((string)$entry['photo'], 13);
                    $this->uploadsGateway->setUsage([$uuid], UploadUsage::PROFILE_PHOTO, $entry['id']);
                }
                ++$taggedFiles;
            } catch (Throwable $t) {
                $output->writeln($t);
                $invalidPictures[] = $entry;
            }
        }

        // print statistics
        $output->writeln("    {$taggedFiles} Dateien markiert");
        if (sizeof($invalidPictures) > 0) {
            $output->writeln('    ' . sizeof($invalidPictures) . ' Einträge die nicht korrigiert werden konnten: '
                . json_encode(array_column($invalidPictures, 'id')));
        }

        return 0;
    }
}
