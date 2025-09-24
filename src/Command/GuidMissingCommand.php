<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:guid:missing',
    description: 'Chechs if given GUIDs exist. If not they are added to output.',
)]
class GuidMissingCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('input-path', InputArgument::REQUIRED, 'Path to the GUIDs file (output of app:file:create:guid command)')
            ->addArgument('output-path', InputArgument::REQUIRED, 'Output path')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Find missing GUIDs');

        $inputPath = $input->getArgument('input-path');
        $outputPath = $input->getArgument('output-path');

        $guids = json_decode(file_get_contents($inputPath));
        $progressBar = new ProgressBar($output, count($guids));
        $progressBar->start();

        $missingGuids = [];

        $connection = $this->entityManager->getConnection();

        foreach($guids as $guid){
            if(0 === $connection->executeQuery('SELECT id FROM image WHERE id=\''.$guid.'\'')->rowCount()){
                $missingGuids[] = $guid;
            }

            $progressBar->advance();
        }

        $progressBar->finish();

        $io->newLine(2);
        file_put_contents($outputPath, json_encode($missingGuids, JSON_THROW_ON_ERROR));

        $io->success('Missing GUIDs written to '.$outputPath);

        return Command::SUCCESS;
    }

    private function getGuidsFromFolderStructure(string $currentPath, array &$guids, ProgressBar $progressBar, int $level = 0)
    {
        $items = scandir($currentPath);

        if($level === 0){

        }

        if($level < 8){
            if($level === 0){
                $progressBar->setMaxSteps($progressBar->getMaxSteps()+count($items)-2);
            }else{
                $progressBar->setMaxSteps($progressBar->getMaxSteps()+count($items)-3);
            }

            $level++;

            foreach($items as $item){
                if(str_starts_with($item, '.')){
                    continue;
                }

                $itemPath = $currentPath.DIRECTORY_SEPARATOR.$item;
                $this->getGuidsFromFolderStructure($itemPath, $guids, $progressBar, $level);
            }

            return;
        }

        foreach($items as $item) {
            if (str_starts_with($item, '.')) {
                continue;
            }

            if(str_ends_with(mb_strtolower($item), '.jpg')){
                $guids[] = $this->createGuidFromPath($currentPath);
                $progressBar->advance();
                return;
            }
        }
    }

    private function createGuidFromPath(string $path): string
    {
        $arr = explode(DIRECTORY_SEPARATOR, $path);
        $arrLength = count($arr);
        for($i = 8; $i < $arrLength; $i++){
            array_shift($arr);
        }

        $guid = array_shift($arr);
        $guid .= array_shift($arr);
        $guid .= '-'.array_shift($arr);
        $guid .= '-'.array_shift($arr);
        $guid .= '-'.array_shift($arr);
        $guid .= '-'.array_shift($arr);
        $guid .= array_shift($arr);
        $guid .= array_shift($arr);

        return $guid;
    }
}
