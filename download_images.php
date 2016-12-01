<?PHP

set_time_limit(0);

$HOSTURL = 'http://'.$_SERVER['HTTP_HOST'];
$EX_FILE_PATTERN = ('/src[ =\'\"]*http:[^\'\">]*\.[^\'\">]*[ \"\']*/') ;

$FILE_PATH = '/uploads/fotos/'; // path to download files to
$TABLE = 'dle_post'; // table to check
$COLUMNS = array('short_story','full_story'); // table columns to check
$LOGFILE = fopen("uploads/logs.txt", "a") or die("Unable to open logs file!"); // log file to register updates in db.
    
define ("DATALIFEENGINE", "True"); // to pass DLE security check

include 'engine/classes/mysql.php';
include 'engine/data/dbconfig.php';

if (!$db_link = mysql_connect(DBHOST, DBUSER, DBPASS)) {
    echo 'Couldn\'t connect to DB server.';
    exit;
}

if (!mysql_select_db(DBNAME, $db_link)) {
    echo 'Couldn\'t connect to DB.';
    exit;
}

$sql    = 'SELECT * FROM '.;
$result = mysql_query($sql, $db_link);

if (!$result) {
    echo 'MySQL Error: ' . mysql_error();
    exit;
}

$printed = 0;
while ( $row = mysql_fetch_assoc($result) ) {
    $id = $row['id'];
    foreach($COLUMNS as $key) {
        $value_changed = false;
        $value = $row[$key];
        preg_match_all($EX_FILE_PATTERN, $value, $matches); // search for all *src="http://x"*
        foreach($matches[0] as $link){
            if(strpos($link, $HOSTURL)===false){
                //strip *scr="*
                $url = rtrim(preg_replace('/src[ =\'\"]*/', '', $link),'/"\' ');
                $filename = explode(".", end(explode("/", $url))); // [name, extension]
                $hash = substr(md5($url),0,6); // generate this to avoid collisions, 
                                               // also later we can get new url of image knowing old url
                $new_filename = $filename[0].$hash.'.'.$filename[1];
                $new_path = $FILE_PATH.$new_filename;
                //download image
                if (!file_exists($new_path)){
                    if($file = @fopen($url, 'r')){
                        file_put_contents($new_path, $file);
                        $value = str_replace($link, 'src="'.$new_path.'"' , $value);
                        $value_changed = true;
                        fwrite($LOGFILE, "\n". $url.';'.$new_path); // write logs
                    } else {
                        // bad url
                    }
                }
            }
        }
        if($value_changed){
            //do update query with new value where table.id = $id
            $sql    = sprintf("UPDATE ".$TABLE." SET %s = '%s' WHERE id = '%s' ",
                                    mysql_real_escape_string($key),
                                    mysql_real_escape_string($value),
                                    $id
                                    );
            mysql_query($sql, $db_link);
        }
    }
    $printed++;
}

fclose($LOGFILE);
mysql_free_result($result);

?>
