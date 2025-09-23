<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:file:create:guid',
    description: 'Creates GUIDs from the given data directory (based on IIIF server data folder structure)',
)]
class FileCreateGuidCommand extends Command
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('data-directory', InputArgument::REQUIRED, 'Path to the IIIF Server data directory')
            ->addArgument('output-path', InputArgument::REQUIRED, 'Output path')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Generating GUIDS from data folders');

        $dataDirectory = rtrim($input->getArgument('data-directory'), DIRECTORY_SEPARATOR);
        $outputPath = $input->getArgument('output-path');

        $guids = [];
        $progressBar = new ProgressBar($output);
        $progressBar->start();
        $this->getGuidsFromFolderStructure($dataDirectory, $guids, $progressBar);
        $progressBar->finish();

        $io->newLine(2);
        file_put_contents($outputPath, json_encode($guids, JSON_THROW_ON_ERROR));

        $io->success('Guids written to '.$outputPath);

        return Command::SUCCESS;
    }

    private function getGuidsFromFolderStructure(string $currentPath, array &$guids, ProgressBar $progressBar, int $level = 0)
    {
        if(count($guids) >= 100){
            return;
        }

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
