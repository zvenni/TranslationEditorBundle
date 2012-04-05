<?php

namespace ServerGrove\Bundle\TranslationEditorBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Translation\Loader\YamlFileLoader;

/**
 * Command for zipping translation files
 */
class ZipCommand extends Base {

    private $platform;

    private $zip;

    private $platformAr = array("webs",
                                "iphone",
                                "android");

    protected function configure() {
        parent::configure();
        $this->setName('locale:editor:zip')->setDescription('Zip translation files')->addArgument('filename')->addOption("dry-run");
    }

    public function execute(InputInterface $input, OutputInterface $output) {
        $this->input = $input;
        $this->output = $output;

        $filename = $input->getArgument('filename');


        $files = array();

        $this->zip = new  \ZipArchive();
        $m = $this->getManager("webs");
        if( !$this->zip->open($m->getSourceDir() . "/../zip/translation.zip", \ZipArchive::OVERWRITE) ) {
            throw new \Exception("Cannot create archive");
        }

        if( !empty($filename) && is_dir($filename) ) {
            $output->writeln("Importing translations from <info>$filename</info>...");
            $finder = new Finder();
            $finder->files()->in($filename)->name('*');

            foreach( $finder as $file ) {
                $output->writeln("Found <info>" . $file->getRealpath() . "</info>...");
                $files[] = $file->getRealpath();
            }
            $count = count($files);
            $this->zipfiles($files);

        } else {
            $count = 0;
            foreach( $this->platformAr as $platform ) {
                $this->setPlatform($platform);
                $name = "*";
                switch( $this->platform ) {
                    case "webs":
                        $dir = $this->getContainer()->getParameter('kernel.root_dir') . '/../src';
                        $name = "translations";
                        break;
                    case "iphone":
                        $dir = $this->getContainer()->getParameter('kernel.root_dir') . '/Resources/translations/' . $this->platform;
                        break;
                    case "android":
                        $dir = $this->getContainer()->getParameter('kernel.root_dir') . '/Resources/translations/' . $this->platform;
                        break;
                    default:
                        throw new \Exception("Platform " . $this->platform . " does not exist");
                        break;
                }


                $output->writeln("Scanning " . $dir . " for platform " . $this->platform);
                $finder = new Finder();

                $finder->directories()->in($dir)->name($name);

                foreach( $finder as $dir ) {


                    $finder2 = new Finder();
                    $finder2->files()->in($dir->getRealpath())->name('*');
                    foreach( $finder2 as $file ) {

                        $output->writeln("Found <info>" . $file->getRealpath() . "</info>...");
                        $count++;
                        $files[] = $file->getRealpath();
                    }

                    $this->zipfiles($files);
                    unset($files);
                }
            }
        }


        $this->zip->close();
        $output->writeln("Zipping complete - <info>" . $count . " Files at all</info>");
    }

    private function zipfiles($files) {
        if( !count($files) ) {
            $this->output->writeln("<error>No files found for " . $this->platform . ".</error>");
            return;
        }
        $this->output->writeln(sprintf("Found %d " . $this->platform . " files, importing...", count($files)));
        $m = $this->getManager($this->platform);
        foreach( $files as $filename ) {
            $this->zip($filename);
        }


    }

    public function zip($filename) {
        $m = $this->getManager($this->platform);
        if( !$this->zip instanceof \ZipArchive ) {
            throw new \Exception("Zip Archive not implemented");
        }
        $this->output->writeln("Processing <info>" . $filename . "</info>...");
        switch( $this->platform ) {
            case "webs":
                $zipentry = "Kiss/" . $m->extractBundle($filename) . "/Resources/translations/" . $m->extractLocaleFromFilename($filename) . "/" . basename($filename);
                break;
            case "iphone":
                $folder = $m->extractLocaleFolderFromFilename($filename);
                $zipentry = "app/Resources/translations/" . $this->platform . "/" . $folder . "/" . basename($filename);

                break;
            case "android":
                $folder = $m->extractLocaleFolderFromFilename($filename);
                $zipentry = "app/Resources/translations/" . $this->platform . "/" . $folder . "/" . basename($filename);
                #td($zipentry);
                break;
            default:
                throw new \Exception("Platform " . $this->platform . " does not exist");
                break;
        }

        $this->zip->addFile($filename, $zipentry);
    }

    private function setPlatform($platform) {
        $this->platform = $platform;
    }

}


