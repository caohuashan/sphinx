<?php
// 版权 (c) 2001-2016, Andrew Aksyonoff 
// 版权 (c) 2008-2016, Sphinx Technologies Inc  保留所有权利

//这个程序是免费软件；你可以重新发布和/或修改它，根据GNU图书馆通用公共许可证的条款。你应该
//已收到LGPL许可证的副本以及此程序；如果没有，您可以在http://www.dippanv.com；翻译-【前方走路】

// 警告!!!
// 截至2015年，我们强烈建议使用SphinxQL或REST APIs 而不是使用本地的SphinxAPI.

//当都在本地SphinxAPI协议和现有的APIs将继续存在，这或许不能，和甚至断裂（太多），曝光
//所有的新特性，通过多个不同implementations本地API对我们来说太复杂了

//也就是说，欢迎您补充未给定的维护官方的API，并删除此警告；

/////////////////////////////////////////////////////////////////////////////
// PHP版本的SphinxApi文档
/////////////////////////////////////////////////////////////////////////////

/// 已知搜索命令
define ( "SEARCHD_COMMAND_SEARCH",		0 );
define ( "SEARCHD_COMMAND_EXCERPT",		1 );
define ( "SEARCHD_COMMAND_UPDATE",		2 );
define ( "SEARCHD_COMMAND_KEYWORDS",	3 );
define ( "SEARCHD_COMMAND_PERSIST",		4 );
define ( "SEARCHD_COMMAND_STATUS",		5 );
define ( "SEARCHD_COMMAND_FLUSHATTRS",	7 );

/// 当前客户端命令实现版本
define ( "VER_COMMAND_SEARCH",		0x120 );
define ( "VER_COMMAND_EXCERPT",		0x105 );
define ( "VER_COMMAND_UPDATE",		0x104 );
define ( "VER_COMMAND_KEYWORDS",	0x100 );
define ( "VER_COMMAND_STATUS",		0x101 );
define ( "VER_COMMAND_QUERY",		0x100 );
define ( "VER_COMMAND_FLUSHATTRS",	0x100 );

/// 已知搜索状态代码
define ( "SEARCHD_OK",				0 );
define ( "SEARCHD_ERROR",			1 );
define ( "SEARCHD_RETRY",			2 );
define ( "SEARCHD_WARNING",			3 );

/// 已知排名模式（仅限ext2
define ( "SPH_RANK_PROXIMITY_BM15",	0 );	/// <默认模式，短语邻近主要因素和BM15次要因素
define ( "SPH_RANK_BM15",			1 );	 /// <统计模式，BM15仅排名（更快但质量更差）
define ( "SPH_RANK_NONE",			2 );	/// <无排名，所有匹配的权重为1
define ( "SPH_RANK_WORDCOUNT",		3 );	/// <简单字数加权，rank是每字段关键字出现次数的加权和
define ( "SPH_RANK_PROXIMITY",		4 );
define ( "SPH_RANK_MATCHANY",		5 );
define ( "SPH_RANK_FIELDMASK",		6 );
define ( "SPH_RANK_SPH04",			7 );
define ( "SPH_RANK_EXPR",			8 );
define ( "SPH_RANK_TOTAL",			9 );

define ( "SPH_RANK_PROXIMITY_BM25",	0 );	/// <PROXIMITY_BM15的别名; 要弃用了
define ( "SPH_RANK_BM25",			1 );	/// <BM15的别名; 要弃用了

///已知的排序模式
define ( "SPH_SORT_RELEVANCE",		0 );
define ( "SPH_SORT_ATTR_DESC",		1 );
define ( "SPH_SORT_ATTR_ASC",		2 );
define ( "SPH_SORT_TIME_SEGMENTS", 	3 );
define ( "SPH_SORT_EXTENDED", 		4 );

///已知过滤器类型
define ( "SPH_FILTER_VALUES",		0 );
define ( "SPH_FILTER_RANGE",		1 );
define ( "SPH_FILTER_FLOATRANGE",	2 );
define ( "SPH_FILTER_STRING",	3 );
define ( "SPH_FILTER_STRING_LIST",	6 );

///已知属性类型
define ( "SPH_ATTR_INTEGER",		1 );
define ( "SPH_ATTR_TIMESTAMP",		2 );
define ( "SPH_ATTR_ORDINAL",		3 );
define ( "SPH_ATTR_BOOL",			4 );
define ( "SPH_ATTR_FLOAT",			5 );
define ( "SPH_ATTR_BIGINT",			6 );
define ( "SPH_ATTR_STRING",			7 );
define ( "SPH_ATTR_FACTORS",			1001 );
define ( "SPH_ATTR_MULTI",			0x40000001 );
define ( "SPH_ATTR_MULTI64",			0x40000002 );

///已知的分组功能
define ( "SPH_GROUPBY_DAY",			0 );
define ( "SPH_GROUPBY_WEEK",		1 );
define ( "SPH_GROUPBY_MONTH",		2 );
define ( "SPH_GROUPBY_YEAR",		3 );
define ( "SPH_GROUPBY_ATTR",		4 );
define ( "SPH_GROUPBY_ATTRPAIR",	5 );

///已知的更新类型
define ( "SPH_UPDATE_PLAIN",		0 );
define ( "SPH_UPDATE_MVA",			1 );
define ( "SPH_UPDATE_STRING",		2 );
define ( "SPH_UPDATE_JSON",			3 );

// PHP整数的重要属性：
//  - 总是签名（一点点PHP_INT_SIZE）
//  - 从字符串到int的转换已饱和
//  -  float是double
//  -  div将参数转换为浮点数
//  -  mod将参数转换为int

//下面的包装代码如下：
//  - 当我们得到一个int时，只需打包它
//如果性能有问题，这是用户应该追求的分支
//
//  - 否则，我们得到一个字符串形式的数字
//这可能是由于不同的原因，但我们假设这是
//因为它不适合PHP int
//
//  - 将字符串分解为高和低整数以进行打包
//  - 如果我们有bcmath，则使用它
//  - 如果我们不这样做，我们必须手动完成（这是有趣的部分）
//
//  -  x64分支使用整数进行分解
//  -  x32（ab）使用浮点数，因为我们无法将无符号的32位数放入int中
//
//解包程序几乎是一样的。
//  - 如果可以的话，返回整数
//  - 否则将数字格式化为字符串

/// 打包64位签名
function sphPackI64 ( $v )
{
	assert ( is_numeric($v) );
	
	// x64
	if ( PHP_INT_SIZE>=8 )
	{
		$v = (int)$v;
		return pack ( "NN", $v>>32, $v&0xFFFFFFFF );
	}

	// x32, int
	if ( is_int($v) )
		return pack ( "NN", $v < 0 ? -1 : 0, $v );

	// x32, bcmath	
	if ( function_exists("bcmul") )
	{
		if ( bccomp ( $v, 0 ) == -1 )
			$v = bcadd ( "18446744073709551616", $v );
		else if ( bccomp ( $v, "9223372036854775807" ) > 0 )
			$v = "9223372036854775807"; // 钳在2 ^ 63-1像老板（即像64位php一样）
		$h = bcdiv ( $v, "4294967296", 0 );
		$l = bcmod ( $v, "4294967296" );
		return pack ( "NN", (float)$h, (float)$l ); //转换为float是故意的; int将失去第31位
	}

	// x32, no-bcmath
	$p = max(0, strlen($v) - 13);
	$lo = abs((float)substr($v, $p));
	$hi = abs((float)substr($v, 0, $p));

	$m = $lo + $hi*1316134912.0; // (10 ^ 13) % (1 << 32) = 1316134912
	$q = floor($m/4294967296.0);
	$l = $m - ($q*4294967296.0);
	$h = $hi*2328.0 + $q; // (10 ^ 13) / (1 << 32) = 2328

	if ( $v<0 )
	{
		if ( $l==0 )
			$h = 4294967296.0 - $h;
		else
		{
			$h = 4294967295.0 - $h;
			$l = 4294967296.0 - $l;
		}
	}
	return pack ( "NN", $h, $l );
}

/// 打包64位无符号d
function sphPackU64 ( $v )
{
	assert ( is_numeric($v) );
	
	// x64
	if ( PHP_INT_SIZE>=8 )
	{
		assert ( $v>=0 );
		
		// x64, int
		if ( is_int($v) )
			return pack ( "NN", $v>>32, $v&0xFFFFFFFF );
						  
		// x64, bcmath
		if ( function_exists("bcmul") )
		{
			$h = bcdiv ( $v, 4294967296, 0 );
			$l = bcmod ( $v, 4294967296 );
			return pack ( "NN", $h, $l );
		}
		
		// x64, no-bcmath
		$p = max ( 0, strlen($v) - 13 );
		$lo = (int)substr ( $v, $p );
		$hi = (int)substr ( $v, 0, $p );
	
		$m = $lo + $hi*1316134912;
		$l = $m % 4294967296;
		$h = $hi*2328 + (int)($m/4294967296);

		return pack ( "NN", $h, $l );
	}

	// x32, int
	if ( is_int($v) )
		return pack ( "NN", 0, $v );
	
	// x32, bcmath
	if ( function_exists("bcmul") )
	{
		$h = bcdiv ( $v, "4294967296", 0 );
		$l = bcmod ( $v, "4294967296" );
		return pack ( "NN", (float)$h, (float)$l ); //转换为float是故意的; int将失去第31位
	}

	// x32, no-bcmath
	$p = max(0, strlen($v) - 13);
	$lo = (float)substr($v, $p);
	$hi = (float)substr($v, 0, $p);
	
	$m = $lo + $hi*1316134912.0;
	$q = floor($m / 4294967296.0);
	$l = $m - ($q * 4294967296.0);
	$h = $hi*2328.0 + $q;

	return pack ( "NN", $h, $l );
}

// 解压缩64位无符号
function sphUnpackU64 ( $v )
{
	list ( $hi, $lo ) = array_values ( unpack ( "N*N*", $v ) );

	if ( PHP_INT_SIZE>=8 )
	{
		if ( $hi<0 ) $hi += (1<<32); // //因为php 5.2.2到5.2.5再次搞砸了
		if ( $lo<0 ) $lo += (1<<32);

		// x64, int
		if ( $hi<=2147483647 )
			return ($hi<<32) + $lo;

		// x64, bcmath
		if ( function_exists("bcmul") )
			return bcadd ( $lo, bcmul ( $hi, "4294967296" ) );

		// x64, no-bcmath
		$C = 100000;
		$h = ((int)($hi / $C) << 32) + (int)($lo / $C);
		$l = (($hi % $C) << 32) + ($lo % $C);
		if ( $l>$C )
		{
			$h += (int)($l / $C);
			$l  = $l % $C;
		}

		if ( $h==0 )
			return $l;
		return sprintf ( "%d%05d", $h, $l );
	}

	// x32, int
	if ( $hi==0 )
	{
		if ( $lo>0 )
			return $lo;
		return sprintf ( "%u", $lo );
	}

	$hi = sprintf ( "%u", $hi );
	$lo = sprintf ( "%u", $lo );

	// x32, bcmath
	if ( function_exists("bcmul") )
		return bcadd ( $lo, bcmul ( $hi, "4294967296" ) );
	
	// x32, no-bcmath
	$hi = (float)$hi;
	$lo = (float)$lo;
	
	$q = floor($hi/10000000.0);
	$r = $hi - $q*10000000.0;
	$m = $lo + $r*4967296.0;
	$mq = floor($m/10000000.0);
	$l = $m - $mq*10000000.0;
	$h = $q*4294967296.0 + $r*429.0 + $mq;

	$h = sprintf ( "%.0f", $h );
	$l = sprintf ( "%07.0f", $l );
	if ( $h=="0" )
		return sprintf( "%.0f", (float)$l );
	return $h . $l;
}

// 解压缩64位签名
function sphUnpackI64 ( $v )
{
	list ( $hi, $lo ) = array_values ( unpack ( "N*N*", $v ) );

	// x64
	if ( PHP_INT_SIZE>=8 )
	{
		if ( $hi<0 ) $hi += (1<<32); // //因为php 5.2.2到5.2.5再次搞砸了
		if ( $lo<0 ) $lo += (1<<32);

		return ($hi<<32) + $lo;
	}

	// x32, int
	if ( $hi==0 )
	{
		if ( $lo>0 )
			return $lo;
		return sprintf ( "%u", $lo );
	}
	// x32, int
	elseif ( $hi==-1 )
	{
		if ( $lo<0 )
			return $lo;
		return sprintf ( "%.0f", $lo - 4294967296.0 );
	}
	
	$neg = "";
	$c = 0;
	if ( $hi<0 )
	{
		$hi = ~$hi;
		$lo = ~$lo;
		$c = 1;
		$neg = "-";
	}	

	$hi = sprintf ( "%u", $hi );
	$lo = sprintf ( "%u", $lo );

	// x32, bcmath
	if ( function_exists("bcmul") )
		return $neg . bcadd ( bcadd ( $lo, bcmul ( $hi, "4294967296" ) ), $c );

	// x32, no-bcmath
	$hi = (float)$hi;
	$lo = (float)$lo;
	
	$q = floor($hi/10000000.0);
	$r = $hi - $q*10000000.0;
	$m = $lo + $r*4967296.0;
	$mq = floor($m/10000000.0);
	$l = $m - $mq*10000000.0 + $c;
	$h = $q*4294967296.0 + $r*429.0 + $mq;
	if ( $l==10000000 )
	{
		$l = 0;
		$h += 1;
	}

	$h = sprintf ( "%.0f", $h );
	$l = sprintf ( "%07.0f", $l );
	if ( $h=="0" )
		return $neg . sprintf( "%.0f", (float)$l );
	return $neg . $h . $l;
}


function sphFixUint ( $value )
{
	if ( PHP_INT_SIZE>=8 )
	{
		// x64 route，解决方法在5.2.2+中破解了unpack（）
		if ( $value<0 ) $value += (1<<32);
		return $value;
	}
	else
	{
		// x32 route，workaround php signed / unsigned braindamage
		return sprintf ( "%u", $value );
	}
}

function sphSetBit ( $flag, $bit, $on )
{
	if ( $on )
	{
		$flag |= ( 1<<$bit );
	} else
	{
		$reset = 16777215 ^ ( 1<<$bit );
		$flag = $flag & $reset;
	}
	return $flag;
}


/// sphinx searchd 客户端类
class SphinxClient
{
	var $_host; /// <searchd host（默认为“localhost”）
	var $_port; /// <searchd port（默认为9312）
	var $_offset; /// <从结果集开始搜索的记录数（默认为0）
	var $_limit; /// <从offset开始从结果集返回的记录数（默认值为20）
	var $_sort; /// <匹配排序模式（默认为SPH_SORT_RELEVANCE）
	var $_sortby; /// <要排序的属性（defualt是“”）
	var $_min_id; /// <min ID匹配（默认为0，表示没有限制）
	var $_max_id; /// <要匹配的最大ID（默认为0，表示没有限制）
	var $_filters; /// <搜索过滤器
	var $_groupby; /// <group-by属性名称
	var $_groupfunc; /// <group-by函数（用于预处理group-by属性值）
	var $_groupsort; /// <group-by sorting子句（用于对结果集中的组进行排序）
	var $_groupdistinct; /// <group-by count-distinct属性
	var $_maxmatches; /// <max匹配检索
	var $_cutoff; /// <cutoff停止搜索（默认为0）
	var $_retrycount; /// <分布式重试计数
	var $_retrydelay; /// <分布式重试延迟
	var $_indexweights; /// <每索引权重
	var $_ranker; /// <排名模式（默认为SPH_RANK_PROXIMITY_BM15）
	var $_rankexpr; /// <排名模式表达式（对于SPH_RANK_EXPR）
	var $_maxquerytime; /// <最大查询时间，毫秒（默认为0，不限制）
	var $_fieldweights; /// <每字段名称权重
	var $_select; /// <select-list（属性或表达式，带有可选的别名）
	var $_query_flags; /// <per-query各种标志
	var $_predictedtime; /// <per-query max_predicted_time
	var $_outerorderby; /// <外部匹配排序依据
	var $_outeroffset; /// <外部偏移量
	var $_outerlimit; /// <外部限制
	var $_hasouter;
	var $_token_filter_library; /// <token_filter插件库名称
	var $_token_filter_name; /// <token_filter插件名称
	var $_token_filter_opts; /// <token_filter插件选项

	var $_error; /// <最后一条错误消息
	var $_warning; /// <最后一条警告信息
	var $_connerror; /// <连接错误与远程错误标志

	var $_reqs; /// <请求多个查询的数组
	var $_mbenc; /// <存储的mbstring编码
	var $_arrayresult; /// <$result [“matches”]应该是散列还是数组
	var $_timeout; /// <连接超时

	/////////////////////////////////////////////////////////////////////////////
	// common stuff
	/////////////////////////////////////////////////////////////////////////////

	/// 创建一个新的客户端对象并填充默认值
	function __construct ()
	{
		// 每个客户端对象设置
		$this->_host		= "localhost";
		$this->_port		= 9312;
		$this->_path		= false;
		$this->_socket		= false;

		// 每个查询设置
		$this->_offset		= 0;
		$this->_limit		= 20;
		$this->_sort		= SPH_SORT_RELEVANCE;
		$this->_sortby		= "";
		$this->_min_id		= 0;
		$this->_max_id		= 0;
		$this->_filters		= array ();
		$this->_groupby		= "";
		$this->_groupfunc	= SPH_GROUPBY_DAY;
		$this->_groupsort	= "@group desc";
		$this->_groupdistinct= "";
		$this->_maxmatches	= 1000;
		$this->_cutoff		= 0;
		$this->_retrycount	= 0;
		$this->_retrydelay	= 0;
		$this->_indexweights= array ();
		$this->_ranker		= SPH_RANK_PROXIMITY_BM15;
		$this->_rankexpr	= "";
		$this->_maxquerytime= 0;
		$this->_fieldweights= array();
		$this->_select		= "*";
		$this->_query_flags = sphSetBit ( 0, 6, true ); // 默认idf = tfidf_normalized
		$this->_predictedtime = 0;
		$this->_outerorderby = "";
		$this->_outeroffset = 0;
		$this->_outerlimit = 0;
		$this->_hasouter = false;
		$this->_token_filter_library = '';
		$this->_token_filter_name = '';
		$this->_token_filter_opts = '';

		$this->_error		= ""; // 每个回复字段（对于单个查询案例）
		$this->_warning		= "";
		$this->_connerror	= false;

		$this->_reqs		= array ();	// 请求存储（对于多查询案例）
		$this->_mbenc		= "";
		$this->_arrayresult	= false;
		$this->_timeout		= 0;
	}

	function __destruct()
	{
		if ( $this->_socket !== false )
			fclose ( $this->_socket );
	}

	/// 获取最后一条错误消息（字符串）
	function GetLastError ()
	{
		return $this->_error;
	}

	/// 获取最后一条警告消息（字符串）
	function GetLastWarning ()
	{
		return $this->_warning;
	}

	/// 获取上一个错误标志（告诉网络连接错误来自搜索错误或损坏的响应）
	function IsConnectError()
	{
		return $this->_connerror;
	}

	/// 设置searchd主机名（字符串）和端口（整数）
	function SetServer ( $host, $port = 0 )
	{
		assert ( is_string($host) );
		if ( $host[0] == '/')
		{
			$this->_path = 'unix://' . $host;
			return;
		}
		if ( substr ( $host, 0, 7 )=="unix://" )
		{
			$this->_path = $host;
			return;
		}
				
		$this->_host = $host;
		$port = intval($port);
		assert ( 0<=$port && $port<65536 );
		$this->_port = ( $port==0 ) ? 9312 : $port;
		$this->_path = '';
	}

	/// 设置服务器连接超时（0删除）
	function SetConnectTimeout ( $timeout )
	{
		assert ( is_numeric($timeout) );
		$this->_timeout = $timeout;
	}


	function _Send ( $handle, $data, $length )
	{
		if ( feof($handle) || fwrite ( $handle, $data, $length ) !== $length )
		{
			$this->_error = 'connection unexpectedly closed (timed out?)';
			$this->_connerror = true;
			return false;
		}
		return true;
	}

	/////////////////////////////////////////////////////////////////////////////

	/// 进入mbstring解决方法模式
	function _MBPush ()
	{
		$this->_mbenc = "";
		if ( ini_get ( "mbstring.func_overload" ) & 2 )
		{
			$this->_mbenc = mb_internal_encoding();
			mb_internal_encoding ( "latin1" );
		}
    }

	/// 离开mbstring解决方法模式
	function _MBPop ()
	{
		if ( $this->_mbenc )
			mb_internal_encoding ( $this->_mbenc );
	}

	/// 连接到searchd服务器
	function _Connect ()
	{
		if ( $this->_socket!==false )
		{
			// 我们处于持久连接模式，所以我们有一个套接字
			// 但是，需要检查它是否还活着
			if ( !@feof ( $this->_socket ) )
				return $this->_socket;

			// force reopen
			$this->_socket = false;
		}

		$errno = 0;
		$errstr = "";
		$this->_connerror = false;

		if ( $this->_path )
		{
			$host = $this->_path;
			$port = 0;
		}
		else
		{
			$host = $this->_host;
			$port = $this->_port;
		}

		if ( $this->_timeout<=0 )
			$fp = @fsockopen ( $host, $port, $errno, $errstr );
		else
			$fp = @fsockopen ( $host, $port, $errno, $errstr, $this->_timeout );
		
		if ( !$fp )
		{
			if ( $this->_path )
				$location = $this->_path;
			else
				$location = "{$this->_host}:{$this->_port}";
			
			$errstr = trim ( $errstr );
			$this->_error = "connection to $location failed (errno=$errno, msg=$errstr)";
			$this->_connerror = true;
			return false;
		}

		//发送我的版本
		//这是一个微妙的部分。我们必须在（！）从searchd读回来之前做。
		//因为在某些情况下（例如在FreeBSD上报告）
		//由于Nagle，TCP堆栈可能会限制写 - 写 - 读取模式。
		if ( !$this->_Send ( $fp, pack ( "N", 1 ), 4 ) )
		{
			fclose ( $fp );
			$this->_error = "failed to send client protocol version";
			return false;
		}

		// check version
		list(,$v) = unpack ( "N*", fread ( $fp, 4 ) );
		$v = (int)$v;
		if ( $v<1 )
		{
			fclose ( $fp );
			$this->_error = "expected searchd protocol version 1+, got version '$v'";
			return false;
		}

		return $fp;
	}

	/// 从搜索服务器获取并检查响应数据包
	function _GetResponse ( $fp, $client_ver )
	{
		$response = "";
		$len = 0;

		$header = fread ( $fp, 8 );
		if ( strlen($header)==8 )
		{
			list ( $status, $ver, $len ) = array_values ( unpack ( "n2a/Nb", $header ) );
			$left = $len;
			while ( $left>0 && !feof($fp) )
			{
				$chunk = fread ( $fp, min ( 8192, $left ) );
				if ( $chunk )
				{
					$response .= $chunk;
					$left -= strlen($chunk);
				}
			}
		}
		if ( $this->_socket === false )
			fclose ( $fp );

		// check response
		$read = strlen ( $response );
		if ( !$response || $read!=$len )
		{
			$this->_error = $len
				? "failed to read searchd response (status=$status, ver=$ver, len=$len, read=$read)"
				: "received zero-sized searchd response";
			return false;
		}

		// check status
		if ( $status==SEARCHD_WARNING )
		{
			list(,$wlen) = unpack ( "N*", substr ( $response, 0, 4 ) );
			$this->_warning = substr ( $response, 4, $wlen );
			return substr ( $response, 4+$wlen );
		}
		if ( $status==SEARCHD_ERROR )
		{
			$this->_error = "searchd error: " . substr ( $response, 4 );
			return false;
		}
		if ( $status==SEARCHD_RETRY )
		{
			$this->_error = "temporary searchd error: " . substr ( $response, 4 );
			return false;
		}
		if ( $status!=SEARCHD_OK )
		{
			$this->_error = "unknown status code '$status'";
			return false;
		}

		// check version
		if ( $ver<$client_ver )
		{
			$this->_warning = sprintf ( "searchd command v.%d.%d older than client's v.%d.%d, some options might not work",
				$ver>>8, $ver&0xff, $client_ver>>8, $client_ver&0xff );
		}

		return $response;
	}

	/////////////////////////////////////////////////////////////////////////////
	//搜索
	/////////////////////////////////////////////////////////////////////////////

	///设置偏移量并计入结果集，
	///并可选择设置最大匹配和截止限制
	function SetLimits ( $offset, $limit, $max=0, $cutoff=0 )
	{
		assert ( is_int($offset) );
		assert ( is_int($limit) );
		assert ( $offset>=0 );
		assert ( $limit>0 );
		assert ( $max>=0 );
		$this->_offset = $offset;
		$this->_limit = $limit;
		if ( $max>0 )
			$this->_maxmatches = $max;
		if ( $cutoff>0 )
			$this->_cutoff = $cutoff;
	}

	///设置每个索引的最大查询时间（以毫秒为单位）
	///整数，0表示“不限制”
	function SetMaxQueryTime ( $max )
	{
		assert ( is_int($max) );
		assert ( $max>=0 );
		$this->_maxquerytime = $max;
	}

	///设置排名模式
	function SetRankingMode ( $ranker, $rankexpr="" )
	{
		assert ( $ranker===0 || $ranker>=1 && $ranker<SPH_RANK_TOTAL );
		assert ( is_string($rankexpr) );
		$this->_ranker = $ranker;
		$this->_rankexpr = $rankexpr;
	}

	///设置匹配排序模式
	function SetSortMode ( $mode, $sortby="" )
	{
		assert (
			$mode==SPH_SORT_RELEVANCE ||
			$mode==SPH_SORT_ATTR_DESC ||
			$mode==SPH_SORT_ATTR_ASC ||
			$mode==SPH_SORT_TIME_SEGMENTS ||
			$mode==SPH_SORT_EXTENDED );
		assert ( is_string($sortby) );
		assert ( $mode==SPH_SORT_RELEVANCE || strlen($sortby)>0 );

		$this->_sort = $mode;
		$this->_sortby = $sortby;
	}

	///按顺序绑定每个字段的权重
	///已弃用; 请改用SetFieldWeights（）
	function SetWeights ( $weights )
	{
		die("This method is now deprecated; please use SetFieldWeights instead");
	}

	/// 按名称绑定每个字段的权重
	function SetFieldWeights ( $weights )
	{
		assert ( is_array($weights) );
		foreach ( $weights as $name=>$weight )
		{
			assert ( is_string($name) );
			assert ( is_int($weight) );
		}
		$this->_fieldweights = $weights;
	}

	/// 按名称绑定每个索引的权重
	function SetIndexWeights ( $weights )
	{
		assert ( is_array($weights) );
		foreach ( $weights as $index=>$weight )
		{
			assert ( is_string($index) );
			assert ( is_int($weight) );
		}
		$this->_indexweights = $weights;
	}

	/// 设置要匹配的ID范围
	/// 仅匹配记录，如果文档ID是beetwen $ min和$ max（包括）
	function SetIDRange ( $min, $max )
	{
		assert ( is_numeric($min) );
		assert ( is_numeric($max) );
		assert ( $min<=$max );
		$this->_min_id = $min;
		$this->_max_id = $max;
	}

	/// 设置值设置过滤器
	/// 仅匹配$属性值在给定集合中的记录
	function SetFilter ( $attribute, $values, $exclude=false )
	{
		assert ( is_string($attribute) );
		assert ( is_array($values) );

		if ( count($values) )
		{
			foreach ( $values as $value )
				assert ( is_numeric($value) );

			$this->_filters[] = array ( "type"=>SPH_FILTER_VALUES, "attr"=>$attribute, "exclude"=>$exclude, "values"=>$values );
		}
	}
	
	/// 设置字符串过滤器
	/// 仅匹配$ attribute值相等的记录
	function SetFilterString ( $attribute, $value, $exclude=false )
	{
		assert ( is_string($attribute) );
		assert ( is_string($value) );
		$this->_filters[] = array ( "type"=>SPH_FILTER_STRING, "attr"=>$attribute, "exclude"=>$exclude, "value"=>$value );
	}	
	
	/// 设置字符串列表过滤器
	function SetFilterStringList ( $attribute, $value, $exclude=false )
	{
		assert ( is_string($attribute) );
		assert ( is_array($value) );
		
		foreach ( $value as $v )
			assert ( is_string($v) );
		
		$this->_filters[] = array ( "type"=>SPH_FILTER_STRING_LIST, "attr"=>$attribute, "exclude"=>$exclude, "values"=>$value );
	}	
	

	/// 设置范围过滤器
	/// 仅匹配记录，如果$属性值是beetwen $ min和$ max（包括）
	function SetFilterRange ( $attribute, $min, $max, $exclude=false )
	{
		assert ( is_string($attribute) );
		assert ( is_numeric($min) );
		assert ( is_numeric($max) );
		assert ( $min<=$max );

		$this->_filters[] = array ( "type"=>SPH_FILTER_RANGE, "attr"=>$attribute, "exclude"=>$exclude, "min"=>$min, "max"=>$max );
	}

	///设置浮点范围过滤器
	///仅匹配记录，如果$属性值是beetwen $ min和$ max（包括）
	function SetFilterFloatRange ( $attribute, $min, $max, $exclude=false )
	{
		assert ( is_string($attribute) );
		assert ( is_float($min) );
		assert ( is_float($max) );
		assert ( $min<=$max );

		$this->_filters[] = array ( "type"=>SPH_FILTER_FLOATRANGE, "attr"=>$attribute, "exclude"=>$exclude, "min"=>$min, "max"=>$max );
	}

	///设置分组属性和功能
	function SetGroupBy ( $attribute, $func, $groupsort="@group desc" )
	{
		assert ( is_string($attribute) );
		assert ( is_string($groupsort) );
		assert ( $func==SPH_GROUPBY_DAY
			|| $func==SPH_GROUPBY_WEEK
			|| $func==SPH_GROUPBY_MONTH
			|| $func==SPH_GROUPBY_YEAR
			|| $func==SPH_GROUPBY_ATTR
			|| $func==SPH_GROUPBY_ATTRPAIR );

		$this->_groupby = $attribute;
		$this->_groupfunc = $func;
		$this->_groupsort = $groupsort;
	}

	///为group-by查询设置count-distinct属性
	function SetGroupDistinct ( $attribute )
	{
		assert ( is_string($attribute) );
		$this->_groupdistinct = $attribute;
	}

	///设置分布式重试计数和延迟
	function SetRetries ( $count, $delay=0 )
	{
		assert ( is_int($count) && $count>=0 );
		assert ( is_int($delay) && $delay>=0 );
		$this->_retrycount = $count;
		$this->_retrydelay = $delay;
	}

	///设置结果集格式（哈希或数组;默认为哈希）
	/// PHP特定; 对于可能包含重复ID的逐个MVA结果集所需的
	function SetArrayResult ( $arrayresult )
	{
		assert ( is_bool($arrayresult) );
		$this->_arrayresult = $arrayresult;
	}

	///设置select-list（属性或表达式），类似SQL的语法
	function SetSelect ( $select )
	{
		assert ( is_string ( $select ) );
		$this->_select = $select;
	}
	
	function SetQueryFlag ( $flag_name, $flag_value )
	{
		$known_names = array ( "reverse_scan", "sort_method", "max_predicted_time", "boolean_simplify", "idf", "global_idf", "low_priority" );
		$flags = array (
		"reverse_scan" => array ( 0, 1 ),
		"sort_method" => array ( "pq", "kbuffer" ),
		"max_predicted_time" => array ( 0 ),
		"boolean_simplify" => array ( true, false ),
		"idf" => array ("normalized", "plain", "tfidf_normalized", "tfidf_unnormalized" ),
		"global_idf" => array ( true, false ),
		"low_priority" => array ( true, false )
		);
		
		assert ( isset ( $flag_name, $known_names ) );
		assert ( in_array( $flag_value, $flags[$flag_name], true ) || ( $flag_name=="max_predicted_time" && is_int ( $flag_value ) && $flag_value>=0 ) );
		
		if ( $flag_name=="reverse_scan" )	$this->_query_flags = sphSetBit ( $this->_query_flags, 0, $flag_value==1 );
		if ( $flag_name=="sort_method" )	$this->_query_flags = sphSetBit ( $this->_query_flags, 1, $flag_value=="kbuffer" );
		if ( $flag_name=="max_predicted_time" )
		{
			$this->_query_flags = sphSetBit ( $this->_query_flags, 2, $flag_value>0 );
			$this->_predictedtime = (int)$flag_value;
		}
		if ( $flag_name=="boolean_simplify" )	$this->_query_flags = sphSetBit ( $this->_query_flags, 3, $flag_value );
		if ( $flag_name=="idf" && ( $flag_value=="normalized" || $flag_value=="plain" ) )	$this->_query_flags = sphSetBit ( $this->_query_flags, 4, $flag_value=="plain" );
		if ( $flag_name=="global_idf" )	$this->_query_flags = sphSetBit ( $this->_query_flags, 5, $flag_value );
		if ( $flag_name=="idf" && ( $flag_value=="tfidf_normalized" || $flag_value=="tfidf_unnormalized" ) )	$this->_query_flags = sphSetBit ( $this->_query_flags, 6, $flag_value=="tfidf_normalized" );
		if ( $flag_name=="low_priority" ) $this->_query_flags = sphSetBit ( $this->_query_flags, 8, $flag_value );
	}
	
	///通过参数设置外部顺序
	function SetOuterSelect ( $orderby, $offset, $limit )
	{
		assert ( is_string($orderby) );
		assert ( is_int($offset) );
		assert ( is_int($limit) );
		assert ( $offset>=0 );
		assert ( $limit>0 );

		$this->_outerorderby = $orderby;
		$this->_outeroffset = $offset;
		$this->_outerlimit = $limit;
		$this->_hasouter = true;
	}

	///通过参数设置外部顺序
	function SetTokenFilter ( $library, $name, $opts="" )
	{
		assert ( is_string($library) );
		assert ( is_string($name) );
		assert ( is_string($opts) );
		
		$this->_token_filter_library = $library;
		$this->_token_filter_name = $name;
		$this->_token_filter_opts = $opts;
	}
	
	//////////////////////////////////////////////////////////////////////////////

	///清除所有过滤器（用于多个查询）
	function ResetFilters ()
	{
		$this->_filters = array();
	}

	///清除groupby设置（用于多个查询）
	function ResetGroupBy ()
	{
		$this->_groupby		= "";
		$this->_groupfunc	= SPH_GROUPBY_DAY;
		$this->_groupsort	= "@group desc";
		$this->_groupdistinct= "";
	}

	function ResetQueryFlag ()
	{
		$this->_query_flags = sphSetBit ( 0, 6, true ); // default idf=tfidf_normalized
		$this->_predictedtime = 0;
	}

	function ResetOuterSelect ()
	{
		$this->_outerorderby = '';
		$this->_outeroffset = 0;
		$this->_outerlimit = 0;
		$this->_hasouter = false;
	}

	//////////////////////////////////////////////////////////////////////////////

	///连接到searchd服务器，通过给定的索引运行给定的搜索查询，
	///并返回搜索结果
	function Query ( $query, $index="*", $comment="" )
	{
		assert ( empty($this->_reqs) );

		$this->AddQuery ( $query, $index, $comment );
		$results = $this->RunQueries ();
		$this->_reqs = array (); // just in case it failed too early

		if ( !is_array($results) )
			return false; // probably network error; error message should be already filled

		$this->_error = $results[0]["error"];
		$this->_warning = $results[0]["warning"];
		if ( $results[0]["status"]==SEARCHD_ERROR )
			return false;
		else
			return $results[0];
	}

	/// helper以网络字节顺序打包浮动
	function _PackFloat ( $f )
	{
		$t1 = pack ( "f", $f ); // machine order
		list(,$t2) = unpack ( "L*", $t1 ); // int in machine order
		return pack ( "N", $t2 );
	}

	///将查询添加到多查询批处理
	///从RunQueries（）调用将索引返回到结果数组中
	function AddQuery ( $query, $index="*", $comment="" )
	{
		// mbstring workaround
		$this->_MBPush ();

		// build request
		// 6 == match_mode extended2
		$req = pack ( "NNNNN", $this->_query_flags, $this->_offset, $this->_limit, 6, $this->_ranker );
		if ( $this->_ranker==SPH_RANK_EXPR )
			$req .= pack ( "N", strlen($this->_rankexpr) ) . $this->_rankexpr;
		$req .= pack ( "N", $this->_sort ); // (deprecated) sort mode
		$req .= pack ( "N", strlen($this->_sortby) ) . $this->_sortby;
		$req .= pack ( "N", strlen($query) ) . $query; // query itself
		$req .= pack ( "N", 0 ); // weights
		$req .= pack ( "N", strlen($index) ) . $index; // indexes
		$req .= pack ( "N", 1 ); // id64 range marker
		$req .= sphPackU64 ( $this->_min_id ) . sphPackU64 ( $this->_max_id ); // id64 range

		// filters
		$req .= pack ( "N", count($this->_filters) );
		foreach ( $this->_filters as $filter )
		{
			$req .= pack ( "N", strlen($filter["attr"]) ) . $filter["attr"];
			$req .= pack ( "N", $filter["type"] );
			switch ( $filter["type"] )
			{
				case SPH_FILTER_VALUES:
					$req .= pack ( "N", count($filter["values"]) );
					foreach ( $filter["values"] as $value )
						$req .= sphPackI64 ( $value );
					break;

				case SPH_FILTER_RANGE:
					$req .= sphPackI64 ( $filter["min"] ) . sphPackI64 ( $filter["max"] );
					break;

				case SPH_FILTER_FLOATRANGE:
					$req .= $this->_PackFloat ( $filter["min"] ) . $this->_PackFloat ( $filter["max"] );
					break;
					
				case SPH_FILTER_STRING:
					$req .= pack ( "N", strlen($filter["value"]) ) . $filter["value"];
					break;

				case SPH_FILTER_STRING_LIST:
					$req .= pack ( "N", count($filter["values"]) );
					foreach ( $filter["values"] as $value )
						$req .= pack ( "N", strlen($value) ) . $value;
					break;
					
				default:
					assert ( 0 && "internal error: unhandled filter type" );
			}
			$req .= pack ( "N", $filter["exclude"] );
		}

		// group-by clause, max-matches count, group-sort clause, cutoff count
		$req .= pack ( "NN", $this->_groupfunc, strlen($this->_groupby) ) . $this->_groupby;
		$req .= pack ( "N", $this->_maxmatches );
		$req .= pack ( "N", strlen($this->_groupsort) ) . $this->_groupsort;
		$req .= pack ( "NNN", $this->_cutoff, $this->_retrycount, $this->_retrydelay );
		$req .= pack ( "N", strlen($this->_groupdistinct) ) . $this->_groupdistinct;

		// geoanchor point
		$req .= pack ( "N", 0 );

		// per-index weights
		$req .= pack ( "N", count($this->_indexweights) );
		foreach ( $this->_indexweights as $idx=>$weight )
			$req .= pack ( "N", strlen($idx) ) . $idx . pack ( "N", $weight );

		// max query time
		$req .= pack ( "N", $this->_maxquerytime );

		// per-field weights
		$req .= pack ( "N", count($this->_fieldweights) );
		foreach ( $this->_fieldweights as $field=>$weight )
			$req .= pack ( "N", strlen($field) ) . $field . pack ( "N", $weight );

		// comment
		$req .= pack ( "N", strlen($comment) ) . $comment;

		// attribute overrides
		$req .= pack ( "N", 0 );

		// select-list
		$req .= pack ( "N", strlen($this->_select) ) . $this->_select;
		
		// max_predicted_time
		if ( $this->_predictedtime>0 )
			$req .= pack ( "N", (int)$this->_predictedtime );
			
		$req .= pack ( "N", strlen($this->_outerorderby) ) . $this->_outerorderby;
		$req .= pack ( "NN", $this->_outeroffset, $this->_outerlimit );
		if ( $this->_hasouter )
			$req .= pack ( "N", 1 );
		else
			$req .= pack ( "N", 0 );
		
		// token_filter
		$req .= pack ( "N", strlen($this->_token_filter_library) ) . $this->_token_filter_library;
		$req .= pack ( "N", strlen($this->_token_filter_name) ) . $this->_token_filter_name;
		$req .= pack ( "N", strlen($this->_token_filter_opts) ) . $this->_token_filter_opts;

		// mbstring workaround
		$this->_MBPop ();

		// store request to requests array
		$this->_reqs[] = $req;
		return count($this->_reqs)-1;
	}

	///连接到searchd，运行查询批处理，并返回结果集数组
	function RunQueries ()
	{
		if ( empty($this->_reqs) )
		{
			$this->_error = "no queries defined, issue AddQuery() first";
			return false;
		}

		// mbstring workaround
		$this->_MBPush ();

		if (!( $fp = $this->_Connect() ))
		{
			$this->_MBPop ();
			return false;
		}

		// send query, get response
		$nreqs = count($this->_reqs);
		$req = join ( "", $this->_reqs );
		$len = 8+strlen($req);
		$req = pack ( "nnNNN", SEARCHD_COMMAND_SEARCH, VER_COMMAND_SEARCH, $len, 0, $nreqs ) . $req; // add header

		if ( !( $this->_Send ( $fp, $req, $len+8 ) ) ||
			 !( $response = $this->_GetResponse ( $fp, VER_COMMAND_SEARCH ) ) )
		{
			$this->_MBPop ();
			return false;
		}

		// query sent ok; we can reset reqs now
		$this->_reqs = array ();

		// parse and return response
		return $this->_ParseSearchResponse ( $response, $nreqs );
	}

	///解析并返回搜索查询（或查询）响应
	function _ParseSearchResponse ( $response, $nreqs )
	{
		$p = 0; // current position
		$max = strlen($response); //检查的最大位置，以防止损坏的响应

		$results = array ();
		for ( $ires=0; $ires<$nreqs && $p<$max; $ires++ )
		{
			$results[] = array();
			$result =& $results[$ires];

			$result["error"] = "";
			$result["warning"] = "";

			// extract status
			list(,$status) = unpack ( "N*", substr ( $response, $p, 4 ) ); $p += 4;
			$result["status"] = $status;
			if ( $status!=SEARCHD_OK )
			{
				list(,$len) = unpack ( "N*", substr ( $response, $p, 4 ) ); $p += 4;
				$message = substr ( $response, $p, $len ); $p += $len;

				if ( $status==SEARCHD_WARNING )
				{
					$result["warning"] = $message;
				} else
				{
					$result["error"] = $message;
					continue;
				}
			}

			// read schema
			$fields = array ();
			$attrs = array ();

			list(,$nfields) = unpack ( "N*", substr ( $response, $p, 4 ) ); $p += 4;
			while ( $nfields-->0 && $p<$max )
			{
				list(,$len) = unpack ( "N*", substr ( $response, $p, 4 ) ); $p += 4;
				$fields[] = substr ( $response, $p, $len ); $p += $len;
			}
			$result["fields"] = $fields;

			list(,$nattrs) = unpack ( "N*", substr ( $response, $p, 4 ) ); $p += 4;
			while ( $nattrs-->0 && $p<$max  )
			{
				list(,$len) = unpack ( "N*", substr ( $response, $p, 4 ) ); $p += 4;
				$attr = substr ( $response, $p, $len ); $p += $len;
				list(,$type) = unpack ( "N*", substr ( $response, $p, 4 ) ); $p += 4;
				$attrs[$attr] = $type;
			}
			$result["attrs"] = $attrs;

			// read match count
			list(,$count) = unpack ( "N*", substr ( $response, $p, 4 ) ); $p += 4;
			list(,$id64) = unpack ( "N*", substr ( $response, $p, 4 ) ); $p += 4;

			// read matches
			$idx = -1;
			while ( $count-->0 && $p<$max )
			{
				// index into result array
				$idx++;

				// parse document id and weight
				if ( $id64 )
				{
					$doc = sphUnpackU64 ( substr ( $response, $p, 8 ) ); $p += 8;
					list(,$weight) = unpack ( "N*", substr ( $response, $p, 4 ) ); $p += 4;
				}
				else
				{
					list ( $doc, $weight ) = array_values ( unpack ( "N*N*",
						substr ( $response, $p, 8 ) ) );
					$p += 8;
					$doc = sphFixUint($doc);
				}
				$weight = sprintf ( "%u", $weight );

				// create match entry
				if ( $this->_arrayresult )
					$result["matches"][$idx] = array ( "id"=>$doc, "weight"=>$weight );
				else
					$result["matches"][$doc]["weight"] = $weight;

				// parse and create attributes
				$attrvals = array ();
				foreach ( $attrs as $attr=>$type )
				{
					// handle 64bit ints
					if ( $type==SPH_ATTR_BIGINT )
					{
						$attrvals[$attr] = sphUnpackI64 ( substr ( $response, $p, 8 ) ); $p += 8;
						continue;
					}

					// handle floats
					if ( $type==SPH_ATTR_FLOAT )
					{
						list(,$uval) = unpack ( "N*", substr ( $response, $p, 4 ) ); $p += 4;
						list(,$fval) = unpack ( "f*", pack ( "L", $uval ) ); 
						$attrvals[$attr] = $fval;
						continue;
					}

					// handle everything else as unsigned ints
					list(,$val) = unpack ( "N*", substr ( $response, $p, 4 ) ); $p += 4;
					if ( $type==SPH_ATTR_MULTI )
					{
						$attrvals[$attr] = array ();
						$nvalues = $val;
						while ( $nvalues-->0 && $p<$max )
						{
							list(,$val) = unpack ( "N*", substr ( $response, $p, 4 ) ); $p += 4;
							$attrvals[$attr][] = sphFixUint($val);
						}
					} else if ( $type==SPH_ATTR_MULTI64 )
					{
						$attrvals[$attr] = array ();
						$nvalues = $val;
						while ( $nvalues>0 && $p<$max )
						{
							$attrvals[$attr][] = sphUnpackI64 ( substr ( $response, $p, 8 ) ); $p += 8;
							$nvalues -= 2;
						}
					} else if ( $type==SPH_ATTR_STRING )
					{
						$attrvals[$attr] = substr ( $response, $p, $val );
						$p += $val;						
					} else if ( $type==SPH_ATTR_FACTORS )
					{
						$attrvals[$attr] = substr ( $response, $p, $val-4 );
						$p += $val-4;						
					} else
					{
						$attrvals[$attr] = sphFixUint($val);
					}
				}

				if ( $this->_arrayresult )
					$result["matches"][$idx]["attrs"] = $attrvals;
				else
					$result["matches"][$doc]["attrs"] = $attrvals;
			}

			list ( $total, $total_found, $msecs, $words ) =
				array_values ( unpack ( "N*N*N*N*", substr ( $response, $p, 16 ) ) );
			$result["total"] = sprintf ( "%u", $total );
			$result["total_found"] = sprintf ( "%u", $total_found );
			$result["time"] = sprintf ( "%.3f", $msecs/1000 );
			$p += 16;

			while ( $words-->0 && $p<$max )
			{
				list(,$len) = unpack ( "N*", substr ( $response, $p, 4 ) ); $p += 4;
				$word = substr ( $response, $p, $len ); $p += $len;
				list ( $docs, $hits ) = array_values ( unpack ( "N*N*", substr ( $response, $p, 8 ) ) ); $p += 8;
				$result["words"][$word] = array (
					"docs"=>sprintf ( "%u", $docs ),
					"hits"=>sprintf ( "%u", $hits ) );
			}
		}

		$this->_MBPop ();
		return $results;
	}

	/////////////////////////////////////////////////////////////////////////////
	// excerpts generation
	/////////////////////////////////////////////////////////////////////////////

	///连接到searchd服务器，并生成exceprts（片段）
	///给定查询的给定文档。失败时返回false，
	///成功的一系列片段
	function BuildExcerpts ( $docs, $index, $words, $opts=array() )
	{
		assert ( is_array($docs) );
		assert ( is_string($index) );
		assert ( is_string($words) );
		assert ( is_array($opts) );

		$this->_MBPush ();

		if (!( $fp = $this->_Connect() ))
		{
			$this->_MBPop();
			return false;
		}

		/////////////////
		// fixup options
		/////////////////

		if ( !isset($opts["before_match"]) )		$opts["before_match"] = "<b>";
		if ( !isset($opts["after_match"]) )			$opts["after_match"] = "</b>";
		if ( !isset($opts["chunk_separator"]) )		$opts["chunk_separator"] = " ... ";
		if ( !isset($opts["field_separator"]) )		$opts["field_separator"] = "<br>";
		if ( !isset($opts["limit"]) )				$opts["limit"] = 256;
		if ( !isset($opts["limit_passages"]) )		$opts["limit_passages"] = 0;
		if ( !isset($opts["limit_words"]) )			$opts["limit_words"] = 0;
		if ( !isset($opts["around"]) )				$opts["around"] = 5;
		if ( !isset($opts["exact_phrase"]) )		$opts["exact_phrase"] = false;
		if ( !isset($opts["single_passage"]) )		$opts["single_passage"] = false;
		if ( !isset($opts["use_boundaries"]) )		$opts["use_boundaries"] = false;
		if ( !isset($opts["weight_order"]) )		$opts["weight_order"] = false;
		if ( !isset($opts["query_mode"]) )			$opts["query_mode"] = false;
		if ( !isset($opts["force_all_words"]) )		$opts["force_all_words"] = false;
		if ( !isset($opts["start_passage_id"]) )	$opts["start_passage_id"] = 1;
		if ( !isset($opts["load_files"]) )			$opts["load_files"] = false;
		if ( !isset($opts["html_strip_mode"]) )		$opts["html_strip_mode"] = "index";
		if ( !isset($opts["allow_empty"]) )			$opts["allow_empty"] = false;
		if ( !isset($opts["passage_boundary"]) )	$opts["passage_boundary"] = "none";
		if ( !isset($opts["emit_zones"]) )			$opts["emit_zones"] = false;
		if ( !isset($opts["load_files_scattered"]) )		$opts["load_files_scattered"] = false;
		

		/////////////////
		// build request
		/////////////////

		// v.1.2 req
		$flags = 1; // remove spaces
		if ( $opts["exact_phrase"] )	$flags |= 2;
		if ( $opts["single_passage"] )	$flags |= 4;
		if ( $opts["use_boundaries"] )	$flags |= 8;
		if ( $opts["weight_order"] )	$flags |= 16;
		if ( $opts["query_mode"] )		$flags |= 32;
		if ( $opts["force_all_words"] )	$flags |= 64;
		if ( $opts["load_files"] )		$flags |= 128;
		if ( $opts["allow_empty"] )		$flags |= 256;
		if ( $opts["emit_zones"] )		$flags |= 512;
		if ( $opts["load_files_scattered"] )	$flags |= 1024;
		$req = pack ( "NN", 0, $flags ); // mode=0, flags=$flags
		$req .= pack ( "N", strlen($index) ) . $index; // req index
		$req .= pack ( "N", strlen($words) ) . $words; // req words

		// options
		$req .= pack ( "N", strlen($opts["before_match"]) ) . $opts["before_match"];
		$req .= pack ( "N", strlen($opts["after_match"]) ) . $opts["after_match"];
		$req .= pack ( "N", strlen($opts["chunk_separator"]) ) . $opts["chunk_separator"];
		$req .= pack ( "N", strlen($opts["field_separator"]) ) . $opts["field_separator"];
		$req .= pack ( "NN", (int)$opts["limit"], (int)$opts["around"] );
		$req .= pack ( "NNN", (int)$opts["limit_passages"], (int)$opts["limit_words"], (int)$opts["start_passage_id"] ); // v.1.2
		$req .= pack ( "N", strlen($opts["html_strip_mode"]) ) . $opts["html_strip_mode"];
		$req .= pack ( "N", strlen($opts["passage_boundary"]) ) . $opts["passage_boundary"];

		// documents
		$req .= pack ( "N", count($docs) );
		foreach ( $docs as $doc )
		{
			assert ( is_string($doc) );
			$req .= pack ( "N", strlen($doc) ) . $doc;
		}

		////////////////////////////
		// send query, get response
		////////////////////////////

		$len = strlen($req);
		$req = pack ( "nnN", SEARCHD_COMMAND_EXCERPT, VER_COMMAND_EXCERPT, $len ) . $req; // add header
		if ( !( $this->_Send ( $fp, $req, $len+8 ) ) ||
			 !( $response = $this->_GetResponse ( $fp, VER_COMMAND_EXCERPT ) ) )
		{
			$this->_MBPop ();
			return false;
		}

		//////////////////
		// parse response
		//////////////////

		$pos = 0;
		$res = array ();
		$rlen = strlen($response);
		for ( $i=0; $i<count($docs); $i++ )
		{
			list(,$len) = unpack ( "N*", substr ( $response, $pos, 4 ) );
			$pos += 4;

			if ( $pos+$len > $rlen )
			{
				$this->_error = "incomplete reply";
				$this->_MBPop ();
				return false;
			}
			$res[] = $len ? substr ( $response, $pos, $len ) : "";
			$pos += $len;
		}

		$this->_MBPop ();
		return $res;
	}


	/////////////////////////////////////////////////////////////////////////////
	// 关键字生成
	/////////////////////////////////////////////////////////////////////////////

	///连接到searchd服务器，并为给定查询生成关键字列表
	///失败时返回false，
	///成功的一系列文字
	function BuildKeywords ( $query, $index, $hits )
	{
		assert ( is_string($query) );
		assert ( is_string($index) );
		assert ( is_bool($hits) );

		$this->_MBPush ();

		if (!( $fp = $this->_Connect() ))
		{
			$this->_MBPop();
			return false;
		}

		/////////////////
		// 构建请求
		/////////////////

		// v.1.0 req
		$req  = pack ( "N", strlen($query) ) . $query; // req query
		$req .= pack ( "N", strlen($index) ) . $index; // req index
		$req .= pack ( "N", (int)$hits );

		////////////////////////////
		// 发送查询，获得响应
		////////////////////////////

		$len = strlen($req);
		$req = pack ( "nnN", SEARCHD_COMMAND_KEYWORDS, VER_COMMAND_KEYWORDS, $len ) . $req; // add header
		if ( !( $this->_Send ( $fp, $req, $len+8 ) ) ||
			 !( $response = $this->_GetResponse ( $fp, VER_COMMAND_KEYWORDS ) ) )
		{
			$this->_MBPop ();
			return false;
		}

		//////////////////
		// 解析响应
		//////////////////

		$pos = 0;
		$res = array ();
		$rlen = strlen($response);
		list(,$nwords) = unpack ( "N*", substr ( $response, $pos, 4 ) );
		$pos += 4;
		for ( $i=0; $i<$nwords; $i++ )
		{
			list(,$len) = unpack ( "N*", substr ( $response, $pos, 4 ) );	$pos += 4;
			$tokenized = $len ? substr ( $response, $pos, $len ) : "";
			$pos += $len;

			list(,$len) = unpack ( "N*", substr ( $response, $pos, 4 ) );	$pos += 4;
			$normalized = $len ? substr ( $response, $pos, $len ) : "";
			$pos += $len;

			$res[] = array ( "tokenized"=>$tokenized, "normalized"=>$normalized );

			if ( $hits )
			{
				list($ndocs,$nhits) = array_values ( unpack ( "N*N*", substr ( $response, $pos, 8 ) ) );
				$pos += 8;
				$res [$i]["docs"] = $ndocs;
				$res [$i]["hits"] = $nhits;
			}

			if ( $pos > $rlen )
			{
				$this->_error = "incomplete reply";
				$this->_MBPop ();
				return false;
			}
		}

		$this->_MBPop ();
		return $res;
	}

	function EscapeString ( $string )
	{
		$from = array ( '\\', '(',')','|','-','!','@','~','"','&', '/', '^', '$', '=', '<' );
		$to   = array ( '\\\\', '\(','\)','\|','\-','\!','\@','\~','\"', '\&', '\/', '\^', '\$', '\=', '\<' );

		return str_replace ( $from, $to, $string );
	}

	/////////////////////////////////////////////////////////////////////////////
	// 属性更新
	/////////////////////////////////////////////////////////////////////////////

	///批量更新给定索引中给定行中的给定属性
	///成功时返回更新文档的数量（0或更多），失败时返回-1
	function UpdateAttributes ( $index, $attrs, $values, $type=SPH_UPDATE_PLAIN, $ignorenonexistent=false )
	{
		// 校验参数
		assert ( is_string($index) );
		assert ( is_bool($ignorenonexistent) );
		assert ( $type==SPH_UPDATE_PLAIN || $type==SPH_UPDATE_MVA || $type==SPH_UPDATE_STRING || $type==SPH_UPDATE_JSON );

		$mva = $type==SPH_UPDATE_MVA;
		$string = $type==SPH_UPDATE_STRING || $type==SPH_UPDATE_JSON;

		assert ( is_array($attrs) );
		foreach ( $attrs as $attr )
			assert ( is_string($attr) );

		assert ( is_array($values) );
		foreach ( $values as $id=>$entry )
		{
			assert ( is_numeric($id) );
			assert ( is_array($entry) );
			assert ( count($entry)==count($attrs) );
			foreach ( $entry as $v )
			{
				if ( $mva )
				{
					assert ( is_array($v) );
					foreach ( $v as $vv )
						assert ( is_int($vv) );
				} else if ( $string )
					assert ( is_string($v) );
				else
					assert ( is_int($v) );
			}
		}

		// build request
		$this->_MBPush ();
		$req = pack ( "N", strlen($index) ) . $index;

		$req .= pack ( "N", count($attrs) );
		$req .= pack ( "N", $ignorenonexistent ? 1 : 0 );
		foreach ( $attrs as $attr )
		{
			$req .= pack ( "N", strlen($attr) ) . $attr;
			$req .= pack ( "N", $type );
		}

		$req .= pack ( "N", count($values) );
		foreach ( $values as $id=>$entry )
		{
			$req .= sphPackU64 ( $id );
			foreach ( $entry as $v )
			{
				$nvalues = $mva ? count($v) : ( $string ? strlen($v) : $v );
				$req .= pack ( "N", $nvalues );
				if ( $mva )
				{
					foreach ( $v as $vv )
						$req .= pack ( "N", $vv );
				} else if ( $string )
						$req .= $v;
			}
		}

		// 连接，发送查询，获得响应
		if (!( $fp = $this->_Connect() ))
		{
			$this->_MBPop ();
			return -1;
		}

		$len = strlen($req);
		$req = pack ( "nnN", SEARCHD_COMMAND_UPDATE, VER_COMMAND_UPDATE, $len ) . $req; // add header
		if ( !$this->_Send ( $fp, $req, $len+8 ) )
		{
			$this->_MBPop ();
			return -1;
		}

		if (!( $response = $this->_GetResponse ( $fp, VER_COMMAND_UPDATE ) ))
		{
			$this->_MBPop ();
			return -1;
		}

		// parse response
		list(,$updated) = unpack ( "N*", substr ( $response, 0, 4 ) );
		$this->_MBPop ();
		return $updated;
	}

	/////////////////////////////////////////////////////////////////////////////
	// 持久连接
	/////////////////////////////////////////////////////////////////////////////

	function Open()
	{
		if ( $this->_socket !== false )
		{
			$this->_error = 'already connected';
			return false;
		}
		if ( !$fp = $this->_Connect() )
			return false;

		// command, command version = 0, body length = 4, body = 1
		$req = pack ( "nnNN", SEARCHD_COMMAND_PERSIST, 0, 4, 1 );
		if ( !$this->_Send ( $fp, $req, 12 ) )
			return false;

		$this->_socket = $fp;
		return true;
	}

	function Close()
	{
		if ( $this->_socket === false )
		{
			$this->_error = 'not connected';
			return false;
		}

		fclose ( $this->_socket );
		$this->_socket = false;
		
		return true;
	}

	//////////////////////////////////////////////////////////////////////////
	// 状态
	//////////////////////////////////////////////////////////////////////////

	function Status ($session=false)
	{
        assert ( is_bool($session) );

		$this->_MBPush ();
		if (!( $fp = $this->_Connect() ))
		{
			$this->_MBPop();
			return false;
		}

		$req = pack ( "nnNN", SEARCHD_COMMAND_STATUS, VER_COMMAND_STATUS, 4, $session?0:1 ); // len=4, body=1
		if ( !( $this->_Send ( $fp, $req, 12 ) ) ||
			 !( $response = $this->_GetResponse ( $fp, VER_COMMAND_STATUS ) ) )
		{
			$this->_MBPop ();
			return false;
		}

		$p = 0;
		list ( $rows, $cols ) = array_values ( unpack ( "N*N*", substr ( $response, $p, 8 ) ) ); $p += 8;

		$res = array();
		for ( $i=0; $i<$rows; $i++ )
			for ( $j=0; $j<$cols; $j++ )
		{
			list(,$len) = unpack ( "N*", substr ( $response, $p, 4 ) ); $p += 4;
			$res[$i][] = substr ( $response, $p, $len ); $p += $len;
		}

		$this->_MBPop ();
		return $res;
	}

	//////////////////////////////////////////////////////////////////////////
	// 释放
	//////////////////////////////////////////////////////////////////////////

	function FlushAttributes ()
	{
		$this->_MBPush ();
		if (!( $fp = $this->_Connect() ))
		{
			$this->_MBPop();
			return -1;
		}

		$req = pack ( "nnN", SEARCHD_COMMAND_FLUSHATTRS, VER_COMMAND_FLUSHATTRS, 0 ); // len=0
		if ( !( $this->_Send ( $fp, $req, 8 ) ) ||
			 !( $response = $this->_GetResponse ( $fp, VER_COMMAND_FLUSHATTRS ) ) )
		{
			$this->_MBPop ();
			return -1;
		}

		$tag = -1;
		if ( strlen($response)==4 )
			list(,$tag) = unpack ( "N*", $response );
		else
			$this->_error = "unexpected response length";

		$this->_MBPop ();
		return $tag;
	}
}


