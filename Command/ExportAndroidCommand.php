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
class ExportAndroidCommand extends Base {

    private $platform = "android";

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
                #   throw new \Exception("File '" . $lib . "' has directly been changed after last import. Resolve on reverting files and editing in TranslationEditor");
                #   return;
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

            $xml = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
            $xml .= "<resources>\n";
            foreach( $results as $data ) {
                if( !isset($data['entries'][$locale]) || empty($data['entries'][$locale]) ) {
                    continue;
                }

                $count++;
                $key = $data['keyOrig'];
                $trl = isset($data['entries'][$locale]) ? $data['entries'][$locale] : "";
                //wergen späterem simplexml import, sonst knallts wieder bei & zeichen ....
                $trl = preg_replace("/&/", "&amp;", $trl);
              #  $trl = utf8_encode($trl);

                $xml .= "   <string name=\"" . $key . "\">" . $trl . "</string>\n";
            }
            $xml .= "</resources>";
          # if ( $locale =="de") td($xml);




            if( !$this->input->getOption('dry-run') ) {
                file_put_contents($filename, $xml);
            }
            $this->output->writeln("Exporting to <info>" . $filename . "</info> completed. Entries: " . $count);
        }
    }


    /**
     *
     * Erstelle Filecontent über pühp simplexml - kommt leider ni mit utf und "&" klar!
     *
     *
     * @param $lib
     * @return mixed
     * @throws \Exception
     */
    public function __export($lib) {


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
            $xml = new \SimpleXMLElement('<resource/>');
            $xml->addAttribute('encoding', 'utf-8');
            foreach( $results as $data ) {
                $count++;
                $key = $data['keyOrig'];
                $trl = isset($data['entries'][$locale]) ? $data['entries'][$locale] : "";
                #$trl = utf8_encode($trl);
                try {
                    $child = $xml->addChild("string", $trl);
                } catch( \ErrorException $e ) {
                    //Bei & zeichen
                    $child = $xml->addChild("string", utf8_encode($trl));
                }

                $child->addAttribute('name', $key);


                //update Date neu setzen for synchonization
                $data['dateImport'] = $dt;
                $m->updateData($data);
            }

            if( !$this->input->getOption('dry-run') ) {
                //utf 8 wird trotzdem nicht genommen ....
                $dom = new \DOMDocument('1.0', "utf-8");
                $dom->preserveWhiteSpace = false;
                $dom->formatOutput = true;
                $dom->loadXML($xml->asXML());
                //Save XML to file - remove this and following line if save not desired
                $dom->save($filename);
            }
            $this->output->writeln("Exporting to <info>" . $filename . "</info> completed. Entries: " . $count);
        }
    }

}
