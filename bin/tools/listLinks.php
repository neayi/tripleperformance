<?php

$pageNames = array(
    "Ecimeuse pour désherber dans les légumineuses à graines - retour d'expérience en bio (Robert Melix - Aglae)",
    "Choix variétal pour limiter le développement des adventices en maïs ensilage - retour d'expérience (Guy Doléac - Aglae)",
    "Réduction de l'écartement et augmentation de la densité de semis pour réduire la pression adventice - retour d'expérience (Jérôme Sainte-Marie - Aglae)",
    "Faux-semis scalpeur pour lutter contre les chardons en désherbage mécanique (Damien Carpene - Aglae)",
    "Féverole en couvert végétal d'interculture dans le cadre des TCS - retour d'experience (JM Bardou - Aglae)",
    "Alternance des cultures pour lutter contre les graminées d’hiver - retour d'expérience (Georges Joya - Aglae)");

foreach ($pageNames as $pageName)
{
    $parameters = ["action" => "query", "gpllimit" => "500", "generator" => "links", "prop" => "info", "format" => "json",	"titles" => "$pageName"];

    $endPoint = "https://wiki.tripleperformance.fr/api.php";
    $url = $endPoint . "?" . http_build_query($parameters);

    $ch = curl_init( $url );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $output = curl_exec( $ch );
    curl_close( $ch );

    $result = json_decode( $output, true );

    if (empty($result['query']['pages']))
    {
        echo "No results\n";
        exit();
    }

    echo "\n";
    echo "$pageName\t\thttps://wiki.tripleperformance.fr/wiki/". wikiURLEncode($pageName). "\n";

    foreach ($result['query']['pages'] as $page)
    {
        if (isset($page['missing']))
            echo $page['title'] . "\tx\thttps://wiki.tripleperformance.fr/wiki/". wikiURLEncode($page['title']). "\n";
    }

    foreach ($result['query']['pages'] as $page)
    {
        if (!isset($page['missing']))
            echo $page['title'] . "\t\thttps://wiki.tripleperformance.fr/wiki/". wikiURLEncode($page['title']). "\n";
    }
}

function wikiURLEncode($pageName)
{
    return urlencode(str_replace(' ', '_', $pageName));
}