<?php
session_start();

$maxCats=5;
$title="Trunk categories to csv";

function getRemote($url){
	$curl = curl_init();
	
	curl_setopt_array($curl, array(
	  CURLOPT_URL => $url,
	  CURLOPT_RETURNTRANSFER => true,
	  CURLOPT_TIMEOUT => 30,
	  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	  CURLOPT_CUSTOMREQUEST => "GET",
	  CURLOPT_HTTPHEADER => array(
		"cache-control: no-cache"
	  ),
	));
	
	$response = curl_exec($curl);
	$err = curl_error($curl);
	
	curl_close($curl);
	
	if($err){
		return false;
	}
	
	return $response;
}

if (!file_exists('cache/cat')) {
	mkdir('cache/cat', 0755, true);
}

$showForm=true;

if(isset($_POST) && count($_POST)>0 && isset($_SESSION["token"]) && $_SESSION["token"]!='' && isset($_POST["bootstrapFollows"]) && $_POST["bootstrapFollows"]==$_SESSION["token"]){
	$_SESSION["token"]='';
	//10 plust bootstrapFollows
	if(count($_POST)<=$maxCats+1){
		$list=file_get_contents('cache/list.json');
		$list=json_decode($list, true);
		if(!$list){
			$_POST=array();
		}
		$str="Account address,Show boosts\n";
		foreach($_POST as $k=>$v){
			if($k!="bootstrapFollows"){
				
				$showForm=true;
				$cachefile='cache/cat/'.$k.'.json';
				$attemptRefresh=true;
				if(file_exists($cachefile) && time()-filemtime($cachefile)<60 * 60 * 24){
					$attemptRefresh=false;
				}
				if($attemptRefresh){
					//half a second
					usleep(500000);
					$cleank=str_replace('_', ' ', $k);
					$enck=str_replace('_', '%20', $k);
					if(!in_array($cleank ,$list)){
						break;
					}
					$jsonRequest = getRemote('https://communitywiki.org/trunk/api/v1/list/'.$enck);
					if($jsonRequest){
						$cat=json_decode($jsonRequest, true);
						if($cat){
							file_put_contents($cachefile, trim($jsonRequest));
						}else{
							break;
						}
					}else{
						break;
					}
				}
				$cat=file_get_contents($cachefile);
				$cat=json_decode($cat, true);
				if($cat){
					foreach($cat as $kk=>$vv){
						if(isset($vv["acct"])){
							$str.=$vv["acct"].",true\n";
						}
					}
					$showForm=false;
				}else{
					break;
				}
			}
		}
		$filename = 'following_accounts.csv';
		
		header("Content-Type: text/plain");
		header('Content-Disposition: attachment; filename="'.$filename.'"');
		header("Content-Length: " . strlen($str));
		echo $str;
		exit;
	}else{
		$showOverLimitWarning=true;
	}
}

if($showForm==true){
?><!DOCTYPE html>

<html lang="en">

<head>
	<meta charset="UTF-8">
	<title><?php echo $title; ?></title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0,maximum-scale=1.0, user-scalable=no">
	<style>
	*{margin:0;padding:0;}
	body{font: 16px/26px Verdana, sans-serif;background: #fff;color:#222;}
	h1{font-size:1.4em;margin-bottom:20px;}
	h2{font-size:1.2em;}
	#main{margin: 20px auto 100px;padding:40px;max-width: 600px;}
	#bootstrapFollows{padding:10px;}
	#footer{margin:50px 0 10px;text-align: center;}
	.checkb{margin-bottom: 10px;}
	.checkb label{padding-left: 5px;}
	input[type=submit] {
		padding:5px 15px; 
		background:#ccc; 
		border:0 none;
		cursor:pointer;
		-webkit-border-radius: 5px;
		border-radius: 5px; 
		font-size: 1.2em;
	}
	input[type=submit]:hover{
		background: #f0f0f0;
	}
	</style>
</head>
<body>
	<div id="main">
		<h1><?php echo $title; ?></h1>
		<p style="margin-bottom:20px;">This tool uses <a href="https://communitywiki.org/trunk/">Trunk</a> to generate a csv file that can be used on <a href="https://joinmastodon.org/">Mastodon</a> (Account->Import and export->Import) to follow all the accounts from the Trunk categories you choose below.</p>
		<script>
		function checkboxes(){
			var inputElems = document.getElementsByTagName("input"),
			count = 0;
			for (var i=0; i<inputElems.length; i++) {
				if (inputElems[i].type === "checkbox" && inputElems[i].checked === true){
					count++;
				}
			}
			if(count==<?php echo $maxCats; ?>){
				ok=confirm('<?php echo $maxCats; ?> categories choosen. Generate the csv now?');
				if(ok){
					document.getElementById('bootstrapFollows').submit();
				}
			}else if(count><?php echo $maxCats; ?>){
				alert('You can only pick <?php echo $maxCats; ?> categories!');
			}
		}
		</script>
		<?php
		$token=sha1(microtime(true).mt_rand(10000,90000));
		$_SESSION["token"]=$token;
		
		$attemptRefresh=true;
		if(file_exists('cache/list.json') && time()-filemtime('cache/list.json')<60 * 60 * 24){
			$attemptRefresh=false;
		}
		if($attemptRefresh){
			$jsonRequest = getRemote('https://communitywiki.org/trunk/api/v1/list');
			if($jsonRequest){
				$list=json_decode($jsonRequest, true);
				if($list && count($list)>10){
					file_put_contents('cache/list.json', trim($jsonRequest));
				}
			}
		}
		$list=file_get_contents('cache/list.json');
		$list=json_decode($list, true);
		if($list){
			echo '<h2>Pick up to '.$maxCats.' topics you are interested in:</h2>';
			if(isset($showOverLimitWarning)){
				echo '<div style="margin:10px;color:red;">You can only choose '.$maxCats.' categories</div>';
			}
			echo '<form method="POST" id="bootstrapFollows">';
			echo '<input type="hidden" name="bootstrapFollows" value="'.$token.'" />';
			foreach($list as $k=>$v){
				$nsv=str_replace(' ', '_', $v);
				echo '<div class="checkb"><input type="checkbox" id="'.$nsv.'" name="'.$nsv.'" onclick="checkboxes()"><label for="'.$nsv.'">'.$v.'</label></div>';
			}
			echo '<input type="submit" />';
			echo '</form>';
		}
		?>
		<div id="footer"><a href="https://github.com/hugogameiro/trunk-to-csv">source</a></div>
	</div>
	
</body>
</html>
<?php
}
