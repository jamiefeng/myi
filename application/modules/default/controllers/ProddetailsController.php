<?php
require_once 'Iceaclib/common/fun.php';
require_once 'Iceaclib/common/filter.php';
require_once 'Iceaclib/default/prodhistory.php';
require_once 'Iceaclib/common/page.php';
class ProddetailsController extends Zend_Controller_Action {
	private $_prodhistory;
	private $_prodService;
	public function init() {
		/*
		 * Initialize action controller here
		 */
		//菜单选择
		$_SESSION['menu'] = 'product';
		$this->filter = new MyFilter();
		//获取购物车寄存
		$cartService = new Default_Service_CartService();
		$cartService->getCartDeposit();
        
		$this->view->fun =$this->fun =new MyFun();
		$this->_prodhistory=new Prodhistory();
		//产品目录
		$prodService = new Default_Service_ProductService();
		$prodCategory = $prodService->getProdCategory();
		$this->view->first = $prodCategory['first'];
		$this->view->second = $prodCategory['second'];
		$this->view->third  = $prodCategory['third'];
		//目录推荐品牌
		$this->view->categorybarnd = $prodService->getCategoryBrand();
		
		$this->_prodService = new Default_Service_ProductService();
		
		$this->config = Zend_Registry::get('config');
		$this->seoconfig = $this->view->seoconfig = Zend_Registry::get('seoconfig');
		$this->commonconfig = Zend_Registry::get('commonconfig');
	}
	public function indexAction() {
		$prodModel = new Default_Model_DbTable_Product();
		$this->view->st = $_GET['st'];
		$this->config    = Zend_Registry::get('config');
		$this->seoconfig = Zend_Registry::get('seoconfig');
		$this->view->freetotl = $this->config->cost->online_free;
		$this->view->freetotl_hk = $this->config->cost->inquiry_free_USD;
		$this->view->keyword = trim($this->_getParam('keyword'));
		$id_no='';
		if($this->_getParam('item')){
			$item = explode("-",$this->_getParam('item'));
			$partid = $id_no = (int)$item[3];
			$qstr = "po.id ='$partid'";
		}else{
			if(isset($_GET['partno'])) $partno = $this->filter->pregHtmlSql($_GET['partno']);
		    if(isset($_GET['partid'])){
		    	$partid = $this->filter->pregHtmlSql($_GET['partid']);
		    }
		    if(!empty($partid)){
		        $qstr = "po.id ='{$partid}'";
		        $id_no = $partid;
		    }elseif(!empty($partno)){
		       $qstr = "po.part_no ='{$partno}'";
		       $id_no = $partno;
		    }else $this->_redirect('/error');	
		    
		    $sqlstr ="SELECT po.id,po.part_no,po.manufacturer,po.part_level1,po.part_level2,po.part_level3
		    FROM product as po
		    LEFT JOIN brand as br ON po.manufacturer=br.id
		    WHERE {$qstr} AND po.status='1' AND br.status='1'";
		    $prodarr = $prodModel->getByOneSql($sqlstr);
		    if($prodarr['part_level3']) $part_level = $prodarr['part_level3'];
		    elseif($prodarr['part_level2']) $part_level = $prodarr['part_level2'];
		    else $part_level = $prodarr['part_level1'];
		    header( "HTTP/1.1 301 Moved Permanently" );
		    header("Location:/item-b".$prodarr['manufacturer']."-".$part_level."-".$prodarr['id']."-".str_replace("/", "_", $prodarr['part_no']).".html");
		}
		
		$sqlstr ="SELECT po.*,pc1.name as cname1,pc2.name as cname2,pc3.name as cname3,
		    br.id as bid,br.name as bname
		    FROM product as po 
		    LEFT JOIN prod_category as pc3 ON po.part_level3=pc3.id
    	    LEFT JOIN prod_category as pc2 ON po.part_level2=pc2.id
    	    LEFT JOIN prod_category as pc1 ON po.part_level1=pc1.id
    	    LEFT JOIN brand as br ON po.manufacturer=br.id
		    WHERE {$qstr} AND po.status='1' AND br.status='1'";
		
		$this->view->prodarr = $product=  $prodModel->getByOneSql($sqlstr);
		//pdnpcn
		$this->view->pdn = $this->_prodService->getPdn($partid);
		$this->view->pcn = $this->_prodService->getPcn($partid);
		
		$series = $product['series'];
		$app_notes = $this->view->prod_notes = $this->_prodService->getAppNotesBySeries($series);
		if(empty($this->view->prodarr)) $this->_redirect('/error');
		//记录浏览记录
		$this->_prodhistory->addhistry($id_no);
		//关联型号
		$this->view->relevance = $this->_prodService->getRelevanceInfo($partid);
		//重新设置headtitle 、 description和keywords等
		$part_no = $this->view->prodarr['part_no'];
		if($this->view->prodarr['cname3']) $cname   = $this->view->prodarr['cname3'];
		elseif($this->view->prodarr['cname2']) $cname   = $this->view->prodarr['cname2'];
		else $cname   = $this->view->prodarr['cname1'];
		$bandname = $this->view->prodarr['bname'];
		$layout = $this->_helper->layout();
		$viewobj = $layout->getView();;
		$viewobj->headTitle(str_ireplace(array("<part_no>","<brand_name>","<category_name>"),array($part_no,$bandname,$cname),$this->seoconfig->general->details_title),'SET');
		$viewobj->headMeta()->setName('description',str_ireplace(array("<part_no>","<brand_name>","<category_name>"),array($part_no,$bandname,$cname),$this->seoconfig->general->details_description));
		$viewobj->headMeta()->setName('keywords',str_ireplace(array("<part_no>","<brand_name>","<category_name>"),array($part_no,$bandname,$cname),$this->seoconfig->general->details_keywords));
		//新版本
		if(isset($_SESSION['new_version'])){
			$this->fun = new MyFun();
			$this->fun->changeView($this->view,$_SESSION['new_version']);
		}	
		//限时促销
		$eventservice = new Default_Service_EventService();
		$pod = $eventservice->getEvent("id = 6");
		$pod['data'];
		@eval($pod['data']);
		$this->view->stime = $this->view->oldprice = '';
		if($viewer->specials[$partid]){
		  $this->view->stime = $viewer->time;
		  $this->view->oldprice = $viewer->specials[$partid][0][0];
		}
		//推荐方案
		$appserivce = new Default_Service_ApplicationsService;
		$this->view->solution = $appserivce->getSolutionByCode($partid);
	}
	/**
	 * 分类列表
	 */
	public function listAction() {
		//新版本
		if(isset($_SESSION['new_version'])){
			$this->fun = new MyFun();
			$this->fun->changeView($this->view,$_SESSION['new_version']);
			$this->commonconfig->page->prodlistype = 9 ;
		}
		$strwhere = '';
		//产品目录类型
		$list = $this->filter->pregHtmlSql($this->_getParam('list'));
		if($list){
			$list = explode("-",$list);
			$subid = $this->view->subid = isset($list[1])?(int)$list[1]:'';
			$secid = $this->view->secid = isset($list[2])?(int)$list[2]:'';
			$thiid = $this->view->thiid = isset($list[3])?(int)$list[3]:'';
		}else{
		    $subid = $this->view->subid = (int)$this->_getParam('subid');
		    $secid = $this->view->secid = (int)$this->_getParam('secid');
		    $thiid = $this->view->thiid = (int)$this->_getParam('thiid');
		}
		//库存
		$stock = $this->view->stock = $this->filter->pregHtmlSql($this->_getParam('stock'));
		if($stock=='spot') $strwhere .=" (po.sz_stock- po.sz_cover)>0 AND ";
		elseif($stock=='order') $strwhere .=" (po.sz_stock- po.sz_cover)<=0 AND ";
		//品牌
		$brandid = $this->view->brandid = (int)$this->_getParam('brand');
		$this->view->brand = $this->_prodService->getBrand();
		if($brandid) $strwhere .= "  po.manufacturer='{$brandid}' AND ";
		if($subid){
			//查询产品
			if(!empty($thiid)){
				$strwhere .= "  po.part_level3='{$thiid}' ";
			}elseif(!empty($secid)){
				$strwhere .= "  po.part_level2='{$secid}' ";
			}else{
				$strwhere .= "  po.part_level1='{$subid}' ";
			}
			//总数
			$total = $this->_prodService->getNum($strwhere);
			//分页
			$perpage = $this->commonconfig->page->prodlist;
			$page_ob = new Page(array('total'=>$total,'perpage'=>$perpage));
			$offset  = $page_ob->offset();
			$this->view->page_bar= $page_ob->show($this->commonconfig->page->prodlistype);
			$this->view->allProd = $this->_prodService->getProdPage($strwhere,$offset,$perpage);
				
			//重新设置headtitle 、 description和keywords等
			$layout = $this->_helper->layout();
			$this->view->viewobj = $layout->getView();
		}else $this->_redirect('/');
		
	}
	/*
	 * 点击品牌查看产品
	*/
	public function brandAction() {
		$_SESSION['menu'] = 'brand';
		$brandModel = new Default_Model_DbTable_Model('brand');
		//新版本
		if(isset($_SESSION['new_version'])){
			$this->fun = new MyFun();
			$this->fun->changeView($this->view,$_SESSION['new_version']);
			$this->commonconfig->page->prodlistype = 9 ;
		}
		if($this->_getParam('brand')){
			$brand = explode("-",$this->_getParam('brand'));
			$brandid = (int)$brand[1];
		}elseif($this->_getParam('brandname')){
			$brandname = trim($this->_getParam('brandname'));
			$brandid = $brandModel->QueryItem("SELECT id FROM `brand` WHERE name='{$brandname}' AND status='1' LIMIT 0 , 1");
		}else{
		    $brandid = (int)$this->_getParam('bid');
		}
		
		//OTAX 跳到专题页
		//if($_GET['t']==1 && $brandid==17) $this->_redirect('/event/201312044');
		//EPSON 跳转专题页
		//if($_GET['t']==1 && $brandid==13) $this->_redirect('/event/201404228');
		if($brandid){
			
			$this->view->allbrand = $allbrand = $brandModel->getAllByWhere("status='1'","displayorder ASC");
			//如何不存在$brandid，就退出
			$tmp = 0;$brandname = '';
			for($i=0;$i<count($allbrand);$i++){
				if($allbrand[$i]['id'] == $brandid) {$brandname=$allbrand[$i]['name'];$tmp=1;}
			}
			if($tmp==0) $this->_redirect('/');
			
			//获取品牌信息
			$this->view->brandinfo = $this->_prodService->getBrandInfoById($brandid);
			//获取品牌所有系列
			$this->view->series = $this->_prodService->getSeriesByBrand($brandid);
				
			if($this->view->brandinfo['show']==1){
				//品牌应用分类
				$this->view->app = $this->_prodService->getAppByBrandId($brandid,$this->view->brandinfo['apporder']);
			}
			$strwhere ='';
			//库存
			/*$stock = $this->view->stock = $this->_getParam('stock');
			if($stock=='spot') $strwhere =" (po.sz_stock- po.sz_cover)>0 AND ";
			elseif($stock=='order') $strwhere =" (po.sz_stock- po.sz_cover)<=0 AND ";
			*/
			//筛选
			$app2 = $_GET['app2'];
			$app3 = $_GET['app3'];
			$this->view->p = $p    = trim($_GET['p']);
			$t2 = $t3 = array();
			if($app2){
				$t2 = explode("_", $app2);
			}
			$t3_tmp = array();
			if($app3){
				$t3 = explode("_", $app3);
				foreach($t3 as $k=>$v3){
					$t4 = explode(";", $v3);
					if(in_array($t4[0],$t2)){
						unset($t3[$k]);
					}else{
						$t3_tmp[] = $t4[1];
					}
				}
			}
			$this->view->app2 = $t2;
			$this->view->app3 = $t3;
			if($t2 && $t3_tmp){
				$strwhere .=" (po.part_level2 IN ('".implode("','",$t2)."') OR po.part_level3 IN ('".implode("','",$t3_tmp)."') ) AND ";
			}elseif($t2){
				$strwhere .=" po.part_level2 IN ('".implode("','",$t2)."') AND ";
			}elseif($t3_tmp){
				$strwhere .=" po.part_level3 IN ('".implode("','",$t3_tmp)."') AND ";
			}
			if($p){
				$strwhere .=" po.part_no LIKE '%{$p}%' AND ";
			}
			//系列
			$this->view->s = $this->_getParam('s');
			if($this->view->s) $strwhere .=" po.series='{$this->view->s}' AND ";

			//查询产品
			$strwhere .= "manufacturer='{$brandid}'";
			//总数
			$sqlstr = "SELECT count(po.id) as num FROM product as po WHERE {$strwhere} AND po.status=1";
			$all = $brandModel->getBySql($sqlstr,array());
			$total = $all[0]['num'];
			//分页
			$perpage = $this->commonconfig->page->prodlist;
			$page_ob = new Page(array('total'=>$total,'perpage'=>$perpage));
			$offset  = $page_ob->offset();
			$this->view->page_bar= $page_ob->show($this->commonconfig->page->prodlistype);
	
			$this->view->allProd = $this->_prodService->getProdPage($strwhere,$offset,$perpage);
			//显示参数
			$this->view->brandname = $brandname;
			$this->view->brandid   = $brandid;
			//重新设置headtitle 、 description和keywords等
			$layout = $this->_helper->layout();
			$viewobj = $layout->getView();
			$viewobj->headTitle(str_ireplace(array("<brand_name>"),array($brandname),$this->seoconfig->general->brand_title),'SET');
			$viewobj->headMeta()->setName('description',str_ireplace(array("<brand_name>"),array($brandname),$this->seoconfig->general->brand_description));
			$viewobj->headMeta()->setName('keywords',str_ireplace(array("<brand_name>"),array($brandname),$this->seoconfig->general->brand_keywords));

			//查询方案列表
			$solModel = new Default_Model_DbTable_Solution();
			$sqlstr = "SELECT count(s.id) as num
			FROM solution as s
			LEFT JOIN solution_product  as sp ON s.id = sp.solution_id
			LEFT JOIN product as p ON sp.prod_id = p.id
			WHERE p.manufacturer='{$brandid}' AND sp.type='core' AND s.status=1 AND sp.status=1 ";
			$allcan = $solModel->getBySql($sqlstr,array());
			$this->view->bsNum = $allcan[0]['num'];
			
		}else $this->_redirect('/brand');
	}
	/*
	 * 加入收藏夹
	 */
	public function addfavoritesAction()
	{
		$this->_helper->layout->disableLayout();
		$this->_helper->viewRenderer->setNoRender();
		$favModel = new Default_Model_DbTable_Favorites();
		$partid = (int)$_POST['partid'];
		if($partid){
			//最多添加1000个收藏记录，防止攻击
			$favService = new Default_Service_FavoritesService();
			$favre = $favService->checkNum(1000);
			if($favre){
				echo Zend_Json_Encoder::encode(array("code"=>111, "message"=>"很抱歉，收藏夹对多存储1000条记录，请删除旧记录后再收藏。"));
				exit;
			}
			
			$re = $favModel->getRowByWhere("prod_id='$partid' AND status=1 AND uid='".$_SESSION['userInfo']['uidSession']."'");
		    if(!empty($re)){
		    	echo Zend_Json_Encoder::encode(array("code"=>1, "message"=>"你已经收藏过该商品"));
		    	exit;
		    }else{
		    	$adddate = array('uid'=>$_SESSION['userInfo']['uidSession'],
		    			'prod_id'=>$partid,
		    			'cart'=>0,
		    			'inquiry'=>0,
		    			'status'=>1,
		    			'created'=>time());
		    	$favModel->addData($adddate);
		    	echo Zend_Json_Encoder::encode(array("code"=>0, "message"=>"添加收藏成功"));
		    	exit;
		    }
		}else{
			echo Zend_Json_Encoder::encode(array("code"=>101, "message"=>"请填入正确产品编号"));
			exit;
		}
	}
	/*
	 * 操作收藏夹
	*/
	public function changefavAction()
	{
		$this->_helper->layout->disableLayout();
		$this->_helper->viewRenderer->setNoRender();
		$favService = new Default_Service_FavoritesService();
		$fid = (int)$_POST['fid'];
		$type   = $_POST['type'];
		if($fid){
			if($type=='i'){//更新询价数量
				$favService->updateInquiry($fid);
				echo Zend_Json_Encoder::encode(array("code"=>1, "message"=>"请填入正确参数"));
				exit;
			}elseif($type=='c'){//更新购买数量
				$favService->updateCart($fid);
				echo Zend_Json_Encoder::encode(array("code"=>2, "message"=>"请填入正确参数"));
				exit;
			}elseif($type=='d'){//删除收藏夹
				$favService->deleteFov($fid);
				$_SESSION['favmessage'] = "删除收藏记录成功";
				echo Zend_Json_Encoder::encode(array("code"=>0, "message"=>"删除收藏记录成功"));
				exit;
			}else{
				echo Zend_Json_Encoder::encode(array("code"=>102, "message"=>"请填入正确参数"));
				exit;
			}
		}else{
			echo Zend_Json_Encoder::encode(array("code"=>101, "message"=>"请填入正确编号"));
			exit;
		}
	}
}