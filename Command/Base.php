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

class Base extends ContainerAwareCommand
{
    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface
     */
    protected $output;

    protected function fileChangedAfterImport(array $data)
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
