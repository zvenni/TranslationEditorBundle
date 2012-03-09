<?php

namespace ServerGrove\Bundle\TranslationEditorBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Translation\Loader\YamlFileLoader;

class EditorController extends Controller {

    ############################################################################
    ########################      class     ####################################
    ############################################################################

    private function getContainer() {
        return $this->container;
    }

    private function getManager() {
        return $this->getContainer()->get('server_grove_translation_editor.storage_manager');
    }

    public function getCollection() {
        return $this->getManager()->getCollection();
    }



    ############################################################################
    ########################      ACTION    ####################################
    ############################################################################

    public function listAction() {

        /** @var $m \ServerGrove\Bundle\TranslationEditorBundle\MongoStorageManager */
        $m = $this->getManager();
        //Request Handling
        $request = $this->getRequest();
        $bundle = ucfirst($request->get("bundle"));
        $lib = strtolower($request->get("lib"));
        $bundle = $bundle ? $bundle : "CoreBundle";
        $lib = $lib ? $lib : "messages";
        //fetch data
        $trlKeys = $m->getEntriesByBundleAndLibPrepared($bundle, $lib);
        //sidebar menu
        $sidebar = $this->sideBar();

        return $this->render('ServerGroveTranslationEditorBundle:Editor:list.html.twig', array('trlKeys' => $trlKeys,
                                                                                              "sidebar" => $sidebar,));
    }

    public function listMissingGlobalAction() {
        /** @var $m \ServerGrove\Bundle\TranslationEditorBundle\MongoStorageManager */
        $m = $this->getManager();
        $trlKeys = $m->getAllMissingEntriesPrepared();
        //sidebar menu
        $sidebar = $this->sideBar();

        return $this->render('ServerGroveTranslationEditorBundle:Editor:list_missing_global.html.twig', array('trlKeys' => $trlKeys,
                                                                                                             "sidebar" => $sidebar,));
    }

    private function sideBar() {
        /** @var $m \ServerGrove\Bundle\TranslationEditorBundle\MongoStorageManager */
        $m = $this->getManager();
        $bundles = $m->getBundlesWithTranslations();
        $sidebar = array();

        foreach( $bundles as $key => $bundle ) {
            $sidebar['data'][$bundle] = $m->getFileOverviewByBundle($bundle);
        }
        $sidebar['link']['listMissingGlobal'] = $this->generateUrl("sg_localeditor_list_missing_global");

        return $sidebar;
    }

    public function removeAction() {
        $request = $this->getRequest();
        if( $request->isXmlHttpRequest() ) {
            $key = $request->request->get('key');
            $bundle = $request->request->get('bundle');
            $lib = $request->request->get('lib');
            /** @var $m \ServerGrove\Bundle\TranslationEditorBundle\MongoStorageManager */
            $m = $this->getManager();
            $values = $m->getEntriesByBundleAndLib($bundle, $lib);
            foreach( $values as $data ) {
                if( isset($data['entries'][$key]) ) {
                    unset($data['entries'][$key]);
                    $m->updateData($data);
                }
            }
            $res = array('result' => true);

            return new \Symfony\Component\HttpFoundation\Response(json_encode($res));
        }
    }

    public function addAction() {
        $request = $this->getRequest();
        $locales = $request->request->get('locale');
        $newKey = $request->request->get('key');
        $bundle = $request->request->get('bundle');
        $lib = $request->request->get('lib');
        /** @var $m \ServerGrove\Bundle\TranslationEditorBundle\MongoStorageManager */
        $m = $this->getManager();
        $entries = $m->getEntriesByBundleAndLibPrepared($bundle, $lib);
        foreach( $entries['entries'] as $key => $values ) {
            if( $newKey == $key ) {
                $res = array('result' => false,
                             'msg' => 'The key already exists. Please update it instead.',);

                return new \Symfony\Component\HttpFoundation\Response(json_encode($res));
            }
        }

        foreach( $locales as $locale => $value ) {
            if( !$request->request->get('check-only') ) {
                $data = $m->getentriesByBundleAndLocalAndLib($bundle, $locale, $lib);
                if( !$data ) {
                    $data['filename'] = $m->libFileName($bundle, $locale, $lib);
                    $data['bundle'] = $bundle;
                    $data['lib'] = $lib;
                    $data['dateImport'] = new \DateTime();
                    $data['locale'] = $locale;
                    $data['entries'][$newKey] = $value;
                    $data['type'] = "yml";
                    $m->insertData($data);
                } else {
                    $data['entries'][$newKey] = $value;
                    $m->updateData($data);
                }

            }
        }

        if( $request->isXmlHttpRequest() ) {
            $res = array('result' => true,
                         "redirect" => $this->generateUrl('sg_localeditor_list', array("bundle" => $bundle,
                                                                                      "lib" => $lib)));

            return new \Symfony\Component\HttpFoundation\Response(json_encode($res));
        }

        return new \Symfony\Component\HttpFoundation\RedirectResponse($this->generateUrl('sg_localeditor_list', array("bundle" => $bundle,
                                                                                                                     "lib" => $lib)));
    }

    public function updateAction() {
        $request = $this->getRequest();

        if( $request->isXmlHttpRequest() ) {
            //request handling
            $locale = $request->request->get('locale');
            $key = $request->request->get('key');
            $val = $request->request->get('val');
            $lib = $request->request->get('lib');
            $bundle = $request->request->get('bundle');
            //hole datei
            $m = $this->getManager();
            $values = $m->getEntriesByBundleAndLocalAndLib($bundle, $locale, $lib);
            //das document gibts noch gornie (z.b lib.de.yml ex. aber lib.en.yml nicht)
            if( !$values ) {
                $values['bundle'] = $bundle;
                $values['filename'] = $m->libFileName($bundle, $locale, $lib);
                $values['dateImport'] = new \DateTime();
                $values['lib'] = $lib;
                $values['locale'] = $locale;
                $values['type'] = "yml";
                $values['entries'][$key] = $val;
                $m->insertData($values);
            } else {
                $values['entries'][$key] = $val;
                $m->updateData($values);
            }
            //überschreibe nmit new


            $res = array('result' => true,

            );

            return new \Symfony\Component\HttpFoundation\Response(json_encode($res));
        }
    }

}
