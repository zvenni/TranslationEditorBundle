<?php

namespace ServerGrove\Bundle\TranslationEditorBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Finder\Finder;


/**
 * Command for importing translation files
 */

class ImportCSVCommand extends Base {

    protected function configure() {
        parent::configure();
        $this->setName('locale:editor:importcsv')->setDescription('Import translation CSV into MongoDB for using through /translations/editor')->addArgument('filename')->addOption("dry-run");
    }

    public function execute(InputInterface $input, OutputInterface $output) {

        throw new \Exception("SCRIPT NOT READY");

        $this->input = $input;
        $this->output = $output;
        if( !empty($filename) && is_dir($filename) ) {
            $filename = $this->getFilename($filename);

        } else {
            $filename = $this->getFilename();
        }
        //check file changes not necessary, overwrite existing keys in db, rest stays untouched
        $output->writeln("Scanning <info>$filename</info>...");
        $this->import($filename);
    }

    public function import($filename) {
        $fname = basename($filename);

        $this->output->writeln("Processing <info>" . $filename . "</info>...");

        list($name, $locale, $type) = explode('.', $fname);

        $this->setIndexes();

        switch( $type ) {
            case 'yml':
                $m = $this->getContainer()->get('server_grove_translation_editor.storage_manager');
                $lib = $m->extractLib($filename);
                $entries = $this->concludeEntries($filename, $locale, $lib);

                $data = $m->getCollection()->findOne(array('filename' => $filename));

                if( !$data ) {
                    $data = array('filename' => $filename,
                                  "bundle" => $m->extractBundle($filename),
                                  "dateImport" => new \DateTime(),
                                  "lib" => $lib,
                                  'locale' => $locale,
                                  'type' => $type,
                                  'entries' => $entries,);

                } elseif( $data && $this->fileChangedAfterImport($data) ) {
                    throw new \Exception("File '" . $data['filename'] . "' has directly been changed after last import. Resolve on reverting files and editing in TranslationEditor");
                    return;
                }
                $this->output->writeln("  Found " . count($entries) . " entries...");
                if( !$this->input->getOption('dry-run') ) {
                    $this->updateValue($data);
                }
                break;
            case 'xliff':
                $this->output->writeln("  Skipping, not implemented");
                break;
        }
    }



    protected function setIndexes() {
        $collection = $this->getContainer()->get('server_grove_translation_editor.storage_manager')->getCollection();
        $collection->ensureIndex(array("filename" => 1,
                                      'locale' => 1));
    }

    protected function updateValue($data) {
        $collection = $collection = $this->getContainer()->get('server_grove_translation_editor.storage_manager')->getCollection();

        $criteria = array('filename' => $data['filename'],);

        $mdata = array('$set' => $data,);

        return $collection->update($criteria, $data, array('upsert' => true));
    }

}


