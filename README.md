# phpmysqli

#### 介绍
基于swoole的mysql客户端 语法兼容thinkphp db数据库操作 (回调函数操作除外)

#### 软件架构
软件架构说明


#### 安装教程
```
composer require szjcomo/phpmysqli
```
### 特别说明
- 如果debug为false 不打印sql语句
- 如果debug为true 打印sql语句在控制台
- 如果debug为回调函数 则执行用户自定义的回调函数 

#### 使用说明
```
	$config = new \szjcomo\mysqli\Config([
	    'host' => '127.0.0.1',
	    'port' => 3306,
	    'user' => 'xxx',
	    'password' => 'xxx',
	    'database' => 'xxx',
	    'prefix'=>'szj_',
	    'debug'	=> function($sql,$params,$start_time,$end_time) {
			echo $sql.PHP_EOL;
			print_r($params);
			echo ($end_time - $start_time).PHP_EOL;
	    }
	]);
	$db = new \szjcomo\mysqli\Mysqli($config);
	//var_dump($db);
	try{


		//or查询
		/*$result = $db->name('article')->where('article_id',['<',100],['>',1],'or')->field('title,article_id')->select();
		var_dump($result);*/

		//whereNotIn查询
		/*$result = $db->name('article')->whereNotIn('article_id',[1,73,76])->field('article_id,title')->select();
		var_dump($result);*/

		//whereNotLike查询
		/*$result = $db->name('article')->whereNotLike('title','%测试%')->field('title,article_id')->select();
		var_dump($result);	*/

		//notbetween查询
		/*$result = $db->name('article')->whereNotBetween('create_time',['2019-10-30','2019-12-30'])->field('title,article_id,create_time')->select();
		var_dump($result);*/


		//between查询
		/*$result = $db->name('article')->whereBetween('create_time',['2019-10-30','2019-12-30 23:59:59'])->field('title,article_id,create_time')->select();
		var_dump($result);*/

		//wherelike查询
/*		$result = $db->name('article')->whereLike('title','%测试%')->field('title,article_id')->select();
		var_dump($result);
*/
		//wherein 查询
/*		$result = $db->name('article')->whereIn('article_id',[1,73,76])->field('title,article_id')->select();
		var_dump($result);*/

		//in查询用法
		/*$result = $db->name('article')->where('article_id','not in',[1,4,5,6])->select();
		print_r($result);*/

		//查询一条数据
		/*$info = $db->name('article')->alias('a')->leftJoin(['__ARTICLE_CATEGORY__'=>'ac'],'ac.category_id = a.category_id')
				->field('a.article_id,a.title,a.article_desc,ac.category_name,a.category_id')
				->where('a.article_id',70)
				->find();
		print_r($info);*/
		//查询多条数据
		/*$list = $db->name('article')->alias('a')->leftJoin(['__ARTICLE_CATEGORY__'=>'ac'],'ac.category_id = a.category_id')
				->field('a.article_id,a.title,a.article_desc,ac.category_name,a.category_id')
				->select();
		print_r($list);*/

		//插入数据
		/*$data = ['tag_name'=>'测试一下有没有','admin_id'=>1,'create_time'=>time()];
		$result = $db->name('tags')->insert($data);
		var_dump($result);*/

		/*$data = ['tag_name'=>'测试一下有没有','admin_id'=>1,'create_time'=>date('Y-m-d H:i:s')];
		$result = $db->name('tags')->insert($data);
		print_r($result);*/
		/*$result = $db->name('tags')->where('tag_id',2)->delete();
		print_r($result);*/

		//批量插入
		/*$data = [
			['tag_name'=>'szjcomo1','admin_id'=>1,'create_time'=>date('Y-m-d')],
			['tag_name'=>'szjcomo2','admin_id'=>1,'create_time'=>date('Y-m-d')],
			['tag_name'=>'szjcomo3','admin_id'=>1,'create_time'=>date('Y-m-d')]
		];
		$result = $db->name('tags')->insertAll($data);
		var_dump($result);*/


		//事务处理,不支持嵌套事务
		/*$db->startTrans();
		$data = ['tag_name'=>'测试一下有没有','admin_id'=>1,'create_time'=>date('Y-m-d H:i:s')];
		$id = $db->name('tags')->insert($data);
		$user = ['username'=>'szjcomo1','admin_id'=>1,'password'=>sha1('szjcomo'),'create_time'=>date('Y-m-d H:i:s')];
		$user_id = $db->name('admin_user')->insert($user);
		//$user_id = true;
		if($id && $user_id){
			echo '执行成功...'.PHP_EOL;
			$db->commit();
		} else {
			echo '执行失败'.PHP_EOL;
			$db->rollback();
		}*/


		//更新数据
/*		$data = ['tag_name'=>'xiaotao3'];
		$result = $db->name('tags')->update($data,['tag_id'=>18]);
		var_dump($result);*/
		//删除数据
		/*$result = $db->name('tags')->delete();
		var_dump($result);*/
		//查询某例的值
		/*$result = $db->name('admin_user')->column('username','id');
		print_r($result);*/
		//查询某字段值
		/*$result = $db->name('admin_user')->where('id',10)->value('username');
		var_dump($result);*/
		//统计查询
		/*$result = $db->name('article')->count('article_id');
		var_dump($result);*/
		//求最大值
		/*$result = $db->name('article_category')->max('category_sort');
		var_dump($result);*/
		//求最小值
		/*$result = $db->name('article_category')->min('category_id');
		var_dump($result);*/
		//求和
		/*$result = $db->name('article_category')->sum('category_sort');
		var_dump($result);*/
		//求平均值
		/*$result = $db->name('article_category')->avg('category_sort');
		var_dump($result);*/
		//distinct用法
		/*$result = $db->name('admin_user')->distinct(true)->field('password')->select();
		var_dump($result);
		//指定使用索引
		$result = $db->name('admin_user')->force('password')->select();
		var_dump($result);*/
		//获取查询语句
		/*$result = $db->name('admin_user')->where('id','>',1)->fetchSql(true)->select();
		echo $result.PHP_EOL;*/
		//实现子查询1
		/*$sql = $db->name('admin_user')->field('username,id')->select(true);
		$result = $db->table('('.$sql.') au')->where('id',1)->find();
		var_dump($result);*/
		//实现子查询2
		/*$buildSql = $db->name('admin_user')->field('username,id')->buildSql(true);
		$result = $db->table($buildSql.' au')->where('id',1)->field('username')->find();
		var_dump($result);*/
		//使用函数
		/*$result = $db->name('admin_user')->where('id',1)->value('UNIX_TIMESTAMP(create_time)');
		print_r($result);*/

	} catch(\Exception $err){
		echo $err->getMessage().PHP_EOL;
		//$db->rollback();
	}
```