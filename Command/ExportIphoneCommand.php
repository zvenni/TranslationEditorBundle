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
class ExportIphoneCommand extends Base {

    private $platform = "iphone";

    protected function configure() {
        parent::configure();
        $this->setName('locale:editor:export' . $this->platform)->setDescription('Export ' . ucfirst($this->platform) . ' translations into files')->addArgument('filename')->addOption("dry-run");
    }

    public function execute(InputInterface $input, OutputInterface $output) {
        //partams
        $this->input = $input;
        $this->output = $output;
        /** @var $m \ServerGrove\Bundle\TranslationEditorBundle\Manager\IphoneManager */
        $m = $this->getManager($this->platform);
        //nothing to do
        if( !$m->getCount(array("platform" => $this->platform)) ) {
            $output->writeln("<error>No files found.</error>");
            return;
        }
        //check file changes - neu einbauen
        $output->writeln(sprintf("Check files on changes since last import before starting export..."));
        $libs = $m->collectLibs();

        foreach( $libs as $lib ) {
            /*
             * import date für diese datenstruktur pro libfile gleich, daher reicht eins
             */
            $data = $m->getCollection()->findOne(array("lib" => $lib,
                                                      "platform" => $this->platform));
            if( $this->fileChangedAfterImportNew($data, $this->platform) ) {
                throw new \Exception("File '" . $lib . "' has directly been changed after last import. Resolve on reverting files and editing in TranslationEditor");
                return;
            }

        }

        //start
        $output->writeln(sprintf("Found %d files, exporting...", count($libs)));

        foreach( $libs as $lib ) {
            //deal entrys
            $this->export($lib);
        }

    }


    public function export($lib) {
        /** @var $m \ServerGrove\Bundle\TranslationEditorBundle\Manager\IphoneManager */
        $m = $this->getManager($this->platform);
        //$limit ausschalten, sonst nicht alles
        $m->setLimit(null);
        $results = $m->getResultsByLib($lib);
        //invalid db entry
        if( !$results ) {
            throw new \Exception("Could not find any data for " . ucfirst($lib));
            return;
        }
        $locales = $m->getUsedLocales();
        foreach( $locales as $locale ) {
            $dt = new \DateTime();
            $filename = $m->getFilenameForLibAndLocale($lib, $locale);
            $content = array();
            $count = 0;
            foreach( $results as $data ) {
                if( !isset($data['entries'][$locale]) || empty($data['entries'][$locale]) ) {
                    continue;
                }
                $count++;
                $key = $data['keyOrig'];
                $trl = $data['entries'][$locale];
                $line = "\"" . $key . "\" = \"" . $trl . "\";\n";
                $content[] = $line;
                //update Date neu setzen for synchonization
                $data['dateImport'] = $dt;
                $m->updateData($data);
            }

            if( !$this->input->getOption('dry-run') ) {
                file_put_contents($filename, $content);
            }
            $this->output->writeln("Exporting to <info>" . $filename . "</info> completed. Entries: " . $count);

        }
    }

}
