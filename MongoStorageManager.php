<?php

namespace ServerGrove\Bundle\TranslationEditorBundle;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

class MongoStorageManager extends ContainerAware
{
    protected $mongo;

    protected $query;

    public function __construct(ContainerInterface $container)
    {
        $this->setContainer($container);
    }

    public function getLocales()
    {

    }

    private function getContainer()
    {
        return $this->container;
    }


    private function getQuery()
    {
        return $this->query;
    }

    private function setQuery(array $query)
    {
        $this->query = $query;
    }


    public function getMongo()
    {
        if (!$this->mongo) {
            $this->mongo = new \Mongo($this->container->getParameter('translation_editor.mongodb'));
        }

        if (!$this->mongo) {
            throw new \Exception("failed to connect to mongo");
        }

        return $this->mongo;
    }

    public function getDB()
    {
        return $this->getMongo()->translations;
    }

    public function getCollection()
    {
        return $this->getDB()->selectCollection($this->container->getParameter('translation_editor.collection'));
    }

    private function getCursor()
    {
        return $this->getCollection()->find($this->getQuery());

    }

    private function getFinder()
    {
        return new Finder();
    }

    private function getResults(array $query)
    {
        $this->setQuery($query);

        $cursor = $this->getCursor();

        $results = array();

        while ($cursor->hasNext()) {
            $results[] = $cursor->getNext();
        }

        return (array)$results;
    }

    private function getSourceDir()
    {
        return $this->getContainer()->getParameter('kernel.root_dir') . '/../src';
    }

    private function getTranslationFinder()
    {
        $finder = $this->getFinder();
        $finder->directories()->in($this->getSourceDir())->name('translations');

        return $finder;
    }

    public function getBundlesWithTranslations()
    {
        $finder = $this->getTranslationFinder();

        $bundles = array();
        foreach ($finder as $bundle) {
            $bundles[] = $this->extractBundle($bundle->getPath());
        }

        return $bundles;
    }


    public function getBundleLocales($bundle)
    {
        $dir = $this->getSourceDir() . "/Kiss/" . $bundle;
        $finder = $this->getFinder();
        $finder->directories()->in($dir)->name('translations');

        foreach ($finder as $dir) {

            $finder2 = $this->getFinder();
            $finder2->files()->in($dir->getRealpath())->name('*');

            $locales = array();
            foreach ($finder2 as $file) {
                $locale = $this->extractLocaleFromFilename($file->getFilename());

                if (!in_array($locale, $locales)) {
                    $locales[] = $locale;
                }
            }
        }

        return $locales;
    }

    private function extractLocaleFromFilename($filename)
    {
        $fileExplode = explode(".", $filename);
        end($fileExplode);
        return prev($fileExplode);
    }

    public function getFilesByBundle($bundle)
    {
        $query = array("bundle" => $bundle);
        $results = $this->getResults($query);

        $bundleFiles = array();

        foreach ($results as $key => $trlFile) {
            $lib = $trlFile['lib'];
            if (!in_array($lib, $bundleFiles)) {
                $bundleFiles[] = $lib;
            }

        }

        return $bundleFiles;
    }

    public function getEntriesByBundleAndLib($bundle, $lib)
    {
        $query = array("bundle" => $bundle, "lib" => $lib);

        $results = $this->getResults($query);
        #td($results);
        return $results;
    }


    private function getDefaultLanguage()
    {
        return $this->getContainer()->getParameter('locale', 'de');
    }

    public function getEntriesByBundleAndLibPrepared($bundle, $lib)
    {
        $locales = $this->getBundleLocales($bundle);
        $data = $this->getEntriesByBundleAndLib($bundle, $lib);

        $keyAr = array();
        foreach ($data as $d) {
            $entries = $d['entries'];
            $dloc = $d['locale'];
            if (is_array($entries)) {
                foreach ($entries as $key => $entry) {
                    $keyAr[$key][$dloc] = $entry;
                }
            }
        }

        $missing = array();
        $default = $this->getDefaultLanguage();
        $missingCount = 0;
        foreach ($locales as $locale) {
            foreach ($keyAr as $key => $entry) {
                #   td($entries);
                if (!isset($entry[$locale]) || !$entry[$locale]) {
                    $keyAr[$key][$locale] = null;
                    if ($locale != $default) {
                        $missingCount++;
                        $missing['entries'][$key] = $key;

                    }
                }
            }
        }
        $missing['all'] = $missingCount;
        $returnAr['default'] = $default;
        $returnAr['missing'] = $missing;
        $returnAr['lib'] = $lib;
        $returnAr['bundle'] = $bundle;
        $returnAr['locales'] = $locales;
        $returnAr['entries'] = $keyAr;

        return $returnAr;
    }

    public function getEntriesByBundleAndLocalAndLib($bundle, $locale, $lib)
    {
        $query = array("bundle" => $bundle, "locale" => $locale, "lib" => $lib);
        $this->setQuery($query);
        $result = $this->getCollection()->findOne($this->getQuery());

        return $result;
    }

    public function extractBundle($filename)
    {
        preg_match("/[^\/]*Bundle/", $filename, $bundle);
        return $bundle[0];
    }

    public function extractLib($filename)
    {
        preg_match("/[^\/]+$/", $filename, $file);
        list($lib, $a, $b) = explode('.', $file[0]);
        return $lib;
    }


}
