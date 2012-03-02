<?php

namespace ServerGrove\Bundle\TranslationEditorBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Dumper;

/**
 * Command for exporting translations into files
 */

class ExportCommand extends Base
{

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('locale:editor:export')
            ->setDescription('Export translations into files')
            ->addArgument('filename')
            ->addOption("dry-run");

    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $filename = $input->getArgument('filename');

        $files = array();

        if (!empty($filename) && is_dir($filename)) {
            $output->writeln("Exporting translations to <info>$filename</info>...");
            $finder = new Finder();
            $finder->files()->in($filename)->name('*');

            foreach ($finder as $file) {
                $output->writeln("Found <info>" . $file->getRealpath() . "</info>...");
                $files[] = $file->getRealpath();
            }

        } else {
            $dir = $this->getContainer()->getParameter('kernel.root_dir') . '/../src';

            $output->writeln("Scanning " . $dir . "...");
            $finder = new Finder();
            $finder->directories()->in($dir)->name('translations');

            foreach ($finder as $dir) {
                $finder2 = new Finder();
                $finder2->files()->in($dir->getRealpath())->name('*');
                foreach ($finder2 as $file) {
                    $output->writeln("Found <info>" . $file->getRealpath() . "</info>...");
                    $files[] = $file->getRealpath();
                }
            }
        }

        if (!count($files)) {
            $output->writeln("<error>No files found.</error>");
            return;
        }
        $output->writeln(sprintf("Found %d files, exporting...", count($files)));

        foreach ($files as $filename) {
            $this->export($filename);
        }

    }

    public function export($filename)
    {
        $fname = basename($filename);
        $this->output->writeln("Exporting to <info>" . $filename . "</info>...");

        list($name, $locale, $type) = explode('.', $fname);
        $m = $this->getContainer()->get('server_grove_translation_editor.storage_manager');
        switch ($type) {
            case 'yml':
                $data = $m->getCollection()->findOne(array('filename' => $filename));
                if (!$data) {
                    throw new \Exception("Could not find data for this locale");
                    return;
                }
                if ($this->fileChangedAfterImport($data)) {
                    throw new \Exception("File '" . $data['filename'] . "' has been changed after import. Resolve conflict on tool!");
                    return;
                }

                foreach ($data['entries'] as $key => $val) {
                    if (empty($val)) {
                        unset($data['entries'][$key]);
                    }
                }

                $dumper = new Dumper();
                $result = $dumper->dump($data['entries'], 1);

                $this->output->writeln("  Writing " . count($data['entries']) . " entries to $filename");
                if (!$this->input->getOption('dry-run')) {
                    file_put_contents($filename, $result);
                }
                //update Date for synchonization
                $data['dateImport'] = new \DateTime();
                $m->updateData($data);

                break;
            case 'xliff':
                $this->output->writeln("  Skipping, not implemented");
                break;
        }
    }

    private function fileChangedAfterImport($data)
    {
        $dateImport = $data['dateImport'];

        $fileTime = filemtime($data['filename']);
        $timezone = new \DateTimeZone($dateImport['timezone']);
        //set DT OBJS, use this way since constructor is buggy
        $dtImport = new \DateTime();
        $dtFile = new \DateTime();
        $dtImport->setTimezone($timezone);
        $dtFile->setTimezone($timezone);
        $dtImport->setTimestamp(strtotime($dateImport['date']));
        $dtFile->setTimestamp($fileTime);
        //File has been changed after import
        if ($dtFile > $dtImport) {
            return true;
        }

        return false;
    }


}
