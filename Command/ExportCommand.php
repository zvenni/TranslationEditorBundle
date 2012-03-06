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
class ExportCommand extends Base {

    protected function configure() {
        parent::configure();
        $this->setName('locale:editor:export')->setDescription('Export translations into files')->addArgument('filename')->addOption("dry-run");
    }

    public function execute(InputInterface $input, OutputInterface $output) {
        //partams
        $this->input = $input;
        $this->output = $output;
        $m = $this->getContainer()->get('server_grove_translation_editor.storage_manager');
        //fetch results
        $results = $m->getAll();
        //nothing to do
        if( !$results ) {
            $output->writeln("<error>No files found.</error>");
            return;
        }
        //check file changes
        $output->writeln(sprintf("Check files on changes since last import before starting export..."));
        foreach( $results as $data ) {
            if( $this->fileChangedAfterImport($data) ) {
                throw new \Exception("File '" . $data['filename'] . "' has directly been changed after last import. Resolve on reverting files and editing in TranslationEditor");
                return;
            }
        }
        unset($data);
        //start
        $output->writeln(sprintf("Found %d files, exporting...", count($results)));
        foreach( $results as $data ) {
            $filename = $m->libFileName($data['bundle'], $data['locale'], $data['lib']);
            $output->writeln("Scanning " . $filename . "...");
            //file exists
            if( is_dir($filename) ) {
                $output->writeln("Exporting translations to <info>$filename</info>...");
            } else {
                //file doesnst exist
                $output->writeln("Creating file <info>$filename</info>...");
                $output->writeln("Exporting translations to <info>$filename</info>...");
            }
            $output->writeln("Found <info>" . $filename . "</info>...");
            //deal entrys
            $this->export($data);
        }
    }


    public function export($data) {
        //invalid db entry
        if( !$data ) {
            throw new \Exception("Could not find data for this locale");
            return;
        }
        $filename = $data['filename'];
        $fname = basename($filename);
        $this->output->writeln("Exporting to <info>" . $filename . "</info>...");
        list($name, $locale, $type) = explode('.', $fname);
        $m = $this->getContainer()->get('server_grove_translation_editor.storage_manager');
        switch( $type ) {
            case 'yml':
                //destroy empty entries
                foreach( $data['entries'] as $key => $val ) {
                    if( empty($val) ) {
                        unset($data['entries'][$key]);
                    }
                }
                //yml parsing
                $dumper = new Dumper();
                $result = $dumper->dump($data['entries'], 1);
                //create or update file entries
                $this->output->writeln("  Writing " . count($data['entries']) . " entries to $filename");
                if( !$this->input->getOption('dry-run') ) {
                    file_put_contents($filename, $result);
                }
                //update Date for synchonization
                $data['dateImport'] = new \DateTime();
                $m->updateData($data);
                break;
            case 'xliff':
                $this->output->writeln("  Skipping, not implemented");
                break;
        }
    }

}
