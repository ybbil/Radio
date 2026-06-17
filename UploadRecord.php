<?php

//https://www.hebcal.com/shabbat?cfg=json&geonameid=281184&M=off&leyning=off&lg=he

//require_once "config.php"; // פה אתה מגדיר $CurToken אם צריך
//require_once "functions.php"; // פה נמצאת UploadTextFile()

// קבלת פרמטרים (POST או GET)
$UserName    = $_REQUEST['username'] ?? '';
$Password    = $_REQUEST['password'] ?? '';
$Address     = $_REQUEST['address'] ?? '';
$sourceFile  = $_REQUEST['source_file'] ?? '';
$targetFile  = $_REQUEST['target_file'] ?? '';
//$text        = $_REQUEST['text'] ?? '';
$convertAudio = isset($_REQUEST['convertaudio']) ? intval($_REQUEST['convertaudio']) : 1;
$filePath = $_REQUEST['file_path'] ?? '';
$text = '';


if ($filePath !== '') {
    if (!file_exists($filePath)) {
        die("ERROR: File not found: " . $filePath);
    }
    if (!is_readable($filePath)) {
        die("ERROR: File not readable: " . $filePath);
    }

    // קריאה בטוחה גם לקבצים גדולים
    $text = file_get_contents($filePath);
} else {
    // fallback אם בכל זאת שולחים text
    $text = $_REQUEST['text'] ?? '';
}

// קריאה לפונקציה
$response = UploadTextFile(
    $UserName,
    $Password,
    $Address,
    $targetFile,
    $text,
    $convertAudio
);

// כתיבת לוג
$logLine = sprintf(
    "[%s] SOURCE: %s | TARGET: %s | RESPONSE: %s\n",
    date("Y-m-d H:i:s"),
    $sourceFile,
    $targetFile,
    str_replace(["\r", "\n"], " ", $response)
);

file_put_contents("upload_log.txt", $logLine, FILE_APPEND);

// החזרת תשובה ללקוח
//header("Content-Type: text/plain; charset=UTF-8");
echo $response;


function UploadTextFile($UserName, $Password, $Address, $fileName, $text, $convertAudio = 1)
{
    global $CurToken;

    // בניית URL
    $sURL = "https://call2all.co.il/ym/api/UploadFile?"
          . "token=" . "033069597:1478"
          . "&path=ivr2:/1234/6" . $Address . "/" . $fileName
          . "&convertAudio=" . intval($convertAudio);

    echo $sURL . "\n";

    // נתונים שנשלחים בגוף הבקשה
    $postData = http_build_query([
        'contents'      => $text
    ]);

    $headers = [
        "Content-Type: application/x-www-form-urlencoded; charset=UTF-8",
        "Content-Length: " . strlen($postData)
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $sURL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        $error = "cURL error: " . curl_error($ch);
        curl_close($ch);
        return $error;
    }

    curl_close($ch);
    return $response . "\n";
}


function GetParasha (){
    header('Content-Type: application/json; charset=utf-8');

    // הגדרות
    $locationId = 281184;  // ירושלים (GeoName ID)
    $urlHe = "https://www.hebcal.com/shabbat?cfg=json&geonameid=$locationId&M=off&leyning=off&lg=he";
    $urlEn = "https://www.hebcal.com/shabbat?cfg=json&geonameid=$locationId&M=off&leyning=off&lg=en";

    // פונקציה לשליפת שם הפרשה מ-URL
    function getParashaName($url, $lang = 'hebrew') {
        $json = @file_get_contents($url);
        if ($json === false) {
            return null;
        }
        
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['items'])) {
            return null;
        }
        
        foreach ($data['items'] as $item) {
            if (isset($item['category']) && $item['category'] === 'parashat') {
                if ($lang === 'hebrew') {
                    return $item['hebrew'] ?? null;
                } else {
                    return $item['title'] ?? null;
                }
            }
        }
        
        return null;
    }

    // שליפת השמות
    $hebrew  = getParashaName($urlHe, 'hebrew');
    $english = getParashaName($urlEn, 'english');

    // בניית מערך תוצאה
    $result = [
        'parasha' => [
            'hebrew'  => $hebrew  ?: 'לא נמצאה פרשה',
            'english' => $english ?: 'Parasha not found'
        ],
        'timestamp' => date('c'),  // אופציונלי - זמן השאילתה
        'location'  => 'ירושלים'
    ];

    // הדפסת JSON יפה (אפשר להסיר json_encode options אם לא צריך יפה)
    return $result;
}
?>