<?php

namespace ServerGrove\Bundle\TranslationEditorBundle\Manager;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

class AndroidManager extends ContainerAware {

    ############################################################################
    ########################      CLASS     ####################################
    ############################################################################

    private $platform = "android";

    private $lib = "strings.xml";

    private $page = 1;

    private $limit = 50;

    private $paging = array();

    protected $mongo;

    protected $query = array();

    public function __construct(ContainerInterface $container) {
        $this->setContainer($container);
    }

    private function getContainer() {
        return $this->container;
    }

    public function setLib($lib) {
        $this->lib = $lib;
    }

    public function getLib($lib = "") {
        if( !$lib ) {
            $lib = $this->lib;
        }
        return $lib;
    }

    private function getFinder() {
        return new Finder();
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
        return $this->getDB()->selectCollection($this->container->getParameter('translation_editor.collection.' . $this->platform));
    }

    public function getPaging() {
        return $this->paging;
    }

    private function setPaging() {
        $limit = 50;
        $allCount = $this->getCount($this->getQuery());
        $skip = (int)($limit * ($this->getPage() - 1));

        $totalPages = ceil($allCount / $limit);
        $this->paging = array("limit" => $limit,
                              "totalPages" => $totalPages,
                              "page" => $this->getPage(),
                              "allCount" => $allCount,
                              "skip" => $skip);

    }

    private function setPage($page) {
        $this->page = $page;
    }

    private function getPage() {
        return $this->page;
    }

    private function getCursor() {
        //limit genullt, dann alles ohne limit/paging
        if( !$this->getLimit() ) {
            return $this->getCursorForAll();
        }

        $this->setPaging();
        $paging = $this->getPaging();
        return $this->getCollection()->find($this->getQuery())->limit($paging['limit'])->skip($paging['skip']);
    }

    /**
     * no no limit
     *
     * @return mixed
     */
    private function getCursorForAll() {
        return $this->getCollection()->find($this->getQuery());
    }

    public function getCount(array $query) {
        $this->setQuery($query);
        return $this->getCursorForAll()->count();
    }

    private function getQuery() {
        return $this->query;
    }

    private function setQuery(array $query) {
        $this->query = $query;
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

    public function insertData(array $data) {
        $this->getCollection()->insert($data);
    }

    public function getAll() {
        return $this->getResults();
    }

    private function remove(array $data) {
        $id = new \MongoId($data["_id"]->__toString());
        $this->getCollection()->remove(array('_id' => $id), array("justOne" => true));
    }

    ############################################################################
    ########################    data                ############################
    ############################################################################

    public function collectLibs() {
        $this->setQuery(array("platform" => $this->platform));
        $cursor = $this->getCollection()->find($this->getQuery(), array("lib" => "distinct"));

        $results = array();
        while( $cursor->hasNext() ) {
            $lib = $cursor->getNext("lib");
            $lib = $lib['lib'];
            if( !in_array($lib, $results) ) {
                $results[] = $lib;
            }
        }

        return (array)$results;
    }

    public function getResultsByLib($lib = "") {
        if( !$lib ) {
            $lib = $this->getLib();
        }
        $query = array("lib" => $lib,
                       "platform" => $this->platform);
        return $this->getResults($query);
    }

    public function getEntryByLibAndKey($lib, $key) {
        $shakeYaBoody = $this->shakeYaBoody($key);
        $query = array("lib" => $lib,
                       "key" => $shakeYaBoody);
        $result = $this->getResults($query);
        return reset($result);
    }

    public function getEntriesByLibPrepared($lib, $page) {
        $this->setPage($page);
        $results = $this->getResultsByLib();
        $prepared = array("default" => $this->getDefaultLanguage(),
                          "lib" => $lib,
                          "locales" => $this->getUsedLocales());

        $entries = $this->manageEntries($results);
        $prepared['entries'] = $entries['entries'];
        $prepared['missing'] = $entries['missing'];

        return $prepared;
    }

    private function manageEntries($results) {
        $entries = array();
        $missing = array();
        $all = 0;

        foreach( $results as $data ) {
            $key = $data['keyOrig'];
            $entries[$key] = $data['entries'];
            $isAlpha = $this->isAlphabetic($key);
            if( $missed = $this->checkMissing($entries[$key]) ) {
                $missing['entries'][$key] = $isAlpha;
                $all++;
            }
        }
        $missing['all'] = $all;

        $return['missing'] = $missing;
        $return['entries'] = $entries;

        return $return;
    }

    public function removeEntry($lib, $key) {

        $entry = $this->getEntryByLibAndKey($lib, $key);

        $this->remove($entry);
    }

    public function shakeYaBoody($string) {
        return md5($string);
    }

    public function countWords($platform = "") {
        $locale = $this->getDefaultLanguage();
        $query = array();
        if( $platform ) {
            $query['platform'] = $platform;
        }
        $results = $this->getResults($query);
        $wordCount = 0;
        foreach( $results as $entry ) {
            $wordCount += str_word_count($entry['entries'][$locale]);
        }

        return $wordCount;
    }

############################################################################
########################    data - missing   ###############################
############################################################################

    private function countMissing(array$missing) {
        $count = 0;
        foreach( $missing as $miss ) {
            $count += $miss['all'];
        }

        return $count;
    }

    public function getMissingGlobal($page) {
        $this->setPage($page);
        $libs = $this->getLibs();
        $missing = array("locales" => $this->getUsedLocales(),
                         "default" => $this->getDefaultLanguage());
        $count = 0;
        foreach( $libs as $lib ) {
            $libMissing = $this->getMissingEntries($lib['lib']);

            $missing['entries'][$lib['lib']] = $libMissing;
            $count += count($libMissing);
        }

        $this->paging['allCount'] = $count;
        $missing['entriesCount'] = $count;

        return $missing;
    }

    private function getMissingEntries($lib) {
        $results = $this->getResultsByLib($lib);

        $missing['lib'] = $lib;
        foreach( $results as $data ) {
            $key = $data['keyOrig'];
            if( $this->checkMissing($data['entries']) ) {
                $missing[$key] = $data['entries'];
            }
        }
        unset($missing['lib']);
        return $missing;
    }

    private function checkMissing(&$entries) {
        $default = $this->getDefaultLanguage();
        $locales = $this->getUsedLocales();
        foreach( $locales as $locale ) {
            if( !isset($entries[$locale]) || !$entries[$locale] ) {
                $entries[$locale] = null;
                #if( $locale != $default ) {
                return true;
                #}
            }
        }

        return false;
    }

############################################################################
####################     LANGAUE           #################################
############################################################################

    public function getDefaultLanguage() {
        //if english is needed
        #return $this->container->getParameter('locale', 'en');
        return $this->getContainer()->get("service_container")->getParameter("kernel.default_locale");
    }

    /**
     * @return array
     */
    public function getUsedLocales() {
        $dir = $this->getTranslationPath();

        $finder = $this->getFinder();
        $finder->in($dir);

        $locales = array();
        foreach( $finder as $file ) {
            $locale = $this->extractLocaleFromFolder($file->getRelativePathname());

            if( !in_array($locale, $locales) ) {
                $locales[] = $locale;
            }
        }
        #  td($locales);
        return $locales;
    }

    public function extractLocaleFromFilename($filename) {
        preg_match("/[^\/]?values_*/", $filename, $match);
        $explode = explode("_", $match[1]);
        return $explode[1];
    }


    private function extractLocaleFromFolder($folder) {
        $regex = "#[^values_](.*)#";

        preg_match($regex, $folder, $match);
        $explode = explode("_", $folder);
        $locale = substr(end($explode), 0, 2);

        return $locale;
    }

    private function  getLocaleFolder($locale) {
        return "values_" . $locale;
    }

############################################################################
####################     FILE           ####################################
############################################################################


    private function getSourceDir() {
        return $this->getContainer()->getParameter('kernel.root_dir');
    }

    private function getTranslationPath() {
        return $this->getSourceDir() . "/Resources/translations/" . $this->platform;
    }

    public function getFilenameForLocale($locale) {
        $dir = $this->getTranslationPath();
        $localeFolder = $this->getLocaleFolder($locale);
        $lib = $this->getLib();

        return $dir . "/" . $localeFolder . "/" . $lib;
    }


    public function getFilenameForLibAndLocale($lib, $locale) {
        $dir = $this->getTranslationPath();
        $localeFolder = $this->getLocaleFolder($locale);
        $lib = $this->getLib($lib);

        return $dir . "/" . $localeFolder . "/" . $lib;
    }

    private function getType($filename) {
        preg_match("//", $filename, $match);
        return $match[0];
    }

    public function getLibs() {
        $query = array("platform" => $this->platform);
        $results = $this->getResults($query);
        $libs = array();
        foreach( $results as $data ) {
            $lib['lib'] = $data['lib'];
            $lib['entryCount'] = $this->getEntriesCountByLib($data['lib']);
            if( !in_array($lib, $libs) ) {
                $libs[] = $lib;
            }

        }
        natcasesort($libs);

        return $libs;
    }

    private function getEntriesCountByLib($lib) {
        $count = $this->getCount(array("lib" => $lib));
        return $count;
    }

############################################################################
########################    parse/check          ###########################
############################################################################

    private function isAlphabetic($key) {
        //all signs but alphabetics/whitespace/ _
        $forbiddenSigns = "/[^a-zA-z\s]|[_]+/";
        if( preg_match($forbiddenSigns, $key) ) {
            return false;
        }

        return true;
    }

    private function getXMLParser($filename) {
        return new \XMLReader();
    }


    private function objectsIntoArray($arrObjData, $arrSkipIndices = array()) {
        $arrData = array();

        // if input is object, convert into array
        if( is_object($arrObjData) ) {
            $arrObjData = get_object_vars($arrObjData);
        }

        if( is_array($arrObjData) ) {
            foreach( $arrObjData as $index => $value ) {
                if( is_object($value) || is_array($value) ) {
                    $value = $this->objectsIntoArray($value, $arrSkipIndices); // recursive call
                }
                if( in_array($index, $arrSkipIndices) ) {
                    continue;
                }
                $arrData[$index] = $value;
            }
        }
        return $arrData;
    }

    public function parseContent($lib, $locale) {
        $file = $this->getFilenameForLibAndLocale($lib, $locale);
        $return = array();
        if( file_exists($file) ) {
            //haut fehler wenn datei leer...
            try {
                $xml = simplexml_load_file($file);
            } catch( \ErrorException $e ) {
                return $return;
            }
            if( $parsed = $this->simpleXMLToArray($xml) ) {

                foreach( $parsed['string'] as $content ) {
                    $key = $content["name"];
                    $trl = $content[0];
                    $transformed = array("key" => $key,
                                         "trl" => $trl);

                    $return[] = $transformed;
                }
            }
        }
        #td($return);
        return $return;
    }

    private function simpleXMLToArray(\SimpleXMLElement $xml, $attributesKey = null, $childrenKey = null, $valueKey = null) {

        if( $childrenKey && !is_string($childrenKey) ) {
            $childrenKey = '@children';
        }
        if( $attributesKey && !is_string($attributesKey) ) {
            $attributesKey = '@attributes';
        }
        if( $valueKey && !is_string($valueKey) ) {
            $valueKey = '@values';
        }

        $return = array();
        $name = $xml->getName();
        $_value = trim((string)$xml);
        if( !strlen($_value) ) {
            $_value = null;
        }
        ;

        if( $_value !== null ) {
            if( $valueKey ) {
                $return[$valueKey] = $_value;
            } else {
                $return = $_value;
            }
        }

        $children = array();
        $first = true;
        foreach( $xml->children() as $elementName => $child ) {
            $value = $this->simpleXMLToArray($child, $attributesKey, $childrenKey, $valueKey);
            if( isset($children[$elementName]) ) {
                if( is_array($children[$elementName]) ) {
                    if( $first ) {
                        $temp = $children[$elementName];
                        unset($children[$elementName]);
                        $children[$elementName][] = $temp;
                        $first = false;
                    }
                    $children[$elementName][] = $value;
                } else {
                    $children[$elementName] = array($children[$elementName],
                                                    $value);
                }
            } else {
                $children[$elementName] = $value;
            }
        }
        if( $children ) {
            if( $childrenKey ) {
                $return[$childrenKey] = $children;
            }

            else {

                $return = array_merge((array)$return, $children);
            }
        }

        $attributes = array();
        foreach( $xml->attributes() as $name => $value ) {
            $attributes[$name] = trim($value);
        }
        if( $attributes ) {
            if( $attributesKey ) {
                $return[$attributesKey] = $attributes;
            } else {
                $return = array_merge((array)$return, $attributes);
            }
        }

        return $return;
    }

    private function cleanContent($string) {
        preg_match("#\"(.*?)\"#", $string, $match);
        return $match[1];
    }

    private function islineContentRelevant($line) {
        if( preg_match("/\"{1}/", $line) && preg_match("/=/", $line) && preg_match("/[^;]$/", $line) ) {
            return true;
        }

        return false;
    }

    public function extractType($filename) {
        preg_match("/[^.]*$/", $filename, $match);
        return $match[0];
    }

    public function extractLib($filename) {
        preg_match("/[^\/]+$/", $filename, $lib);
        return $lib[0];
    }

    public function getLimit() {
        return $this->limit;
    }

    public function setLimit($limit) {
        $this->limit = $limit;
    }

}
