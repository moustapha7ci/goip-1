<?php
require_once("session.php");
define("OK", true);
require_once("global.php");
if($_SESSION['goip_permissions'] > 1)	
die("Permission denied!");

?>
<html>                                                                                                            
<head>                                                                                                            
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">                                               
<link href="style.css" rel="stylesheet" type="text/css">                                                          
<title>Call Waiting</title>                                                                                               
<body leftmargin="2" topmargin="0" marginwIdth="0" marginheight="0">   

<?php

if(empty($_GET[id]))
	die("NOT find goip id");
if($goipcronport)
	$port=$goipcronport;
	else 
	$port=44444;

	if (($socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP)) <= 0) {
		echo "socket_create() failed: reason: " . socket_strerror($socket) . "\n";
		exit;
	}
$query=$db->query("SELECT prov.*,goip.* FROM goip,prov where prov.id=goip.provider and goip.id=$_GET[id]");

if(($goiprow=$db->fetch_array($query)) ==NULL){
	die("Not find this goip");
}

//print_r($_GET);
	if(isset($_GET[mode])) $mode=$_GET[mode];
	else $mode=2;
	$cwstate=$_GET[cwstate];
	$recvid=time();
	ignore_user_abort(true);

	for($i=0;$i<3;$i++){
		$read=array($socket);
		$buf="START $recvid $goiprow[host] $goiprow[port]\n";
		if (@socket_sendto($socket,$buf, strlen($buf), 0, $goipdocker, $port)===false){
			echo ("sendto error".socket_strerror($socket) . "\n");
			exit;
		}
		$err=socket_select($read, $write = NULL, $except = NULL, 5);
		if($err>0){		
			if(($n=@socket_recvfrom($socket,$buf,1024,0,$ip,$port1))==false){
				echo("recvform error".socket_strerror($ret)."<br>");
				continue;
			}
			else{
				if($buf=="OK"){
					$flag=1;
					break;
				}
			}
		}

	}//for
	if($i>=3) die("goipcron 服务进程没有响应");

	$sendbuf="CW ".$recvid." ".$goiprow[password]." ".$mode;

	$socks[]=$socket;
	$timer=2;
	$timeout=10;
	if (@socket_sendto($socket,$sendbuf, strlen($sendbuf), 0, $goipdocker, $port)===false)
		echo ("sendto error");
	for(;;){
		$read=$socks;
		flush();
		if(count($read)==0)
			break;
		$err=socket_select($read, $write = NULL, $except = NULL, $timeout);
		if($err===false)
			echo "select error!";
		elseif($err==0){ //全体超时
			if(--$timer <= 0){
				echo "<script language=\"javascript\">alert('超时! Goip 没有响应')</script>"; 
				break;
			}
			if (@socket_sendto($socket,$sendbuf, strlen($sendbuf), 0, $goipdocker, $port)===false)
				echo ("sendto error");
		}
		else {
			if(($n=@socket_recvfrom($socket,$buf,1024,0,$ip,$port1))==false){
				echo("recvform error".socket_strerror($ret)."<br>");
				continue;
			}
			//echo $buf;
			//echo "<script language=\"javascript\">alert('recvbuf:$buf')</script>";
			$comm=explode(" ",$buf);
			
			if($comm[0] == "CWOK") {
				if($mode == 0){ //关闭
					$cwstate=0;
				}
				else if($mode == 1){
					$cwstate=1;
				}
				else if($mode == 2){
					/* 啥都不干*/
				}
				break;
			}
			else if($comm[0] == "CWERROR"){
				WriteErrMsg("指令设置失败");
				//echo "<script language=\"javascript\">alert('设置指令失败')</script>"; 
				//echo "<meta http-equiv=refresh content=0;url=\"callw.php?id=$_GET[id]\">";
				break;
			}
			else if($comm[0] == "CWSTATE"){
				if($comm[2] == 1)
					$cwstate=1;
				else 
					$cwstate=0;
				break;
			}
			else {
				WriteErrMsg("不明回复:".$buf);
				//echo "<script language=\"javascript\">alert('不明回复:$buf')</script>";
				break;

			}


		}
	}
	$buf1="DONE $recvid";
	if (@socket_sendto($socket,$buf1, strlen($buf1), 0, $goipdocker, $port)===false)
		echo ("sendto error");


//if(!strncmp($buf, "ERROR", 5) || !strncmp($buf, "GSMERROR", 8))
//echo "<script language=\"javascript\">alert('error! $buf ')</script>";
//echo $buf;
?>
<table wIdth="100%" border="0" align="center" cellpadding="2" cellspacing="1" class="border">
  <tr class="topbg">
    <td height="22" colspan="2" align="center"><strong>呼叫等待</strong></td>
  </tr>
  <tr class="tdbg"> 
    <td wIdth="140" height="30"><strong>goip管理导航:</strong></td>
    <td height="30"><a href="goip.php" target=main>参数管理</a>&nbsp;|&nbsp;<a href="goip.php?action=add" target=main>添加机器</a></td>
  </tr>
</table>

<form method="post" action="ussd.php?id=<?php echo $_GET[id]?>" name="form1">
  <br>
  <br>
  <table wIdth="600" border="0" align="center" cellpadding="1" cellspacing="1" class="border" >
    <tr class="title"> 
      <td height="22" colspan="2"> <div align="center"><strong>GOIP(<?php echo $goiprow[name] ?>)呼叫等待</strong></div></td>
    </tr>
    <tr>
	<td width=50% align="right" class="tdbg"><strong>呼叫等待状态:</strong></td>
      <td height="22"  class="tdbg"><?php if($cwstate === 1) echo "已启用"; else if($cwstate === 0) echo "已禁用"; ?></td>
    </tr>
    <tr> 
      <td class="tdbg" colspan="2" align="center"><a href="callw.php?id=<?php echo $_GET[id]?>&mode=0&cwstate=<?php echo $cwstate ?>" target=main >禁用</a> | <a href="callw.php?id=<?php echo $_GET[id]?>&mode=1&cwstate=<?php echo $cwstate ?>" target=main >启用</a></td>
    </tr>
  </table>                                                                                                        
</form> 
</body>
</html>
<?php 
//}
?>
