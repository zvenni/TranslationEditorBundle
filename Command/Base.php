<?php

namespace ServerGrove\Bundle\TranslationEditorBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Finder\Finder;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

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

    private $time;

    protected function getManager($platform = "webs") {
        return $this->getContainer()->get('server_grove_translation_editor.' . $platform . '_manager');
    }

    protected function commandTime() {
        return intval(time()) - intval($this->time);
    }

    protected function  setStartTime() {
        $this->time = time();
    }

    /*
     * neue db struktur mit einzelenn entries (iphone, android)
     */
    protected function fileChangedAfterImportNew($data, $platform) {

        /** @var $m \ServerGrove\Bundle\TranslationEditorBundle\Manager\IphoneManager */
        $m = $this->getManager($platform);
        //alle locales durchgehen und nach changes gucken
        $locales = $m->getUsedLocales();
        foreach( $locales as $locale ) {
            $filename = $m->getFilenameForLibAndLocale($data['lib'], $locale);
            $dateImport = $data['dateImport'];
            $fileTime = file_exists($filename) ? filemtime($filename) : 0;
            $timezone = new \DateTimeZone($dateImport['timezone']);
            //set DT OBJS, use this way since constructor is buggy
            $dtImport = new \DateTime();
            $dtFile = new \DateTime();
            $dtImport->setTimezone($timezone);
            $dtFile->setTimezone($timezone);
            $dtImport->setTimestamp(strtotime($dateImport['date']));
            $dtFile->setTimestamp($fileTime);
            //File has been changed after import
            # td($dtFile);
            if( $dtFile > $dtImport ) {
                return true;
            }
        }
        return false;

    }


    protected function fileChangedAfterImport(array $data, $platform = "webs") {

        if( $platform != "webs" ) {
            return $this->fileChangedAfterImportNew($data, $platform);
        }

        /*
        * neue db struktur mit einzelenn entries (iphone, android)
        */
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
        # td($dtFile);
        if( $dtFile > $dtImport ) {
            return true;
        }

        return false;
    }

}
