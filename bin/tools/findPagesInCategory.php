<?php

$SearchTerm = "Vigne";
$Category = "Exemples de mise en œuvre";

$parameters = ["action" => "query", "srlimit" => "500", "list" => "search", "format" => "json", "srsearch" => "$SearchTerm"];

$endPoint = "https://wiki.tripleperformance.fr/api.php";
$url = $endPoint . "?" . http_build_query($parameters);
$url .= "+incategory:" . urlencode('"' . $Category . '"');

$ch = curl_init( $url );
curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$output = curl_exec( $ch );
curl_close( $ch );

$result = json_decode( $output, true );

echo $url . "\n";

if (empty($result['query']['search']))
{
    echo "No results\n";
    exit();
}

echo "\n";

foreach ($result['query']['search'] as $page)
{
    echo $page['title'] . "\n";
}
