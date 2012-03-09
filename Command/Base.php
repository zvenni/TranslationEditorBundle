<?php

namespace ServerGrove\Bundle\TranslationEditorBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Finder\Finder;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Translation\Loader\YamlFileLoader;

/**
 */

class Base extends ContainerAwareCommand {
    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * has file been changed?
     * @param array $data
     * @return bool
     */
    protected function fileChangedAfterImport(array $data) {
        $dateImport = $data['dateImport'];
        $fileTime = file_exists($data['filename']) ? filemtime($data['filename']) : 0;
        $timezone = new \DateTimeZone($dateImport['timezone']);
        //set DT OBJS, use this way since constructor is buggy
        $dtImport = new \DateTime();
        $dtFile = new \DateTime();
        $dtImport->setTimezone($timezone);
        $dtFile->setTimezone($timezone);
        $dtImport->setTimestamp(strtotime($dateImport['date']));
        $dtFile->setTimestamp($fileTime);
        //File has been changed after import
        if( $dtFile > $dtImport ) {
            return true;
        }

        return false;
    }

    /**
     * @param which entries have entries beene changed?
     * @return array
     */
    protected function entryChangedAfterImport($data) {
        /** @var $m \ServerGrove\Bundle\TranslationEditorBundle\MongoStorageManager */
        $m = $this->getContainer()->get('server_grove_translation_editor.storage_manager');
        $locales = $m->getUsedLocales();
        $filename = $data['filename'];
        $difference = array();
        foreach( $locales as $locale ) {
            $lib = $m->extractLib($filename);
            $entries = $this->concludeEntries($filename, $locale, $lib);
            foreach( $entries as $key => $val ) {
                if( !isset($data['entries'][$key]) || $data['entries'][$key] != $val ) {
                    $difference[$key] = $key;
                }
            }
        }

        return $difference;
    }

    /**
     * has file been changed and if yes, have any entries been changed,
     * then collect the keys and output it with an exception
     *
     * @param string $filename
     * @throws \Exception
     */
    protected function syncFileForTransfer($filename) {
        /** @var $m \ServerGrove\Bundle\TranslationEditorBundle\MongoStorageManager */
        $m = $this->getContainer()->get('server_grove_translation_editor.storage_manager');
        $output = $this->output;
        $data = $m->getCollection()->findOne(array('filename' => $filename));

        //file changed where not empty
        if( $data && $this->fileChangedAfterImport($data) ) {
            //have entries been changed?
            if( $difference = $this->entryChangedAfterImport($data) ) {
                foreach( $difference as $key => $val ) {
                    $output->writeln("Key has changed since last Import: <error>" . $key . "</error>");
                }
                $output->writeln("<error>" . count($difference) . "</error> entries in <error>" . $data['bundle'] . "/" . $data['lib'] . "</error>");
                throw new \Exception("File '" . $data['filename'] . "' has directly been changed after last import. Resolve on reverting files and editing in TranslationEditor");
            }
        }

    }

    protected function getCSVFile($filename = "") {
        $path = $this->getContainer()->getParameter('kernel.root_dir') . '/logs/csv';
        if( !$filename ) {
            $filename = "lovoo_translation." . date("d.m.y") . ".csv";
        }

        return $path . "/" . $filename;
    }

    protected function concludeEntries($filename, $locale, $lib) {
        $yamlFileLoader = new YamlFileLoader();
        $entries = $yamlFileLoader->load($filename, $locale, $lib)->all($lib);
        return $entries;
    }

}
