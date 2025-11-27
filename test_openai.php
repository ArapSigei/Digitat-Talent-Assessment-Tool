<?php
$OPENAI_API_KEY = "YOUR_REAL_KEY_HERE";

$ch = curl_init("https://api.openai.com/v1/models");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer $OPENAI_API_KEY"
]);

$response = curl_exec($ch);
if ($response === false) {
    die('cURL error: ' . curl_error($ch));
}
curl_close($ch);

echo $response;
?>
