<?php
/**
 * Dresden, 2012-03-06 LOVOO
 * Sven Schwerdtfeger <sven@dampfer.net>
 */

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
class ExportCSVCommand extends Base {

    private $content = array();
    private $locales = array();

    private $platform = "webs";

    protected function configure() {
        parent::configure();
        $this->setName('locale:editor:exportcsv')->setDescription('Export translations into csv file')->addArgument('filename')->addOption("dry-run");
    }

    public function execute(InputInterface $input, OutputInterface $output) {
        //partams
        $this->input = $input;
        $this->output = $output;
        $m = $this->getManager($this->platform);
        //fetch results
        $results = $m->getAll();
        //nothing to do
        if( !$results ) {
            $output->writeln("<error>No files found.</error>");
            return;
        }
        //check file changes
        $output->writeln(sprintf("Check files on changes since last import before starting CSV export..."));
        foreach( $results as $data ) {
            if( $this->fileChangedAfterImport($data) ) {
                throw new \Exception("File '" . $data['filename'] . "' has directly been changed after last import. Resolve on reverting files and editing in TranslationEditor");
                return;
            }
        }
        unset($data);
        //start
        $this->locales = $m->getUsedLocales();

        $output->writeln(sprintf("Found %d files, exporting...", count($results)));
        $output->writeln("Exporting translations Database to CSV...");
        $this->export();
    }


    public function export() {
        /** @var $m \ServerGrove\Bundle\TranslationEditorBundle\WebsManager */
        $m = $this->getManager($this->platform);
        //nüscht Gewordetes gefunden;
        if( !$bundles = $m->getBundlesWithTranslations() ) {
            throw new \Exception("No translation bundles");
            return;
        }
        //file creating
        $path = $this->getContainer()->getParameter('kernel.root_dir') . '/logs/csv';
        $filename = "lovoo_translation." . date("d.m.y") . ".csv";
        $file = fopen($path . "/" . $filename, "w+");
        //csv table header
        $head[] = "key";
        $locales = $m->getUsedLocales();
        $delimiter = ";";
        foreach( $locales as $locale ) {
            $head[] = $locale;
        }
        $head[] = "bundle";
        $head[] = "lib";
        fputcsv($file, $head, $delimiter);
        //csv tzable content -all bundles
        $entriesCount = 0;
        foreach( $bundles as $bundle ) {
            //all libs in bundlue
            $libs = $m->getFilesByBundle($bundle);
            foreach( $libs as $lib ) {
                $this->output->writeln($bundle . "::" . $lib);
                // all lenguages for entry
                $prepared = $m->getEntriesByBundleAndLibPrepared($bundle, $lib);
                foreach( $prepared['entries'] as $key => $entry ) {
                    $csv[] = $key;
                    foreach( $prepared['entries'][$key] as $trl ) {
                       // $trl = utf8_encode($trl);
                        $csv[] = $trl ? $trl : "";
                    }
                    $csv[] = $bundle;
                    $csv[] = $lib;
                    fputcsv($file, $csv, $delimiter);
                    unset($csv);
                    $entriesCount++;
                }
            }
        }
        //succeeded
        fclose($file);
        $this->output->writeln("<info>CSV export successful - EntriesCount: "  . $entriesCount . "</info>");
    }


}
