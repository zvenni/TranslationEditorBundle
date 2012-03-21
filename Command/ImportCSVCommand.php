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

    private $platform = "webs";

    protected function configure() {
        parent::configure();
        $this->setName('locale:editor:importcsv')->setDescription('Import translation CSV into MongoDB for using through /translations/editor')->addArgument('filename')->addOption("dry-run");
    }

    public function execute(InputInterface $input, OutputInterface $output) {
        $this->input = $input;
        $this->output = $output;
        $filename = $input->getArgument('filename');

        if( !empty($filename) ) {
            $filename = $this->getCSVFile($filename);
        } else {
            $filename = $this->getCSVFile();
        }


        $this->import($filename);
    }

    public function import($filename) {
        $fname = basename($filename);
        $output = $this->output;
        //checking file changes not necessary, overwrite existing keys in db, rest stays untouched
        $output->writeln("Scanning <info> . $filename . </info>...");
        if( !$file = fopen($filename, "r+") ) {
            throw new \Exception("CSV file '" . $filename . "' not found");
        }
        $delimiter = ";";
        //data keys
        $head = fgetcsv($file, 0, $delimiter);
        $csvContent = array();
        //create named data collection
        while( $data = fgetcsv($file, 0, $delimiter) ) {
            $csv = array();
            foreach( $head as $index => $key ) {
                $csv[$key] = $data[$index];
            }
            $csvContent[] = $csv;
        }
        //do not import empty stuff
        if( !$csvContent ) {
            throw new \Exception("Empty CSV file '" . $filename . "'");
        }
        $this->setIndexes();


        /** @var $m \ServerGrove\Bundle\TranslationEditorBundle\MongoStorageManager */
        $m = $this->getManager($this->platform);
        $locales = $m->getUsedLocales();
        $changedKeys = 0;
        #td($csvContent);
        foreach( $csvContent as $data ) {
            foreach( $locales as $locale ) {
                $changedKeys++;
                if( !isset($data[$locale]) ) {
                    throw \Exception("Locale '" . $locale . "' not synced");
                }

                //just update changed or new keys;
                $key = $data['key'];

                $entry = $data[$locale];
                $dbData = $m->getEntriesByBundleAndLocalAndLib($data['bundle'], $locale, $data['lib']);
                if( !$dbData ) {
                    $updateData = array('filename' => $m->createFilename($locale, $data['lib']),
                                        "bundle" => $data['bundle'],
                                        "dateImport" => new \DateTime(),
                                        "lib" => $data['lib'],
                                        'locale' => $locale,
                                        'type' => "yml",
                                        'entries' => array($key => $entry));
                 #   $m->insertData($updateData);
                    $changedKeys++;
                }
                if( !isset($dbData['entries'][$key]) || (isset($dbData['entries'][$key]) && $dbData['entries'][$key] != $entry) ) {
                    $updateData['_id'] = $dbData['_id'];
                    $updateData['entries'][$key] = $entry;
                    $updateData['dateImport'] = new \DateTime();
                    #   $m->updateData($updateData);
                    $changedKeys++;
                }
            }
        }
        $output->writeln("CSV import complete! Changed Keys: <info>" . $changedKeys . "</info>...");
    }


    protected function setIndexes() {
        $collection = $this->getManager($this->platform)->getCollection();
        $collection->ensureIndex(array("filename" => 1,
                                      'locale' => 1));
    }

    protected function updateValue($data) {
        $collection = $this->getManager($this->platform)->getCollection();

        $criteria = array('filename' => $data['filename'],);

        return $collection->update($criteria, $data, array('upsert' => true));
    }


}


