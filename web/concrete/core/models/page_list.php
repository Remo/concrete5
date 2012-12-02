<?php

defined('C5_EXECUTE') or die("Access Denied.");

/**
*
* An object that allows a filtered list of pages to be returned.
* @package Pages
*
*/
class Concrete5_Model_PageList extends DatabaseItemList {

	protected $includeSystemPages = false;
	protected $attributeFilters = array();
	protected $includeAliases = true;
	protected $displayOnlyPermittedPages = false; // not used.
	protected $displayOnlyApprovedPages = true;
	protected $displayOnlyActivePages = true;
	protected $filterByCParentID = 0;
	protected $filterByCT = false;
	protected $ignorePermissions = false;
	protected $attributeClass = 'CollectionAttributeKey';
	protected $autoSortColumns = array('cvName', 'cvDatePublic', 'cDateAdded', 'cDateModified');
	protected $indexedSearch = false;
	
	/* magic method for filtering by page attributes. */
	
	public function __call($nm, $a) {
		if (substr($nm, 0, 8) == 'filterBy') {
			$txt = Loader::helper('text');
			$attrib = $txt->uncamelcase(substr($nm, 8));
			if (count($a) == 2) {
				$this->filterByAttribute($attrib, $a[0], $a[1]);
			} else {
				$this->filterByAttribute($attrib, $a[0]);
			}
		}			
	}
	
	public function includeInactivePages() {
		$this->displayOnlyActivePages = false;
	}
	
	public function ignorePermissions() {
		$this->ignorePermissions = true;
	}
	
	public function ignoreAliases() {
		$this->includeAliases = false;
	}

	public function includeSystemPages() {
		$this->includeSystemPages = true;
	}
	
	public function displayUnapprovedPages() {
		$this->displayOnlyApprovedPages = false;
	}
	
	public function isIndexedSearch() {return $this->indexedSearch;}
	/** 
	 * Filters by "keywords" (which searches everything including filenames, title, tags, users who uploaded the file, tags)
	 */
	public function filterByKeywords($keywords, $simple = false) {
		$db = Loader::db();
		$kw = $db->quote($keywords);
		$qk = $db->quote('%' . $keywords . '%');
		Loader::model('attribute/categories/collection');		
		$keys = CollectionAttributeKey::getSearchableIndexedList();
		$attribsStr = '';
		foreach ($keys as $ak) {
			$cnt = $ak->getController();			
			$attribsStr.=' OR ' . $cnt->searchKeywords($keywords);
		}

		if ($simple || $this->indexModeSimple) { // $this->indexModeSimple is set by the IndexedPageList class
			$this->filter(false, "(psi.cName like $qk or psi.cDescription like $qk or psi.content like $qk {$attribsStr})");		
		} else {
			$this->indexedSearch = true;
			$this->indexedKeywords = $keywords;
			$this->autoSortColumns[] = 'cIndexScore';
			$this->filter(false, "((match(psi.cName, psi.cDescription, psi.content) against ({$kw})) {$attribsStr})");
		}
	}

	public function filterByName($name, $exact = false) {
		if ($exact) {
			$this->filter('cvName', $name, '=');
		} else {
			$this->filter('cvName', '%' . $name . '%', 'like');
		}	
	}
	
	public function filterByPath($path, $includeAllChildren = true) {
		if (!$includeAllChildren) {
			$this->filter('PagePaths.cPath', $path, '=');
		} else {
			$this->filter('PagePaths.cPath', $path . '/%', 'like');
		}	
		$this->filter('PagePaths.ppIsCanonical', 1);
	}
	
	/** 
	 * Sets up a list to only return items the proper user can access 
	 */
	public function setupPermissions() {
		
		$u = new User();
		if ($u->isSuperUser() || ($this->ignorePermissions)) {
			return; // super user always sees everything. no need to limit
		}
		
		$accessEntities = $u->getUserAccessEntityObjects();
		foreach($accessEntities as $pae) {
			$peIDs[] = $pae->getAccessEntityID();
		}
		
		$owpae = PageOwnerPermissionAccessEntity::getOrCreate();
		// now we retrieve a list of permission duration object IDs that are attached view_page or view_page_version
		// against any of these access entity objects. We just get'em all.
		$db = Loader::db();
		$activePDIDs = array();
		$vpPKID = $db->GetOne('select pkID from PermissionKeys where pkHandle = \'view_page\'');
		$vpvPKID = $db->GetOne('select pkID from PermissionKeys where pkHandle = \'view_page_versions\'');
		$pdIDs = $db->GetCol("select distinct pdID from PagePermissionAssignments ppa inner join PermissionAccessList pa on ppa.paID = pa.paID where pkID in (?, ?) and pdID > 0", array($vpPKID, $vpvPKID));
		if (count($pdIDs) > 0) {
			// then we iterate through all of them and find any that are active RIGHT NOW
			foreach($pdIDs as $pdID) {
				$pd = PermissionDuration::getByID($pdID);
				if ($pd->isActive()) {
					$activePDIDs[] = $pd->getPermissionDurationID();	
				}
			}
		}
		$activePDIDs[] = 0;

		$cInheritPermissionsFromCID = 'PagesOriginal.cInheritPermissionsFromCID';

		if ($this->displayOnlyApprovedPages) {
			$cvIsApproved = ' and cv.cvIsApproved = 1';
		}
		
		$uID = 0;
		if ($u->isRegistered()) {
			$uID = $u->getUserID();
		}
		
		$this->filter(false, "((select count(cID) from PagePermissionAssignments ppa1 inner join PermissionAccessList pa1 on ppa1.paID = pa1.paID where ppa1.cID = {$cInheritPermissionsFromCID} and pa1.accessType = " . PermissionKey::ACCESS_TYPE_INCLUDE . " and pa1.pdID in (" . implode(',', $activePDIDs) . ")
			and pa1.peID in (" . implode(',', $peIDs) . ") and (if(pa1.peID = " . $owpae->getAccessEntityID() . " and PagesOriginal.uID <>" . $uID . ", false, true)) and (ppa1.pkID = " . $vpPKID . $cvIsApproved . " or ppa1.pkID = " . $vpvPKID . ")) > 0
			or (PagesOriginal.cPointerExternalLink !='' AND PagesOriginal.cPointerExternalLink IS NOT NULL))");
		$this->filter(false, "((select count(cID) from PagePermissionAssignments ppaExclude inner join PermissionAccessList paExclude on ppaExclude.paID = paExclude.paID where ppaExclude.cID = {$cInheritPermissionsFromCID} and accessType = " . PermissionKey::ACCESS_TYPE_EXCLUDE . " and pdID in (" . implode(',', $activePDIDs) . ")
			and paExclude.peID in (" . implode(',', $peIDs) . ") and (if(paExclude.peID = " . $owpae->getAccessEntityID() . " and PagesOriginal.uID <>" . $uID . ", false, true)) and (ppaExclude.pkID = " . $vpPKID . $cvIsApproved . " or ppaExclude.pkID = " . $vpvPKID . ")) = 0)");		
	}

	public function sortByRelevance() {
		if ($this->indexedSearch) {
			parent::sortBy('cIndexScore', 'desc');
		}
	}
	

	/** 
	 * Sorts this list by display order 
	 */
	public function sortByDisplayOrder() {
		parent::sortBy('PagesOriginal.cDisplayOrder', 'asc');
	}
	
	/** 
	 * Sorts this list by display order descending 
	 */
	public function sortByDisplayOrderDescending() {
		parent::sortBy('PagesOriginal.cDisplayOrder', 'desc');
	}

	public function sortByCollectionIDAscending() {
		parent::sortBy('PagesOriginal.cID', 'asc');
	}
	
	/** 
	 * Sorts this list by public date ascending order 
	 */
	public function sortByPublicDate() {
		parent::sortBy('cvDatePublic', 'asc');
	}
	
	/** 
	 * Sorts this list by name 
	 */
	public function sortByName() {
		parent::sortBy('cvName', 'asc');
	}
	
	/** 
	 * Sorts this list by name descending order
	 */
	public function sortByNameDescending() {
		parent::sortBy('cvName', 'desc');
	}

	/** 
	 * Sorts this list by public date descending order 
	 */
	public function sortByPublicDateDescending() {
		parent::sortBy('cvDatePublic', 'desc');
	}	
	
	/** 
	 * Sets the parent ID that we will grab pages from. 
	 * @param mixed $cParentID
	 */
	public function filterByParentID($cParentID) {
		$db = Loader::db();
		if (is_array($cParentID)) {
			$cth = '(';
			for ($i = 0; $i < count($cParentID); $i++) {
				if ($i > 0) {
					$cth .= ',';
				}
				$cth .= $db->quote($cParentID[$i]);
			}
			$cth .= ')';
			$this->filter(false, "(PagesOriginal.cParentID in {$cth})");
		} else {
			$this->filterByCParentID = $cParentID;
			$this->filter('PagesOriginal.cParentID', $cParentID);
		}
	}
	
	/** 
	 * Filters by type of collection (using the ID field)
	 * @param mixed $ctID
	 */
	public function filterByCollectionTypeID($ctID) {
		$this->filterByCT = true;
		$this->filter("pt.ctID", $ctID);
	}

	/** 
	 * Filters by user ID of collection (using the uID field)
	 * @param mixed $ctID
	 */
	public function filterByUserID($uID) {
		$this->filter('PagesOriginal.uID', $uID);
	}

	public function filterByIsApproved($cvIsApproved) {
		$this->filter('cv.cvIsApproved', $cvIsApproved);	
	}
	
	public function filterByIsAlias($ia) {
		if ($this->includeAliases) {
			if ($ia) {
				$this->filter(false, "(Pages.cPointerID > 0)");
			} else {
				$this->filter(false, "(Pages.cPointerID is null OR Pages.cPointerID < 1)");
			}
		}
	}
	
	/** 
	 * Filters by type of collection (using the handle field)
	 * @param mixed $ctID
	 */
	public function filterByCollectionTypeHandle($ctHandle) {
		$db = Loader::db();
		$this->filterByCT = true;
		if (is_array($ctHandle)) {
			$cth = '(';
			for ($i = 0; $i < count($ctHandle); $i++) {
				if ($i > 0) {
					$cth .= ',';
				}
				$cth .= $db->quote($ctHandle[$i]);
			}
			$cth .= ')';
			$this->filter(false, "(pt.ctHandle in {$cth})");
		} else {
			$this->filter('pt.ctHandle', $ctHandle);
		}
	}

	/** 
	 * Filters by date added
	 * @param string $date
	 */
	public function filterByDateAdded($date, $comparison = '=') {
		$this->filter('c.cDateAdded', $date, $comparison);
	}
	
	public function filterByNumberOfChildren($num, $comparison = '>') {
		if (!Loader::helper('validation/numbers')->integer($num)) {
			$num = 0;
		}
		$this->filter('PagesOriginal.cChildren', $num, $comparison);
	}

	public function filterByDateLastModified($date, $comparison = '=') {
		$this->filter('c.cDateModified', $date, $comparison);
	}

	/** 
	 * Filters by public date
	 * @param string $date
	 */
	public function filterByPublicDate($date, $comparison = '=') {
		$this->filter('cv.cvDatePublic', $date, $comparison);
	}
	
	/** 
	 * If true, pages will be checked for permissions prior to being returned
	 * @param bool $checkForPermissions
	 */
	public function displayOnlyPermittedPages($checkForPermissions) {
		if ($checkForPermissions) {
			$this->ignorePermissions = false;
		} else {
			$this->ignorePermissions = true;
		}
	}
	
	protected function setBaseQuery($additionalFields = '') {
		if ($this->isIndexedSearch()) {
			$db = Loader::db();
			$ik = ', match(psi.cName, psi.cDescription, psi.content) against (' . $db->quote($this->indexedKeywords) . ') as cIndexScore ';
		}
	
		if (!$this->includeAliases) {
			$this->filter(false, '(Pages.cPointerID < 1 or Pages.cPointerID is null)');
		}
		
		$cvID = '(select max(cvID) from CollectionVersions where cID = cv.cID)';		
		if ($this->displayOnlyApprovedPages) {
			$cvID = '(select cvID from CollectionVersions where cvIsApproved = 1 and cID = cv.cID)';
			$this->filter('cvIsApproved', 1);
		}

                $this->setQuery('
                    SELECT
                        PagesOriginal.cID,
                        PagesOriginal.pkgID,
                        Pages.cPointerID,
                        if(Pages.cPointerID>0,Pages.cID,null) cPointerOriginalID,
                        PagesOriginal.cPointerExternalLink,
                        PagesOriginal.cIsActive,
                        PagesOriginal.cIsSystemPage,
                        PagesOriginal.cPointerExternalLinkNewWindow,
                        PagesOriginal.cFilename,
                        Collections.cDateAdded,
                        PagesOriginal.cDisplayOrder,
                        Collections.cDateModified,
                        PagesOriginal.cInheritPermissionsFromCID, 
                        PagesOriginal.cInheritPermissionsFrom, 
                        PagesOriginal.cOverrideTemplatePermissions, 
                        PagesOriginal.cCheckedOutUID, 
                        PagesOriginal.cIsTemplate, 
                        PagesOriginal.uID, 
                        PagePaths.cPath, 
                        PagesOriginal.cParentID, 
                        PagesOriginal.cChildren, 
                        PagesOriginal.cCacheFullPageContent, PagesOriginal.cCacheFullPageContentOverrideLifetime, 
                        PagesOriginal.cCacheFullPageContentLifetimeCustom,
                        pt.ctHandle,
                        cv.*
                    FROM Pages 
                    INNER JOIN Pages PagesOriginal ON if(Pages.cPointerID>0,Pages.cPointerID, Pages.cID)=PagesOriginal.cID
                    INNER JOIN Collections ON PagesOriginal.cID = Collections.cID
                    INNER JOIN CollectionVersions cv ON cv.cID = PagesOriginal.cID AND cv.cvID = ' . $cvID . '
                    LEFT JOIN PageSearchIndex psi ON psi.cID = PagesOriginal.cID
                    LEFT JOiN PageTypes pt ON pt.ctID = cv.ctID
                    LEFT JOIN PagePaths ON PagesOriginal.cID = PagePaths.cID AND PagePaths.ppIsCanonical = 1
                ');
                
                $this->filter('PagesOriginal.cIsTemplate', 0);
                $this->setupPermissions();
		
		$this->setupAttributeFilters("left join CollectionSearchIndexAttributes on (CollectionSearchIndexAttributes.cID = PagesOriginal.cID)");		
                    
		if ($this->displayOnlyActivePages) {
			$this->filter('PagesOriginal.cIsActive', 1);
		}
		$this->setupSystemPagesToExclude();
		
	}
	
	protected function setupSystemPagesToExclude() {
		if ($this->includeSystemPages || $this->filterByCParentID > 1 || $this->filterByCT == true) {
			return false;
		}
		$this->filter(false, "(PagesOriginal.cIsSystemPage = 0)");	
	}
		
	public function getTotal() {
		if ($this->getQuery() == '') {
			$this->setBaseQuery();
		}		
		return parent::getTotal();
	}
	
	/** 
	 * Returns an array of page objects based on current settings
	 */
	public function get($itemsToGet = 0, $offset = 0) {
		$pages = array();
		if ($this->getQuery() == '') {
			$this->setBaseQuery();
		}		
		
		$this->setItemsPerPage($itemsToGet);
		
		$r = parent::get($itemsToGet, $offset);
		foreach($r as $row) {
        		$nc = new Page();
                        $nc->setPropertiesFromArray($row);
        		$nc->setPageIndexScore($row['cIndexScore']);
	                                        
                        //$nc = Page::getByID($row['cID'], 'ACTIVE');
                    	if (!$this->displayOnlyApprovedPages) {
				$cp = new Permissions($nc);
				if ($cp->canViewPageVersions()) {
					$nc->loadVersionObject('RECENT');
				}
			}
			$nc->setPageIndexScore($row['cIndexScore']);
			$pages[] = $nc;
		}
		return $pages;
	}
}

class Concrete5_Model_PageSearchDefaultColumnSet extends DatabaseItemListColumnSet {
	protected $attributeClass = 'CollectionAttributeKey';	

	public static function getCollectionDatePublic($c) {
		return date(DATE_APP_DASHBOARD_SEARCH_RESULTS_PAGES, strtotime($c->getCollectionDatePublic()));
	}

	public static function getCollectionDateModified($c) {
		return date(DATE_APP_DASHBOARD_SEARCH_RESULTS_PAGES, strtotime($c->getCollectionDateLastModified()));
	}
	
	public function getCollectionAuthor($c) {
		$uID = $c->getCollectionUserID();
		$ui = UserInfo::getByID($uID);
		if (is_object($ui)) {
			return $ui->getUserName();
		}
	}
	
	public function __construct() {
		$this->addColumn(new DatabaseItemListColumn('ctHandle', t('Type'), 'getCollectionTypeName', false));
		$this->addColumn(new DatabaseItemListColumn('cvName', t('Name'), 'getCollectionName'));
		$this->addColumn(new DatabaseItemListColumn('cvDatePublic', t('Date'), array('PageSearchDefaultColumnSet', 'getCollectionDatePublic')));
		$this->addColumn(new DatabaseItemListColumn('cDateModified', t('Last Modified'), array('PageSearchDefaultColumnSet', 'getCollectionDateModified')));
		$this->addColumn(new DatabaseItemListColumn('author', t('Author'), array('PageSearchDefaultColumnSet', 'getCollectionAuthor'), false));
		$date = $this->getColumnByKey('cDateModified');
		$this->setDefaultSortColumn($date, 'desc');
	}
}

class Concrete5_Model_PageSearchAvailableColumnSet extends Concrete5_Model_PageSearchDefaultColumnSet {
	protected $attributeClass = 'CollectionAttributeKey';
	public function __construct() {
		parent::__construct();
	}
}

class Concrete5_Model_PageSearchColumnSet extends DatabaseItemListColumnSet {
	protected $attributeClass = 'CollectionAttributeKey';
	public function getCurrent() {
		$u = new User();
		$fldc = $u->config('PAGE_LIST_DEFAULT_COLUMNS');
		if ($fldc != '') {
			$fldc = @unserialize($fldc);
		}
		if (!($fldc instanceof DatabaseItemListColumnSet)) {
			$fldc = new PageSearchDefaultColumnSet();
		}
		return $fldc;
	}
}
