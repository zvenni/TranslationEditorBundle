<?
namespace ServerGrove\Bundle\TranslationEditorBundle\Paging;

/**
 * @author Sven Schwerdtfeger, Dresden, 2011-09-06 <sven@dampfer.net>
 */
class Paging {

    /**
     *
     *     Container
     * @access  private
     * @package LOVOO Translation Paging
     * @var string
     */
    private $container;

    /**
     *
     *     Anzeige nur wenn Sinnvoll (mehr als eins)
     * @access  protected
     * @package LOVOO Translation Paging
     * @var string
     */
    protected $showPaging = true;



    /**
     *
     *     Kommt Paging in vorderern Bereich
     * @access  protected
     * @package LOVOO Translation Paging
     * @var boolean
     */
    protected $skipPre = true;

    /**
     *
     *     Kommt Paging in hinteren Bereich
     * @access  protected
     * @package LOVOO Translation Paging
     * @var boolean
     */
    protected $skipPost = true;

    /**
     *
     *     Aktuelle Seite
     * @access  protected
     * @package LOVOO Translation Paging
     * @var integer
     */
    public $currentPage = 1;

    /**
     *
     *     Wieviel Seiten zur Auswahl links und rechts
     * @access  protected
     * @package LOVOO Translation Paging
     * @var integer
     */
    public $pageRange = 3;

    /**
     *
     *     Wieivel Ergebnisse Pro Seite
     * @access  protected
     * @package LOVOO Translation Paging
     * @var integer
     */
    public $displayPerPage = 50;

    /**
     *
     *     letzte Seite
     * @access  protected
     * @package LOVOO Translation Paging
     * @var integer
     */
    protected $lastPage = 1;

    /**
     *
     *     erste Seite
     * @access  protected
     * @package LOVOO Translation Paging
     * @var integer
     */
    protected $firstPage = 1;

    /**
     *
     *     Vorgängerseiten
     * @access  protected
     * @package LOVOO Translation Paging
     * @var array
     */
    protected $neighboursPre = array();

    /**
     *
     *     Vorgängerseiten
     * @access  protected
     * @package LOVOO Translation Paging
     * @var array
     */
    protected $neighboursPost = array();

    private $resultCount;

    public function setResulCount($resultCount) {
        $this->resultCount = $resultCount;
    }

    public function getResultCount() {
        return $this->resultCount;
    }

    /**
     *
     *     Hole Vorgägner Seiten
     * @package LOVOO Translation Paging
     * @access  public
     * @return array (ggf leer)
     */
    public function setCurrentPage($currentPage) {
        $this->currentPage = $currentPage;
    }

    /**
     *
     *     Hole Vorgägner Seiten
     * @package LOVOO Translation Paging
     * @access  public
     * @return array (ggf leer)
     */
    public function getNeighboursPre() {
        return $this->neighboursPre;
    }

    /**
     *
     *     Hole Nachfolgende Seiten
     * @package LOVOO Translation Paging
     * @access  public
     * @return array (ggf leer)
     */
    public function getNeighboursPost() {
        return $this->neighboursPost;
    }

    /**
     *
     *     Hole Letzte Seite
     * @package LOVOO Translation Paging
     * @access  public
     * @return integer
     */
    public function getLastPage() {
        return $this->lastPage;
    }

    /**
     *
     *     Hole Aktuelöle Seite
     * @package LOVOO Translation Paging
     * @access  public
     * @return integer
     */
    public function getCurrentPage() {
        return $this->currentPage;
    }

    public function getShowPaging() {
        return $this->showPaging;
    }

    public function getSkipPre() {
        return $this->skipPre;
    }

    public function getSkipPost() {
        return $this->skipPost;
    }

    public function getFirstPage() {
        return $this->firstPage;
    }

    private function _checkInput($input) {
        $keys = array( "currentPage", "resultCount", "pageRange", "displayPerPage" );
        if( !is_array($input) || empty($input) ) {
            throw new PagingException("Ugly input");
        }

        foreach( $keys as $k => $v ) {
            if( !in_array($v, $input) ) {
                throw new PagingException("Key " . $k . " has not been transferred");
                die();
            }
        }

        return true;
    }

    /**
     *
     *     setze PageNaviObj mit den Seitenziffern für die Anzeige (inkl. skip oder ni)
     * @access  public
     * @package LOVOO Translation Paging
     *
     * @param array $results (um results) array(pages => array(page, all, skip), allCount)
     *
     * @return array(neighbours_pre,current_page,neighbours_post)
     */
    public function setPaging(Array $pageSettings) {
        //$this->_checkInput($pageSettings);
        $this->_mergeSettings($pageSettings);

        /*  $this->currentPage = $pageSettings['currentPage'];
        $this->resultCount = $pageSettings['resultCount'];
        $this->pageRange = $pageSettings['pageRange'];
        $this->displayPerPage = $pageSettings['displayPerPage'];
        
        $this->lastPage = ceil($this->resultCount / $this->displayPerPage); //hier über das array den pageRange beeinflussen
        */
        //nur 1 seite
        if( $this->lastPage == $this->firstPage ) {
            $this->showPaging = false;
            $this->pageRange = 1;
            $this->setSkipPre(false);
            $this->setSkipPost(false);

        }

        //unsinn ausschließen wenn größer last dann current = letzte, wenn kleiner first dann current = first 
        $this->currentPage = min($this->currentPage, $this->lastPage);
        $this->currentPage = max($this->firstPage, $this->currentPage);

        ////anzeige skip backwards?
        $this->neighboursPre = $this->_set_neighbours_pre();
        if( empty($this->neighboursPre) || in_array($this->firstPage, $this->neighboursPre) ) {
            $this->setSkipPre(false);
            //            $this->neighboursPre = $this->_set_neighbours_pre();
            //            $this->setNeighboursPre($this->neighboursPre);
        }

        //anzeige rechts ( nachfolger)
        $this->neighboursPost = $this->_set_neighbours_post();
        if( empty($this->neighboursPost) || in_array($this->lastPage, $this->neighboursPost) ) {
            $this->setSkipPost(false);
        }
    }

    public function setSkipPost($skipPost) {
        $this->skipPost = $skipPost;
    }

    public function setSkipPre($skipPre) {
        $this->skipPre = $skipPre;
    }

    /**
     *
     *     Setze Nachfolgeseiten
     * @package LOVOO Translation Paging
     * @access  public
     *
     * @param array (auch leer) $neighboursPost
     */
    public function setNeighboursPost($neighboursPost) {
        $this->neighboursPost = $neighboursPost;
    }

    /**
     *
     *     Setze VorgängerSDeoiten
     * @package LOVOO Translation Paging
     * @access  public
     *
     * @param array (auch leer) $neighboursPre
     */
    public function setNeighboursPre($neighboursPre) {
        $this->neighboursPre = $neighboursPre;
    }

    /**
     *
     *     Berechne Vorhergehende Seiten
     * @package LOVOO Translation Paging
     * @access  public
     * @return array
     */
    private function _set_neighbours_pre() {
        $neighbour_ar = array();
        //noch platz nach links, wenn nicht wieviel?
        if( $this->pageRange - $this->currentPage < 0 ) $neighbour_cnt = $this->pageRange; else
            $neighbour_cnt = $this->currentPage - 1;
        //setze seiten
        for( $j = 1; $j <= $neighbour_cnt; $j++ ) {
            $neighbour_ar[$j] = $this->currentPage - $neighbour_cnt + $j - 1;
        }

        return $neighbour_ar;
    }

    /**
     *
     *     Berechne Nachfolgende Seiten
     * @access  protected
     * @package LOVOO Translation Paging
     * @return array
     */
    private function _set_neighbours_post() {
        $neighbour_ar = array();
        //noch platz nach rechts, wenn nicht wieviel?
        if( $this->lastPage - $this->currentPage - $this->pageRange > 0 ) $neighbour_cnt = $this->pageRange; else
            $neighbour_cnt = $this->lastPage - $this->currentPage;
        //setze seiten
        for( $j = 1; $j <= $neighbour_cnt; $j++ ) {
            $neighbour_ar[$j] = $this->currentPage + $j;
        }

        return $neighbour_ar;
    }

    /**
     *
     *     Hole ContainerProperty
     * @access  public
     * @package LOVOO Translation Paging
     * @return container
     */
    public function getContainer() {
        return $this->container;
    }

    /**
     *
     *     Setze Container
     * @access  public
     * @package LOVOO Translation Paging
     *
     * @param void container (string container beschgreibung)
     *
     * @return container
     */
    public function setContainer($container) {
        $this->container = $container;
    }

    /**
     *
     *     Hole Spez. ContainerEigensch
     * @access  public
     * @package LOVOO Translation Paging
     *
     * @param string $k
     *
     * @return Conainer->get($key)
     */
    public function get($k) {
        return $this->container->get($k);
    }

    private function _mergeSettings($pageSettings) {
        $this->currentPage = $pageSettings['page'];
        $this->resultCount = $pageSettings['allCount'];
        //$this->pageRange = $this->
        //$this->displayPerPage = $pageSettings['displayPerPage'];
        //$this->lastPage = ceil($this->resultCount / $this->displayPerPage); //hier über das array den pageRange beeinflussen
        $this->lastPage = $pageSettings['totalPages'];
    }

}
