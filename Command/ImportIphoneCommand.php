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
class ImportIphoneCommand extends Base {

    private $platform = "iphone";

    protected function configure() {
        parent::configure();
        $this->setStartTime();
        $this->setName('locale:editor:import' . $this->platform)->setDescription('Import ' . ucfirst($this->platform) . ' translation files into MongoDB for using through /translations/editor')->addArgument('filename')->addOption("dry-run");
    }

    public function execute(InputInterface $input, OutputInterface $output) {
        $this->input = $input;
        $this->output = $output;

        $filename = $input->getArgument('filename');
        $files = array();
        /** @var $m \ServerGrove\Bundle\TranslationEditorBundle\IphoneManager */
        $m = $this->getManager($this->platform);
        if( !empty($filename) && is_dir($filename) ) {
            $files[] = $filename;
        } else {
            //ale Sprachen holen
            $locales = $m->getUsedLocales();

            foreach( $locales as $locale ) {
                //alle Libs
                $filename = $m->getFilenameForLocale($locale);
                $lib = $m->extractLib($filename);
                if( !in_array($lib, $files) ) {
                    $files[] = $lib;
                }
            }
        }

        if( !count($files) ) {
            $output->writeln("<error>No files found.</error>");
            return;
        }

        $output->writeln(sprintf("Found %d files, importing...", count($files)));

        foreach( $files as $lib ) {
            $this->import($lib);
        }
    }

    public function import($lib) {
        $this->output->writeln("Processing <info>" . $lib . "</info>...");
        $this->setIndexes();
        /** @var $m \ServerGrove\Bundle\TranslationEditorBundle\IphoneManager */
        $m = $this->getManager($this->platform);
        $locales = $m->getUsedLocales();
        $dt = new \DateTime();

        foreach( $locales as $locale ) {

            $content = $m->parseContent($lib, $locale);

            foreach( $content as $cnt ) {
                $translation = array();
                $this->output->writeln("Writing entry <info>'" . $cnt['key'] . "'</info>...");
                $key = $cnt['key'];
                $shaked = $m->shakeYaBoody($key);
                $data = $m->getCollection($this->platform)->findOne(array('lib' => $lib,
                                                                         "platform" => $this->platform,
                                                                         "key" => $shaked));


                if( !$data ) {

                    $this->output->writeln("Key not found - <info>install</info>...");
                    $filename = $m->getFilenameForLibAndLocale($lib, $locale);
                    $translation[$locale] = $cnt['trl'];
                    $data = array("filename" => $filename,
                                  "platform" => $this->platform,
                                  "entries" => $translation,
                                  "info" => array(),
                                  "lib" => $lib,
                                  "type" => $m->extractType($filename),
                                  "key" => $shaked,
                                  "keyOrig" => $key,
                                  "dateImport" => $dt,
                                  "dateUpdate" => $dt);

                    /*if( $locale == "en" ) {
                        $data['entries'][$locale] = "";
                    }*/

                    #} elseif( $data && $this->fileChangedAfterImport($data) ) {
                    #  throw new \Exception("File '" . $data['filename'] . "' has directly been changed after last import . Resolve on reverting files and editing in TranslationEditor");
                    #   return;
                } else {


                    $this->output->writeln("Key found - <info> (" . $locale . ")" . $data['keyOrig'] . "</info>...");
                    $data["dateImport"] = $dt;
                    if( !isset($data['entries'][$locale]) || !$data['entries'][$locale] ) {
                        $data['entries'][$locale] = $cnt['trl'];
                    }

                }
                if( !$this->input->getOption('dry-run') ) {

                    $this->updateValue($data);
                }
            }
        }

        $this->output->writeln("Import Complete - Locales: = <info>" . implode(", ", $locales) . "</info>...");
        $this->output->writeln("Import Complete (" . $this->commandTime() . " seconds) - #Entries = <info>" . $m->getCount(array()));
        #$this->output->writeln("Import Complete (" . $this->commandTime() . " seconds) - #Entries = <info>" . $m->getCount(array()) . "</info> with totally <info>" . $m->countWords() . " Words </info>");
    }


    private function concludeEntries($filename, $locale, $lib) {
        $yamlFileLoader = new YamlFileLoader();
        $entries = $yamlFileLoader->load($filename, $locale, $lib)->all($lib);
        return $entries;
    }

    protected function setIndexes() {
        $collection = $this->getManager($this->platform)->getCollection();
        $collection->ensureIndex(array("platform" => 1,
                                      "key" => 1));


    }

    protected function updateValue($data) {
        $collection = $collection = $this->getManager($this->platform)->getCollection();
        $criteria = array("key" => $data["key"],
                          "lib" => $data['lib'],
                          "platform" => $data['platform']);

        return $collection->update($criteria, $data, array('upsert' => true));
    }

}
