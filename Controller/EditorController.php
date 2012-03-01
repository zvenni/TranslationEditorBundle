<?php

namespace ServerGrove\Bundle\TranslationEditorBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;


class EditorController extends Controller
{
    private function getContainer()
    {
        return $this->container;
    }


    private function getManager()
    {
        return $this->getContainer()->get('server_grove_translation_editor.storage_manager');
    }


    public function getCollection()
    {
        return $this->getManager()->getCollection();
    }

    private function getData()
    {
        $data = $this->getCollection()->find();
        $data->sort(array('locale' => 1));

        return $data;
    }

    public function listAction()
    {
        /** @var $m \ServerGrove\Bundle\TranslationEditorBundle\MongoStorageManager */
        $m = $this->getManager();
        //Request Handling
        $request = $this->getRequest();
        $bundle = ucfirst($request->get("bundle"));
        $lib = strtolower($request->get("lib"));
        $bundle = $bundle ? $bundle : "CoreBundle";
        $lib = $lib ? $lib : "messages";
        $trlKeys = $m->getEntriesByBundleAndLibPrepared($bundle, $lib);
        //sidebar menu
        $sidebar = $this->sideBar();

        return $this->render('ServerGroveTranslationEditorBundle:Editor:list.html.twig', array(
                'trlKeys' => $trlKeys,
                "sidebar" => $sidebar,
            )
        );
    }

    private function sideBar()
    {
        $m = $this->getManager();
        $bundles = $m->getBundlesWithTranslations();
        $sidebar = array();
        foreach ($bundles as $key => $bundle) {
            $sidebar[$bundle] = $m->getFilesByBundle($bundle);
        }

        return $sidebar;
    }

    public function removeAction()
    {

        $request = $this->getRequest();

        if ($request->isXmlHttpRequest()) {
            $key = $request->request->get('key');
            $bundle = $request->request->get('bundle');
            $lib = $request->request->get('lib');
            /** @var $m \ServerGrove\Bundle\TranslationEditorBundle\MongoStorageManager */
            $m = $this->getManager();
            $values = $m->getEntriesByBundleAndLib($bundle, $lib);

            foreach ($values as $data) {
                if (isset($data['entries'][$key])) {
                    unset($data['entries'][$key]);
                    $this->updateData($data);
                }
            }

            $res = array(
                'result' => true,
            );
            return new \Symfony\Component\HttpFoundation\Response(json_encode($res));
        }
    }

    public function addAction()
    {
        $request = $this->getRequest();

        $locales = $request->request->get('locale');
        $newKey = $request->request->get('key');
        $bundle = $request->request->get('bundle');
        $lib = $request->request->get('lib');
        /** @var $m \ServerGrove\Bundle\TranslationEditorBundle\MongoStorageManager */
        $m = $this->getManager();
        $entries = $m->getEntriesByBundleAndLibPrepared($bundle, $lib);

        foreach ($entries['entries'] as $key => $values) {
            if ($newKey == $key) {
                $res = array(
                    'result' => false,
                    'msg' => 'The key already exists. Please update it instead.',
                );
                return new \Symfony\Component\HttpFoundation\Response(json_encode($res));
            }
        }

        foreach ($locales as $locale => $value) {
            if (!$request->request->get('check-only')) {
                $data = $m->getentriesByBundleAndLocalAndLib($bundle, $locale, $lib);
                $data['entries'][$newKey] = $value;

                $this->updateData($data);
            }
        }

        if ($request->isXmlHttpRequest()) {
            $res = array(
                'result' => true,
                "redirect" => $this->generateUrl('sg_localeditor_list', array("bundle" => $bundle, "lib" => $lib))
            );

            return new \Symfony\Component\HttpFoundation\Response(json_encode($res));
        }

        return new \Symfony\Component\HttpFoundation\RedirectResponse($this->generateUrl('sg_localeditor_list', array("bundle" => $bundle, "lib" => $lib)));
    }

    public function updateAction()
    {
        $request = $this->getRequest();

        if ($request->isXmlHttpRequest()) {
            //request handling
            $locale = $request->request->get('locale');
            $key = $request->request->get('key');
            $val = $request->request->get('val');
            $lib = $request->request->get('lib');
            $bundle = $request->request->get('bundle');
            //hole datei
            $m = $this->getManager();
            $values = $m->getEntriesByBundleAndLocalAndLib($bundle, $locale, $lib);
            //key validation
            $found = false;
            foreach ($values['entries'] as $data) {
                if (isset($data[$key])) {
                    $found = true;
                    break;
                }
            }
            //wrong key
            if (!$found) {
                $res = array(
                    'result' => false,
                    'msg' => 'The key does not exists. Please add it instead.',
                );
                return new \Symfony\Component\HttpFoundation\Response(json_encode($res));
            }
            //überschreibe nmit new
            $values['entries'] [$key] = $val;
            $this->updateData($values);

            $res = array(
                'result' => true,
                'oldata' => $data[$key],

            );
            return new \Symfony\Component\HttpFoundation\Response(json_encode($res));
        }
    }

    protected function updateData($data)
    {
        $this->getCollection()->update(
            array('_id' => $data['_id'])
            , $data, array('upsert' => true));
    }
}
