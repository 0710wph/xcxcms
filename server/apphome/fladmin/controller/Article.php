<?php
namespace app\fladmin\controller;
use think\Db;
use app\common\lib\ReturnData;
use app\common\logic\ArticleLogic;

class Article extends Base
{
	public function _initialize()
	{
		parent::_initialize();
    }
	
    public function getLogic()
    {
        return new ArticleLogic();
    }
    
    public function index()
    {
		$where = array();
        if(!empty($_REQUEST["keyword"]))
        {
            $where['title'] = array('like','%'.$_REQUEST['keyword'].'%');
        }
        if(!empty($_REQUEST["typeid"]) && $_REQUEST["typeid"]!=0)
        {
            $where['typeid'] = $_REQUEST["typeid"];
        }
        if(!empty($_REQUEST["id"]))
        {
            $where['typeid'] = $_REQUEST["id"];
        }
        $where['ischeck'] = 0; //审核过的文章
        if(!empty($_REQUEST["ischeck"]))
        {
            $where['ischeck'] = $_REQUEST["ischeck"]; //未审核过的文章
        }
        
        $posts = $this->getLogic()->getPaginate($where,'id desc',['body'],15);
		
		$this->assign('page',$posts->render());
        $this->assign('posts',$posts);
		
		return $this->fetch();
		
        //if(!empty($_GET["id"])){$id = $_GET["id"];}else {$id="";}if(preg_match('/[0-9]*/',$id)){}else{exit;}
        
        /* if(!empty($id)){$map['typeid']=$id;}
        $Article = M("Article")->field('id')->where($map);
        $counts = $Article->count();
        
        $pagesize =CMS_PAGESIZE;$page =0;
        if($counts % $pagesize){ //取总数据量除以每页数的余数
        $pages = intval($counts/$pagesize) + 1; //如果有余数，则页数等于总数据量除以每页数的结果取整再加一,如果没有余数，则页数等于总数据量除以每页数的结果
        }else{$pages = $counts/$pagesize;}
        if(!empty($_GET["page"])){$page = $_GET["page"]-1;$nextpage=$_GET["page"]+1;$previouspage=$_GET["page"]-1;}else{$page = 0;$nextpage=2;$previouspage=0;}
        if($counts>0){if($page>$pages-1){exit;}}
        $start = $page*$pagesize;
        $Article = M("Article")->field('id,typeid,title,pubdate,click,litpic,tuijian')->where($map)->order('id desc')->limit($start,$pagesize)->select();
        
        $this->counts = $counts;
		$this->pages = $pages;
        $this->page = $page;
        $this->nextpage = $nextpage;
        $this->previouspage = $previouspage;
        $this->id = $id;
        $this->posts = $Article; */
        
        //echo '<pre>';
        //print_r($Article);
        //return $this->fetch();
    }
    
    public function add()
    {
		if(!empty($_REQUEST["catid"])){$this->assign('catid',$_REQUEST["catid"]);}else{$this->assign('catid',0);}
		
        return $this->fetch();
    }
    
    public function doadd()
    {
        $litpic="";if(!empty($_POST["litpic"])){$litpic = $_POST["litpic"];}else{$_POST['litpic']="";} //缩略图
        if(empty($_POST["description"])){if(!empty($_POST["body"])){$_POST['description']=cut_str($_POST["body"]);}} //description
        $content="";if(!empty($_POST["body"])){$content = $_POST["body"];}
        $_POST['pubdate'] = time();//更新时间
        $_POST['addtime'] = time();//添加时间
		$_POST['user_id'] = session('admin_user_info')['id']; // 发布者id
        
		//关键词
        if(!empty($_POST["keywords"]))
		{
			$_POST['keywords']=str_replace("，",",",$_POST["keywords"]);
		}
		else
		{
			if(!empty($_POST["title"]))
			{
				$title=$_POST["title"];
				$title=str_replace("，","",$title);
				$title=str_replace(",","",$title);
				$_POST['keywords']=get_keywords($title);//标题分词
			}
		}
        
		if(isset($_POST["dellink"]) && $_POST["dellink"]==1 && !empty($content)){$content=replacelinks($content,array(CMS_BASEHOST));} //删除非站内链接
		$_POST['body']=$content;
		
		//提取第一个图片为缩略图
		if(isset($_POST["autolitpic"]) && $_POST["autolitpic"] && empty($litpic))
		{
			if(getfirstpic($content))
			{
				//获取文章内容的第一张图片
				$imagepath = '.'.getfirstpic($content);
				
				//获取后缀名
				preg_match_all ("/\/(.+)\.(gif|jpg|jpeg|bmp|png)$/iU",$imagepath,$out, PREG_PATTERN_ORDER);
				
				$saveimage='./uploads/'.date('Y/m',time()).'/'.basename($imagepath,'.'.$out[2][0]).'-lp.'.$out[2][0];
				
				//生成缩略图
				$image = \think\Image::open($imagepath);
				// 按照原图的比例生成一个最大为240*180的缩略图
				$image->thumb(CMS_IMGWIDTH, CMS_IMGHEIGHT)->save($saveimage);
				
				//缩略图路径
				$_POST['litpic']='/uploads/'.date('Y/m',time()).'/'.basename($imagepath,'.'.$out[2][0]).'-lp.'.$out[2][0];
			}
		}
		
		unset($_POST["dellink"]);
		unset($_POST["autolitpic"]);
        if(isset($_POST['editorValue'])){unset($_POST['editorValue']);}
        if(!empty($_POST["tags"])){$tags = $_POST["tags"];}else{$tags = '';}unset($_POST["tags"]);
        
        $res = $this->getLogic()->add($_POST);
		if($res['code']==ReturnData::SUCCESS)
        {
            //Tag
            if(!empty($tags))
            {
                $tags = explode(",",str_replace("，",",",$tags));
                foreach($tags as $v)
                {
                    if($tagindex = db('tagindex')->where(array('tag'=>$v))->find())
                    {
                        $where['tid'] = $tagindex['id'];
                        $where['aid'] = $res['data'];
                        if(!db('taglist')->where($where)->find()){db('taglist')->insert($where);}
                    }
                }
            }
            
            $this->success('添加成功！', url('index'), '', 1);
        }
		else
		{
			$this->error($res['msg'], url('add'), '', 3);
		}
    }
    
    public function edit()
    {
        if(!empty($_GET["id"])){$id = $_GET["id"];}else {$id="";}if(preg_match('/[0-9]*/',$id)){}else{exit;}
        
        $this->assign('id',$id);
		$this->assign('post',$this->getLogic()->getOne("id=$id"));
        
        $tags = '';
        $taglist = db('taglist')->where("aid=$id")->select();
        if($taglist)
        {
            foreach($taglist as $k=>$v)
            {
                $tmp[] = db('tagindex')->where('id='.$v['tid'])->value('tag');
            }
            $tags = implode(',',$tmp);
        }
        $this->assign('tags',$tags);
        
        return $this->fetch();
    }
    
    public function doedit()
    {
        if(!empty($_POST["id"])){$id = $_POST["id"];unset($_POST["id"]);}else{$id="";exit;}
        $litpic="";if(!empty($_POST["litpic"])){$litpic = $_POST["litpic"];}else{$_POST['litpic']="";} //缩略图
        if(empty($_POST["description"])){if(!empty($_POST["body"])){$_POST['description']=cut_str($_POST["body"]);}} //description
        $content="";if(!empty($_POST["body"])){$content = $_POST["body"];}
        $_POST['pubdate'] = time();//更新时间
        $_POST['user_id'] = session('admin_user_info')['id']; // 修改者id
        
		if(!empty($_POST["keywords"]))
		{
			$_POST['keywords']=str_replace("，",",",$_POST["keywords"]);
		}
		else
		{
			if(!empty($_POST["title"]))
			{
				$title=$_POST["title"];
				$title=str_replace("，","",$title);
				$title=str_replace(",","",$title);
				$_POST['keywords']=get_keywords($title);//标题分词
			}
		}
		
		if(isset($_POST["dellink"]) && $_POST["dellink"]==1 && !empty($content)){$content=replacelinks($content,array(CMS_BASEHOST));} //删除非站内链接
		$_POST['body']=$content;
		
		//提取第一个图片为缩略图
		if(isset($_POST["autolitpic"]) && $_POST["autolitpic"] && empty($litpic))
		{
			if(getfirstpic($content))
			{
				//获取文章内容的第一张图片
				$imagepath = '.'.getfirstpic($content);
				
				//获取后缀名
				preg_match_all ("/\/(.+)\.(gif|jpg|jpeg|bmp|png)$/iU",$imagepath,$out, PREG_PATTERN_ORDER);
				
				$saveimage='./uploads/'.date('Y/m',time()).'/'.basename($imagepath,'.'.$out[2][0]).'-lp.'.$out[2][0];
				
				//生成缩略图
				$image = \think\Image::open($imagepath);
				// 按照原图的比例生成一个最大为240*180的缩略图
				$image->thumb(CMS_IMGWIDTH, CMS_IMGHEIGHT)->save($saveimage);
				
				//缩略图路径
				$_POST['litpic']='/uploads/'.date('Y/m',time()).'/'.basename($imagepath,'.'.$out[2][0]).'-lp.'.$out[2][0];
			}
		}
		
		unset($_POST["dellink"]);
		unset($_POST["autolitpic"]);
        
        //Tag
        if(!empty($_POST["tags"]))
        {
            $tags = explode(",",str_replace("，",",",$_POST["tags"]));
            db('taglist')->where(array('aid'=>$id))->delete();
            
            foreach($tags as $v)
            {
                if($tagindex = db('tagindex')->where(array('tag'=>$v))->find())
                {
                    $where['tid'] = $tagindex['id'];
                    $where['aid'] = $id;
                    if(!db('taglist')->where($where)->find()){db('taglist')->insert($where);}
                }
            }
        }
        unset($_POST["tags"]);
        
        if(isset($_POST['editorValue'])){unset($_POST['editorValue']);}
        $res = $this->getLogic()->edit($_POST,array('id'=>$id));
        if($res['code']==ReturnData::SUCCESS)
        {
            if(!empty($_POST['ischeck']))
            {
                $this->success('修改成功！', url('index',array('ischeck'=>1)), '', 1);
            }
            else
            {
                $this->success('修改成功！', url('index'), '', 1);
            }
        }
		else
		{
			$this->error($res['msg'], url('edit',array('id'=>$id)), '', 3);
		}
    }
    
    public function del()
    {
		if(!empty($_GET["id"])){$id = $_GET["id"];}else{$this->error('参数错误', url('index'), '', 3);}
		
        $res = model('Article')->del("id in ($id)");
		if($res['code']==ReturnData::SUCCESS)
        {
            $this->success("$id ,删除成功", url('index'), '', 1);
        }
		else
		{
			$this->error($res['msg'], url('index'), '', 3);
		}
    }
    
    public function repetarc()
    {
		$this->assign('posts',Db::query("select title,count(*) AS count from ".config('database.prefix')."article group by title HAVING count>1 order by count DESC"));
		
        return $this->fetch();
    }
	
	public function recommendarc()
    {
		if(!empty($_GET["id"])){$id = $_GET["id"];}else{$this->error('参数错误', url('index'), '', 3);} //if(preg_match('/[0-9]*/',$id)){}else{exit;}
		
		$data['tuijian'] = 1;
		
        $res = $this->getLogic()->edit($data, "id in ($id)");
		if($res['code']==ReturnData::SUCCESS)
        {
            $this->success("$id ,推荐成功", url('index'), '', 1);
        }
		else
		{
			$this->error("$id ,推荐失败！请重新提交", url('index'), '', 3);
		}
    }
    
    public function articleexists()
    {
        if(!empty($_GET["title"]))
        {
            $map['title'] = $_GET["title"];
        }
        else
        {
            $map['title']="";
        }
        
        if(!empty($_GET["id"]))
        {
            $map['id'] = array('NEQ',$_GET["id"]);
        }
        
        echo db("article")->where($map)->count();
    }
}