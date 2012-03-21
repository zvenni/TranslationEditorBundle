<?php

namespace ServerGrove\Bundle\TranslationEditorBundle;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

class IphoneStorageManager extends ContainerAware {

    ############################################################################
    ########################      CLASS     ####################################
    ############################################################################

    protected $mongo;

    protected $query = array();

    public function __construct(ContainerInterface $container) {
        $this->setContainer($container);
    }

    private function getContainer() {
        return $this->container;
    }


    private function getDefaultLanguage() {
        //if english is needed
        #return $this->container->getParameter('locale', 'en');
        return $this->getContainer()->get("service_container")->getParameter("kernel.default_locale");
    }

    ############################################################################
    ########################      db        ####################################
    ############################################################################

    public function getMongo() {
        if( !$this->mongo ) {
            $this->mongo = new \Mongo($this->container->getParameter('translation_editor.mongodb'));
        }
        if( !$this->mongo ) {
            throw new \Exception("failed to connect to mongo");
        }

        return $this->mongo;
    }

    private function getDB() {
        return $this->getMongo()->translations;
    }

    public function getCollection() {
        return $this->getDB()->selectCollection($this->container->getParameter('translation_editor.collection'));
    }

    private function getCursor() {
        return $this->getCollection()->find($this->getQuery());
    }

    private function getCount(array $query) {
        $this->setQuery($query);
        return $this->getCursor()->count();
    }

    private function getQuery() {
        return $this->query;
    }

    private function setQuery(array $query) {
        $this->query = $query;
    }

    private function getFinder() {
        return new Finder();
    }

    private function getResults($query = array()) {
        $this->setQuery((array)$query);
        $cursor = $this->getCursor();

        $results = array();
        while( $cursor->hasNext() ) {
            $results[] = $cursor->getNext();
        }

        return (array)$results;
    }

    public function updateData(array $data) {
        $this->getCollection()->update(array('_id' => $data['_id']), $data, array('upsert' => true));
    }

    public function insertData(array$data) {
        $this->getCollection()->insert($data);
    }

    public function getAll() {
        return $this->getResults();
    }

    ############################################################################
    ####################     FILE           ####################################
    ############################################################################

    private function getSourceDir() {
        return $this->getContainer()->getParameter('kernel.root_dir') . '/../src';
    }



    private function getTranslationFinder($path = "") {
        $finder = $this->getFinder();
        if( !$path ) {
            $path = $this->getSourceDir();
        }
        $finder->directories()->in($path)->name('translations');
        return $finder;
    }

    public function libFileName($bundle, $locale, $lib) {
        $dir = $this->getSourceDir() . "/Kiss/" . $bundle;
        $finder = $this->getFinder();
        $finder->in($dir)->exclude("views")->exclude("public")->name("translations")->name($locale);

        foreach( $finder as $dir ) {
            $path = $dir->getPathname() . "/" . $this->createFilename($locale, $lib);
            continue;
        }

        return $path;
    }

    public function libExists($bundle, $locale, $lib) {
        # td($this->createLibPath($bundle, $locale, $lib));

        $return = file_exists($this->libFileName($bundle, $locale, $lib)) ? true : false;

        return $return;
    }

    public function createFilename($locale, $lib, $type = "yml") {
        return $lib . "." . $locale . "." . $type;
    }

    ############################################################################
    ########################    parse/check          ###########################
    ############################################################################

    private function isAlphabetic($key) {
        //all signs but alphabetics/whitespace
        $forbiddenSigns = "/[^a-zA-z\s]+/";
        if( preg_match($forbiddenSigns, $key) ) {
            return false;
        }

        return true;
    }

    public function extractLib($filename) {
        preg_match("/[^\/]+$/", $filename, $file);
        list($lib, $a, $b) = explode('.', $file[0]);
        return $lib;
    }

    public function extractBundle($filename) {
        preg_match("/[^\/]*Bundle/", $filename, $bundle);
        return $bundle[0];
    }

    private function extractLocaleFromFilename($filename) {
        $fileExplode = explode(".", $filename);
        end($fileExplode);
        return prev($fileExplode);
    }

    ############################################################################
    ########################    data                ############################
    ############################################################################

    public function getBundlesWithTranslations() {
        $finder = $this->getTranslationFinder();

        $bundles = array();
        foreach( $finder as $bundle ) {
            $bundles[] = $this->extractBundle($bundle->getPath());
        }
        natcasesort($bundles);

        return $bundles;
    }

    public function getUsedLocales() {
        $dir = $this->getSourceDir() . "/Kiss";
        $finder = $this->getFinder();
        $finder->directories()->in($dir)->name('translations');

        foreach( $finder as $dir ) {

            $finder2 = $this->getFinder();
            $finder2->files()->in($dir->getRealpath())->name('*');

            $locales = array();
            foreach( $finder2 as $file ) {
                $locale = $this->extractLocaleFromFilename($file->getFilename());
                if( !in_array($locale, $locales) ) {
                    $locales[] = $locale;
                }
            }
        }

        return $locales;
    }



    public function getFileOverviewByBundle($bundle) {
        $bundleFiles = $this->getFilesByBundle($bundle);
        $overviewAr = array();
        foreach( $bundleFiles as $lib ) {
            $fileOverview['lib'] = $lib;
            $fileOverview['entryCount'] = $this->getEntriesCountByBundleAndLib($bundle, $lib);
            $overviewAr[] = $fileOverview;
        }

        return $overviewAr;
    }

    public function getFilesByBundle($bundle) {
        $query = array("bundle" => $bundle);
        $results = $this->getResults($query);

        $bundleFiles = array();

        foreach( $results as $key => $trlFile ) {
            $lib = $trlFile['lib'];
            if( !in_array($lib, $bundleFiles) ) {
                $bundleFiles[] = $lib;
            }

        }
        natcasesort($bundleFiles);

        return $bundleFiles;
    }

    public function getEntriesCountByBundleAndLib($bundle, $lib) {
        $results = $this->getEntriesByBundleAndLib($bundle, $lib);
        $count = 0;
        foreach( $results as $content ) {
            $count = max($count, count($content['entries']));
        }

        return $count;
    }

    public function getEntriesByBundleAndLibPrepared($bundle, $lib) {
        $data = $this->getEntriesByBundleAndLib($bundle, $lib);
        $trlEntryCollection = $this->prepareData($data);
        $missing = $this->getMissingOverview($trlEntryCollection);

        $default = $this->getDefaultLanguage();
        $locales = $this->getUsedLocales();

        $returnAr['default'] = $default;
        $returnAr['missing'] = $missing;
        $returnAr['lib'] = $lib;
        $returnAr['bundle'] = $bundle;
        $returnAr['locales'] = $locales;
        $returnAr['entries'] = $trlEntryCollection;

        return $returnAr;
    }

    public function getEntriesByBundleAndLocalAndLib($bundle, $locale, $lib) {
        $query = array("bundle" => $bundle,
                       "locale" => $locale,
                       "lib" => $lib);
        $this->setQuery($query);
        $result = (array)$this->getCollection()->findOne($this->getQuery());

        return $result;
    }

    public function getEntriesByBundleAndLib($bundle, $lib) {
        $query = array("bundle" => $bundle,
                       "lib" => $lib);
        $results = $this->getResults($query);

        return $results;
    }

    private function prepareData($data) {
        $trlEntryCollection = array();
        foreach( $data as $d ) {
            $entries = $d['entries'];
            $dloc = $d['locale'];
            if( is_array($entries) ) {
                foreach( $entries as $key => $entry ) {
                    $trlEntryCollection[$key][$dloc] = $entry;
                }
            }
        }

        return $trlEntryCollection;
    }

    private function findBundles($data) {
        $returnAr = array();
        foreach( $data as $d ) {
            $bundle = $d['bundle'];
            if( !in_array($bundle, $returnAr) ) {
                $returnAr[] = $bundle;
            }
        }

        return $returnAr;
    }


    ############################################################################
    ########################    data - missing   ###############################
    ############################################################################

    public function getAllMissingEntriesPrepared() {
        $missing = $this->prepareMissingDataGlobal();
        $default = $this->getDefaultLanguage();
        $locales = $this->getUsedLocales();

        $returnAr['entriesCount'] = $this->countMissing($missing);
        $returnAr['locales'] = $locales;
        $returnAr['default'] = $default;
        $returnAr['entries'] = $missing;

        return $returnAr;
    }

    private function prepareMissingDataGlobal() {
        $data = $this->getResults();
        $bundleAr = $this->findBundles($data);
        foreach( $bundleAr as $bundle ) {
            $fileAr[$bundle] = $this->getFilesByBundle($bundle);
        }
        $returnMissing = array();
        foreach( $fileAr as $bundle => $libs ) {
            foreach( $libs as $lib ) {
                $libEntries = $this->getEntriesByBundleAndLibPrepared($bundle, $lib);
                if( $libEntries = $this->extractFullfilledEntries($libEntries) ) {
                    $returnMissing[] = $libEntries;
                }
            }
        }

        return $returnMissing;
    }

    private function extractFullfilledEntries($trlEntryCollection) {
        foreach( $trlEntryCollection['entries'] as $key => $val ) {
            if( !isset($trlEntryCollection['missing']['entries'][$key]) ) {
                unset($trlEntryCollection['entries'][$key]);
            }
        }

        $trlEntryCollection['all'] = $trlEntryCollection['missing']['all'];
        unset($trlEntryCollection['default']);
        unset($trlEntryCollection['missing']);
        unset($trlEntryCollection['locales']);
        unset($trlEntryCollection['locales']);

        return $trlEntryCollection;
    }

    private function countMissing(array$missing) {
        $count = 0;
        foreach( $missing as $miss ) {
            $count += $miss['all'];
        }

        return $count;
    }

    private function getMissingOverview(&$trlEntry) {
        $default = $this->getDefaultLanguage();
        $locales = $this->getUsedLocales();
        $missing = array();
        $missingCount = 0;
        foreach( $trlEntry as $key => $entry ) {

            foreach( $locales as $locale ) {
                if( !isset($entry[$locale]) || !$entry[$locale] ) {
                    $trlEntry[$key][$locale] = null;
                    if( $locale != $default || !$this->isAlphabetic($key) ) {

                        $missingCount++;
                        $missing['entries'][$key] = $key;

                    }
                }
            }
        }
        $missing['all'] = $missingCount;

        return $missing;
    }

}
