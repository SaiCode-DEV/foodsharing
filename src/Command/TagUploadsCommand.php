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
				`foodsaver_id`,
				`attach`
			FROM
				`fs_wallpost`
			WHERE
			    attach IS NOT NULL AND attach <> "" AND attach LIKE "%images%"'
        );

        $invalidEntries = [];
        $taggedEntries = 0;
        $taggedFiles = 0;
        foreach ($entriesWithValidPictures as $entry) {
            try {
                $files = json_decode($entry['attach'], true)['images'];
                $uuids = array_map(fn ($file) => substr($file, 13), $files);
                if (!$isDryRun) {
                    $this->uploadsGateway->setUsage($uuids, UploadUsage::WALL_POST, $entry['id']);
                }
                $taggedFiles += count($files);
                ++$taggedEntries;
            } catch (Throwable $t) {
                $output->writeln($t);
                $invalidEntries[] = $entry;
            }
        }

        // print statistics
        $output->writeln('Einträge gelesen: ' . count($entriesWithValidPictures));
        $output->writeln("{$taggedEntries} Einträge bearbeitet, {$taggedFiles} Dateien markiert");
        if (sizeof($invalidEntries) > 0) {
            $output->writeln(sizeof($invalidEntries) . ' Einträge die nicht markiert werden konnten: '
                . json_encode(array_column($invalidEntries, 'id')));
        }

        return 0;
    }
}
