<?php

$pageNames = explode("\n", "Acarien
Acarien jaune
Acarien rouge (panonychus ulmi)
Acariose
Albugo
Alternaria
Alternariose
Altise
Anthonome
Anthracnose
Aphanomyces
Apion
Ascochytose
Atomaire
Avoine
Bactériose
Betterave sucrière
Black rot
Blaniule
Blé tendre
Boarmie
Botrytis
Bruche
Bupestre
Campagnol
Carpocapse
Cercopsoriose
Chancre
Chanvre
Charançon
Charançon de la tige
Charançon des siliques
Charançon du bourgeon terminal
Charbon
Cicadelle
Cicadelle de la flavescence dorée
Cicadelle des grillures
Cicadelle du bois noir
Cicadelle pruineuse
Cochenille
Cochenille rouge
Cochylis
Colza
Court noué
Cuscute
Cécidomyie
Dartrose
Doryphore
Drosophile suzukii
Erinose
Esca
Escargot
Eudemis
Eutypiose
Excoriose
Feu bactérien
Flétrissement bactérien
Fonte des semis
Fusariose
Féverole d'hiver
Féverole de printemps
Gale agentée
Gale commune à liège
Gale commune à pustules
Grillures
Grosse altise
Helminthosporiose
Hernie des crucifères
Hoplocampe
Héliothis
Jaunisse
Kabatiellose
Limace
Limace grise
Lin
Lupin d'hiver
Lupin de printemps
Luzerne
Maïs
Mildiou
Mosaïque
Mouche des semis
Mouche mineuse
Méligèthe
Noctuelle
Noctuelle terricole
Nécrose bactérienne
Nématode
Nématode des tiges
Nématode du collet
Nématode à galles
Nématode à kystes
Oiseaux
Orge
Orobanche
Orobanche rameuse
Oïdium
Pepper spot
Petite altise
Phoma
Phomopsis
Phylloxera
Phytonome
Pigeon
Piétin verse
Poirier
Pois chiche
Pois d'hiver
Pois de printemps
Pomme de terre
Pommier
Pou de San José
Pourridié
Pourriture brune
Pourriture grise
Pourriture racinaire
Pseudomonas
Pseudopeziza
Psylle
Puceron
Puceron cendré
Puceron lanigère
Puceron mauve
Puceron Metopolophium dirhodum
Puceron noir
Puceron Rhopalosiphum pad
Puceron Sitobion avenae
Puceron vert
Punaise
Punaise terne
Punaise verte
Pyrale
Pythium
Pégomyie
Ramulariose
Rhizoctone
Rhizoctone brun
Rhizoctone violet
Rhizomanie
Rhizopus
Rhynchite rouge
Rhynchosporiose
Rongeur
Rougeot parasitaire
Rouille
Rouille brune
Rouille couronnée
Rouille jaune
Rouille naine
Sclérotinia
Septoriose
Sitone
Soja
Sésamie
Taches brunes
Taupins
Tavelures
Teigne
Tenthrède
Thrips
Tipule
Tordeuse
Tordeuse de la pelure Capua
Tordeuse orientale
Tournesol
Triticale
Vanesse du chardon
Verticilliose
Vigne
Virus YNTN
Zeuzère");

array_unique($pageNames);
$pageNames = array_map("trim", $pageNames);

$calls = array_chunk($pageNames, 49);

foreach ($calls as $pageNames)
{
    $parameters = ["action" => "query", "redirects" => "false", "prop" => "pageprops", "format" => "json",	"titles" => implode("|", $pageNames)];

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

    if (!empty($result['query']['redirects']))
    {
        foreach ($result['query']['redirects'] as $page)
        {
            echo $page['from'] . "\t" . $page['to'] . "\n";
        }
    }

    foreach ($result['query']['pages'] as $page)
    {
        if (isset($page['missing']))
            echo $page['title'] . "\tPage missing\thttps://wiki.tripleperformance.fr/wiki/". wikiURLEncode($page['title']). "\n";
        else if (isset($page['pageprops']['description']))
            echo $page['title'] . "\tOK\thttps://wiki.tripleperformance.fr/wiki/". wikiURLEncode($page['title']). "\t".str_replace("\n", " ", $page['pageprops']['description'])."\n";
        else
            echo $page['title'] . "\tOK\thttps://wiki.tripleperformance.fr/wiki/". wikiURLEncode($page['title']). "\n";
    }
}

function wikiURLEncode($pageName)
{
    return urlencode(str_replace(' ', '_', $pageName));
}