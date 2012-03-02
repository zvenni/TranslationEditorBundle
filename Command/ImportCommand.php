<?php

namespace ServerGrove\Bundle\TranslationEditorBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Translation\Loader\YamlFileLoader;

/**
 * Command for importing translation files
 */

class ImportCommand extends Base
{

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('locale:editor:import')
            ->setDescription('Import translation files into MongoDB for using through /translations/editor')
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
            $output->writeln("Importing translations from <info>$filename</info>...");
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
        $output->writeln(sprintf("Found %d files, importing...", count($files)));

        foreach ($files as $filename) {
            $this->import($filename);
        }

    }

    public function import($filename)
    {
        $fname = basename($filename);

        $this->output->writeln("Processing <info>" . $filename . "</info>...");

        list($name, $locale, $type) = explode('.', $fname);

        $this->setIndexes();

        switch ($type) {
            case 'yml':
                $m = $this->getContainer()->get('server_grove_translation_editor.storage_manager');
                $lib = $m->extractLib($filename);
                $entries = $this->concludeEntries($filename, $locale, $lib);

                $data = $m->getCollection()->findOne(array('filename' => $filename));

                if (!$data) {
                    $data = array(
                        'filename' => $filename,
                        "bundle" => $m->extractBundle($filename),
                        "dateImport" => new \DateTime(),
                        "lib" => $lib,
                        'locale' => $locale,
                        'type' => $type,
                        'entries' => $entries,
                    );

                }

                $this->output->writeln("  Found " . count($entries) . " entries...");
                if (!$this->input->getOption('dry-run')) {
                    $this->updateValue($data);
                }
                break;
            case 'xliff':
                $this->output->writeln("  Skipping, not implemented");
                break;
        }
    }

    private function concludeEntries($filename, $locale, $lib)
    {
        $yamlFileLoader = new YamlFileLoader();
        $entries = $yamlFileLoader->load($filename, $locale, $lib)->all($lib);
        return $entries;
    }

    protected function setIndexes()
    {
        $collection = $this->getContainer()->get('server_grove_translation_editor.storage_manager')->getCollection();
        $collection->ensureIndex(array("filename" => 1, 'locale' => 1));
    }

    protected function updateValue($data)
    {
        $collection = $collection = $this->getContainer()->get('server_grove_translation_editor.storage_manager')->getCollection();

        $criteria = array(
            'filename' => $data['filename'],
        );

        $mdata = array(
            '$set' => $data,
        );

        return $collection->update($criteria, $data, array('upsert' => true));
    }

}


