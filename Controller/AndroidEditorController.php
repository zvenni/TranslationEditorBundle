<?php

namespace ServerGrove\Bundle\TranslationEditorBundle\Controller;

use ServerGrove\Bundle\TranslationEditorBundle\Controller\ServerGroveController;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use ServerGrove\Bundle\TranslationEditorBundle\Command\ImportIphoneCommand;


use Symfony\Component\Finder\Finder;


class AndroidEditorController extends ServerGroveController {

    ############################################################################
    ########################      class     ####################################
    ############################################################################

    private $platform = "android";

    ############################################################################
    ########################      ACTION    ####################################
    ############################################################################

    public function listAction() {
        /** @var $m \ServerGrove\Bundle\TranslationEditorBundle\AndroidManager */
        $m = $this->getManager($this->platform);
        //Request Handling
        $request = $this->getRequest();

        $lib = strtolower($request->get("lib"));
        $lib = $lib ? $lib : $m->getLib();
        $page = max(intval($request->get("page")), 1);
        //Data
        $trlKeys = $m->getEntriesByLibPrepared($lib, $page);

        //sidebar menu
        $sidebar = $this->sideBar();
        $paging = $m->getPaging();
        $pagination = $this->getPagination($paging);

        return $this->render('ServerGroveTranslationEditorBundle:Editor:' . $this->platform . '/list.html.twig', array("platform" => $this->platform,
                                                                                                                      'trlKeys' => $trlKeys,
                                                                                                                      "Paging" => $pagination,
                                                                                                                      "path" => $this->platform . "_list",
                                                                                                                      "sidebar" => $sidebar,));
    }

    public function listMissingGlobalAction() {
        /** @var $m \ServerGrove\Bundle\TranslationEditorBundle\Manager\AndroidManager */
        $m = $this->getManager($this->platform);
        $request = $this->getRequest();
        $page = max(intval($request->get("page")), 1);
        $trlKeys = $m->getMissingGlobal($page);
        //sidebar menu
        $sidebar = $this->sideBar();

        $paging = $m->getPaging();
        $pagination = $this->getPagination($paging);

        return $this->render('ServerGroveTranslationEditorBundle:Editor:' . $this->platform . '/list_missing_global.html.twig', array("platform" => $this->platform,
                                                                                                                                     'trlKeys' => $trlKeys,
                                                                                                                                     "sidebar" => $sidebar,
                                                                                                                                     "path" => $this->platform . "_missing_global",
                                                                                                                                     "Paging" => $pagination));
    }

    private function sideBar() {
        /** @var $m \ServerGrove\Bundle\TranslationEditorBundle\Manager\AndroidManager */
        $m = $this->getManager($this->platform);
        $sidebar = array();
        $sidebar['libs'] = $m->getLibs();
        $sidebar['link']['listMissingGlobal'] = $this->generateUrl($this->platform . "_missing_global");

        return $sidebar;
    }

    public function removeAction() {
        $request = $this->getRequest();
        if( $request->isXmlHttpRequest() ) {
            $key = $request->request->get('key');
            $platform = $request->request->get('platform');
            $lib = $request->request->get('lib');
            /** @var $m \ServerGrove\Bundle\TranslationEditorBundle\Manager\AndroidManager */
            $m = $this->getManager($this->platform);
            $m->removeEntry($lib, $key);
            $res = array('result' => true);

            return new \Symfony\Component\HttpFoundation\Response(json_encode($res));
        }
    }

    public function addAction() {
        //request
        $request = $this->getRequest();
        $entries = $request->request->get('locale');
        $newKey = $request->request->get('key');
        $platform = $request->request->get('platform');
        $lib = $request->request->get('lib');
        /** @var $m \ServerGrove\Bundle\TranslationEditorBundle\Manager\AndroidManager */
        $m = $this->getManager($this->platform);
        //entry existert bereits -error
        if( $entry = $m->getEntryByLibAndKey($lib, $newKey) ) {
            $res = array('result' => false,
                         'msg' => 'The key already exists. Please update it instead.');
            return new \Symfony\Component\HttpFoundation\Response(json_encode($res));
        }

        //check ob key schon existiert - nein? dann saven
        if( !$request->request->get('check-only') ) {
            $default = $m->getDefaultLanguage();
            $filename = $m->getFilenameForLibAndLocale($lib, $default);
            $dt = new \DateTime();
            $shaked = $m->shakeYaBoody($newKey);
            $data = array("filename" => $filename,
                          "platform" => $platform,
                          "entries" => $entries,
                          "info" => array(),
                          "lib" => $lib,
                          "type" => $m->extractType($filename),
                          "key" => $shaked,
                          "keyOrig" => $newKey,
                          "dateImport" => $dt,
                          "dateUpdate" => $dt);
            $m->insertData($data);
        }

        if( $request->isXmlHttpRequest() ) {
            $res = array('result' => true,
                         "redirect" => $this->generateUrl($this->platform . '_list', array("lib" => $lib)));

            return new \Symfony\Component\HttpFoundation\Response(json_encode($res));
        }

        return new \Symfony\Component\HttpFoundation\RedirectResponse($this->generateUrl($this->platform . '_list', array("lib" => $lib)));
    }

    public function updateAction() {
        $request = $this->getRequest();

        if( $request->isXmlHttpRequest() ) {
            //request handling
            $locale = $request->request->get('locale');
            $key = $request->request->get('key');
            $val = $request->request->get('val');
            $platform = $request->request->get('platform');
            $lib = $request->request->get('lib');
            /** @var $m \ServerGrove\Bundle\TranslationEditorBundle\Manager\AndroidManager */
            $m = $this->getManager($this->platform);
            //hole datei
            if( !$data = $m->getEntryByLibAndKey($lib, $key) ) {
                $res = array('result' => false,
                             'msg' => 'The key does not exists. Please add it instead.');
                return new \Symfony\Component\HttpFoundation\Response(json_encode($res));
            }
            //prepare and update
            $dt = new \DateTime();
            $data['entries'][$locale] = $val;
            $data['dateUpdate'] = $dt;
            $m->updateData($data);
            print_r($data);
            //return
            $res = array('result' => true);

            return new \Symfony\Component\HttpFoundation\Response(json_encode($res));
        }
    }
}
