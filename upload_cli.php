<?php
error_reporting(E_ALL);          // שיאסוף הכל
ini_set('display_errors', 0);    // לא להציג למסך
ini_set('log_errors', 1);        // כן לרשום לוג
ini_set('error_log', __DIR__ . '/php_errors.log'); // קובץ לוג

var_dump($argv);

echo "START:\n";

//require_once '/var/www/yeshivatrabenu/YEMOT_functions.php';

// קבלת פרמטרים מה-shell
$token        = $argv[1] ?? '';
$path         = $argv[2] ?? '';
$name         = $argv[3] ?? '';
$contentFile  = $argv[4] ?? '';
$convertAudio = (int)($argv[5] ?? 1);
$autoNumbering = (int)($argv[6] ?? 1);
$tts          = (int)($argv[7] ?? 0);

// קריאת תוכן הקובץ
if ($contentFile === '' || !is_file($contentFile)) {
    $res = [
        'type' => 'FILE_ERROR',
        'message' => 'File not found: ' . $contentFile,
    ];
    error_log('safeUpload file error: ' . json_encode($res, JSON_UNESCAPED_UNICODE));
    echo "ERROR\n";
    echo "YEMOT: " . json_encode($res, JSON_UNESCAPED_UNICODE) . "\n";
    exit(1);
}

// קריאה לפונקציה שלך
//$res = safeUpload($token, $path, $name, $content, $convertAudio, $tts);
$res = NEWsafeUpload($token, $path, $name, $contentFile, $convertAudio, $autoNumbering, $tts);

$status = null;

if (is_string($res)) {
    $status = $res;
} elseif (is_array($res)) {
    $status = $res['responseStatus'] ?? $res['status'] ?? null;
}


// פלט ל-shell
if ($status === 'OK') {
    echo "OK\n";
    exit(0);
}

echo "ERROR\n";
echo "YEMOT: " . json_encode($res, JSON_UNESCAPED_UNICODE) . "\n";
exit(1);


/**
 * העלאה בטוחה של קובץ תוכן ל-Call2All
 *
 * @param string $token   טוקן API
 * @param string $path    נתיב ב-IVR
 * @param string $name    שם הקובץ ביעד
 * @param string $content תוכן הקובץ
 * @param int    $tts     ערך tts
 *
 * @return mixed|null     תשובת השרת, או null במקרה שגיאה
 */
function NEWsafeUpload(string $token, string $path, string $name, string $contentFile, int $convertAudio = 1, int $autoNumbering = 1, int $tts = 0)
{
    if (!is_file($contentFile)) {
        return [
            'type' => 'FILE_ERROR',
            'message' => 'File not found: ' . $contentFile,
        ];
    }

    $sURL = 'https://www.call2all.co.il/ym/api/UploadFile'
        . '?token=' . rawurlencode($token)
        . '&path=ivr2:' . rawurlencode($path) . '/' . rawurlencode($name)
        . '&convertAudio=' . $convertAudio
        . '&autoNumbering=' . $autoNumbering
        . '&tts=' . abs($tts);

    error_log("Upload URL: " . $sURL);

    $postFields = [
        'File1' => new CURLFile($contentFile, 'audio/mpeg', $name),
    ];

    $ch = curl_init($sURL);

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_TIMEOUT => 60,
    ]);

    $responseBody = curl_exec($ch);
    /**var_dump([
        'curlErrNo' => $curlErrNo,
        'curlErrMsg' => $curlErrMsg,
        'httpCode' => $httpCode,
        'responseBody' => $responseBody,
    ]);
     */ 
    $curlErrNo    = curl_errno($ch);
    $curlErrMsg   = curl_error($ch);
    $httpCode     = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);


echo "curlErrNo=" . var_export($curlErrNo, true) . "\n";
echo "curlErrMsg=" . var_export($curlErrMsg, true) . "\n";
echo "httpCode=" . var_export($httpCode, true) . "\n";
echo "responseType=" . gettype($responseBody) . "\n";
echo "responseLen=" . strlen((string)$responseBody) . "\n";
echo "responseFirst200=" . substr((string)$responseBody, 0, 200) . "\n";



    curl_close($ch);

    if ($curlErrNo !== 0) {

    
        return [
            'type' => 'CURL_ERROR',
            'code' => $curlErrNo,
            'message' => $curlErrMsg,
        ];
    }

    $decoded = json_decode($responseBody, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'type' => 'JSON_ERROR',
            'httpCode' => $httpCode,
            'message' => json_last_error_msg(),
            'raw_response' => $responseBody,
        ];
    }

    if (($decoded['responseStatus'] ?? '') !== 'OK') {
        return [
            'type' => 'API_ERROR',
            'httpCode' => $httpCode,
            'responseStatus' => $decoded['responseStatus'] ?? null,
            'message' => $decoded['message'] ?? null,
            'full_response' => $decoded,
        ];
    }

    return [
        'status' => 'OK',
        'data' => $decoded,
    ];
}



function safeUpload(string $token, string $path, string $name, string $content, int $convertAudio, $autoNumbering = 1, int $tts = 0)
{
    $STR_BOUNDARY = 'a832972453175';

    // בניית ה-URL בדיוק כמו ב-Node.js
    $sURL = 'https://www.call2all.co.il/ym/api/UploadFile'
        . '?token=' . rawurlencode($token)
        . '&path=ivr2:' . rawurlencode($path) . '/' . rawurlencode($name)
        . '&convertAudio=' . $convertAudio
        . '&autoNumbering=' . $autoNumbering
        . '&tts=' . abs($tts);

    // בניית גוף הבקשה multipart/form-data ידנית
    $strPostData =
        '--' . $STR_BOUNDARY . "\r\n"
        . 'Content-Disposition: form-data; name="File1"; filename="FILENAME"' . "\r\n"
        . 'Content-Type: multipart/form-data' . "\r\n\r\n"
        . $content . "\r\n\r\n"
        . '--' . $STR_BOUNDARY . '--';

echo $sURL;
    $ch = curl_init($sURL);

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => $strPostData,
        CURLOPT_HTTPHEADER => [
            'Content-Type: multipart/form-data; Charset=UTF-8; convertAudio=1; boundary=' . $STR_BOUNDARY,
            'Content-Length: ' . strlen($strPostData),
        ],
        CURLOPT_TIMEOUT => 20,
    ]);

    $responseBody = curl_exec($ch);
    $curlErrNo    = curl_errno($ch);
    $curlErrMsg   = curl_error($ch);
    $httpCode     = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    // שגיאת cURL
    if ($curlErrNo !== 0) {
        $err = [
            'type' => 'CURL_ERROR',
            'code' => $curlErrNo,
            'message' => $curlErrMsg,
        ];
        error_log('❌ safeUpload cURL error: ' . json_encode($err, JSON_UNESCAPED_UNICODE));
        return $err;
    }
    
    // נסיון פענוח JSON
    $decoded = json_decode($responseBody, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        $err = [
            'type' => 'JSON_ERROR',
            'message' => json_last_error_msg(),
            'raw_response' => $responseBody,
        ];
        error_log('❌ safeUpload JSON error: ' . json_encode($err, JSON_UNESCAPED_UNICODE));
        return $err;
    }
    
    // אם יש תשובה מהשרת אבל לא OK
    if (($decoded['responseStatus'] ?? '') !== 'OK') {
        $err = [
            'type' => 'API_ERROR',
            'responseStatus' => $decoded['responseStatus'] ?? null,
            'message' => $decoded['message'] ?? null,
            'full_response' => $decoded,
        ];
        error_log('❌ safeUpload API error: ' . json_encode($err, JSON_UNESCAPED_UNICODE));
        return $err;
    }
    
    // הצלחה
    return [
        'status' => 'OK',
        'data' => $decoded
    ];
}
