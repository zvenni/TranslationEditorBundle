<?php

namespace ServerGrove\Bundle\TranslationEditorBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class ServerGroveController extends Controller {

    ############################################################################
    ########################      class     ####################################
    ############################################################################

    protected function getContainer() {
        return $this->container;
    }

    protected function getManager($platform = "webs") {
        return $this->getContainer()->get('server_grove_translation_editor.' . $platform . '_manager');
    }

    protected function getPagination(array $paging) {
        $pagingObj = $this->getContainer()->get('server_grove_translation_editor.paging');
        $pagingObj->setPaging($paging);

        return $pagingObj;
    }

}
